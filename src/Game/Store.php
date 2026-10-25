<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;

/**
 * Music Store: ein Regal mit 5 bis 10 Instrumenten, je Art höchstens eins. Es ist für alle Labels gleich und wechselt alle paar Stunden.
 * Das Regal ergibt sich ganz aus der Nummer des Zeitfensters (`slot`), nicht aus dem Zufall: Dieselbe Nummer ergibt immer dieselben Instrumente.
 * Zurücklegen hält ein Instrument für ein Label über den Wechsel hinaus (eines je Label, einen Tag lang).
 */
final class Store
{
    public function __construct(private Repo $repo)
    {
    }

    public static function slot(?int $now = null): int
    {
        return intdiv($now ?? Clock::now(), Balance::STORE['hours'] * 3600);
    }

    /** Zahlenstrom aus der Nummer des Fensters (xorshift32): derselbe Start, dieselbe Folge – unabhängig vom Spiel-Zufall. */
    private static function stream(int $slot): \Closure
    {
        $s = crc32('store|' . $slot) ?: 1;
        return static function (int $max) use (&$s): int {
            $s ^= ($s << 13) & 0xFFFFFFFF;
            $s ^= $s >> 17;
            $s ^= ($s << 5) & 0xFFFFFFFF;
            $s &= 0xFFFFFFFF;
            return $s % max(1, $max);
        };
    }

    /** @return list<array<string, mixed>> das Regal dieses Fensters */
    public static function shelf(int $slot): array
    {
        $r = self::stream($slot);
        $c = Balance::STORE;
        $types = array_keys(Catalog::INSTRUMENTS);
        for ($i = count($types) - 1; $i > 0; $i--) {
            $j = $r($i + 1);
            [$types[$i], $types[$j]] = [$types[$j], $types[$i]];
        }
        $count = min(count($types), $c['count'][0] + $r($c['count'][1] - $c['count'][0] + 1));
        $odds = $c['odds'];
        $total = array_sum($odds);
        $order = ['legendary', 'epic', 'rare', 'common'];
        $items = [];
        foreach (array_slice($types, 0, $count) as $idx => $type) {
            $roll = $r(1000) / 1000 * $total;
            $rarity = 'common';
            foreach ($odds as $name => $weight) {
                $roll -= $weight;
                if ($roll < 0) {
                    $rarity = $name;
                    break;
                }
            }
            // Gibt es die Seltenheit für diese Art nicht, nimm die nächst gewöhnlichere.
            $models = [];
            for ($k = array_search($rarity, $order, true); $k < count($order) && !$models; $k++) {
                $rarity = $order[$k];
                $models = array_keys(array_filter(Catalog::GEAR, static fn ($g) => $g['type'] === $type && $g['rarity'] === $rarity));
            }
            $model = $models[$r(count($models))];
            $base = Catalog::GEAR[$model];
            $spread = Balance::GEAR_VARIANCE;
            $stats = [];
            foreach (['klang', 'spielbarkeit', 'design'] as $stat) {
                $stats[$stat] = max(1, min(100, $base[$stat] + $r(2 * $spread + 1) - $spread));
            }
            $perks = [];
            $pool = array_keys(Catalog::GEAR_PERKS);
            for ($n = Balance::GEAR_PERKS[$rarity] ?? 0; $n > 0 && $pool; $n--) {
                array_push($perks, ...array_splice($pool, $r(count($pool)), 1));
            }
            $diff = ($stats['klang'] - $base['klang']) + ($stats['design'] - $base['design']);
            $price = (int) (round($c['price'][$rarity] * (1 + $c['pricePerk'] * count($perks)) * (1 + $c['priceStat'] * $diff) / 5) * 5);
            $items[] = ['idx' => $idx, 'slot' => $slot, 'model' => $model, 'type' => $type, 'rarity' => $rarity] + $stats + ['perks' => $perks, 'price' => max(5, $price)];
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
        foreach ($db->all('SELECT slot, idx FROM store_buys WHERE label_id = ? AND slot >= ?', [$this->repo->labelId, $slot - intdiv(Balance::STORE['holdHours'], Balance::STORE['hours']) - 2]) as $b) {
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
            'endsAt' => ($slot + 1) * Balance::STORE['hours'] * 3600,
            'items' => $items,
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
            $id = $this->repo->insertGear($item['model'], ['klang' => $item['klang'], 'spielbarkeit' => $item['spielbarkeit'], 'design' => $item['design'], 'perks' => $item['perks']]);
            return ['gear' => Present::gear($this->repo->gear()[$id])];
        });
    }

    /** Zurücklegen lassen: nur eines je Label; es bleibt, auch wenn das Regal wechselt. */
    public function hold(int $idx): void
    {
        $this->repo->db->transaction(function () use ($idx): void {
            $now = Clock::now();
            $slot = self::slot($now);
            $item = $this->find($slot, $idx);
            $this->present();
            if ($this->repo->db->one('SELECT id FROM store_holds WHERE label_id = ?', [$this->repo->labelId]) !== null) {
                throw new ApiError('Du hast schon ein Instrument zurücklegen lassen. Kaufe es oder gib es zurück.');
            }
            if ((int) $this->repo->db->value('SELECT COUNT(*) FROM store_buys WHERE label_id = ? AND slot = ? AND idx = ?', [$this->repo->labelId, $slot, $item['idx']]) > 0) {
                throw new ApiError('Das hast du schon gekauft.');
            }
            $this->repo->db->insert('store_holds', ['label_id' => $this->repo->labelId, 'slot' => $slot, 'idx' => $idx, 'created_at' => $now, 'expires_at' => $now + Balance::STORE['holdHours'] * 3600]);
        });
    }

    public function release(): void
    {
        $this->repo->db->exec('DELETE FROM store_holds WHERE label_id = ?', [$this->repo->labelId]);
    }
}
