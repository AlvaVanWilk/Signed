<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;
use Signed\Rng;

/**
 * Post im Posteingang (Kassetten in `submissions`, Briefe in `mail`): Alles kommt in Umschlägen und landet im Eingang.
 * Auspacken dauert ein paar Sekunden (Balance::POST), selbst oder per Praktikum (gleich schnell, aber nebenbei). Das Praktikum arbeitet nur
 * auf Zuruf und nur an dem, was gerade im Eingang liegt: auspacken, Kassetten anhören, in die Fächer sortieren. Eine Assistenz packt laufend aus
 * und sortiert. Von Hand kann man alles in ein anderes Fach ziehen (`box`, überstimmt die Sortierung).
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

    /**
     * Nächste Ankunft bei der Rate von jetzt: Liegt sie noch in der Zukunft, wird die Wartezeit neu gewürfelt (zufällige Ankünfte haben
     * kein Gedächtnis, das ändert nichts am Schnitt). So wirkt eine höhere Rate (mehr Erfolg, bekannter) sofort und nicht erst nach der alten Pause.
     */
    public static function nextArrival(int $stored, int $now, float $mean): int
    {
        return $stored > $now ? $now + self::gap($mean) : $stored;
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

    /** Liegt im Eingang: nicht gemerkt, und entweder noch nicht (fertig) sortiert oder von Hand zurück in den Eingang gezogen. */
    private const IN_INBOX = "saved = 0 AND ((box IS NULL AND (sorted_at IS NULL OR sorted_at > ?)) OR box = 'inbox')";

    /** Die Fächer, in die man von Hand ziehen kann ('saved' = merken). */
    public const BOXES = ['inbox', 'demos', 'interview', 'job', 'saved'];

    /** Wie viel gerade im Eingang liegt (das bearbeitet das Praktikum auf Zuruf). */
    public function todo(int $now): int
    {
        $n = 0;
        foreach (self::TABLES as $t) {
            $n += (int) $this->repo->db->value("SELECT COUNT(*) FROM $t WHERE label_id = ? AND expires_at > ? AND " . self::IN_INBOX, [$this->repo->labelId, $now, $now]);
        }
        return $n;
    }

    /**
     * Auftrag ans Praktikum: alles, was gerade im Eingang liegt, Stück für Stück auspacken, Kassetten anhören und ins passende Fach legen.
     * Dauert so lange wie selbst gemacht; läuft auch offline. Was später kommt, bleibt liegen.
     * @return array{int, int} Anzahl der Stücke, fertig um
     */
    public function order(int $now): array
    {
        $db = $this->repo->db;
        $rows = [];
        foreach (self::TABLES as $t) {
            foreach ($db->all("SELECT * FROM $t WHERE label_id = ? AND expires_at > ? AND " . self::IN_INBOX, [$this->repo->labelId, $now, $now]) as $r) {
                $rows[] = [$t, $r];
            }
        }
        if (!$rows) {
            throw new ApiError('Im Eingang liegt gerade nichts.');
        }
        // Die ältesten zuerst (die laufen als Erste ab).
        usort($rows, static fn ($a, $b) => [(int) $a[1]['created_at'], (int) $a[1]['id']] <=> [(int) $b[1]['created_at'], (int) $b[1]['id']]);
        $at = $now;
        foreach ($rows as [$t, $r]) {
            $set = ['box' => null];
            // Was man gerade selbst auspackt oder anhört, macht man fertig; das Praktikum wartet darauf.
            if ($r['unpacked_at'] === null) {
                $at += Balance::POST['unpack'];
                $set += ['unpacked_at' => $at, 'worker' => 'intern'];
            } else {
                $at = max($at, (int) $r['unpacked_at']);
            }
            if ($t === 'submissions') {
                if ($r['opened_at'] === null) {
                    $at += Balance::SUBMISSIONS['listen'];
                    $set += ['opened_at' => $at, 'worker' => 'intern'];
                } else {
                    $at = max($at, (int) $r['opened_at']);
                }
            }
            $at += Balance::POST['sort'];
            $set['sorted_at'] = $at;
            $db->update($t, $set, ['id' => (int) $r['id']]);
        }
        return [count($rows), $at];
    }

    /** Von Hand in ein Fach ziehen (auch zurück in den Eingang oder zu den Gemerkten). */
    public function move(string $key, int $id, string $box): void
    {
        $table = $this->table($key);
        if (!in_array($box, self::BOXES, true)) {
            throw new ApiError('Dieses Fach gibt es nicht.');
        }
        $db = $this->repo->db;
        if ($db->one("SELECT id FROM $table WHERE id = ? AND label_id = ? AND expires_at > ?", [$id, $this->repo->labelId, Clock::now()]) === null) {
            throw new ApiError('Das gibt es nicht mehr.');
        }
        $db->update($table, $box === 'saved' ? ['saved' => 1] : ['saved' => 0, 'box' => $box], ['id' => $id]);
    }
}
