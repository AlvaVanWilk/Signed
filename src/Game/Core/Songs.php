<?php
declare(strict_types=1);

namespace Signed\Game\Core;

use Signed\Game\Balance;

/**
 * Songwriting im neuen Kern: Songs je Tag aus der Zahl der wirklichen Songschreiber und den Einflüssen. Noch nicht ins Spiel eingebaut (neuer Kern).
 */
final class Songs
{
    /** Gewicht eines Mitglieds beim Schreiben: normal 1, nach Charakter (Kreativ 1,5 … Technisch 0); mehrere Züge werden multipliziert. */
    public static function weight(array $musician): float
    {
        $writers = Balance::CORE_SONGWRITING['writers'];
        $weight = 1.0;
        foreach ($musician['personality'] ?? [] as $trait) {
            $weight *= $writers[$trait] ?? 1.0;
        }
        return $weight;
    }

    /** Zahl der wirklichen Songschreiber n (Summe der Gewichte). */
    public static function writers(array $members): float
    {
        return (float) array_sum(array_map([self::class, 'weight'], $members));
    }

    /**
     * Songs je Tag = base × (1 + spread × (n − 1) / (n + 1)) × Regler × Stimmung × Tätigkeit × Genre × Coach, nie unter 0.
     * $slider: fast/normal/careful, $mood: green/yellow/red, $activity: record/gig/rehearsal/idle, $genre: genre/hybrid/crossover/mixed,
     * $coach: Stufe 1–3 oder 0 ohne Coach.
     */
    public static function perDay(float $writers, string $slider = 'normal', string $mood = 'green', string $activity = 'idle', string $genre = 'genre', int $coach = 0): float
    {
        $c = Balance::CORE_SONGWRITING;
        $base = $c['base'] * (1 + $c['spread'] * ($writers - 1) / ($writers + 1));
        return max(0.0, $base * $c['slider'][$slider] * $c['mood'][$mood] * $c['activity'][$activity] * $c['genre'][$genre] * ($c['coach'][$coach] ?? 1.0));
    }
}
