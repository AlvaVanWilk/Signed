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

    /** Die Fächer, in die man von Hand ziehen kann ('saved' = merken). */
    public const BOXES = ['inbox', 'demos', 'interview', 'job', 'saved'];

    /** Spalte, Dauer je Stück und Text, wenn nichts da ist: je Zuruf ans Praktikum. */
    private const JOBS = [
        'unpack' => ['unpacked_at', 'Gerade liegt kein ungeöffneter Brief da.'],
        'listen' => ['opened_at', 'Gerade liegt keine ungehörte Kassette da.'],
        'sort' => ['sorted_at', 'Im Eingang liegt nichts zum Sortieren.'],
    ];

    /** Sekunden je Stück für diesen Zuruf. */
    public static function seconds(string $job): int
    {
        return $job === 'listen' ? Balance::SUBMISSIONS['listen'] : Balance::POST[$job];
    }

    /** Bedingung, unter der ein Stück für diesen Zuruf (noch) zu tun ist, zur Zeit ? (zweimal: Ablauf, Zeitpunkt). */
    private static function open(string $job, string $table): ?string
    {
        return match ($job) {
            'unpack' => 'expires_at > ? AND unpacked_at IS NULL AND created_at <= ?',
            // Sortieren kann man nur Ausgepacktes, und nur, was im Eingang liegt (auch von Hand zurückgelegtes).
            'sort' => "expires_at > ? AND unpacked_at <= ? AND saved = 0 AND ((box IS NULL AND sorted_at IS NULL) OR box = 'inbox')",
            'listen' => $table === 'submissions' ? 'expires_at > ? AND unpacked_at <= ? AND opened_at IS NULL' : null,
            default => null,
        };
    }

    /** Was für diesen Zuruf gerade da liegt, das Neueste zuerst: [[Tabelle, ID], …]. */
    private function pending(string $job, int $now): array
    {
        $rows = [];
        foreach (self::TABLES as $t) {
            $where = self::open($job, $t);
            if ($where === null) {
                continue;
            }
            foreach ($this->repo->db->all("SELECT id, created_at FROM $t WHERE label_id = ? AND $where", [$this->repo->labelId, $now, $now]) as $r) {
                $rows[] = [$t, (int) $r['id'], (int) $r['created_at']];
            }
        }
        usort($rows, static fn ($a, $b) => [$b[2], $b[1]] <=> [$a[2], $a[1]]);
        return array_map(static fn ($r) => [$r[0], $r[1]], $rows);
    }

    /** @return array{unpack: int, listen: int, sort: int} wie viel je Zuruf gerade da liegt */
    public function todo(int $now): array
    {
        return array_map(fn ($job) => count($this->pending($job, $now)), array_combine(array_keys(self::JOBS), array_keys(self::JOBS)));
    }

    /**
     * Zuruf ans Praktikum: die Liste dessen, was gerade da liegt (Neuestes zuerst). Abgearbeitet wird sie Stück für Stück (step); gesperrt ist
     * immer nur das Stück, an dem das Praktikum gerade sitzt.
     * @return list<array{string, int}>
     */
    public function order(string $job, int $now): array
    {
        [, $none] = self::JOBS[$job] ?? throw new ApiError('Diesen Auftrag gibt es nicht.');
        $rows = $this->pending($job, $now);
        if (!$rows) {
            throw new ApiError($none);
        }
        return $rows;
    }

    /** Das nächste Stück anfangen (um $at): fertig um …, oder null, wenn es inzwischen jemand anders erledigt hat (oder es weg ist). */
    public function step(string $job, array $item, int $at): ?int
    {
        [$table, $id] = $item;
        $where = self::open($job, $table);
        if ($where === null || $this->repo->db->one("SELECT id FROM $table WHERE id = ? AND label_id = ? AND $where", [$id, $this->repo->labelId, $at, $at]) === null) {
            return null;
        }
        $end = $at + self::seconds($job);
        $this->repo->db->update($table, [self::JOBS[$job][0] => $end, 'worker' => 'intern'] + ($job === 'sort' ? ['box' => null] : []), ['id' => $id]);
        return $end;
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
