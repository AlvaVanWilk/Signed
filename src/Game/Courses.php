<?php
declare(strict_types=1);

namespace Signed\Game;

use DateTimeImmutable;
use DateTimeZone;
use Signed\ApiError;
use Signed\Clock;

/**
 * Tageskurse: Jeden Tag mehrere Kurse (Balance::COURSE['perDay']), je ein Instrument und eine Kursart (Kurs, Workshop, Meisterklasse –
 * kürzer/schwächer bis länger/stärker), für alle Spieler gleich, mit Gastdozent. Angeboten in drei Schwierigkeiten (Anfänger,
 * Fortgeschrittene, Profis); jeder Musiker nur in seiner. Gleicher Zuwachs für alle, Preis je Schwierigkeit
 * (Balance::COURSE). Jeder Musiker darf an einem Tag in einen Kurs – auch ohne Band.
 */
final class Courses
{
    /** Starttag der Kursfolge (Tage seit 1970), etwas vor dem Spielstart. */
    private const ANCHOR = 20000;
    private const TEACHERS = ['Mira Klang', 'Rocky Steel', 'Jo Tremolo', 'Lou Groove', 'Sam Vibrato', 'Kim Tonart', 'Alex Akkord', 'Nina Takt'];

    /** Wortanfang für zusammengesetzte Namen: „Geigen-Workshop“, „Gesangs-Kurs“. */
    private const PREFIX = [
        'vocals' => 'Gesangs', 'guitar' => 'Gitarren', 'bass' => 'Bass', 'drums' => 'Schlagzeug', 'keys' => 'Keyboard',
        'turntables' => 'DJ', 'sax' => 'Saxophon', 'trumpet' => 'Trompeten', 'violin' => 'Geigen', 'flute' => 'Flöten',
    ];

    public function __construct(private Repo $repo)
    {
    }

    /** Tag in deutscher Zeit (Kurse wechseln um Mitternacht). */
    public static function day(?int $now = null): string
    {
        return (new DateTimeImmutable('@' . ($now ?? Clock::now())))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('Y-m-d');
    }

    /** Instrument und Dozent des Tages: für alle gleich, aus dem Datum berechnet, nie zweimal dasselbe hintereinander. */
    public static function today(?int $now = null): array
    {
        $day = self::day($now);
        $index = intdiv((new DateTimeImmutable($day . ' 12:00', new DateTimeZone('Europe/Berlin')))->getTimestamp(), 86400);
        $types = array_keys(Catalog::INSTRUMENTS);
        $count = count($types);
        // Von einem festen Starttag aus springt das Instrument jeden Tag um 1 bis count−1 weiter: zufällig wirkend,
        // für alle gleich und nie zweimal dasselbe hintereinander.
        $position = 0;
        for ($d = self::ANCHOR; $d <= $index; $d++) {
            $position = ($position + 1 + abs(crc32("signed-kurs-$d")) % ($count - 1)) % $count;
        }
        $type = $types[$position];
        // Kursart: gewichteter Zufall, aus dem Datum berechnet.
        $kinds = Balance::COURSE['kinds'];
        $roll = abs(crc32("signed-kursart-$index")) % array_sum(array_column($kinds, 1));
        $kind = 0;
        while ($roll >= $kinds[$kind][1]) {
            $roll -= $kinds[$kind][1];
            $kind++;
        }
        $ends = (new DateTimeImmutable($day . ' 00:00', new DateTimeZone('Europe/Berlin')))->modify('+1 day')->getTimestamp();
        return [
            'day' => $day,
            'instrument' => $type,
            'prefix' => self::PREFIX[$type] ?? Catalog::INSTRUMENTS[$type]['name'],
            'teacher' => self::TEACHERS[$index % count(self::TEACHERS)],
            'kind' => $kind,
            'name' => $kinds[$kind][0],
            'seconds' => $kinds[$kind][2],
            'gain' => $kinds[$kind][3],
            'endsAt' => $ends,
        ];
    }

    /**
     * Alle Kurse des Tages (Balance::COURSE['perDay']): der erste wie today(), die anderen mit anderen Instrumenten, Kursarten und Dozenten.
     * Für alle gleich, aus dem Datum berechnet.
     * @return list<array>
     */
    public static function all(?int $now = null): array
    {
        $first = self::today($now) + ['index' => 0];
        $index = intdiv((new DateTimeImmutable($first['day'] . ' 12:00', new DateTimeZone('Europe/Berlin')))->getTimestamp(), 86400);
        $out = [$first];
        $types = array_values(array_diff(array_keys(Catalog::INSTRUMENTS), [$first['instrument']]));
        $kinds = Balance::COURSE['kinds'];
        for ($k = 1; $k < Balance::COURSE['perDay'] && $types; $k++) {
            $type = $types[abs(crc32("signed-kurs-$index-$k")) % count($types)];
            $types = array_values(array_diff($types, [$type]));
            $roll = abs(crc32("signed-kursart-$index-$k")) % array_sum(array_column($kinds, 1));
            $kind = 0;
            while ($roll >= $kinds[$kind][1]) {
                $roll -= $kinds[$kind][1];
                $kind++;
            }
            $out[] = [
                'instrument' => $type,
                'prefix' => self::PREFIX[$type] ?? Catalog::INSTRUMENTS[$type]['name'],
                'teacher' => self::TEACHERS[($index + 3 * $k) % count(self::TEACHERS)],
                'kind' => $kind, 'name' => $kinds[$kind][0], 'seconds' => $kinds[$kind][2], 'gain' => $kinds[$kind][3], 'index' => $k,
            ] + array_intersect_key($first, ['day' => 1, 'endsAt' => 1]);
        }
        return $out;
    }

    /** Vorschau fürs Schwarze Brett: die Kurse der nächsten Tage (ohne heute). */
    public static function week(?int $now = null, int $days = 6): array
    {
        $now ??= Clock::now();
        $out = [];
        for ($d = 1; $d <= $days; $d++) {
            $all = self::all($now + $d * 86400);
            $out[] = ['day' => $all[0]['day'], 'courses' => array_map(static fn ($c) => array_intersect_key($c, array_flip(['instrument', 'prefix', 'name', 'teacher', 'gain', 'seconds', 'kind'])) + [
                'prices' => array_map(static fn ($i) => self::price($c, $i), array_keys(Balance::COURSE['levels'])),
            ], $all)];
        }
        return $out;
    }

    /** Schwierigkeit für diesen Musiker (nach seiner Stufe auf dem Instrument): 0 Anfänger, 1 Fortgeschrittene, 2 Profis. */
    public static function levelFor(array $m, int $slot): int
    {
        return min(count(Balance::COURSE['levels']) - 1, Levels::forSkill(BandCalc::skill($m, $slot)));
    }

    /** Preis der heutigen Kursart in dieser Schwierigkeit. */
    public static function price(array $course, int $level): int
    {
        return Balance::COURSE['kinds'][$course['kind']][4] * Balance::COURSE['priceFactor'][$level];
    }

    /** Für den Spielstand: die Kurse des Tages, je mit drei Schwierigkeiten und wer aus dem Label hin kann (oder heute schon in einem Kurs war). */
    public function status(array $musicians): array
    {
        $courses = self::all();
        $visited = array_map('intval', array_column($this->repo->db->all(
            'SELECT musician_id FROM course_visits WHERE label_id = ? AND day = ?',
            [$this->repo->labelId, $courses[0]['day']]
        ), 'musician_id'));
        return array_map(function (array $course) use ($musicians, $visited): array {
            $candidates = [];
            foreach ($musicians as $m) {
                foreach ([1 => $m['instr1'], 2 => $m['instr2']] as $slot => $type) {
                    if ($type === $course['instrument']) {
                        $candidates[] = ['musicianId' => $m['id'], 'level' => self::levelFor($m, $slot), 'visited' => in_array($m['id'], $visited, true)];
                    }
                }
            }
            return $course + [
                'levels' => array_map(static fn ($name, $i) => ['name' => $name, 'price' => self::price($course, $i)], Balance::COURSE['levels'], array_keys(Balance::COURSE['levels'])),
                'candidates' => $candidates,
            ];
        }, $courses);
    }

    /** Musiker zum passenden Kurs schicken: kostet, dauert eine Weile (auch offline), einmal pro Musiker und Tag. */
    public function attend(int $musicianId, ?int $index = null): array
    {
        $db = $this->repo->db;
        return $db->transaction(function () use ($db, $musicianId, $index): array {
            $now = Clock::now();
            $m = $this->repo->musician($musicianId);
            $all = self::all($now);
            // Ohne Angabe: der erste Kurs, der zum Musiker passt.
            $course = $index !== null ? ($all[$index] ?? $all[0])
                : (array_values(array_filter($all, static fn ($c) => in_array($c['instrument'], [$m['instr1'], $m['instr2']], true)))[0] ?? $all[0]);
            $slot = $m['instr1'] === $course['instrument'] ? 1 : ($m['instr2'] === $course['instrument'] ? 2 : 0);
            if ($slot === 0) {
                throw new ApiError(sprintf('%s spielt kein %s.', $m['first_name'], Catalog::INSTRUMENTS[$course['instrument']]['name']));
            }
            if ($db->value('SELECT id FROM course_visits WHERE musician_id = ? AND day = ?', [$m['id'], $course['day']]) !== null) {
                throw new ApiError(sprintf('%s war heute schon im Kurs.', $m['first_name']));
            }
            if ($m['busy_until'] > $now) {
                throw new ApiError(sprintf('%s ist gerade beschäftigt.', $m['first_name']));
            }
            if ($m['band_id'] !== null && $this->repo->band($m['band_id'])['busy_until'] > $now) {
                throw new ApiError(sprintf('Die Band von %s ist gerade beschäftigt.', $m['first_name']));
            }
            $level = self::levelFor($m, $slot);
            $price = self::price($course, $level);
            $title = sprintf('%s-%s für %s', $course['prefix'], $course['name'], Balance::COURSE['levels'][$level]);
            $this->repo->spend($price, sprintf('%s: %s', $title, $m['first_name']));
            $endsAt = $now + $course['seconds'];
            if ($db->exec('UPDATE musicians SET busy_until = ? WHERE id = ? AND busy_until <= ?', [$endsAt, $m['id'], $now]) === 0) {
                throw new ApiError(sprintf('%s ist gerade beschäftigt.', $m['first_name']));
            }
            $activity = (new Activities($this->repo))->insertSolo($m['id'], 'course', $now, $endsAt, [
                'title' => sprintf('%s bei %s: %s', $title, $course['teacher'], Bands::fullName($m)),
                'songs' => 0,
                'reward' => ['slot' => $slot, 'skill' => $course['gain']],
            ]);
            $db->insert('course_visits', [
                'label_id' => $this->repo->labelId, 'musician_id' => $m['id'], 'day' => $course['day'],
                'price' => $price, 'activity_id' => $activity['id'], 'created_at' => $now,
            ]);
            return $activity;
        });
    }
}
