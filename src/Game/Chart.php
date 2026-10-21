<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Rng;

/** Noten für das Rhythmus-Minispiel: vier Spuren, Noten auf Achteln. */
final class Chart
{
    public const LANES = 4;

    /** @return list<array{t: float, l: int}> Zeit in Sekunden ab Liedbeginn, Spur 0–3 */
    public static function generate(int $bpm, float $density, int $duration): array
    {
        $step = 60 / $bpm / 2;
        $notes = [];
        $lastLane = -1;
        // Zwei Schläge Vorlauf, eine Sekunde Auslauf.
        for ($i = 4; $i * $step < $duration - 1; $i++) {
            $onBeat = $i % 2 === 0;
            if (!Rng::chance($density * ($onBeat ? 1.0 : 0.5))) {
                continue;
            }
            $lane = Rng::int(0, self::LANES - 1);
            if ($lane === $lastLane && Rng::chance(0.5)) {
                $lane = ($lane + Rng::int(1, self::LANES - 1)) % self::LANES;
            }
            $notes[] = ['t' => round($i * $step, 3), 'l' => $lane];
            $lastLane = $lane;
        }
        return $notes;
    }

    /** Dichte: Anfänger bekommen wenige Noten, bessere Musiker etwas anspruchsvollere Lieder. */
    public static function density(float $ability): float
    {
        return 0.25 + 0.3 * min(100.0, $ability) / 100;
    }

    /** Zeitfenster in Millisekunden. Mehr Können bzw. Sound = großzügigeres Timing. */
    public static function windows(float $ability, bool $perfectPitch = false): array
    {
        $bonus = min(50.0, $ability * 0.5) + ($perfectPitch ? 20 : 0);
        return [
            'perfect' => (int) round(Balance::RHYTHM_WINDOWS['perfect'] + $bonus * 0.3),
            'good' => (int) round(Balance::RHYTHM_WINDOWS['good'] + $bonus),
        ];
    }

    /**
     * Tonhöhen-Spiel (Gesang, Geige): eine Melodie aus gehaltenen Tönen.
     * p = Stufe 0–7 auf der Tonleiter, t/d = Beginn und Dauer in Sekunden.
     * @return list<array{t: float, d: float, p: int}>
     */
    public static function melody(int $bpm, int $duration): array
    {
        $beat = 60 / $bpm;
        $notes = [];
        $t = 2 * $beat;
        $pitch = Rng::int(2, 5);
        while (true) {
            $length = Rng::pick([1, 1.5, 2, 2, 3, 4]) * $beat;
            if ($t + $length > $duration - 1) {
                break;
            }
            $notes[] = ['t' => round($t, 3), 'd' => round($length, 3), 'p' => $pitch];
            $t += $length + (Rng::chance(0.4) ? $beat / 2 : 0);
            $pitch = max(0, min(7, $pitch + Rng::pick([-2, -1, -1, 0, 1, 1, 2])));
        }
        return $notes;
    }

    /** Wie weit der eigene Ton danebenliegen darf (Anteil der Tonhöhen-Skala). */
    public static function pitchTolerance(float $ability, bool $perfectPitch = false): float
    {
        return round(0.07 + 0.06 * min(100.0, $ability) / 100 + ($perfectPitch ? 0.03 : 0), 3);
    }

    /** Abschnitte pro Spur im Mischpult-Spiel; jeder wird einzeln bewertet. */
    public const MIXER_SEGMENTS = 6;

    /** Mehr als so viele Spuren werden zu Gruppen zusammengefasst. */
    public const MIXER_MAX_TRACKS = 4;

    /** Instrumentengruppen am Mischpult, wenn die Band zu groß für Einzelspuren ist. */
    private const MIXER_GROUPS = [
        'Rhythmus' => ['drums', 'bass', 'turntables'],
        'Harmonie' => ['guitar', 'keys'],
        'Melodie' => ['vocals', 'sax', 'trumpet', 'violin', 'flute'],
    ];

    /**
     * Spuren am Mischpult: bis zu vier Musiker einzeln, größere Bands in Gruppen (Rhythmus, Harmonie, Melodie).
     * @param list<array{name: string, instrument: string, skill: float, stem?: array}> $players
     * @return list<array{name: string, instruments: list<string>, skill: float, stems: list<array>}>
     */
    public static function mixerTracks(array $players): array
    {
        if (count($players) <= self::MIXER_MAX_TRACKS) {
            return array_map(static fn ($p) => [
                'name' => $p['name'], 'instruments' => [$p['instrument']], 'skill' => $p['skill'], 'stems' => isset($p['stem']) ? [$p['stem']] : [],
            ], $players);
        }
        $tracks = [];
        foreach (self::MIXER_GROUPS as $group => $types) {
            $members = array_values(array_filter($players, static fn ($p) => in_array($p['instrument'], $types, true)));
            if ($members) {
                $tracks[] = [
                    'name' => $group,
                    'instruments' => array_values(array_unique(array_column($members, 'instrument'))),
                    'skill' => array_sum(array_column($members, 'skill')) / count($members),
                    'stems' => array_values(array_filter(array_column($members, 'stem'))),
                ];
            }
        }
        return $tracks;
    }

    /**
     * Mischpult (Studio): Jede Spur gleitet ständig um ihren Pegel – ein Zufallspfad, der immer wieder zur Mitte
     * zurückstrebt. Schwächere Musiker bewegen sich öfter und weiter weg, gute nur sanft.
     * @param list<float> $skills Können pro Spur
     * @return list<array{t: float, k: int, v: float}> Zeitpunkt, Spur, Ziel-Abweichung vom Sollpegel
     */
    public static function mixerDrifts(array $skills, int $duration): array
    {
        $drifts = [];
        foreach (array_values($skills) as $track => $skill) {
            $shaky = (100 - min(100.0, $skill)) / 100;
            $interval = 1.4 - 0.7 * $shaky;
            $step = 0.05 + 0.13 * $shaky;
            $offset = 0.0;
            for ($t = Rng::float(0.3, $interval); $t < $duration; $t += $interval * Rng::float(0.7, 1.3)) {
                $offset = max(-0.5, min(0.5, $offset * 0.75 + Rng::float(-$step, $step) * 1.6));
                $drifts[] = ['t' => round($t, 2), 'k' => $track, 'v' => round($offset, 3)];
            }
        }
        usort($drifts, static fn ($a, $b) => $a['t'] <=> $b['t']);
        return $drifts;
    }

    /** Master-Mix: Bässe, Mitten, Höhen. */
    public const MASTER_BANDS = ['Bässe', 'Mitten', 'Höhen'];

    /** Bewertungsabschnitte im Master-Mix; nach der Hälfte ändert sich die richtige Einstellung einmal. */
    public const MASTER_SEGMENTS = 4;

    /**
     * Master-Mix: richtige Reglerstellung (0–1) pro Abschnitt und Frequenzband. Zur Mitte des Liedes
     * verschiebt sich jedes Band ein Stück, damit man noch einmal nachhören muss.
     * @return list<list<float>>
     */
    public static function masterTargets(): array
    {
        $first = array_map(static fn () => Rng::float(0.15, 0.85), self::MASTER_BANDS);
        $second = array_map(static fn ($v) => max(0.15, min(0.85, $v + (Rng::chance(0.5) ? 1 : -1) * Rng::float(0.15, 0.35))), $first);
        $round = static fn ($list) => array_map(static fn ($v) => round($v, 3), $list);
        $half = intdiv(self::MASTER_SEGMENTS, 2);
        return array_merge(array_fill(0, $half, $round($first)), array_fill(0, self::MASTER_SEGMENTS - $half, $round($second)));
    }

    /** Halbe Breite des grünen Bereichs am Mischpult. Ein besserer Sound verzeiht mehr. */
    public static function mixerZone(float $sound): float
    {
        return round(0.11 + 0.08 * min(100.0, $sound) / 100, 3);
    }

    /** Trefferquote 0–1: Perfekt zählt voll, Gut zu 60 %. */
    public static function accuracy(int $perfect, int $good, int $miss): float
    {
        $total = $perfect + $good + $miss;
        return $total === 0 ? 0.0 : ($perfect + 0.6 * $good) / $total;
    }
}
