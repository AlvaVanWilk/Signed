<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;
use Signed\Rng;

/**
 * Post im Posteingang (Kassetten in `submissions`, Briefe in `mail`): Alles kommt in Umschlägen und landet im Eingang.
 * Auspacken dauert ein paar Sekunden (Balance::POST), selbst oder per Praktikum (gleich schnell, aber nebenbei). Das Praktikum arbeitet nur
 * auf Zuruf und nur an dem, was gerade da ist: auspacken, Kassetten anhören, in die Fächer sortieren. Eine Assistenz packt laufend aus und sortiert.
 */
final class Post
{
    public const TABLES = ['s' => 'submissions', 'm' => 'mail'];

    public function __construct(private Repo $repo)
    {
    }

    /** Zufälliger Abstand bis zur nächsten Ankunft (im Mittel $mean Sekunden): Man weiß nie genau, wann etwas kommt. */
    public static function gap(float $mean): int
    {
        return max(1, (int) round(-log(1 - Rng::float(0.0, 0.999)) * $mean));
    }

    /** Zustand eines Umschlags: noch zu ('envelope'), wird gerade ausgepackt ('unpacking') oder offen (null). */
    public static function envelope(array $row, int $now): ?string
    {
        if ($row['unpacked_at'] === null) {
            return 'envelope';
        }
        return (int) $row['unpacked_at'] > $now ? 'unpacking' : null;
    }

    /** Liegt im Eingang (noch nicht in ein Fach sortiert)? */
    public static function sorted(array $row, int $now): bool
    {
        return $row['sorted_at'] !== null && (int) $row['sorted_at'] <= $now;
    }

    private function table(string $key): string
    {
        return self::TABLES[$key] ?? throw new ApiError('Unbekannte Post.');
    }

    /** Selbst auspacken: immer nur einen Umschlag zur gleichen Zeit. */
    public function unpack(string $key, int $id): void
    {
        $db = $this->repo->db;
        $now = Clock::now();
        $table = $this->table($key);
        $row = $db->one("SELECT * FROM $table WHERE id = ? AND label_id = ? AND expires_at > ?", [$id, $this->repo->labelId, $now]);
        if ($row === null) {
            throw new ApiError('Diesen Umschlag gibt es nicht mehr.');
        }
        if ($row['unpacked_at'] !== null) {
            return;
        }
        foreach (self::TABLES as $t) {
            if ((int) $db->value("SELECT COUNT(*) FROM $t WHERE label_id = ? AND unpacked_at > ? AND worker IS NULL", [$this->repo->labelId, $now]) > 0) {
                throw new ApiError('Du packst gerade schon einen Umschlag aus.');
            }
        }
        $db->update($table, ['unpacked_at' => $now + Balance::POST['unpack'], 'worker' => null], ['id' => $id]);
    }

    /** Assistenz: Alles, was kommt, ist sofort ausgepackt und in die Fächer sortiert. */
    public function autoSort(int $now): void
    {
        foreach (self::TABLES as $t) {
            $this->repo->db->exec("UPDATE $t SET unpacked_at = ? WHERE label_id = ? AND unpacked_at IS NULL", [$now - 1, $this->repo->labelId]);
            $this->repo->db->exec("UPDATE $t SET sorted_at = ? WHERE label_id = ? AND sorted_at IS NULL AND unpacked_at <= ?", [$now - 1, $this->repo->labelId, $now]);
        }
    }

    /** Was das Praktikum gerade tun könnte: wie viele Umschläge, ungehörte Kassetten und unsortierte Briefe da sind. */
    public function todo(int $now): array
    {
        $db = $this->repo->db;
        $l = $this->repo->labelId;
        $count = static fn (string $sql, array $p) => (int) $db->value($sql, $p);
        $unpack = 0;
        $sort = 0;
        foreach (self::TABLES as $t) {
            $unpack += $count("SELECT COUNT(*) FROM $t WHERE label_id = ? AND expires_at > ? AND unpacked_at IS NULL", [$l, $now]);
            $sort += $count("SELECT COUNT(*) FROM $t WHERE label_id = ? AND expires_at > ? AND unpacked_at <= ? AND sorted_at IS NULL", [$l, $now, $now]);
        }
        $listen = $count('SELECT COUNT(*) FROM submissions WHERE label_id = ? AND expires_at > ? AND unpacked_at <= ? AND opened_at IS NULL', [$l, $now, $now]);
        return ['unpack' => $unpack, 'listen' => $listen, 'sort' => $sort];
    }

    /**
     * Auftrag ans Praktikum: alles, was gerade da ist, nacheinander. Dauert so lange wie selbst gemacht; läuft auch offline.
     * @return int Anzahl der Stücke
     */
    public function order(array $intern, string $job, int $now): int
    {
        $db = $this->repo->db;
        $l = $this->repo->labelId;
        $rows = [];
        if ($job === 'unpack') {
            foreach (self::TABLES as $t) {
                foreach ($db->all("SELECT id, created_at FROM $t WHERE label_id = ? AND expires_at > ? AND unpacked_at IS NULL", [$l, $now]) as $r) {
                    $rows[] = [$t, (int) $r['id'], (int) $r['created_at']];
                }
            }
            $column = 'unpacked_at';
            $step = Balance::POST['unpack'];
        } elseif ($job === 'listen') {
            foreach ($db->all('SELECT id, created_at FROM submissions WHERE label_id = ? AND expires_at > ? AND unpacked_at <= ? AND opened_at IS NULL', [$l, $now, $now]) as $r) {
                $rows[] = ['submissions', (int) $r['id'], (int) $r['created_at']];
            }
            $column = 'opened_at';
            $step = Balance::SUBMISSIONS['listen'];
        } elseif ($job === 'sort') {
            foreach (self::TABLES as $t) {
                foreach ($db->all("SELECT id, created_at FROM $t WHERE label_id = ? AND expires_at > ? AND unpacked_at <= ? AND sorted_at IS NULL", [$l, $now, $now]) as $r) {
                    $rows[] = [$t, (int) $r['id'], (int) $r['created_at']];
                }
            }
            $column = 'sorted_at';
            $step = Balance::POST['sort'];
        } else {
            throw new ApiError('Diesen Auftrag gibt es nicht.');
        }
        if (!$rows) {
            throw new ApiError(['unpack' => 'Gerade liegt kein Umschlag da.', 'listen' => 'Gerade liegt keine ungehörte Kassette da.', 'sort' => 'Im Eingang liegt nichts zum Sortieren.'][$job]);
        }
        // Die ältesten zuerst (die laufen als Erste ab).
        usort($rows, static fn ($a, $b) => $a[2] <=> $b[2]);
        foreach ($rows as $k => [$t, $id]) {
            $db->update($t, [$column => $now + ($k + 1) * $step, 'worker' => 'intern'], ['id' => $id]);
        }
        return count($rows);
    }
}
