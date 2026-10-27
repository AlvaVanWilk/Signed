<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;
use Signed\Rng;

/**
 * Post im Posteingang (Kassetten in `submissions`, Briefe in `mail`): Alles kommt in Umschlägen und landet im Eingang.
 * Auspacken dauert ein paar Sekunden (Balance::POST), selbst oder per Praktikum (gleich schnell, aber nebenbei). Das Praktikum arbeitet nur
 * auf Zuruf, eine Sache auf einmal (Briefe öffnen, Demos anhören oder Post sortieren), nur an dem, was gerade da liegt, das Neueste zuerst,
 * und lässt sich abbrechen. Eine Assistenz packt laufend aus
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

    /** Spalte, Dauer je Stück und Text, wenn nichts da ist: je Zuruf ans Praktikum. */
    private const JOBS = [
        'unpack' => ['unpacked_at', 'unpack', 'Gerade liegt kein ungeöffneter Brief da.'],
        'listen' => ['opened_at', 'listen', 'Gerade liegt keine ungehörte Kassette da.'],
        'sort' => ['sorted_at', 'sort', 'Im Eingang liegt nichts zum Sortieren.'],
    ];

    /** Was das Praktikum gerade tun könnte, je Zuruf (die Stücke, die in dem Moment da liegen). */
    private function pending(string $job, int $now): array
    {
        $db = $this->repo->db;
        $l = $this->repo->labelId;
        $rows = [];
        $add = static function (string $t, array $list) use (&$rows): void {
            foreach ($list as $r) {
                $rows[] = [$t, (int) $r['id'], (int) $r['created_at']];
            }
        };
        foreach (self::TABLES as $t) {
            if ($job === 'unpack') {
                $add($t, $db->all("SELECT id, created_at FROM $t WHERE label_id = ? AND expires_at > ? AND unpacked_at IS NULL", [$l, $now]));
            } elseif ($job === 'sort') {
                // Sortieren kann man nur Ausgepacktes, und nur, was im Eingang liegt.
                $add($t, $db->all("SELECT id, created_at FROM $t WHERE label_id = ? AND expires_at > ? AND unpacked_at <= ? AND " . self::IN_INBOX, [$l, $now, $now, $now]));
            } elseif ($t === 'submissions') {
                $add($t, $db->all('SELECT id, created_at FROM submissions WHERE label_id = ? AND expires_at > ? AND unpacked_at <= ? AND opened_at IS NULL', [$l, $now, $now]));
            }
        }
        // Das Neueste zuerst.
        usort($rows, static fn ($a, $b) => [$b[2], $b[1]] <=> [$a[2], $a[1]]);
        return $rows;
    }

    /** @return array{unpack: int, listen: int, sort: int} wie viel je Zuruf gerade da liegt */
    public function todo(int $now): array
    {
        return array_map(fn ($job) => count($this->pending($job, $now)), array_combine(array_keys(self::JOBS), array_keys(self::JOBS)));
    }

    /**
     * Zuruf ans Praktikum: nur diese eine Sache, und nur für das, was gerade da liegt (Neuestes zuerst). Dauert so lange wie selbst gemacht;
     * läuft auch offline. Was danach kommt, bleibt liegen.
     * @return array{int, int} Anzahl der Stücke, fertig um
     */
    public function order(string $job, int $now): array
    {
        [$column, $step, $none] = self::JOBS[$job] ?? throw new ApiError('Diesen Auftrag gibt es nicht.');
        $rows = $this->pending($job, $now);
        if (!$rows) {
            throw new ApiError($none);
        }
        $seconds = $step === 'listen' ? Balance::SUBMISSIONS['listen'] : Balance::POST[$step];
        foreach ($rows as $k => [$t, $id]) {
            $set = [$column => $now + ($k + 1) * $seconds, 'worker' => 'intern'];
            if ($job === 'sort') {
                $set['box'] = null;
            }
            $this->repo->db->update($t, $set, ['id' => $id]);
        }
        return [count($rows), $now + count($rows) * $seconds];
    }

    /** Zuruf abbrechen: Was das Praktikum noch nicht fertig hat, bleibt liegen wie vorher; Fertiges bleibt fertig. */
    public function cancel(int $now): void
    {
        $db = $this->repo->db;
        foreach (self::TABLES as $t) {
            foreach ($t === 'submissions' ? ['unpacked_at', 'opened_at', 'sorted_at'] : ['unpacked_at', 'sorted_at'] as $column) {
                $db->exec("UPDATE $t SET $column = NULL WHERE label_id = ? AND worker = 'intern' AND $column > ?", [$this->repo->labelId, $now]);
            }
        }
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
