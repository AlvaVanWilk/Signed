<?php
declare(strict_types=1);

namespace Signed\Game\Core;

use Signed\Game\Balance;

/**
 * Qualität einer Platte und ihrer Songs. Q = 100 × (0,40 S + 0,25 W + 0,20 P + 0,15 E); S und P werden an der eigenen Ruhmstufe gemessen,
 * so kann jede Band in jeder Phase perfekt aufnehmen. Noch nicht ins Spiel eingebaut (neuer Kern).
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
     * Qualität eines neuen Songs: Grundwert + kreative Kraft + Regler (`fast`, `normal`, `careful`) + Coach (Stufe 1–3, 0 ohne)
     * + Zufall ($luck von −1 bis 1), begrenzt auf 0–100.
     */
    public static function song(float $creative, string $slider, int $coach, float $luck): float
    {
        $c = Balance::CORE_QUALITY['song'];
        $quality = $c['base'] + $c['creative'] * $creative + $c['slider'][$slider] + ($c['coach'][$coach] ?? 0.0) + $c['random'] * $luck;
        return max(0.0, min(100.0, $quality));
    }

    /** Kreative Kraft K (0–1): Anteil der Songschreiber mit einem der Züge aus `creativeTraits` (wer nicht schreibt, zählt nicht). */
    public static function creativeShare(array $members): float
    {
        $traits = Balance::CORE_QUALITY['song']['creativeTraits'];
        $writers = 0;
        $sum = 0.0;
        foreach ($members as $m) {
            if (Songs::weight($m) <= 0) {
                continue;
            }
            $writers++;
            $sum += max([0.0, ...array_map(static fn ($t) => $traits[$t] ?? 0.0, $m['personality'] ?? [])]);
        }
        return $writers > 0 ? $sum / $writers : 0.0;
    }
}
