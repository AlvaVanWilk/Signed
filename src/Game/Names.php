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

    /** Vorschlag für einen Plattentitel; mit $labelId keiner, den das Label schon hat. */
    public static function record(?Db $db = null, ?int $labelId = null): string
    {
        $taken = $db === null || $labelId === null ? [] : array_map('mb_strtolower', array_column($db->all('SELECT title FROM records WHERE label_id = ?', [$labelId]), 'title'));
        for ($i = 0; $i < 20; $i++) {
            $title = self::recordCandidate();
            if (!in_array(mb_strtolower($title), $taken, true)) {
                return $title;
            }
        }
        return $title;
    }

    private static function recordCandidate(): string
    {
        $w = Catalog::NAME_WORDS;
        // Ein Wort wie „Neonherz“ oder eins der Muster
        if (Rng::chance(0.3)) {
            return Generator::songTitle();
        }
        $nouns = $w['nouns'];
        $n = Rng::pick($nouns);
        do {
            $m = Rng::pick($nouns);
        } while ($m === $n);
        return strtr(Rng::pick($w['recordPatterns']), [
            '%a' => Rng::pick($w['adjectives']), '%n' => $n, '%m' => $m, '%p' => Rng::pick($w['places']),
            '%e' => Rng::pick($w['english']), '%E' => Rng::pick($w['englishPlural']),
        ]);
    }

    /**
     * Drei Vorschläge für einen Künstlernamen, fest aus der Person abgeleitet (dieselben, solange niemand den Namen vergibt):
     * aus Vor- oder Nachname mit einem Zusatz, ein Einzelwort, bei DJs und Rappern mit Kürzel davor. Vergebene Bandnamen fallen weg.
     * @return list<string>
     */
    public static function stageOptions(Db $db, array $m): array
    {
        $w = Catalog::STAGE_WORDS;
        $s = crc32('stage|' . $m['id']) ?: 1;
        $pick = static function (array $list) use (&$s) {
            $s ^= ($s << 13) & 0xFFFFFFFF;
            $s ^= $s >> 17;
            $s ^= ($s << 5) & 0xFFFFFFFF;
            $s &= 0xFFFFFFFF;
            return $list[$s % count($list)];
        };
        $first = $m['first_name'];
        $last = $m['last_name'];
        $played = ($m['band_slot'] ?? 1) === 2 && ($m['instr2'] ?? null) !== null ? $m['instr2'] : $m['instr1'];
        // MC nur für Rapper (Gesang und Hip-Hop), DJ für Turntables und Keyboard.
        $tag = $played === 'vocals' && !in_array('hiphop', $m['genres'], true) ? null : ($w['tag'][$played] ?? null);
        $ideas = [];
        for ($i = 0; $i < 24 && count($ideas) < Balance::STAGE_NAME['choices']; $i++) {
            $idea = match ($i % 4) {
                0 => $tag !== null ? sprintf('%s %s', $tag, $pick([$first, $pick($w['single'])])) : sprintf('%s %s', $first, $pick($w['after'])),
                1 => $pick($w['single']),
                2 => sprintf('%s %s', $pick($w['before']), $pick([$first, $last])),
                default => sprintf('%s %s', mb_substr($first, 0, 1) . '.', $pick($w['after'])),
            };
            if (!in_array($idea, $ideas, true) && !self::bandTaken($db, $idea)) {
                $ideas[] = $idea;
            }
        }
        return $ideas;
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
