<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Rng;

/**
 * Instrumente in vier Güteklassen (Seltenheit → Klasse): gewöhnlich 0 (grau), selten 1 (grün), episch 2 (blau), legendär 3 (gold).
 * Spielen darf ein Musiker nur Instrumente bis zu seiner Können-Stufe am jeweiligen Instrument.
 * Boni je Klasse (Balance::GEAR_BONUS), innerhalb ihrer Spanne gewürfelt (q = 0 schwach … 1 stark, der Preis folgt):
 * grau keiner, grün ein Übungs-Bonus, blau dazu ein Klang-Bonus, gold dazu etwas Besonderes.
 */
final class Gear
{
    public const TIER = ['common' => 0, 'rare' => 1, 'epic' => 2, 'legendary' => 3];

    public static function tier(string $rarity): int
    {
        return self::TIER[$rarity] ?? 0;
    }

    /** Güteklasse eines Modells (Catalog::GEAR-Schlüssel). */
    public static function modelTier(string $model): int
    {
        return self::tier(Catalog::GEAR[$model]['rarity'] ?? 'common');
    }

    /**
     * Boni eines neuen Instruments dieser Seltenheit.
     * @return list<array{slot: string, kind: string, value: float, q: float}>
     */
    public static function roll(string $rarity): array
    {
        $tier = self::tier($rarity);
        $out = [];
        foreach (['green' => 1, 'blue' => 2, 'gold' => 3] as $slot => $from) {
            if ($tier >= $from) {
                $kinds = array_keys(Balance::GEAR_BONUS[$slot]);
                $out[] = self::make($slot, (string) Rng::pick($kinds), $tier, Rng::float());
            }
        }
        return $out;
    }

    /** Einen Bonus aus Platz, Art, Klasse und Stärke q (0–1) bauen. */
    public static function make(string $slot, string $kind, int $tier, float $q): array
    {
        $range = Balance::GEAR_BONUS[$slot][$kind];
        [$lo, $hi] = isset($range[$tier]) ? $range[$tier] : $range;
        $q = max(0.0, min(1.0, $q));
        return ['slot' => $slot, 'kind' => $kind, 'value' => round($lo + ($hi - $lo) * $q, 3), 'q' => round($q, 3)];
    }

    /**
     * Instrumente aus der Zeit vor den Klassen: Boni fest aus den alten Werten ableiten (Spielbarkeit → Übung, Klang → Klang,
     * Extras → Besonderes), damit niemand etwas verliert und es jedes Mal gleich ausfällt.
     */
    public static function legacy(array $item): array
    {
        $model = Catalog::GEAR[$item['model']] ?? null;
        if ($model === null) {
            return [];
        }
        $tier = self::tier($model['rarity']);
        $seed = crc32('gear|' . ($item['id'] ?? 0));
        $q = static fn (int $stat, int $base) => max(0.0, min(1.0, 0.5 + ($stat - $base) / 24));
        $out = [];
        if ($tier >= 1) {
            $kind = in_array('teacher', $item['perks'] ?? [], true) ? 'learn' : (['lust', 'learn'][$seed % 2]);
            $out[] = self::make('green', $kind, $tier, $q((int) ($item['spielbarkeit'] ?? $model['spielbarkeit']), $model['spielbarkeit']));
        }
        if ($tier >= 2) {
            $out[] = self::make('blue', 'sound', $tier, $q((int) ($item['klang'] ?? $model['klang']), $model['klang']));
        }
        if ($tier >= 3) {
            $perks = $item['perks'] ?? [];
            $kind = in_array('stage', $perks, true) ? 'magnet' : (in_array('studio', $perks, true) ? 'hit' : array_keys(Balance::GEAR_BONUS['gold'])[$seed % count(Balance::GEAR_BONUS['gold'])]);
            $out[] = self::make('gold', $kind, $tier, $q((int) ($item['design'] ?? $model['design']), $model['design']));
        }
        return $out;
    }

    /** Wert eines Bonus dieser Art auf dem gespielten Instrument (0, wenn es keinen hat). */
    public static function value(?array $model, string $kind): float
    {
        foreach ($model['bonuses'] ?? [] as $b) {
            if ($b['kind'] === $kind) {
                return (float) $b['value'];
            }
        }
        return 0.0;
    }

    /** Wie gut das Instrument innerhalb seiner Klasse gewürfelt ist (0–1); grau: 0,5. */
    public static function quality(array $bonuses): float
    {
        return $bonuses ? array_sum(array_column($bonuses, 'q')) / count($bonuses) : 0.5;
    }

    /** Preisfaktor nach Güte innerhalb der Klasse (Balance::GEAR_PRICE_SPREAD). */
    public static function priceFactor(array $bonuses): float
    {
        [$lo, $hi] = Balance::GEAR_PRICE_SPREAD;
        return $lo + ($hi - $lo) * self::quality($bonuses);
    }

    /** Können-Stufe, die ein Musiker an diesem Instrumententyp hat (0–3). */
    public static function levelFor(array $m, string $type): int
    {
        $skill = $type === $m['instr1'] ? (float) $m['skill1'] : ($type === ($m['instr2'] ?? null) ? (float) $m['skill2'] : 0.0);
        return Levels::forSkill($skill);
    }

    public static function playable(array $m, string $model): bool
    {
        $g = Catalog::GEAR[$model];
        return self::levelFor($m, $g['type']) >= self::tier($g['rarity']);
    }

    /** Wie viel Können noch fehlt, bis er dieses Instrument spielen darf (0 = darf schon). */
    public static function missingSkill(array $m, string $model): float
    {
        $g = Catalog::GEAR[$model];
        $tier = self::tier($g['rarity']);
        if ($tier === 0) {
            return 0.0;
        }
        $skill = $g['type'] === $m['instr1'] ? (float) $m['skill1'] : ($g['type'] === ($m['instr2'] ?? null) ? (float) $m['skill2'] : 0.0);
        return max(0.0, Balance::SKILL_LEVELS[$tier - 1] - $skill);
    }
}
