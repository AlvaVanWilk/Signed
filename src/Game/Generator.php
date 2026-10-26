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
     * @param array{instrument?: string, genre?: string, single?: bool, minSkill?: float, maxSkill?: float, avoid?: string[], getsAlongWith?: string[]} $opts
     * @return array Musiker im Datenbankformat (JSON-Felder noch als Arrays)
     */
    public static function musician(string $rarity, array $opts = []): array
    {
        $cfg = Balance::MUSICIAN[$rarity];
        $look = self::look();
        [$first, $last] = self::name($look['gender'], $opts['avoid'] ?? []);

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

        [$low, $high] = $cfg['skill'];
        $base = Rng::float(max($low, min($high, $opts['minSkill'] ?? $low)), $high);
        $skill1 = $base;
        $instr2 = null;
        $skill2 = null;
        if (empty($opts['single']) && Rng::chance(Balance::SECOND_INSTRUMENT_CHANCE)) {
            $instr2 = self::secondInstrument($instr1);
            $skill1 = $base * Balance::MULTI_MAIN_FACTOR;
            $skill2 = $base * Rng::float(...Balance::MULTI_SECOND_FACTOR);
        }

        if (isset($opts['minSkill']) && max($skill1, (float) $skill2) < $opts['minSkill']) {
            $skill1 = $opts['minSkill'];
        }
        // Unter maxSkill bleiben (z. B. Können-Stufe 0 aus dem Jam-Café).
        $best = max($skill1, (float) $skill2);
        if (isset($opts['maxSkill']) && $best >= $opts['maxSkill']) {
            $floor = min($opts['maxSkill'] * 0.98, max($opts['maxSkill'] * 0.7, $opts['minSkill'] ?? 0));
            $scale = Rng::float($floor, $opts['maxSkill'] * 0.98) / $best;
            $skill1 *= $scale;
            $skill2 = $skill2 === null ? null : $skill2 * $scale;
        }
        // Aus Packs höchstens Können-Stufe 1.
        $level = min(1, Levels::forSkill(max($skill1, (float) $skill2)));
        // Eine Spieltechnik von Anfang an (man hört sie in der Einsendung)
        $abilities = Levels::randomTechnique(array_filter([$instr1, $instr2]), $genres);

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
            'level' => $level,
            'abilities' => $abilities,
            'personality' => self::personality($opts['getsAlongWith'] ?? []),
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

    /** Werte eines einzelnen Instruments: um das Modell gestreut, mal Klang-, mal Design-lastig. */
    public static function gearStats(string $model): array
    {
        $base = Catalog::GEAR[$model];
        $spread = Balance::GEAR_VARIANCE;
        $stats = [];
        foreach (['klang', 'spielbarkeit', 'design'] as $stat) {
            $stats[$stat] = max(1, min(100, $base[$stat] + Rng::int(-$spread, $spread)));
        }
        $stats['perks'] = [];
        $stats['bonuses'] = Gear::roll($base['rarity']);
        return $stats;
    }

    /** @param string[] $avoid Vor- und Nachnamen, die im Label schon vorkommen */
    public static function manager(string $rarity, array $avoid = []): array
    {
        $look = self::look();
        [$first, $last] = self::name($look['gender'], $avoid);
        $skillCount = Rng::chance(Balance::MANAGER_SECOND_SKILL) ? 2 : 1;
        return [
            'first_name' => $first,
            'last_name' => $last,
            'rarity' => $rarity,
            'level' => Rng::int(...Balance::MANAGER_LEVEL[$rarity]),
            // Nur Fähigkeiten, die schon wirken – sonst zieht man einen Manager, der nichts kann.
            'skills' => Rng::sample(array_keys(array_filter(Catalog::MANAGER_SKILLS, static fn ($s) => $s['active'])), $skillCount),
            'look' => $look,
        ];
    }

    /** Eine Person fürs Label (Assistenz, Scout …): Name und Aussehen. */
    public static function person(array $avoid = []): array
    {
        $look = self::look();
        [$first, $last] = self::name($look['gender'], $avoid);
        return ['first_name' => $first, 'last_name' => $last, 'look' => $look];
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
        $look = [
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
        // Zu Dutt, Undercut, Irokese, Locken und Afro passt keine Kopfbedeckung (wie NO_HAT in avatar.js).
        if (in_array($look['hair'], ['dutt', 'sidecut', 'irokese', 'locken', 'afro'], true)) {
            $look['hat'] = 'none';
        }
        return $look;
    }

    /**
     * Vor- und Nachnamen aus $avoid (schon im Label) werden gemieden, solange noch andere frei sind.
     * @param string[] $avoid
     * @return array{0: string, 1: string}
     */
    private static function name(string $gender, array $avoid = []): array
    {
        $first = array_values(array_diff(Catalog::FIRST_NAMES[$gender], $avoid));
        $last = array_values(array_diff(Catalog::LAST_NAMES, $avoid));
        return [Rng::pick($first ?: Catalog::FIRST_NAMES[$gender]), Rng::pick($last ?: Catalog::LAST_NAMES)];
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

    /** Zwei Eigenschaften, die nicht zusammen in einem Menschen vorkommen (Catalog::CONTRADICTIONS). */
    public static function contradicts(string $a, string $b): bool
    {
        foreach (Catalog::CONTRADICTIONS as [$x, $y]) {
            if (($a === $x && $b === $y) || ($a === $y && $b === $x)) {
                return true;
            }
        }
        return false;
    }

    /** @param string[] $partner Charakterzüge, mit denen sich der neue Musiker vertragen soll */
    private static function personality(array $partner = []): array
    {
        $fits = static fn (string $p): bool => !array_filter($partner, static fn ($q) => BandCalc::clashes($p, $q));
        $pool = array_values(array_filter(array_keys(Catalog::PERSONALITIES), $fits));
        // Zwei Partytiere finden sich gern.
        $first = in_array('partytier', $partner, true) && Rng::chance(0.4) ? 'partytier' : Rng::pick($pool);
        if (!Rng::chance(0.35)) {
            return [$first];
        }
        $options = array_filter($pool, static fn ($p) => $p !== $first && !self::contradicts($first, $p));
        return [$first, Rng::pick(array_values($options))];
    }
}
