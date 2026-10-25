<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Rng;

/**
 * Zwei Stufenleitern bestimmen den Wert eines Musikers: Können-Stufe und Bekanntheits-Stufe, jeweils 0–3.
 * Jede Können-Stufe bringt eine Fähigkeit: Stufe 1 und 2 zufällig, bei Stufe 3 wählt man aus drei.
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

    /** Anteil, zu dem Gewinne auf dieser Stufe wirken (siehe Balance::LEVEL_PACE). */
    public static function pace(int $level): float
    {
        return Balance::LEVEL_PACE[min($level, count(Balance::LEVEL_PACE) - 1)];
    }

    public static function has(array $m, string $ability): bool
    {
        return in_array($ability, $m['abilities'], true);
    }

    /** Spezialtechniken des Musikers. */
    public static function techniques(array $m): array
    {
        return array_values(array_filter($m['abilities'], static fn ($a) => !empty(Catalog::ABILITIES[$a]['technique'])));
    }

    /** Fähigkeiten, die ein Musiker mit diesen Instrumenten noch lernen kann. */
    public static function eligible(array $instruments, array $owned): array
    {
        $techniques = [];
        foreach ($instruments as $instrument) {
            $techniques = array_merge($techniques, Catalog::INSTRUMENTS[$instrument]['techniques'] ?? []);
        }
        $options = [];
        foreach (Catalog::ABILITIES as $key => $ability) {
            // Nicht lernbar: Nachteile (Interpret) und Songwriter für jemanden, der nicht schreibt.
            if (!empty($ability['negative']) || ($key === 'songwriter' && in_array('nowriter', $owned, true))) {
                continue;
            }
            if (!in_array($key, $owned, true) && (empty($ability['technique']) || in_array($key, $techniques, true))) {
                $options[] = $key;
            }
        }
        return $options;
    }

    public static function randomAbility(array $instruments, array $owned): string
    {
        return (string) Rng::pick(self::eligible($instruments, $owned));
    }

    /** @return list<string> */
    public static function options(array $instruments, array $owned, int $count = 3): array
    {
        return Rng::sample(self::eligible($instruments, $owned), $count);
    }

    /**
     * Nach einem Können-Zuwachs: steigt der Musiker auf? Liefert null oder
     * ['level' => neue Stufe, 'abilities' => neu gewonnene, 'choice' => Auswahl für Stufe 3 oder null].
     */
    public static function check(Repo $repo, int $musicianId): ?array
    {
        $m = $repo->musician($musicianId);
        $level = self::forSkill(max($m['skill1'], (float) $m['skill2']));
        if ($level <= $m['level']) {
            return null;
        }
        $instruments = array_filter([$m['instr1'], $m['instr2']]);
        $abilities = $m['abilities'];
        $gained = [];
        $choice = null;
        for ($l = $m['level'] + 1; $l <= $level; $l++) {
            if ($l < 3) {
                $gained[] = $abilities[] = self::randomAbility($instruments, $abilities);
            } else {
                $choice = self::options($instruments, $abilities);
            }
        }
        $repo->db->update('musicians', [
            'level' => $level,
            'abilities' => json_encode($abilities),
            'ability_choice' => $choice === null ? null : json_encode($choice),
        ], ['id' => $musicianId]);
        $best = $m['instr2'] !== null && (float) $m['skill2'] > (float) $m['skill1'] ? $m['instr2'] : $m['instr1'];
        $repo->log('level', sprintf('%s ist %s jetzt %s.', Bands::fullName($m), Catalog::INSTRUMENTS[$best]['at'], Catalog::LEVEL_WORDS[$level]), $m['band_id'], $musicianId);
        return ['musicianId' => $musicianId, 'name' => $m['first_name'], 'level' => $level, 'instrument' => $best, 'abilities' => $gained, 'choice' => $choice];
    }

    /** Stufe 3: Der Musiker wird Experte und lernt die gewählte Fähigkeit. */
    public static function choose(Repo $repo, int $musicianId, string $ability): void
    {
        $m = $repo->musician($musicianId);
        if (!in_array($ability, $m['ability_choice'] ?? [], true)) {
            throw new \Signed\ApiError('Diese Fähigkeit steht nicht zur Wahl.');
        }
        $repo->db->update('musicians', [
            'abilities' => json_encode(array_merge($m['abilities'], [$ability])),
            'ability_choice' => null,
        ], ['id' => $musicianId]);
        $repo->log('level', sprintf('%s lernt „%s“.', Bands::fullName($m), Catalog::ABILITIES[$ability]['name'] ?? $ability), $m['band_id'], $musicianId);
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
