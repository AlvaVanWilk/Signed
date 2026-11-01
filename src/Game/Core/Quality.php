<?php
declare(strict_types=1);

namespace Signed\Game\Core;

use Signed\Game\Balance;

/**
 * Qualität einer Platte und ihrer Songs. Q = 100 × (0,30 S + 0,35 W + 0,20 P + 0,15 E); S und P werden an der eigenen Ruhmstufe gemessen,
 * so kann jede Band in jeder Phase perfekt aufnehmen.
 */
final class Quality
{
    /** Q aus den vier Anteilen (je 0–1): Sound, Songs, Produktion, Stimmung. */
    public static function album(float $sound, float $songs, float $production, float $mood): float
    {
        $w = Balance::CORE_QUALITY['weights'];
        return 100 * ($w['sound'] * $sound + $w['songs'] * $songs + $w['production'] * $production + $w['mood'] * $mood);
    }

    /** Sound-Anteil: Sound gegen die Erwartung der Ruhmstufe, höchstens 1. */
    public static function soundShare(float $sound, int $stage): float
    {
        return max(0.0, min(1.0, $sound / Balance::CORE_SOUND_EXPECT[$stage]));
    }

    /** Songs: Durchschnitt der ausgewählten Songs (Qualität 0–100) als Anteil. */
    public static function songsShare(array $songQualities): float
    {
        return $songQualities ? array_sum($songQualities) / count($songQualities) / 100 : 0.0;
    }

    /** Produktion: Produktions-Stufe gegen die Ruhmstufe, höchstens 1; bei Ruhmstufe 0 immer 1. */
    public static function productionShare(int $level, int $stage): float
    {
        return $stage === 0 ? 1.0 : min(1.0, max(0, $level) / $stage);
    }

    /** Stimmung bei der Aufnahme: durchschnittliches Wohlbefinden gegen `moodFull` (grün = voll), höchstens 1. */
    public static function moodShare(array $wellbeing): float
    {
        return $wellbeing ? min(1.0, array_sum($wellbeing) / count($wellbeing) / Balance::CORE_QUALITY['moodFull']) : 0.0;
    }

    public static function isPerfect(float $quality): bool
    {
        return $quality >= Balance::CORE_QUALITY['perfect'];
    }

    /**
     * Qualität eines neuen Songs aus der Trefferquote p (Anteil der guten Songs) und dem Zufall $u (0–1):
     * floor + (100 − floor) × u hoch a, mit a so, dass genau der Anteil p ab `good` liegt. Die meisten Songs liegen darunter.
     */
    public static function song(float $hitRate, float $u): float
    {
        $c = Balance::CORE_SONGS;
        $p = max(0.01, min($c['max'], $hitRate));
        $a = log(($c['good'] - $c['floor']) / (100 - $c['floor'])) / log(1 - $p);
        return $c['floor'] + (100 - $c['floor']) * max(0.0, min(1.0, $u)) ** $a;
    }

    /**
     * Trefferquote eines Songschreibers: nach Können-Stufe und kreativer Kraft, dazu Regler der Band (`fast`, `normal`, `careful`)
     * und Coach (Stufe 1–3, 0 ohne); höchstens CORE_SONGS['max'], so schreiben auch die Besten mehr schwache als gute Songs.
     */
    public static function hitRate(array $musician, string $slider = 'normal', int $coach = 0): float
    {
        $c = Balance::CORE_SONGS;
        $level = \Signed\Game\Levels::forSkill(max((float) ($musician['skill1'] ?? 0), (float) ($musician['skill2'] ?? 0)));
        $creative = max([0.0, ...array_map(static fn ($t) => $c['creativeTraits'][$t] ?? 0.0, $musician['personality'] ?? [])]);
        $p = ($c['hit'][$level] + $c['creative'] * $creative) * ($c['slider'][$slider] ?? 1.0) + ($c['coach'][$coach] ?? 0.0);
        return min($c['max'], $p);
    }

    /** Trefferquote der Band: jeder Songschreiber nach seinem Gewicht beim Schreiben (Songs::weight); 0, wenn niemand schreibt. */
    public static function bandHitRate(array $members, string $slider = 'normal', int $coach = 0): float
    {
        $sum = 0.0;
        $weights = 0.0;
        foreach ($members as $m) {
            $w = Songs::weight($m);
            $sum += $w * self::hitRate($m, $slider, $coach);
            $weights += $w;
        }
        return $weights > 0 ? $sum / $weights : 0.0;
    }
}
