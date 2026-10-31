<?php
declare(strict_types=1);

namespace Signed\Game\Core;

use Signed\Game\Balance;

/**
 * Laufbahn einer Band im neuen Kern: Fans, Platten, Auftritte und Ereignisse über die Zeit, in der Reihenfolge, in der das Spiel sie rechnen soll.
 * Vor jeder Handlung wird alles bis zu ihrem Zeitpunkt weitergerechnet (settle), alle Platten der Band im Gleichschritt, Tag für Tag.
 * Reines Rechenwerk ohne Datenbank; das Spiel speichert dieselben Werte. Noch nicht ins Spiel eingebaut (neuer Kern).
 *
 * Zustand: fans, kind (Art der Band fürs Dach), fresh und lastGig (Frische), records (Platten, neueste zuletzt), retired (Platten, die
 * beim Weiterrechnen ganz fertig wurden; der Aufrufer speichert sie und leert die Liste), settledAt, earned (Geld nach Herkunft:
 * records, catalog, gigs, ads), sold und catalogSold (verkaufte Platten).
 */
final class Career
{
    public static function start(float $fans, string $kind, int $at): array
    {
        return [
            'fans' => max(0.0, $fans),
            'kind' => $kind,
            'fresh' => 1.0,
            'lastGig' => null,
            'records' => [],
            'retired' => [],
            'settledAt' => $at,
            'earned' => ['records' => 0.0, 'catalog' => 0.0, 'gigs' => 0.0, 'ads' => 0.0],
            'sold' => 0.0,
            'catalogSold' => 0.0,
        ];
    }

    /** Neueste Platte oder null. */
    public static function newest(array $career): ?array
    {
        return $career['records'] ? $career['records'][count($career['records']) - 1] : null;
    }

    /** Hype der neuesten Platte jetzt (0 ohne Platte). */
    public static function hype(array $career): float
    {
        return self::newest($career)['hype'] ?? 0.0;
    }

    /** Rechnet alles bis $until weiter: Verkäufe, Katalog und neue Fans fließen gleichmäßig, an jeder Tagesgrenze einer Platte beginnt ihr nächster Tag. */
    public static function settle(array $career, int $until): array
    {
        while (true) {
            $next = $until;
            foreach ($career['records'] as $record) {
                $next = min($next, Sales::nextDayAt($record));
            }
            foreach ($career['records'] as $i => $record) {
                [$record, $sold, $gained, $catalog] = Sales::flow($record, $next);
                $career['records'][$i] = $record;
                $career['sold'] += $sold;
                $career['catalogSold'] += $catalog;
                $career['earned']['records'] += Sales::money($sold);
                $career['earned']['catalog'] += Sales::money($catalog);
                $career['fans'] = Fans::grow($career['fans'], $gained, $career['kind']);
            }
            $career['settledAt'] = max($career['settledAt'], $next);
            $crossed = false;
            $last = count($career['records']) - 1;
            foreach ($career['records'] as $i => $record) {
                if (Sales::nextDayAt($record) <= $next) {
                    $record = Sales::nextDay($record, $career['fans']);
                    // Ältere Platten laufen aus, sobald sie durch den Hype weniger als ein Stück am Tag verkaufen; der Katalog läuft weiter.
                    if ($i < $last && Sales::isOver($record)) {
                        $record = Sales::end($record);
                    }
                    $career['records'][$i] = $record;
                    $crossed = true;
                }
            }
            foreach ($career['records'] as $i => $record) {
                if ($i !== $last && Sales::isRetired($record)) {
                    $career['retired'][] = $record;
                    unset($career['records'][$i]);
                }
            }
            $career['records'] = array_values($career['records']);
            if (!$crossed) {
                return $career;
            }
        }
    }

    /** Neue Platte erscheint; die vorige läuft von selbst aus. */
    public static function release(array $career, int $at, float $quality): array
    {
        $career = self::settle($career, $at);
        $career['records'][] = Sales::release($career['fans'], $quality, $at);
        return $career;
    }

    /**
     * Auftritt jetzt. $live = Live-Qualität 0–100 (aus dem Live-Ticker).
     * @return array{0: array, 1: array} [Laufbahn, Ergebnis aus Concert::play]
     */
    public static function gig(array $career, int $at, float $live, bool $tour = false): array
    {
        [$career, $result] = self::gigStart($career, $at, $live, $tour);
        $career = self::gain($career, $result['newFans']);
        $career['earned']['gigs'] += $result['earnings'];
        return [$career, $result];
    }

    /**
     * Beginn eines Auftritts: Zuschauer, Verdienst und neue Fans stehen fest, die Frische ist verbraucht. Neue Fans und Geld bucht das Spiel
     * nach dem Auftritt (addFans, Geld ans Label); gig() macht beides auf einmal.
     * @return array{0: array, 1: array} [Laufbahn, Ergebnis aus Concert::play]
     */
    public static function gigStart(array $career, int $at, float $live, bool $tour = false): array
    {
        $career = self::settle($career, $at);
        [$hype, $f0, $pool] = self::context($career);
        $fresh = Concert::freshness($career['fresh'], $career['lastGig'], $at);
        $result = Concert::play($career['fans'], $hype, $f0, $pool, $fresh, $live, $tour);
        $career['fresh'] = Concert::afterGig($fresh);
        $career['lastGig'] = $at;
        return [$career, $result];
    }

    /** Neue Fans jetzt gutschreiben (nach einem Auftritt): bis zum Dach, und was wirklich dazukommt, zählt für die neueste Platte. */
    public static function addFans(array $career, int $at, float $fans): array
    {
        return self::gain(self::settle($career, $at), $fans);
    }

    /** Was ein Auftritt jetzt bringen würde (für den Knopf „Auftreten: etwa … Leute, rund … ₲“), ohne die Show. */
    public static function preview(array $career, int $at, bool $tour = false): array
    {
        $career = self::settle($career, $at);
        [$hype, $f0, $pool] = self::context($career);
        return Concert::play($career['fans'], $hype, $f0, $pool, Concert::freshness($career['fresh'], $career['lastGig'], $at), 0.0, $tour);
    }

    /**
     * Ereignis mit Publikum (Festival-Bühne, ein Battle-Spiel): neue Fans aus den Zuschauern, danach der Hype-Schub.
     * @return array{0: array, 1: float} [Laufbahn, neue Fans]
     */
    public static function event(array $career, int $at, float $audience, float $live, float $boost = 0.0): array
    {
        $career = self::settle($career, $at);
        [, , $pool] = self::context($career);
        $before = $career['fans'];
        $career = self::gain($career, Events::fans($audience, $career['fans'], $pool, $live));
        if ($boost > 0) {
            $career = self::boost($career, $at, $boost);
        }
        return [$career, $career['fans'] - $before];
    }

    /** Hype-Schub für die neueste Platte (Anfragen, Battle-Platz). Ohne Platte passiert nichts. */
    public static function boost(array $career, int $at, float $amount): array
    {
        $career = self::settle($career, $at);
        if ($career['records']) {
            $last = count($career['records']) - 1;
            $career['records'][$last] = Sales::boost($career['records'][$last], $amount);
        }
        return $career;
    }

    /** Werbung: Schub wie jede Anfrage, dazu so viele ₲, wie die Band Fans hat. */
    public static function ad(array $career, int $at): array
    {
        $career = self::boost($career, $at, Events::request('ad')['boost']);
        $career['earned']['ads'] += Events::adMoney($career['fans']);
        return $career;
    }

    /** Alles Geld, das die Band dem Label gebracht hat. */
    public static function money(array $career): float
    {
        return array_sum($career['earned']);
    }

    /** @return array{0: float, 1: float, 2: float} Hype, F0 und Pool der neuesten Platte (ohne Platte: 0, Fans, Pool aus den Fans). */
    private static function context(array $career): array
    {
        $record = self::newest($career);
        return $record === null
            ? [0.0, $career['fans'], Fans::pool($career['fans'])]
            : [$record['hype'], $record['f0'], $record['pool']];
    }

    /** Neue Fans aus Konzerten und Ereignissen: bis zum Dach; was wirklich dazukommt, zählt auch für die neueste Platte. */
    private static function gain(array $career, float $fans): array
    {
        $before = $career['fans'];
        $career['fans'] = Fans::grow($before, $fans, $career['kind']);
        $gained = $career['fans'] - $before;
        if ($career['records'] && $gained > 0) {
            $last = count($career['records']) - 1;
            $career['records'][$last] = Sales::addFans($career['records'][$last], $gained);
        }
        return $career;
    }
}
