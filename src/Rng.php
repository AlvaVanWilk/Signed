<?php
declare(strict_types=1);

namespace Signed;

/** Zufall für das Spiel. Läuft über mt_rand, damit Tests mit festem Seed reproduzierbar sind. */
final class Rng
{
    public static function seed(int $seed): void
    {
        mt_srand($seed);
    }

    public static function int(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    public static function float(float $min = 0.0, float $max = 1.0): float
    {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
    }

    public static function chance(float $probability): bool
    {
        return self::float() < $probability;
    }

    public static function pick(array $items): mixed
    {
        $items = array_values($items);
        if (!$items) {
            throw new \LogicException('Rng::pick mit leerer Liste');
        }
        return $items[mt_rand(0, count($items) - 1)];
    }

    /** @param array<string|int, float|int> $weights Schlüssel => Gewicht */
    public static function weighted(array $weights): string|int
    {
        $total = array_sum($weights);
        if ($total <= 0) {
            throw new \LogicException('Rng::weighted ohne positive Gewichte');
        }
        $roll = self::float(0, $total);
        foreach ($weights as $key => $weight) {
            if ($weight <= 0) {
                continue;
            }
            $roll -= $weight;
            if ($roll < 0) {
                return $key;
            }
        }
        $keys = array_keys(array_filter($weights, static fn ($w) => $w > 0));
        return end($keys);
    }

    /** Wählt bis zu $count verschiedene Einträge. */
    public static function sample(array $items, int $count): array
    {
        $items = array_values($items);
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
        return array_slice($items, 0, max(0, $count));
    }
}
