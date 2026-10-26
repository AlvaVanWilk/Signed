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
 * Zurücklegen hält ein Angebot für ein Label über den Wechsel hinaus (eines je Label, einen Tag lang).
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

    /** @return list<array<string, mixed>> das Angebot dieses Tages: das legendäre (falls es eins gibt) zuerst, dann das epische, dann die seltenen */
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
        $rarities = [];
        if ($r(10000) < $o['gold'] * 10000) {
            $rarities[] = 'legendary';
        }
        if ($r(10000) < $o['blue'] * 10000) {
            $rarities[] = 'epic';
        }
        // Das legendäre steht doppelt so breit; dafür ein seltenes weniger, damit alles in eine Reihe passt.
        $green = max(1, $o['green'][0] + $r($o['green'][1] - $o['green'][0] + 1) - ($rarities && $rarities[0] === 'legendary' ? 1 : 0));
        $rarities = array_merge($rarities, array_fill(0, $green, 'rare'));
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

    /** Das Regal für dieses Label: mit Markierung, was schon gekauft oder zurückgelegt ist, dazu das zurückgelegte Instrument. */
    public function present(): array
    {
        $db = $this->repo->db;
        $now = Clock::now();
        $slot = self::slot($now);
        $db->exec('DELETE FROM store_holds WHERE label_id = ? AND expires_at <= ?', [$this->repo->labelId, $now]);
        $hold = $db->one('SELECT slot, idx, expires_at FROM store_holds WHERE label_id = ? ORDER BY id DESC', [$this->repo->labelId]);
        $bought = [];
        foreach ($db->all('SELECT slot, idx FROM store_buys WHERE label_id = ? AND slot >= ?', [$this->repo->labelId, $slot - 3]) as $b) {
            $bought[(int) $b['slot'] . ':' . (int) $b['idx']] = true;
        }
        $mark = static fn (array $item) => $item + ['bought' => isset($bought[$item['slot'] . ':' . $item['idx']]), 'held' => false];
        $items = array_map($mark, self::shelf($slot));
        $held = null;
        if ($hold !== null) {
            $shelf = (int) $hold['slot'] === $slot ? $items : array_map($mark, self::shelf((int) $hold['slot']));
            $held = ($shelf[(int) $hold['idx']] ?? null);
            if ($held !== null) {
                $held['held'] = true;
                if ((int) $hold['slot'] === $slot) {
                    $items[(int) $hold['idx']]['held'] = true;
                }
            }
        }
        return [
            'slot' => $slot,
            'endsAt' => self::endsAt($slot),
            'items' => $items,
            // Ein legendäres Instrument im Angebot, das dieses Label noch nicht gekauft hat: wird groß beworben.
            'gold' => (bool) array_filter($items, static fn ($i) => $i['tier'] === 3 && !$i['bought']),
            'basics' => self::basics(),
            'hold' => $held === null ? null : ['item' => $held, 'expiresAt' => (int) $hold['expires_at']],
        ];
    }

    private function find(int $slot, int $idx): array
    {
        $now = Clock::now();
        $current = self::slot($now);
        $hold = $this->repo->db->one('SELECT slot, idx, expires_at FROM store_holds WHERE label_id = ? AND expires_at > ?', [$this->repo->labelId, $now]);
        $isHold = $hold !== null && (int) $hold['slot'] === $slot && (int) $hold['idx'] === $idx;
        if ($slot !== $current && !$isHold) {
            throw new ApiError('Dieses Instrument ist nicht mehr im Regal.');
        }
        return self::shelf($slot)[$idx] ?? throw new ApiError('Dieses Instrument gibt es nicht.');
    }

    /** Kaufen: ein Instrument aus dem Regal (oder das zurückgelegte) ins Lager des Labels. */
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
            $this->repo->db->exec('DELETE FROM store_holds WHERE label_id = ? AND slot = ? AND idx = ?', [$this->repo->labelId, $slot, $idx]);
            $id = $this->repo->insertGear($item['model'], ['klang' => $item['klang'], 'spielbarkeit' => $item['spielbarkeit'], 'design' => $item['design'], 'perks' => [], 'bonuses' => $item['bonuses']]);
            return ['gear' => Present::gear($this->repo->gear()[$id])];
        });
    }

    /** Zurücklegen lassen: nur eines je Label (ein neues ersetzt das alte); es bleibt, auch wenn das Regal wechselt. */
    public function hold(int $idx): void
    {
        $this->repo->db->transaction(function () use ($idx): void {
            $now = Clock::now();
            $slot = self::slot($now);
            $item = $this->find($slot, $idx);
            $this->present();
            if ((int) $this->repo->db->value('SELECT COUNT(*) FROM store_buys WHERE label_id = ? AND slot = ? AND idx = ?', [$this->repo->labelId, $slot, $item['idx']]) > 0) {
                throw new ApiError('Das hast du schon gekauft.');
            }
            $this->repo->db->exec('DELETE FROM store_holds WHERE label_id = ?', [$this->repo->labelId]);
            $this->repo->db->insert('store_holds', ['label_id' => $this->repo->labelId, 'slot' => $slot, 'idx' => $idx, 'created_at' => $now, 'expires_at' => $now + Balance::STORE['holdHours'] * 3600]);
        });
    }

    public function release(): void
    {
        $this->repo->db->exec('DELETE FROM store_holds WHERE label_id = ?', [$this->repo->labelId]);
    }
}
