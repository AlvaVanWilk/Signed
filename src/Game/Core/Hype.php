<?php
declare(strict_types=1);

namespace Signed\Game\Core;

use Signed\Game\Balance;

/**
 * Hype einer Platte: beim Erscheinen aus der Qualität, danach jeden Tag weniger (gezählt ab dem Erscheinen).
 * Ereignisse geben Schübe; jeder weitere für dieselbe Platte zählt nur halb so viel, so bleibt kein Hype dauerhaft oben.
 * Noch nicht ins Spiel eingebaut (neuer Kern).
 */
final class Hype
{
    public static function start(float $quality): float
    {
        return min(Balance::CORE_HYPE['max'], $quality * $quality / 100);
    }

    /** Hype beim Erscheinen eines Albums mit $songs Liedern: × Songs / 12, höchstens 100. */
    public static function release(float $quality, int $songs = Balance::CORE_ALBUM_SONGS): float
    {
        return min(Balance::CORE_HYPE['max'], self::start($quality) * $songs / Balance::CORE_ALBUM_SONGS);
    }

    /** Faktor je Tag: perfekte Platten klingen langsamer ab. */
    public static function decay(float $quality): float
    {
        $c = Balance::CORE_HYPE;
        return $quality >= $c['perfect'] ? $c['decayPerfect'] : $c['decay'];
    }

    public static function next(float $hype, float $quality): float
    {
        return $hype * self::decay($quality);
    }

    /** Hype nach so vielen Tagen ohne Schübe. */
    public static function afterDays(float $quality, int $days): float
    {
        return self::start($quality) * self::decay($quality) ** max(0, $days);
    }

    /**
     * Schub durch ein Ereignis.
     * @return array{0: float, 1: int} [neuer Hype, Zahl der Schübe danach]
     */
    public static function boost(float $hype, float $amount, int $boostsSoFar): array
    {
        $c = Balance::CORE_HYPE;
        return [min($c['max'], $hype + $amount * $c['halve'] ** $boostsSoFar), $boostsSoFar + 1];
    }

    /** Flamme in den Charts: blaze, red, weak, ember oder none. */
    public static function flame(float $hype): string
    {
        foreach (Balance::CORE_HYPE['flames'] as $flame => $from) {
            if ($hype >= $from) {
                return $flame;
            }
        }
        return 'none';
    }
}
