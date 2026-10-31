<?php
declare(strict_types=1);

namespace Signed\Game\Core;

use Signed\Game\Balance;

/**
 * Festival, Battle und Anfragen im neuen Kern: Sie heben den Hype der neuesten Platte (Schübe, siehe Hype::boost)
 * und bringen Neugierige, aus denen Fans werden können. Noch nicht ins Spiel eingebaut (neuer Kern).
 */
final class Events
{
    /** Neue Fans aus den Zuschauern eines Ereignisses: Zuschauer × (1 − Fans / Pool) × Anteil × Live-Qualität / 100. */
    public static function fans(float $audience, float $fans, float $pool, float $live): float
    {
        return $audience * max(0.0, 1 - $fans / $pool) * Balance::CORE_EVENTS['share'] * $live / 100;
    }

    /** @return array{audience: int, boost: float} Bühne `main`, `side` oder `tent` */
    public static function festival(string $stage): array
    {
        return Balance::CORE_EVENTS['festival'][$stage];
    }

    /** Zuschauer je gespieltes Battle-Spiel. */
    public static function battleAudience(): int
    {
        return Balance::CORE_EVENTS['battle']['audience'];
    }

    /** Hype-Schub nach dem Battle-Platz (nur Platz 1 bis 3). */
    public static function battleBoost(int $place): float
    {
        return Balance::CORE_EVENTS['battle']['boost'][$place] ?? 0.0;
    }

    /** Preisgeld nach Platz (4 = im Halbfinale ausgeschieden); der Sieger bekommt es nur, wenn er das Geld statt des Wochenpreises wählt. */
    public static function battleMoney(int $place): int
    {
        $prizes = Balance::CORE_BATTLE_PRIZES;
        return $place === 1 ? $prizes['winner'] : ($prizes['money'][$place] ?? 0);
    }

    /** @return array{seconds: int, boost: float} Anfrage `interview`, `photos`, `article`, `report` oder `ad` */
    public static function request(string $kind): array
    {
        return Balance::CORE_EVENTS['requests'][$kind];
    }

    /** Werbung bringt dazu Geld: so viele ₲, wie die Band Fans hat. */
    public static function adMoney(float $fans): float
    {
        return $fans * Balance::CORE_EVENTS['adPerFan'];
    }
}
