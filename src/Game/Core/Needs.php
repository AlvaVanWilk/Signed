<?php
declare(strict_types=1);

namespace Signed\Game\Core;

use Signed\Game\Balance;

/**
 * Bedürfnisse im neuen Kern (eine Regel für alle, Sandra): Sie dürfen jederzeit auftauchen, wachsen aber nur alle 24 Stunden,
 * und zwar um so viel, wie heute in 24 Stunden dazukommt. Das Auftauchen allein kostet nichts. Noch nicht ins Spiel eingebaut (neuer Kern).
 */
final class Needs
{
    /** So viel Wohlbefinden hat ein Bedürfnis gekostet, das seit $hoursOpen Stunden offen ist ($perHour = Zuwachs je Stunde wie heute). */
    public static function penalty(float $hoursOpen, float $perHour): float
    {
        $step = Balance::CORE_NEEDS['stepHours'];
        return floor(max(0.0, $hoursOpen) / $step) * $step * $perHour;
    }

    /**
     * Zuwachs je Stunde wie heute: geäußerter Wunsch (`wish`), Wunsch nach einem passenden Instrument (`gear`, Egozentrische doppelt),
     * Unruhe ohne Auftritt (`restless`: Extrovertierte, Gesellige) und ohne neue Platte (`stagnant`: Ehrgeizige); wen es nicht betrifft: 0.
     */
    public static function perHour(string $kind, array $musician): float
    {
        $traits = $musician['personality'] ?? [];
        $most = static fn (array $table, float $none) => max([$none, ...array_map(static fn ($t) => $table[$t] ?? $none, $traits)]);
        return match ($kind) {
            'wish' => Balance::NEEDS['wish']['perHour'],
            'gear' => Balance::NEEDS['gear']['perHour'] * $most(Balance::MOOD['gear']['traits'], 1.0),
            'restless', 'stagnant' => Balance::TRAITS[$kind]['grow'] * $most(Balance::TRAITS[$kind]['traits'], 0.0),
        };
    }

    /** Unruhe taucht auf: nach so vielen Stunden ohne Auftritt (`restless`) bzw. ohne neue Platte (`stagnant`), nur bei passendem Charakter. */
    public static function appears(string $kind, array $musician, float $hoursIdle): bool
    {
        return self::perHour($kind, $musician) > 0 && $hoursIdle >= Balance::TRAITS[$kind]['hours'];
    }
}
