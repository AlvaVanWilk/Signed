<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;

/**
 * Music Store: ein Regal mit 5 bis 10 Instrumenten, je Art höchstens eins. Es ist für alle Labels gleich und wechselt alle paar Stunden.
 * Das Regal ergibt sich ganz aus der Nummer des Zeitfensters (`slot`), nicht aus dem Zufall: Dieselbe Nummer ergibt immer dieselben Instrumente.
 * Zurücklegen hält ein Instrument für ein Label über den Wechsel hinaus (eines je Label, einen Tag lang).
 * Gegen Geld bestellt ein Label eine eigene Lieferung: Dann steht bis zum nächsten Wechsel ein anderes Regal nur für dieses Label da.
 * Dessen Nummer ist RESTOCK + Fenster × 100 + n (n = wievielte Lieferung im Fenster), der Zufall dazu hängt auch am Label.
 */
final class Store
{
    public function __construct(private Repo $repo)
    {
    }

    public const RESTOCK = 100_000_000;

    public static function slot(?int $now = null): int
    {
        return intdiv($now ?? Clock::now(), Balance::STORE['hours'] * 3600);
    }

    /** Zu welchem Zeitfenster gehört ein Regal (gewöhnliches oder bestelltes)? */
    public static function windowOf(int $shelf): int
    {
        return $shelf >= self::RESTOCK ? intdiv($shelf - self::RESTOCK, 100) : $shelf;
    }

    /** Das Regal, das dieses Label gerade sieht: die eigene Lieferung dieses Fensters, sonst das für alle. */
    public function currentShelf(?int $now = null): int
    {
        $slot = self::slot($now);
        $own = $this->repo->db->value('SELECT store_shelf FROM labels WHERE id = ?', [$this->repo->labelId]);
        return $own !== null && self::windowOf((int) $own) === $slot ? (int) $own : $slot;
    }

    /** Was die nächste Lieferung kostet (verdoppelt sich je Lieferung im selben Fenster). */
    public function restockPrice(): int
    {
        $shelf = $this->currentShelf();
        $n = $shelf >= self::RESTOCK ? ($shelf - self::RESTOCK) % 100 : 0;
        return (int) round(Balance::STORE['restock'] * Balance::STORE['restockFactor'] ** $n);
    }

    /** Neue Lieferung bestellen: sofort ein frisches Regal nur für dieses Label, bis zum nächsten Wechsel. */
    public function restock(): void
    {
        $this->repo->db->transaction(function (): void {
            $shelf = $this->currentShelf();
            $n = $shelf >= self::RESTOCK ? ($shelf - self::RESTOCK) % 100 : 0;
            if ($n >= Balance::STORE['restockMax']) {
                throw new ApiError('Für heute liefert der Großhandel nichts mehr. Warte auf den nächsten Wechsel.');
            }
            $this->repo->spend($this->restockPrice(), 'Music Store: neue Lieferung');
            $next = self::RESTOCK + self::slot() * 100 + $n + 1;
            $this->repo->db->update('labels', ['store_shelf' => $next], ['id' => $this->repo->labelId]);
        });
    }

    /** Zahlenstrom aus der Nummer des Fensters (xorshift32): derselbe Start, dieselbe Folge – unabhängig vom Spiel-Zufall. */
    private static function stream(int $slot, int $labelId = 0): \Closure
    {
        $s = crc32($slot >= self::RESTOCK ? 'store|' . $slot . '|' . $labelId : 'store|' . $slot) ?: 1;
        return static function (int $max) use (&$s): int {
            $s ^= ($s << 13) & 0xFFFFFFFF;
            $s ^= $s >> 17;
            $s ^= ($s << 5) & 0xFFFFFFFF;
            $s &= 0xFFFFFFFF;
            return $s % max(1, $max);
        };
    }

    /** @return list<array<string, mixed>> das Regal dieses Fensters */
    public static function shelf(int $slot, int $labelId = 0): array
    {
        $r = self::stream($slot, $labelId);
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
            // Boni wie Gear::roll, aber aus dem festen Zufall des Regals (alle Labels sehen dasselbe)
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
        $window = self::slot($now);
        $slot = $this->currentShelf($now);
        $db->exec('DELETE FROM store_holds WHERE label_id = ? AND expires_at <= ?', [$this->repo->labelId, $now]);
        $hold = $db->one('SELECT slot, idx, expires_at FROM store_holds WHERE label_id = ? ORDER BY id DESC', [$this->repo->labelId]);
        $bought = [];
        foreach ($db->all('SELECT slot, idx FROM store_buys WHERE label_id = ? AND slot >= ?', [$this->repo->labelId, $window - intdiv(Balance::STORE['holdHours'], Balance::STORE['hours']) - 2]) as $b) {
            $bought[(int) $b['slot'] . ':' . (int) $b['idx']] = true;
        }
        $mark = static fn (array $item) => $item + ['bought' => isset($bought[$item['slot'] . ':' . $item['idx']]), 'held' => false];
        $items = array_map($mark, self::shelf($slot, $this->repo->labelId));
        $held = null;
        if ($hold !== null) {
            $shelf = (int) $hold['slot'] === $slot ? $items : array_map($mark, self::shelf((int) $hold['slot'], $this->repo->labelId));
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
            'endsAt' => ($window + 1) * Balance::STORE['hours'] * 3600,
            'restock' => ['price' => $this->restockPrice(), 'own' => $slot >= self::RESTOCK],
            'items' => $items,
            'hold' => $held === null ? null : ['item' => $held, 'expiresAt' => (int) $hold['expires_at']],
        ];
    }

    private function find(int $slot, int $idx): array
    {
        $now = Clock::now();
        $current = $this->currentShelf($now);
        $hold = $this->repo->db->one('SELECT slot, idx, expires_at FROM store_holds WHERE label_id = ? AND expires_at > ?', [$this->repo->labelId, $now]);
        $isHold = $hold !== null && (int) $hold['slot'] === $slot && (int) $hold['idx'] === $idx;
        if ($slot !== $current && !$isHold) {
            throw new ApiError('Dieses Instrument ist nicht mehr im Regal.');
        }
        return self::shelf($slot, $this->repo->labelId)[$idx] ?? throw new ApiError('Dieses Instrument gibt es nicht.');
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
            $slot = $this->currentShelf($now);
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
