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

    /** Dichte: bessere Musiker bekommen etwas anspruchsvollere Lieder. */
    public static function density(float $ability): float
    {
        return 0.38 + 0.18 * min(100.0, $ability) / 100;
    }

    /** Zeitfenster in Millisekunden. Mehr Können bzw. Sound = großzügigeres Timing. */
    public static function windows(float $ability, bool $perfectPitch = false): array
    {
        $bonus = min(50.0, $ability * 0.5) + ($perfectPitch ? 20 : 0);
        return ['perfect' => (int) round(45 + $bonus * 0.4), 'good' => (int) round(100 + $bonus)];
    }

    /** Trefferquote 0–1: Perfekt zählt voll, Gut zu 60 %. */
    public static function accuracy(int $perfect, int $good, int $miss): float
    {
        $total = $perfect + $good + $miss;
        return $total === 0 ? 0.0 : ($perfect + 0.6 * $good) / $total;
    }
}
