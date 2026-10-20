<?php
declare(strict_types=1);

namespace Signed\Game;

/**
 * Reine Rechenregeln für Bands: Sound, Ruhm, Chemie, Auftrittsorte.
 * Musiker kommen hier immer im hydrierten Format aus Repo::hydrateMusician an.
 * $gear ist eine Map Musiker-ID => [Instrumententyp => Modell].
 */
final class BandCalc
{
    public static function instrument(array $m): string
    {
        return $m['band_slot'] === 2 && $m['instr2'] !== null ? $m['instr2'] : $m['instr1'];
    }

    public static function skill(array $m, ?int $slot = null): float
    {
        $slot ??= $m['band_slot'];
        return $slot === 2 && $m['instr2'] !== null ? (float) $m['skill2'] : (float) $m['skill1'];
    }

    public static function gearOf(array $m, array $gear): ?array
    {
        return $gear[$m['id']][self::instrument($m)] ?? null;
    }

    /** Beitrag eines Musikers: Können × Instrument (Symbiose) × Genre-Passung. */
    public static function memberValue(array $m, string $genre, ?array $model): float
    {
        $skill = self::skill($m);
        $fit = in_array($genre, $m['genres'], true) ? 1.0 : Balance::OFF_GENRE_FACTOR;
        $gear = $model === null ? Balance::NO_GEAR_FACTOR : 1 + self::gearBonus($model, $skill);
        return $skill * $gear * $fit;
    }

    /** Ein gutes Instrument entfaltet sich nur in fähigen Händen. */
    public static function gearBonus(?array $model, float $skill): float
    {
        if ($model === null) {
            return 0.0;
        }
        $required = 100 - $model['spielbarkeit'];
        $unlock = $required <= 0 ? 1.0 : min(1.0, $skill / $required);
        return $model['klang'] / 100 * Balance::GEAR_MAX_BONUS * $unlock;
    }

    public static function sound(array $members, string $genre, float $cohesion, array $gear): float
    {
        if (!$members) {
            return 0.0;
        }
        $values = array_map(static fn ($m) => self::memberValue($m, $genre, self::gearOf($m, $gear)), $members);
        $mean = array_sum($values) / count($values);
        $core = Balance::SOUND_MEAN_WEIGHT * $mean + (1 - Balance::SOUND_MEAN_WEIGHT) * min($values);
        $types = count(array_unique(array_map([self::class, 'instrument'], $members)));
        $lineup = 1 + Balance::LINEUP_BONUS * min(Balance::LINEUP_BONUS_MAX_TYPES, $types - 1);
        $cohesionFactor = Balance::COHESION_FLOOR + (1 - Balance::COHESION_FLOOR) * $cohesion / 100;
        return round($core * $lineup * $cohesionFactor, 1);
    }

    /** Können-Zuwachs: Talent beschleunigt, je besser jemand schon ist, desto langsamer geht es. */
    public static function trainingGain(array $m, float $base, ?int $slot = null): float
    {
        $talent = Levels::has($m, 'talent') ? Balance::TALENT_FACTOR : 1.0;
        return $base * $talent * max(0.08, 1 - self::skill($m, $slot) / 110);
    }

    /** Bandruhm = Summe der Bekanntheit aller aktuellen Mitglieder. */
    public static function fame(array $members): float
    {
        return round(array_sum(array_map(static fn ($m) => $m['fame'], $members)), 1);
    }

    public static function clashes(string $a, string $b): bool
    {
        foreach (Catalog::CLASHES as [$x, $y]) {
            if (($a === $x && $b === $y) || ($a === $y && $b === $x)) {
                return true;
            }
        }
        return false;
    }

    /** Wie gut die Musiker menschlich zueinander passen: 0,5 bis 1,5. */
    public static function chemistry(array $members): float
    {
        $members = array_values($members);
        $count = count($members);
        $negative = 0.0;
        $positive = 0.0;
        $calm = false;
        for ($i = 0; $i < $count; $i++) {
            $calm = $calm || in_array('ruhepol', $members[$i]['personality'], true);
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $members[$i]['personality'];
                $b = $members[$j]['personality'];
                foreach ($a as $x) {
                    foreach ($b as $y) {
                        if (self::clashes($x, $y)) {
                            $negative += Balance::CHEMISTRY_CLASH;
                        }
                        if ($x === 'partytier' && $y === 'partytier') {
                            $positive += Balance::CHEMISTRY_PARTY;
                        }
                    }
                }
                if (in_array('teamplayer', $a, true) || in_array('teamplayer', $b, true)) {
                    $positive += Balance::CHEMISTRY_TEAMPLAYER;
                }
            }
        }
        if ($calm) {
            $negative *= 0.5;
        }
        return round(max(0.5, min(1.5, 1 + $positive - $negative)), 2);
    }

    /** Chemie bestimmt, wie hoch das Zusammenspiel maximal werden kann. */
    public static function maxCohesion(float $chemistry): float
    {
        return max(40.0, min(100.0, 70 + ($chemistry - 1) * 60));
    }

    /** Wie viel ein Musiker vom Publikum abbekommt. */
    public static function fameShare(array $m, ?array $model): float
    {
        $p = Balance::FAME_SHARE;
        $factor = $p['base'];
        if (Levels::has($m, 'ausstrahlung')) {
            $factor += $p['ausstrahlung'];
        }
        if (Levels::has($m, 'charisma')) {
            $factor += $p['charisma'];
        }
        if ($m['unusual']) {
            $factor += $p['unusual'];
        }
        if ($model !== null) {
            $factor += $model['design'] / 100 * $p['design'];
        }
        return $factor;
    }

    /**
     * Jeder verdient eigentlich einzeln Bekanntheit (Eigenschaften, Instrument-Design). Die Summe wird wie in einer
     * Zugewinngemeinschaft zu gleichen Teilen verteilt.
     * @return array<int, float> Musiker-ID => Gewinn
     */
    public static function splitFame(array $members, float $total, array $gear): array
    {
        $count = count($members);
        if ($count === 0) {
            return [];
        }
        $individual = array_map(static fn ($m) => $total / $count * self::fameShare($m, self::gearOf($m, $gear)), $members);
        $share = round(array_sum($individual) / $count, 2);
        $out = [];
        foreach ($members as $m) {
            $out[$m['id']] = $share;
        }
        return $out;
    }

    /** Auftrittsort nach Ruhm, mit Fortschritt (0–1) bis zur nächsten Stufe. */
    public static function venue(float $fame): array
    {
        $venues = Balance::VENUES;
        $index = 0;
        foreach ($venues as $i => $venue) {
            if ($fame >= $venue['minFame']) {
                $index = $i;
            }
        }
        $current = $venues[$index];
        $next = $venues[$index + 1] ?? null;
        $progress = $next === null ? 1.0 : min(1.0, ($fame - $current['minFame']) / ($next['minFame'] - $current['minFame']));
        return $current + ['progress' => $progress, 'next' => $next['name'] ?? null, 'nextFame' => $next['minFame'] ?? null];
    }

    /** Managt einer mehrere Bands, teilt sich seine Leistung auf. */
    public static function managerLevel(?array $manager, int $bandsManaged): float
    {
        if ($manager === null) {
            return 0.0;
        }
        return round($manager['level'] / max(1, $bandsManaged), 2);
    }

    public static function managerBonus(?array $manager, float $level, string $skill, float $perLevel): float
    {
        if ($manager === null || !in_array($skill, $manager['skills'], true)) {
            return 0.0;
        }
        return $level * $perLevel;
    }

    public static function gigSongCap(float $managerLevel): int
    {
        if ($managerLevel <= 0) {
            return Balance::GIG_SONG_CAP;
        }
        return Balance::GIG_SONG_CAP + (int) ceil($managerLevel * Balance::MANAGER_SONGS_PER_LEVEL);
    }

    /** Ein Gig dauert höchstens so viele Lieder, wie die Band produziert hat. */
    public static function maxGigSongs(int $songsRecorded, float $managerLevel): int
    {
        return min($songsRecorded, self::gigSongCap($managerLevel));
    }

    /** Gage pro Lied: Grundgage des Ortes, etwas mehr je näher an der nächsten Stufe, plus Verhandler. */
    public static function gagePerSong(array $venue, float $negotiatorBonus): int
    {
        return (int) round($venue['gage'] * (0.9 + 0.2 * $venue['progress']) * (1 + $negotiatorBonus));
    }

    /** Open Stage und Straßenmusik verlieren mit wachsendem Ruhm ihren Reiz. */
    public static function falloff(float $fame, float $threshold, float $min = 0.0): float
    {
        return max($min, 1 - $fame / $threshold);
    }

    public static function soundFactor(float $sound): float
    {
        return max(0.2, min(3.0, $sound / Balance::SOUND_FAME_REF));
    }

    /** @return list<string> Fehlermeldungen; leer = Besetzung in Ordnung */
    public static function lineupProblems(array $members): array
    {
        $problems = [];
        $count = count($members);
        if ($count < Balance::MIN_MEMBERS) {
            $problems[] = sprintf('Eine Band braucht mindestens %d Musiker.', Balance::MIN_MEMBERS);
        }
        if ($count > Balance::MAX_MEMBERS) {
            $problems[] = sprintf('Eine Band hat höchstens %d Musiker.', Balance::MAX_MEMBERS);
        }
        $perInstrument = array_count_values(array_map([self::class, 'instrument'], $members));
        foreach (Balance::MAX_PER_INSTRUMENT as $instrument => $max) {
            if (($perInstrument[$instrument] ?? 0) > $max) {
                $problems[] = sprintf('Höchstens %d × %s pro Band.', $max, Catalog::INSTRUMENTS[$instrument]['name']);
            }
        }
        return $problems;
    }
}
