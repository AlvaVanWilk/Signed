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
     * Zuwachs je Stunde wie heute: geäußerter Wunsch (`wish`), Wunsch nach einem passenden Instrument (`gear`, Diven doppelt),
     * Unruhe ohne Auftritt (`restless`, Extrovertierte und Gesellige; sonst 0).
     */
    public static function perHour(string $kind, array $musician): float
    {
        $traits = $musician['personality'] ?? [];
        return match ($kind) {
            'wish' => Balance::NEEDS['wish']['perHour'],
            'gear' => Balance::NEEDS['gear']['perHour'] * max([1.0, ...array_map(static fn ($t) => Balance::MOOD['gear']['traits'][$t] ?? 1.0, $traits)]),
            'restless' => Balance::TRAITS['restless']['grow'] * max([0.0, ...array_map(static fn ($t) => Balance::TRAITS['restless']['traits'][$t] ?? 0.0, $traits)]),
        };
    }

    /** Unruhe taucht nach so vielen Stunden ohne Auftritt auf. */
    public static function isRestless(array $musician, float $hoursSinceGig): bool
    {
        return self::perHour('restless', $musician) > 0 && $hoursSinceGig >= Balance::TRAITS['restless']['hours'];
    }
}
