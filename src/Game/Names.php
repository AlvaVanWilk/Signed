<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Db;
use Signed\Rng;

/** Zufallsnamen für Bands und Platten. Bandnamen sind im ganzen Spiel eindeutig. */
final class Names
{
    public static function band(Db $db): string
    {
        for ($try = 0; $try < 30; $try++) {
            $name = self::bandCandidate();
            if (!self::bandTaken($db, $name)) {
                return $name;
            }
        }
        // Sehr unwahrscheinlich: alle Versuche vergeben. Eine Zahl macht den Namen eindeutig.
        return self::bandCandidate() . ' ' . Rng::int(2, 99);
    }

    public static function record(): string
    {
        $w = Catalog::NAME_WORDS;
        return match (Rng::int(0, 3)) {
            0 => Generator::songTitle(),
            1 => Rng::pick($w['adjectives']) . ' ' . Rng::pick($w['nouns']),
            2 => sprintf('Nachts %s', Rng::pick($w['places'])),
            default => sprintf('%s & %s', Rng::pick($w['nouns']), Rng::pick($w['nouns'])),
        };
    }

    public static function bandTaken(Db $db, string $name, ?int $exceptBandId = null): bool
    {
        // Ehemalige Bands halten ihren Namen nur, wenn er etwas wert war (Balance::NAME_PROTECT_FAME).
        $sql = 'SELECT COUNT(*) FROM bands WHERE name_key = ? AND (retired_at IS NULL OR fame_final >= ?)';
        $params = [self::key($name), Balance::NAME_PROTECT_FAME];
        if ($exceptBandId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptBandId;
        }
        return (int) $db->value($sql, $params) > 0;
    }

    public static function key(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    private static function bandCandidate(): string
    {
        $w = Catalog::NAME_WORDS;
        return match (Rng::int(0, 4)) {
            0 => 'Die ' . Rng::pick($w['bandPlural']),
            1 => Rng::pick($w['adjectives']) . ' ' . Rng::pick($w['nouns']),
            2 => Rng::pick($w['english']) . ' ' . Rng::pick($w['englishPlural']),
            3 => sprintf('%s %s', Rng::pick($w['prefixes']), Rng::pick($w['suffixes'])),
            default => 'The ' . Rng::pick($w['englishPlural']),
        };
    }
}
