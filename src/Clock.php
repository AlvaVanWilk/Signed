<?php
declare(strict_types=1);

namespace Signed;

/** Zeitquelle, die sich in Tests vorspulen lässt. */
final class Clock
{
    private static ?int $fixed = null;

    public static function now(): int
    {
        return self::$fixed ?? time();
    }

    public static function set(?int $time): void
    {
        self::$fixed = $time;
    }

    public static function advance(int $seconds): void
    {
        self::$fixed = self::now() + $seconds;
    }
}
