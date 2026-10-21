<?php
declare(strict_types=1);

namespace Signed\Game;

use DateTimeImmutable;
use DateTimeZone;
use Signed\ApiError;
use Signed\Clock;

/**
 * Tageskurse: Jeden Tag ein Instrument und eine Kursart (Kurs, Workshop, Meisterklasse – kürzer/schwächer bis
 * länger/stärker), für alle Spieler gleich, mit Gastdozent. Angeboten in drei Schwierigkeiten (Anfänger,
 * Fortgeschrittene, Profis); jeder Musiker nur in seiner. Gleicher Zuwachs für alle, Preis je Schwierigkeit
 * (Balance::COURSE). Jeder Musiker darf an seinem Tag einmal hin – auch ohne Band.
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

    /** Für den Spielstand: der Tag, die drei Kurse und wer aus dem Label hin kann (oder schon war). */
    public function status(array $musicians): array
    {
        $course = self::today();
        $visited = array_map('intval', array_column($this->repo->db->all(
            'SELECT musician_id FROM course_visits WHERE label_id = ? AND day = ?',
            [$this->repo->labelId, $course['day']]
        ), 'musician_id'));
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
    }

    /** Musiker zum passenden Kurs schicken: kostet, dauert eine Weile (auch offline), einmal pro Musiker und Tag. */
    public function attend(int $musicianId): array
    {
        $db = $this->repo->db;
        return $db->transaction(function () use ($db, $musicianId): array {
            $now = Clock::now();
            $course = self::today($now);
            $m = $this->repo->musician($musicianId);
            $slot = $m['instr1'] === $course['instrument'] ? 1 : ($m['instr2'] === $course['instrument'] ? 2 : 0);
            if ($slot === 0) {
                throw new ApiError(sprintf('Heute gibt es nur Kurse für %s.', Catalog::INSTRUMENTS[$course['instrument']]['name']));
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
