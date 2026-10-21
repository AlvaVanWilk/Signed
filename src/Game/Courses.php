<?php
declare(strict_types=1);

namespace Signed\Game;

use DateTimeImmutable;
use DateTimeZone;
use Signed\ApiError;
use Signed\Clock;

/**
 * Tageskurse: Jeden Tag ein Instrument, für alle Spieler gleich, mit Gastdozent. Dazu drei Kurse, je nach Stufe:
 * Kurs (Anfänger), Workshop (Fortgeschrittene), Meisterklasse (ab Profi). Fester Preis und fester Zuwachs
 * (Balance::COURSE); jeder Musiker darf an seinem Tag einmal hin – auch ohne Band.
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
        $ends = (new DateTimeImmutable($day . ' 00:00', new DateTimeZone('Europe/Berlin')))->modify('+1 day')->getTimestamp();
        return [
            'day' => $day,
            'instrument' => $type,
            'prefix' => self::PREFIX[$type] ?? Catalog::INSTRUMENTS[$type]['name'],
            'teacher' => self::TEACHERS[$index % count(self::TEACHERS)],
            'endsAt' => $ends,
        ];
    }

    /** Welcher Kurs zu diesem Musiker passt (nach seiner Stufe auf dem Instrument): [Stufe, Name, Können, Preis]. */
    public static function kindFor(array $m, int $slot): array
    {
        $kinds = Balance::COURSE['kinds'];
        $level = min(count($kinds) - 1, Levels::forSkill(BandCalc::skill($m, $slot)));
        return [$level, ...$kinds[$level]];
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
                    $candidates[] = ['musicianId' => $m['id'], 'kind' => self::kindFor($m, $slot)[0], 'visited' => in_array($m['id'], $visited, true)];
                }
            }
        }
        return $course + [
            'kinds' => array_map(static fn ($k) => ['name' => $k[0], 'gain' => $k[1], 'price' => $k[2]], Balance::COURSE['kinds']),
            'seconds' => Balance::COURSE['seconds'],
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
            [, $name, $gain, $price] = self::kindFor($m, $slot);
            $title = sprintf('%s-%s', $course['prefix'], $name);
            $this->repo->spend($price, sprintf('%s: %s', $title, $m['first_name']));
            $endsAt = $now + Balance::COURSE['seconds'];
            if ($db->exec('UPDATE musicians SET busy_until = ? WHERE id = ? AND busy_until <= ?', [$endsAt, $m['id'], $now]) === 0) {
                throw new ApiError(sprintf('%s ist gerade beschäftigt.', $m['first_name']));
            }
            $activity = (new Activities($this->repo))->insertSolo($m['id'], 'course', $now, $endsAt, [
                'title' => sprintf('%s bei %s: %s', $title, $course['teacher'], Bands::fullName($m)),
                'songs' => 0,
                'reward' => ['slot' => $slot, 'skill' => $gain],
            ]);
            $db->insert('course_visits', [
                'label_id' => $this->repo->labelId, 'musician_id' => $m['id'], 'day' => $course['day'],
                'price' => $price, 'activity_id' => $activity['id'], 'created_at' => $now,
            ]);
            return $activity;
        });
    }
}
