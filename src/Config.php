<?php
declare(strict_types=1);

namespace Signed;

final class Config
{
    private static ?array $values = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        self::$values ??= self::load();
        return self::$values[$key] ?? $default;
    }

    /** Für Tests: Werte ersetzen, ohne config.php anzufassen. */
    public static function override(array $values): void
    {
        self::$values = array_replace(self::load(), $values);
    }

    private static function load(): array
    {
        $root = dirname(__DIR__);
        $defaults = [
            'db' => ['dsn' => 'sqlite:' . $root . '/data/signed.sqlite', 'user' => null, 'pass' => null],
            'debug' => false,
        ];
        $file = $root . '/config.php';
        if (is_file($file)) {
            $custom = require $file;
            if (is_array($custom)) {
                return array_replace($defaults, $custom);
            }
        }
        return $defaults;
    }
}
