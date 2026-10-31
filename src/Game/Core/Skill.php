<?php
declare(strict_types=1);

namespace Signed\Game\Core;

use Signed\Game\Balance;

/**
 * Können im neuen Kern: drei Aufstiege (ab 100 / 400 / 1.600), fester Zuwachs je Probestunde, nach oben offen.
 * Dazu der Übergang alter Musiker. Noch nicht ins Spiel eingebaut (neuer Kern).
 */
final class Skill
{
    /** Können-Stufe 0–3: Anfänger, Fortgeschritten, Profi, Virtuose (= Farbe des Instruments, das er spielen darf). */
    public static function stage(float $skill): int
    {
        $stage = 0;
        foreach (Balance::CORE_SKILL['levels'] as $i => $from) {
            if ($skill >= $from) {
                $stage = $i + 1;
            }
        }
        return $stage;
    }

    /** Zuwachs für so viele Stunden, in denen wirklich geprobt wird ($factor: Charakter, Instrument). */
    public static function gain(float $hours, float $factor = 1.0): float
    {
        return $hours * Balance::CORE_SKILL['perHour'] * $factor;
    }

    /** Übergang: altes Können (0–100) an dieselbe Stelle seiner Stufe im neuen Können (35 / 60 / 85 werden 100 / 400 / 1.600). */
    public static function fromLegacy(float $old): float
    {
        $points = Balance::CORE_SKILL['legacy'];
        $keys = array_keys($points);
        for ($i = 1; $i < count($keys); $i++) {
            [$a, $b] = [$keys[$i - 1], $keys[$i]];
            if ($old <= $b || $i === count($keys) - 1) {
                return max(0.0, $points[$a] + ($old - $a) * ($points[$b] - $points[$a]) / ($b - $a));
            }
        }
        return 0.0;
    }

    /** Umkehrung von fromLegacy: neues Können auf der alten Skala (für Regeln, die noch mit 0–100 rechnen; über 100 offen). */
    public static function toLegacy(float $new): float
    {
        $points = Balance::CORE_SKILL['legacy'];
        $old = array_keys($points);
        $values = array_values($points);
        for ($i = 1; $i < count($values); $i++) {
            if ($new <= $values[$i] || $i === count($values) - 1) {
                return max(0.0, $old[$i - 1] + ($new - $values[$i - 1]) * ($old[$i] - $old[$i - 1]) / ($values[$i] - $values[$i - 1]));
            }
        }
        return 0.0;
    }

    /** Übergang: Farbe des eigenen Instruments, die der Musiker behält (höchstens die seiner neuen Stufe; 0 grau … 3 gold). */
    public static function keptGear(int $tier, float $skill): int
    {
        return min($tier, self::stage($skill));
    }

    /** Übergang: Gutschein für Musiker ohne Instrument: grau, ab Können-Stufe 1 grün. */
    public static function voucher(float $skill): int
    {
        return min(1, self::stage($skill));
    }
}
