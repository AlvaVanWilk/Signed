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

    /**
     * Mischpult (Studio): Die Pegel der Spuren schwanken; schwächere Musiker häufiger und stärker.
     * @param list<float> $skills Können pro Spur
     * @return list<array{t: float, k: int, v: float}> Zeitpunkt, Spur, Pegeländerung
     */
    public static function mixerDrifts(array $skills, int $duration): array
    {
        $drifts = [];
        foreach (array_values($skills) as $track => $skill) {
            $shaky = (100 - min(100.0, $skill)) / 100;
            $count = (int) round(3 + $shaky * 8);
            $size = 0.1 + $shaky * 0.22;
            for ($i = 0; $i < $count; $i++) {
                $drifts[] = [
                    't' => round(Rng::float(1.5, $duration - 1.5), 2),
                    'k' => $track,
                    'v' => round(Rng::chance(0.5) ? $size : -$size, 3),
                ];
            }
        }
        usort($drifts, static fn ($a, $b) => $a['t'] <=> $b['t']);
        return $drifts;
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
