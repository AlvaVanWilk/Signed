<?php
declare(strict_types=1);

namespace Signed\Game\Core;

use Signed\Game\Balance;

/**
 * Fans sind der Erfolg einer Band: Ruhmstufe, Dach, Rechen-Pool und was mit den Fans passiert, wenn sich eine Band ändert.
 * Noch nicht ins Spiel eingebaut (neuer Kern).
 */
final class Fans
{
    /** Ruhmstufe 0–3 (= Sterne). */
    public static function stage(float $fans): int
    {
        $stage = 0;
        foreach (Balance::CORE_STAGES as $level => $from) {
            if ($fans >= $from) {
                $stage = $level;
            }
        }
        return $stage;
    }

    /** Höchstens so viele Fans: `mixed`, `genre`, `hybrid` oder `crossover` (Solokünstler mit Pop oder Elektro wie Genre, mit beidem wie Hybrid). */
    public static function roof(string $kind): int
    {
        return Balance::CORE_ROOF[$kind] ?? Balance::CORE_ROOF['mixed'];
    }

    public static function capped(float $fans, string $kind): float
    {
        return max(0.0, min($fans, (float) self::roof($kind)));
    }

    /** Neue Fans bis zum Dach. Wer schon darüber ist (etwa nach einem Wechsel der Besetzung), behält seine Fans, wächst aber nicht weiter. */
    public static function grow(float $fans, float $gain, string $kind): float
    {
        return max($fans, min($fans + $gain, (float) self::roof($kind)));
    }

    /** Rechen-Pool, festgelegt beim Erscheinen einer Platte: nie weniger als 10.000 über den Fans. */
    public static function pool(float $fansAtRelease): float
    {
        $c = Balance::CORE_POOL;
        return max((float) $c['base'], $fansAtRelease + $c['above']);
    }

    /** Gewicht eines Musikers für den Austritt: doppelt mit starkem Auftreten oder Künstlernamen. */
    public static function weight(array $musician): float
    {
        $c = Balance::CORE_FAN_MOVES;
        $strong = array_intersect($musician['personality'] ?? [], $c['strongTraits']) !== [] || ($musician['stage_name'] ?? null) !== null;
        return $strong ? $c['strong'] : 1.0;
    }

    /**
     * Ein Mitglied verlässt die Band. Von den Fans, die vor allem wegen ihm da waren, geht ein Teil mit, ein Teil hört ganz auf.
     * @param float[] $weights Gewichte aller Mitglieder (auch des Gehenden)
     * @return array{0: float, 1: float} [nimmt er mit, behält die Band]
     */
    public static function leave(float $fans, array $weights, int $index): array
    {
        $c = Balance::CORE_FAN_MOVES;
        $total = array_sum($weights);
        $his = $total > 0 ? $fans * ($weights[$index] ?? 0) / $total : 0.0;
        return [$his * $c['follow'], $fans - $his * ($c['follow'] + $c['quit'])];
    }

    /** Zwei Bands tun sich zusammen: die größere plus ein Teil der kleineren (viele sind schon Fans von beiden). */
    public static function merge(float $a, float $b): float
    {
        return max($a, $b) + Balance::CORE_FAN_MOVES['mergeSmall'] * min($a, $b);
    }

    /** Umbenennen: ein Teil der Fans mag keinen neuen Namen. */
    public static function renamed(float $fans): float
    {
        return $fans * (1 - Balance::CORE_FAN_MOVES['rename']);
    }

    /** Label-Bestenliste: alle Fans aller Bands des Labels zusammen. */
    public static function labelTotal(array $bandFans): float
    {
        return (float) array_sum($bandFans);
    }
}
