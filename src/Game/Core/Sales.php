<?php
declare(strict_types=1);

namespace Signed\Game\Core;

use Signed\Game\Balance;

/**
 * Verkauf einer Platte je Tag (Sandras Modell). Die Tage zählen ab dem Erscheinen. Zu Beginn jedes Tages stehen seine Werte fest,
 * dann fließen sie gleichmäßig über den Tag (Verkäufe, Geld, neue Fans). Ein Schub wirkt sofort für den Rest des Tages;
 * neue Fans aus Konzerten und Ereignissen kaufen die Platte ab dem nächsten Tag. Katalogverkäufe laufen daneben, unabhängig vom Hype.
 * Noch nicht ins Spiel eingebaut (neuer Kern).
 *
 * Eine Platte ist ein Array: quality, releasedAt, f0 (Fans beim Erscheinen), pool (Rechen-Pool), hype (Hype des laufenden Tages),
 * boosts (Zahl der Schübe), buyers (B), gained (C), willing (U), day (laufender Tag ab 0), room (Rest des Pools zu Tagesbeginn),
 * soldRate / fanRate / catalogRate (je Tag, fließen gleichmäßig), daySold (V des Tages ohne Schübe), settledAt, sold, catalogSold,
 * ended (ausgelaufen, nur noch Katalog).
 */
final class Sales
{
    public const DAY = 86400;

    /** Anteil der Fans, die die Platte kaufen wollen: Eine gute Platte kaufen sie eher. */
    public static function buyShare(float $quality): float
    {
        $c = Balance::CORE_SALES;
        return min(1.0, $c['buyBase'] * ($quality / $c['buyRef']) ** 2);
    }

    /** Zustand beim Erscheinen; die Werte des ersten Tages stehen sofort fest. */
    public static function release(float $fans, float $quality, int $at): array
    {
        return self::plan([
            'quality' => $quality,
            'releasedAt' => $at,
            'f0' => $fans,
            'pool' => Fans::pool($fans),
            'hype' => Hype::start($quality),
            'boosts' => 0,
            'buyers' => 0.0,
            'gained' => 0.0,
            'willing' => self::buyShare($quality) * $fans,
            'day' => 0,
            'room' => 0.0,
            'soldRate' => 0.0,
            'fanRate' => 0.0,
            'catalogRate' => 0.0,
            'daySold' => 0.0,
            'settledAt' => $at,
            'sold' => 0.0,
            'catalogSold' => 0.0,
        ], $fans);
    }

    /** Wann der nächste Tag der Platte beginnt. */
    public static function nextDayAt(array $record): int
    {
        return $record['releasedAt'] + ($record['day'] + 1) * self::DAY;
    }

    /** Nächster Tag: Der Hype sinkt, die Werte des Tages werden festgelegt ($fans = Fans der Band jetzt, für den Katalog). */
    public static function nextDay(array $record, float $fans): array
    {
        $record['hype'] = Hype::next($record['hype'], $record['quality']);
        $record['day']++;
        return self::plan($record, $fans);
    }

    /**
     * Lässt die Platte bis $until laufen, höchstens bis zum Ende des laufenden Tages.
     * @return array{0: array, 1: float, 2: float, 3: float} [Platte, verkauft durch den Hype, neue Fans, Katalog]
     */
    public static function flow(array $record, int $until): array
    {
        $end = min($until, self::nextDayAt($record));
        if ($end <= $record['settledAt']) {
            return [$record, 0.0, 0.0, 0.0];
        }
        $share = ($end - $record['settledAt']) / self::DAY;
        $sold = $record['soldRate'] * $share;
        $catalog = $record['catalogRate'] * $share;
        $record['settledAt'] = $end;
        $record['sold'] += $sold;
        $record['catalogSold'] += $catalog;
        return [$record, $sold, $record['fanRate'] * $share, $catalog];
    }

    /**
     * Lässt eine einzelne Platte bis $until laufen, auch über Tagesgrenzen. Für den Katalog zählen die Fans der Band ($fans)
     * plus die neuen Fans dieser Platte; mehrere Platten einer Band rechnet Career im Gleichschritt.
     * @return array{0: array, 1: float, 2: float, 3: float} [Platte, verkauft durch den Hype, neue Fans, Katalog]
     */
    public static function advance(array $record, int $until, float $fans = 0.0): array
    {
        $sold = $gained = $catalog = 0.0;
        while (true) {
            [$record, $s, $g, $k] = self::flow($record, $until);
            $sold += $s;
            $gained += $g;
            $catalog += $k;
            if ($until < self::nextDayAt($record)) {
                return [$record, $sold, $gained, $catalog];
            }
            $record = self::nextDay($record, $fans + $gained);
        }
    }

    /**
     * Schub für diese Platte, an ihrem Stand (settledAt): Der Hype steigt sofort, jeder weitere Schub zählt halb so viel.
     * Für den Rest des Tages entdecken entsprechend mehr Leute die Platte.
     */
    public static function boost(array $record, float $amount): array
    {
        [$hype, $record['boosts']] = Hype::boost($record['hype'], $amount, $record['boosts']);
        $extra = self::discovery($record) * ($hype - $record['hype']) / 100 * $record['room'];
        $rest = (self::nextDayAt($record) - $record['settledAt']) / self::DAY;
        $record['hype'] = $hype;
        $record['buyers'] += $extra * $rest;
        $record['soldRate'] += $extra;
        $record['fanRate'] += Balance::CORE_SALES['k'] * $extra;
        return $record;
    }

    /** Neue Fans aus Konzerten und Ereignissen während dieser Platte: Sie zählen in C, und ein Teil will sie kaufen (ab dem nächsten Tag). */
    public static function addFans(array $record, float $fans): array
    {
        $record['gained'] += $fans;
        $record['willing'] += self::buyShare($record['quality']) * $fans;
        return $record;
    }

    /** Ausgelaufen: An ihrem letzten Tag hat sie durch den Hype weniger als ein Stück verkauft (Katalog zählt nicht). */
    public static function isOver(array $record): bool
    {
        return $record['daySold'] < Balance::CORE_SALES['endBelow'];
    }

    /** Platte ist ausgelaufen und nicht mehr die neueste: Ab jetzt verkauft sie nur noch über den Katalog. */
    public static function end(array $record): array
    {
        $record['ended'] = true;
        $record['soldRate'] = $record['fanRate'] = $record['daySold'] = 0.0;
        return $record;
    }

    /** Ganz vorbei: ausgelaufen und auch der Katalog bringt kaum noch etwas (dann muss das Spiel sie nicht mehr rechnen). */
    public static function isRetired(array $record): bool
    {
        return ($record['ended'] ?? false) && $record['catalogRate'] < Balance::CORE_CATALOG['endBelow'];
    }

    /** Katalog je Tag: unabhängig vom Hype, nach den Fans der Band und der Qualität, mit jeder Woche Alter halb so viel. */
    public static function catalogPerDay(float $fans, float $quality, int $day): float
    {
        return $fans * Balance::CORE_CATALOG['rate'] * $quality / 100 * 0.5 ** ($day / 7);
    }

    /** Geld fürs Label (je verkaufter Platte, auch aus dem Katalog). */
    public static function money(float $sold): float
    {
        return $sold * Balance::CORE_SALES['price'];
    }

    /** Werte eines Tages festlegen: nK = (p + q × F0 / M) × A × (M − F0 − B − C), nB = U × r. */
    private static function plan(array $record, float $fans): array
    {
        $c = Balance::CORE_SALES;
        $record['catalogRate'] = self::catalogPerDay($fans, $record['quality'], $record['day']);
        if ($record['ended'] ?? false) {
            return $record;
        }
        $record['room'] = max(0.0, $record['pool'] - $record['f0'] - $record['buyers'] - $record['gained']);
        $discovered = self::discovery($record) * $record['hype'] / 100 * $record['room'];
        $fanBuyers = $record['willing'] * $c['r'];
        $record['willing'] -= $fanBuyers;
        $record['buyers'] += $discovered;
        $record['soldRate'] = $record['daySold'] = $discovered + $fanBuyers;
        $record['fanRate'] = $c['k'] * $discovered;
        return $record;
    }

    /** Anteil des Pools, der je Tag bei vollem Hype die Platte entdeckt: Grundentdeckung plus Mundpropaganda aus den Fans beim Erscheinen. */
    private static function discovery(array $record): float
    {
        $c = Balance::CORE_SALES;
        return $c['p'] + $c['q'] * $record['f0'] / $record['pool'];
    }
}
