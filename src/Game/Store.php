<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;

/**
 * Music Store in zwei Teilen:
 * - Grundausstattung: jedes gewöhnliche Modell steht immer da und lässt sich beliebig oft kaufen (ein graues Instrument braucht jeder).
 * - Angebot: ein paar besondere Instrumente, für alle Labels gleich, das täglich wechselt (Balance::STORE). Meist seltene, ab und zu ein episches,
 *   ganz selten ein legendäres – das wird groß beworben. Jedes Angebot kauft ein Label höchstens einmal.
 * Das Angebot ergibt sich ganz aus der Nummer des Tages (`slot`), nicht aus dem Spiel-Zufall: Dieselbe Nummer ergibt immer dieselben Instrumente.
 */
final class Store
{
    public function __construct(private Repo $repo)
    {
    }

    public static function slot(?int $now = null): int
    {
        $c = Balance::STORE;
        return intdiv(($now ?? Clock::now()) - $c['offset'] * 3600, $c['hours'] * 3600);
    }

    /** Wann das Angebot dieses Tages endet. */
    public static function endsAt(int $slot): int
    {
        $c = Balance::STORE;
        return ($slot + 1) * $c['hours'] * 3600 + $c['offset'] * 3600;
    }

    /** Die Grundausstattung: jedes gewöhnliche Modell, fester Preis. */
    public static function basics(): array
    {
        $out = [];
        foreach (Catalog::GEAR as $model => $g) {
            if ($g['rarity'] === 'common') {
                $out[] = ['model' => $model, 'type' => $g['type'], 'rarity' => 'common', 'tier' => 0, 'bonuses' => [], 'price' => Balance::STORE['price']['common']];
            }
        }
        return $out;
    }

    /** Grundausstattung kaufen: immer vorrätig. */
    public function buyBasic(string $model): array
    {
        $item = array_values(array_filter(self::basics(), static fn ($b) => $b['model'] === $model))[0] ?? throw new ApiError('Dieses Instrument gibt es hier nicht.');
        return $this->repo->db->transaction(function () use ($item): array {
            $this->repo->spend($item['price'], sprintf('Music Store: %s', Catalog::GEAR[$item['model']]['name']));
            $base = Catalog::GEAR[$item['model']];
            $id = $this->repo->insertGear($item['model'], ['klang' => $base['klang'], 'spielbarkeit' => $base['spielbarkeit'], 'design' => $base['design'], 'perks' => [], 'bonuses' => []]);
            return ['gear' => Present::gear($this->repo->gear()[$id])];
        });
    }

    /** Zahlenstrom aus der Nummer des Fensters (xorshift32): derselbe Start, dieselbe Folge – unabhängig vom Spiel-Zufall. */
    private static function stream(int $slot): \Closure
    {
        $s = crc32('offer|' . $slot) ?: 1;
        return static function (int $max) use (&$s): int {
            $s ^= ($s << 13) & 0xFFFFFFFF;
            $s ^= $s >> 17;
            $s ^= ($s << 5) & 0xFFFFFFFF;
            $s &= 0xFFFFFFFF;
            return $s % max(1, $max);
        };
    }

    /** @return list<array<string, mixed>> das Angebot dieses Tages: die Fächer des Regals, dann (falls es eins gibt) das legendäre fürs Schaufenster */
    public static function shelf(int $slot): array
    {
        $r = self::stream($slot);
        $c = Balance::STORE;
        $o = $c['offer'];
        $types = array_keys(Catalog::INSTRUMENTS);
        for ($i = count($types) - 1; $i > 0; $i--) {
            $j = $r($i + 1);
            [$types[$i], $types[$j]] = [$types[$j], $types[$i]];
        }
        // Das Regal: immer gleich viele Fächer, eins davon mit etwas Glück episch. Dahinter (zuletzt) vielleicht ein legendäres fürs Schaufenster.
        $gold = $r(10000) < $o['gold'] * 10000;
        $rarities = array_fill(0, $o['shelf'], 'rare');
        if ($r(10000) < $o['blue'] * 10000) {
            $rarities[$r($o['shelf'])] = 'epic';
        }
        if ($gold) {
            $rarities[] = 'legendary';
        }
        $items = [];
        foreach (array_slice($rarities, 0, count($types)) as $idx => $rarity) {
            $type = $types[$idx];
            $models = array_keys(array_filter(Catalog::GEAR, static fn ($g) => $g['type'] === $type && $g['rarity'] === $rarity));
            $model = $models[$r(count($models))];
            $base = Catalog::GEAR[$model];
            // Boni wie Gear::roll, aber aus dem festen Zufall des Angebots (alle Labels sehen dasselbe)
            $tier = Gear::tier($rarity);
            $bonuses = [];
            foreach (['green' => 1, 'blue' => 2, 'gold' => 3] as $place => $from) {
                if ($tier >= $from) {
                    $kinds = array_keys(Balance::GEAR_BONUS[$place]);
                    $bonuses[] = Gear::make($place, $kinds[$r(count($kinds))], $tier, $r(1001) / 1000);
                }
            }
            $stats = ['klang' => $base['klang'], 'spielbarkeit' => $base['spielbarkeit'], 'design' => $base['design']];
            $price = (int) (round($c['price'][$rarity] * Gear::priceFactor($bonuses) / 5) * 5);
            $items[] = ['idx' => $idx, 'slot' => $slot, 'model' => $model, 'type' => $type, 'rarity' => $rarity, 'tier' => $tier] + $stats + ['perks' => [], 'bonuses' => $bonuses, 'price' => max(5, $price)];
        }
        return $items;
    }

    /** Das Angebot für dieses Label: Gekauftes bleibt als leeres Fach stehen (bought), dazu die Grundausstattung. */
    public function present(): array
    {
        $slot = self::slot(Clock::now());
        $bought = array_map('intval', array_column($this->repo->db->all('SELECT idx FROM store_buys WHERE label_id = ? AND slot = ?', [$this->repo->labelId, $slot]), 'idx'));
        $items = array_map(static fn ($i) => $i + ['bought' => in_array($i['idx'], $bought, true)], self::shelf($slot));
        return [
            'slot' => $slot,
            'endsAt' => self::endsAt($slot),
            'items' => $items,
            // Ein legendäres Instrument im Schaufenster, das dieses Label noch nicht gekauft hat: wird groß beworben.
            'gold' => (bool) array_filter($items, static fn ($i) => $i['tier'] === 3 && !$i['bought']),
            'basics' => self::basics(),
        ];
    }

    private function find(int $slot, int $idx): array
    {
        if ($slot !== self::slot(Clock::now())) {
            throw new ApiError('Dieses Instrument ist nicht mehr im Angebot.');
        }
        return self::shelf($slot)[$idx] ?? throw new ApiError('Dieses Instrument gibt es nicht.');
    }

    /** Kaufen: ein Angebot ins Lager des Labels (je Label einmal). */
    public function buy(int $slot, int $idx): array
    {
        return $this->repo->db->transaction(function () use ($slot, $idx): array {
            $item = $this->find($slot, $idx);
            $done = $this->repo->db->value('SELECT COUNT(*) FROM store_buys WHERE label_id = ? AND slot = ? AND idx = ?', [$this->repo->labelId, $slot, $idx]);
            if ((int) $done > 0) {
                throw new ApiError('Das hast du schon gekauft.');
            }
            $this->repo->spend($item['price'], sprintf('Music Store: %s', Catalog::GEAR[$item['model']]['name']));
            $this->repo->db->insert('store_buys', ['label_id' => $this->repo->labelId, 'slot' => $slot, 'idx' => $idx, 'created_at' => Clock::now()]);
            $id = $this->repo->insertGear($item['model'], ['klang' => $item['klang'], 'spielbarkeit' => $item['spielbarkeit'], 'design' => $item['design'], 'perks' => [], 'bonuses' => $item['bonuses']]);
            return ['gear' => Present::gear($this->repo->gear()[$id])];
        });
    }
}
