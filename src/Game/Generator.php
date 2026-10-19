<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Rng;

/** Erzeugt zufällige Karten: Musiker, Instrumente, Manager. */
final class Generator
{
    public static function rarity(array $odds): string
    {
        return (string) Rng::weighted($odds);
    }

    /**
     * @param array{instrument?: string, genre?: string, single?: bool} $opts
     * @return array Musiker im Datenbankformat (JSON-Felder noch als Arrays)
     */
    public static function musician(string $rarity, array $opts = []): array
    {
        $cfg = Balance::MUSICIAN[$rarity];
        $look = self::look();
        [$first, $last] = self::name($look['gender']);

        $instr1 = $opts['instrument'] ?? (string) Rng::weighted(array_map(static fn ($i) => $i['weight'], Catalog::INSTRUMENTS));
        $typical = Catalog::INSTRUMENTS[$instr1]['genres'];
        $atypical = array_values(array_diff(array_keys(Catalog::GENRES), $typical));

        if (isset($opts['genre'])) {
            $primary = $opts['genre'];
        } elseif ($atypical && Rng::chance(Balance::UNUSUAL_CHANCE)) {
            $primary = Rng::pick($atypical);
        } else {
            $primary = Rng::pick($typical);
        }
        $genres = [$primary];
        $pool = array_values(array_diff($typical, $genres));
        foreach (Balance::EXTRA_GENRE_CHANCE as $chance) {
            if (!$pool || !Rng::chance($chance)) {
                break;
            }
            $genre = Rng::pick($pool);
            $genres[] = $genre;
            $pool = array_values(array_diff($pool, [$genre]));
        }

        $base = Rng::float(...$cfg['skill']);
        $skill1 = $base;
        $instr2 = null;
        $skill2 = null;
        if (empty($opts['single']) && Rng::chance(Balance::SECOND_INSTRUMENT_CHANCE)) {
            $instr2 = self::secondInstrument($instr1);
            $skill1 = $base * Balance::MULTI_MAIN_FACTOR;
            $skill2 = $base * Rng::float(...Balance::MULTI_SECOND_FACTOR);
        }

        $techniques = [];
        foreach (array_filter([$instr1, $instr2]) as $instrument) {
            if (Rng::chance($cfg['technique'])) {
                $techniques[] = Rng::pick(Catalog::INSTRUMENTS[$instrument]['techniques']);
            }
        }

        return [
            'first_name' => $first,
            'last_name' => $last,
            'rarity' => $rarity,
            'instr1' => $instr1,
            'skill1' => round($skill1, 1),
            'instr2' => $instr2,
            'skill2' => $skill2 === null ? null : round($skill2, 1),
            'presence' => round(Rng::float(...$cfg['presence']), 1),
            'fame' => 0.0,
            'genres' => $genres,
            'traits' => Rng::sample(array_keys(Catalog::TRAITS), (int) Rng::weighted($cfg['traits'])),
            'techniques' => array_values(array_unique($techniques)),
            'personality' => self::personality(),
            'unusual' => !in_array($primary, $typical, true),
            'look' => $look,
        ];
    }

    /** @return string Schlüssel des Instrumentenmodells */
    public static function gear(string $rarity, ?string $type = null): string
    {
        $type ??= (string) Rng::weighted(array_map(static fn ($i) => $i['weight'], Catalog::INSTRUMENTS));
        $models = array_keys(array_filter(Catalog::GEAR, static fn ($g) => $g['type'] === $type && $g['rarity'] === $rarity));
        return (string) Rng::pick($models);
    }

    public static function manager(string $rarity): array
    {
        $look = self::look();
        [$first, $last] = self::name($look['gender']);
        $skillCount = Rng::chance(Balance::MANAGER_SECOND_SKILL) ? 2 : 1;
        return [
            'first_name' => $first,
            'last_name' => $last,
            'rarity' => $rarity,
            'level' => Rng::int(...Balance::MANAGER_LEVEL[$rarity]),
            'skills' => Rng::sample(array_keys(Catalog::MANAGER_SKILLS), $skillCount),
            'look' => $look,
        ];
    }

    public static function songTitle(): string
    {
        return Rng::pick(Catalog::SONG_WORDS[0]) . Rng::pick(Catalog::SONG_WORDS[1]);
    }

    /** Profilbild: zufällig kombiniert, ohne ausgearbeitetes Gesicht. */
    public static function look(): array
    {
        $gender = (string) Rng::weighted(['f' => 47, 'm' => 47, 'd' => 6]);
        $hair = match ($gender) {
            'f' => ['lang' => 24, 'zopf' => 14, 'dutt' => 12, 'locken' => 14, 'bob' => 14, 'kurz' => 8, 'afro' => 8, 'sidecut' => 6],
            'm' => ['kurz' => 28, 'locken' => 12, 'glatze' => 10, 'lang' => 10, 'afro' => 8, 'sidecut' => 9, 'irokese' => 5, 'dutt' => 5, 'bob' => 3],
            default => ['kurz' => 14, 'bob' => 14, 'locken' => 12, 'sidecut' => 12, 'lang' => 12, 'afro' => 10, 'irokese' => 8, 'dutt' => 8],
        };
        return [
            'gender' => $gender,
            'skin' => Rng::pick(Catalog::SKIN_TONES),
            'hair' => (string) Rng::weighted($hair),
            'hairColor' => Rng::chance(0.12) ? Rng::pick(Catalog::FUN_HAIR_COLORS) : Rng::pick(Catalog::HAIR_COLORS),
            'beard' => $gender === 'f' ? 'none' : (string) Rng::weighted(['none' => 60, 'stubble' => 22, 'full' => 18]),
            'glasses' => (string) Rng::weighted(['none' => 76, 'round' => 9, 'square' => 9, 'sun' => 6]),
            'hat' => (string) Rng::weighted(['none' => 84, 'beanie' => 6, 'cap' => 5, 'fedora' => 3, 'cowboy' => 2]),
            'piercing' => Rng::chance(0.15),
            'freckles' => Rng::chance(0.12),
            'shirt' => Rng::pick(Catalog::SHIRT_COLORS),
        ];
    }

    /** @return array{0: string, 1: string} */
    private static function name(string $gender): array
    {
        return [Rng::pick(Catalog::FIRST_NAMES[$gender]), Rng::pick(Catalog::LAST_NAMES)];
    }

    private static function secondInstrument(string $first): string
    {
        if ($first !== 'vocals' && Rng::chance(0.5)) {
            return 'vocals';
        }
        $weights = array_map(static fn ($i) => $i['weight'], Catalog::INSTRUMENTS);
        unset($weights[$first]);
        return (string) Rng::weighted($weights);
    }

    private static function personality(): array
    {
        $first = Rng::pick(array_keys(Catalog::PERSONALITIES));
        if (!Rng::chance(0.35)) {
            return [$first];
        }
        $options = array_filter(
            array_keys(Catalog::PERSONALITIES),
            static fn ($p) => $p !== $first && !BandCalc::clashes($first, $p)
        );
        return [$first, Rng::pick($options)];
    }
}
