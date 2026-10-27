<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Rng;

/**
 * Zwei Stufenleitern bestimmen den Wert eines Musikers: Können-Stufe und Bekanntheits-Stufe, jeweils 0–3.
 * Jede Können-Stufe schaltet eine bessere Klasse von Instrumenten frei (0 gewöhnlich, 1 selten, 2 episch, 3 legendär).
 * Die Spieltechnik hat ein Musiker von Anfang an; Fähigkeiten gibt es nicht mehr.
 */
final class Levels
{
    public static function forSkill(float $skill): int
    {
        return self::step($skill, Balance::SKILL_LEVELS);
    }

    public static function forFame(float $fame): int
    {
        return self::step($fame, Balance::FAME_LEVELS);
    }


    public static function has(array $m, string $ability): bool
    {
        return in_array($ability, $m['abilities'], true);
    }

    /** Versteckter Wert eines Musikers aus seinen Charakterzügen (Balance::VALUE); nur für den Preis, nie angezeigt. */
    public static function value(array $m): int
    {
        $sum = 0;
        foreach ($m['personality'] ?? [] as $trait) {
            $sum += Balance::VALUE['traits'][$trait] ?? 0;
        }
        return $sum;
    }

    /** Spieltechnik des Musikers (eine Liste mit höchstens einem Eintrag). */
    public static function techniques(array $m): array
    {
        return array_values(array_filter($m['abilities'] ?? [], static fn ($a) => isset(Catalog::ABILITIES[$a])));
    }

    /**
     * Techniken, die zu diesen Instrumenten und Genres passen. Mag jemand nur Genres, die für sein Instrument untypisch sind,
     * kommen alle Techniken des Instruments infrage.
     * @return list<string>
     */
    public static function fittingTechniques(array $instruments, array $genres): array
    {
        $all = [];
        foreach ($instruments as $instrument) {
            $all = array_merge($all, Catalog::INSTRUMENTS[$instrument]['techniques'] ?? []);
        }
        $all = array_values(array_unique($all));
        $fit = array_values(array_filter($all, static fn ($t) => array_intersect(Catalog::ABILITIES[$t]['genres'], $genres)));
        return $fit ?: $all;
    }

    /** Eine Technik für einen neuen Musiker (gelost). */
    public static function randomTechnique(array $instruments, array $genres): array
    {
        $options = self::fittingTechniques($instruments, $genres);
        return $options ? [(string) Rng::pick($options)] : [];
    }

    /**
     * Nach einem Können-Zuwachs: steigt der Musiker auf? Liefert null oder ['level' => neue Stufe, …].
     * Mit jeder Stufe darf er eine bessere Klasse von Instrumenten spielen (Gear::playable).
     */
    public static function check(Repo $repo, int $musicianId): ?array
    {
        $m = $repo->musician($musicianId);
        $level = self::forSkill(max($m['skill1'], (float) $m['skill2']));
        if ($level <= $m['level']) {
            return null;
        }
        $repo->db->update('musicians', ['level' => $level], ['id' => $musicianId]);
        $best = $m['instr2'] !== null && (float) $m['skill2'] > (float) $m['skill1'] ? $m['instr2'] : $m['instr1'];
        $repo->log('level', sprintf('%s ist %s jetzt %s.', Bands::fullName($m), Catalog::INSTRUMENTS[$best]['at'], Catalog::LEVEL_WORDS[$level]), $m['band_id'], $musicianId);
        return ['musicianId' => $musicianId, 'name' => $m['first_name'], 'level' => $level, 'instrument' => $best, 'abilities' => [], 'choice' => null];
    }

    private static function step(float $value, array $thresholds): int
    {
        $level = 0;
        foreach ($thresholds as $index => $threshold) {
            if ($value >= $threshold) {
                $level = $index + 1;
            }
        }
        return $level;
    }
}
