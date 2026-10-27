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

    /** Gespieltes Instrument; ist es zu gut für sein Können (alte Zuteilung), zählt es wie eines ohne Boni. */
    public static function gearOf(array $m, array $gear): ?array
    {
        $model = $gear[$m['id']][self::instrument($m)] ?? null;
        if ($model !== null && isset($model['key'], $m['skill1']) && !Gear::playable($m, $model['key'])) {
            $model['bonuses'] = [];
        }
        return $model;
    }

    /** Beitrag eines Musikers: Können × Instrument (Symbiose). */
    public static function memberValue(array $m, ?array $model): float
    {
        $skill = self::skill($m);
        $gear = $model === null ? Balance::NO_GEAR_FACTOR : 1 + self::gearBonus($model, $skill);
        return $skill * $gear;
    }

    /** Klang-Bonus des Instruments (blau): ab der Klasse episch, wer es spielen darf, bekommt ihn ganz. */
    public static function gearBonus(?array $model, float $skill): float
    {
        return Gear::value($model, 'sound');
    }

    /** @param float $bonus Genre-Bonus der Band (1 = keine Genre-Einheit), siehe identity() */
    public static function sound(array $members, float $cohesion, array $gear, float $bonus = 1.0): float
    {
        if (!$members) {
            return 0.0;
        }
        $values = array_map(static fn ($m) => self::memberValue($m, self::gearOf($m, $gear)), $members);
        $mean = array_sum($values) / count($values);
        $core = Balance::SOUND_MEAN_WEIGHT * $mean + (1 - Balance::SOUND_MEAN_WEIGHT) * min($values);
        $types = count(array_unique(array_map([self::class, 'instrument'], $members)));
        $lineup = 1 + Balance::LINEUP_BONUS * min(Balance::LINEUP_BONUS_MAX_TYPES, $types - 1);
        $cohesionFactor = Balance::COHESION_FLOOR + (1 - Balance::COHESION_FLOOR) * $cohesion / 100;
        $stage = self::sizeBonus(count($members), Balance::MEMBER_SOUND_BONUS);
        return round($core * $lineup * $stage * $cohesionFactor * $bonus, 1);
    }

    /** Volle Bühne: 1 + $perMember für jedes Mitglied über SIZE_BONUS_FROM. */
    public static function sizeBonus(int $members, float $perMember): float
    {
        return 1 + $perMember * max(0, $members - Balance::SIZE_BONUS_FROM);
    }

    /** Sound einer Band samt Genre-Bonus und Spieltechniken, die zum Genre passen. */
    public static function bandSound(array $band, array $members, array $gear): float
    {
        $identity = self::identity($members, $band['genre'], $band['genre2']);
        $genres = array_values(array_filter([$identity['primary'] ?? null, $identity['second'] ?? null, $identity['third'] ?? null]));
        return self::sound($members, $band['cohesion'], $gear, $identity['bonus'] * self::techniqueBonus($members, $genres));
    }

    /**
     * Spieltechniken wirken nur im passenden Genre: je Technik, die zu einem Genre der Band passt, +TECHNIQUE_SOUND (höchstens zwei je Musiker).
     * Eine Band ohne Genre (gemischt) hat nichts davon; ein Solokünstler zählt mit seinen eigenen Genres.
     */
    public static function techniqueBonus(array $members, array $genres): float
    {
        if (count($members) === 1 && !$genres) {
            $genres = $members[0]['genres'];
        }
        $n = 0;
        foreach ($members as $m) {
            $fit = array_filter(Levels::techniques($m), static fn ($t) => array_intersect(Catalog::ABILITIES[$t]['genres'], $genres));
            $n += min(2, count($fit));
        }
        return 1 + Balance::TECHNIQUE_SOUND * $n;
    }

    /**
     * Genre einer Band ergibt sich aus der Besetzung: Teilen alle Mitglieder ein Genre und passt die Besetzung dazu,
     * ist es eine Genre-Band (z. B. Rockband). Gilt beides auch für ein zweites Genre, wird sie hybrid (Punk-Rock-Band),
     * bei einem dritten sogar Crossover (Rock-Crossover-Band; das dritte ergibt sich aus der festen Genre-Reihenfolge).
     * $prefer/$preferSecond halten die bisherige Ausrichtung, wenn mehrere Genres in Frage kommen.
     * @return array{primary: ?string, second: ?string, third: ?string, title: ?string, bonus: float, music: string, checks: array<string, array>}
     */
    public static function identity(array $members, string $prefer = '', ?string $preferSecond = null): array
    {
        $types = array_count_values(array_map([self::class, 'instrument'], $members));
        $count = count($members);
        $checks = [];
        foreach (Catalog::GENRE_LINEUP as $genre => $needs) {
            $fans = count(array_filter($members, static fn ($m) => in_array($genre, $m['genres'], true)));
            $requirements = [];
            foreach ($needs as [$options, $need]) {
                $have = array_sum(array_map(static fn ($t) => $types[$t] ?? 0, $options));
                $requirements[] = ['instruments' => $options, 'need' => $need, 'have' => $have, 'met' => $have >= $need];
            }
            $lineup = !in_array(false, array_column($requirements, 'met'), true);
            $checks[$genre] = [
                'fans' => $fans,
                'allFans' => $count >= Balance::GENRE_MIN_MEMBERS && $fans === $count,
                'requirements' => $requirements,
                'lineup' => $lineup,
            ];
        }
        $eligible = array_keys(array_filter($checks, static fn ($c) => $c['allFans'] && $c['lineup']));
        // Solokünstler: nur Pop oder Elektro, und nur ein Genre (kein Hybrid allein).
        if ($count === 1) {
            $eligible = array_values(array_filter(Balance::SOLO_GENRES, static fn ($g) => $checks[$g]['fans'] === 1 && $checks[$g]['lineup']));
        }
        $primary = in_array($prefer, $eligible, true) ? $prefer : ($eligible[0] ?? null);
        $second = null;
        $third = null;
        if ($primary !== null && $count > 1) {
            // Auch das zweite und dritte Genre brauchen ihre Besetzung (Punk-Rock ohne Bass gibt es nicht).
            $shared = array_values(array_filter($eligible, static fn ($g) => $g !== $primary));
            $second = in_array($preferSecond, $shared, true) ? $preferSecond : ($shared[0] ?? null);
            $third = array_values(array_diff($shared, [$second]))[0] ?? null;
        }
        // Musik ohne Genre-Einheit: das Genre, das die meisten mögen.
        $music = $primary ?? array_keys($checks)[0];
        foreach ($checks as $genre => $check) {
            if ($primary === null && $check['fans'] > $checks[$music]['fans']) {
                $music = $genre;
            }
        }
        return [
            'primary' => $primary,
            'second' => $second,
            'third' => $third,
            'title' => $primary === null ? null : ($count === 1 ? Catalog::GENRES[$primary]['name'] . ' solo' : self::title($primary, $second, $third)),
            'bonus' => match (true) {
                // Ein Solokünstler hat nie den Malus einer „gemischten“ Band; Bonus nur solo in Pop oder Elektro, und nur zum Teil.
                $count < Balance::GENRE_MIN_MEMBERS => $primary === null ? 1.0 : 1 + (Balance::GENRE_BONUS - 1) * Balance::SOLO_GENRE_SHARE,
                // Teilen alle ein Genre, fehlt aber noch die Besetzung dafür, gibt es keinen Bonus – aber auch keinen Abzug.
                $primary === null => array_filter($checks, static fn ($c) => $c['allFans']) ? 1.0 : Balance::MIXED_BONUS,
                $second === null => Balance::GENRE_BONUS,
                $third === null => Balance::HYBRID_BONUS,
                default => Balance::CROSSOVER_BONUS,
            },
            'music' => $music,
            'checks' => $checks,
        ];
    }

    /** „Rockband“, „Hip-Hop-Band“, „Punk-Rock-Band“, „Rock-Crossover-Band“. */
    public static function title(string $primary, ?string $second, ?string $third = null): string
    {
        $name = Catalog::GENRES[$primary]['name'];
        if ($third !== null) {
            return $name . '-Crossover-Band';
        }
        if ($second !== null) {
            return Catalog::GENRES[$second]['name'] . '-' . $name . '-Band';
        }
        return str_contains($name, '-') ? $name . '-Band' : $name . 'band';
    }

    /**
     * Können-Zuwachs: Talent und ein Lehrmeister-Instrument beschleunigen, je besser jemand schon ist,
     * desto langsamer geht es.
     */
    /** Können-Zuwachs einer Solo-Probe bei dieser Trefferquote (0–1). Auf Stufe 0 doppelt (PRACTICE_BEGINNER_BOOST). */
    public static function practiceGain(array $m, int $slot, float $accuracy, ?array $model): float
    {
        $boost = Levels::forSkill(self::skill($m, $slot)) === 0 ? Balance::PRACTICE_BEGINNER_BOOST : 1.0;
        return self::trainingGain($m, Balance::PRACTICE_GAIN * $boost * $accuracy ** 1.5, $slot, $model);
    }

    /** Können-Zuwachs einer Bandprobe für ein Mitglied (sein Band-Instrument). */
    public static function rehearsalGain(array $m, ?array $model): float
    {
        // Ohne Instrument steht er nur dabei und lernt nichts.
        if ($model === null) {
            return 0.0;
        }
        $trait = 1.0;
        foreach ($m['personality'] ?? [] as $p) {
            $trait *= Balance::TRAITS['rehearsalGain'][$p] ?? 1.0;
        }
        return self::trainingGain($m, Balance::REHEARSAL['skill'] * $trait, null, $model);
    }

    /** Können-Zuwachs: ohne Stufen-Bremse, es bremst nur die Lernkurve (wer schon gut ist, lernt langsamer). */
    public static function trainingGain(array $m, float $base, ?int $slot = null, ?array $model = null): float
    {
        $talent = 1 + Gear::value($model, 'learn');
        return $base * $talent * max(0.003, max(0.0, 1 - self::skill($m, $slot) / 110) ** Balance::TRAINING_CURVE);
    }

    /** Summe der Bekanntheit aller aktuellen Mitglieder. */
    public static function fame(array $members): float
    {
        return round(array_sum(array_map(static fn ($m) => $m['fame'], $members)), 1);
    }

    /** Bandruhm = Ruhm des Bandnamens + Bekanntheit der aktuellen Mitglieder. */
    public static function bandFame(array $band, array $members): float
    {
        return round($band['fame_own'] + array_sum(array_column($members, 'fame')), 1);
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

    /**
     * Zusammenspiel je nach Genre-Linie (COHESION_BY_GENRE): Höchstwert und Wachstum. Die Chemie bremst zusätzlich,
     * wie schnell es wächst (× Chemie), begrenzt aber nicht den Höchstwert.
     * @param array $identity siehe identity()
     * @return array{max: float, growth: float}
     */
    public static function cohesionRule(array $identity): array
    {
        // Umbau: Der Zusammenhalt ergibt sich aus den Beziehungen (Mood::cohesionValue), nicht mehr aus Genre-Linie und Üben.
        return ['max' => 100.0, 'growth' => 1.0];
    }

    public static function maxCohesion(array $identity): float
    {
        return self::cohesionRule($identity)['max'];
    }

    /**
     * Warum die Chemie so ist, in kurzen Sätzen: wer sich reibt, wer hilft.
     * @return list<array{good: bool, text: string}>
     */
    public static function chemistryNotes(array $members): array
    {
        $members = array_values($members);
        $name = static fn ($m, $trait) => sprintf('%s (%s)', $m['first_name'], Catalog::PERSONALITIES[$trait]);
        $notes = [];
        $calm = [];
        for ($i = 0; $i < count($members); $i++) {
            if (in_array('ruhepol', $members[$i]['personality'], true)) {
                $calm[] = $members[$i]['first_name'];
            }
            if (in_array('teamplayer', $members[$i]['personality'], true)) {
                $notes[] = ['good' => true, 'ids' => [$members[$i]['id']], 'text' => sprintf('%s ist kooperativ: hilft allen', $members[$i]['first_name'])];
            }
            for ($j = $i + 1; $j < count($members); $j++) {
                foreach ($members[$i]['personality'] as $x) {
                    foreach ($members[$j]['personality'] as $y) {
                        if (self::clashes($x, $y)) {
                            $notes[] = ['good' => false, 'ids' => [$members[$i]['id'], $members[$j]['id']], 'text' => sprintf('%s und %s reiben sich', $name($members[$i], $x), $name($members[$j], $y))];
                        }
                        if ($x === 'partytier' && $y === 'partytier') {
                            $notes[] = ['good' => true, 'ids' => [$members[$i]['id'], $members[$j]['id']], 'text' => sprintf('%s und %s feiern zusammen', $members[$i]['first_name'], $members[$j]['first_name'])];
                        }
                    }
                }
            }
        }
        if ($calm && array_filter($notes, static fn ($n) => !$n['good'])) {
            $notes[] = ['good' => true, 'ids' => [], 'text' => sprintf('%s %s ausgeglichen: halbiert die Reibung', implode(' und ', $calm), count($calm) > 1 ? 'sind' : 'ist')];
        }
        return $notes;
    }

    /** Wie viel ein Musiker vom Publikum abbekommt ($stage: bei Auftritten, dann zählen auch Extrovertierte und Gesellige mehr). */
    public static function fameShare(array $m, ?array $model, bool $stage = false): float
    {
        $p = Balance::FAME_SHARE;
        $factor = $p['base'] + ($stage ? Mood::traitMax($m, Balance::TRAITS['stageFame'], 0.0) : 0.0);
        if ($m['unusual']) {
            $factor += $p['unusual'];
        }
        if ($stage) {
            $factor += Gear::value($model, 'magnet');
        }
        return $factor;
    }

    /**
     * Jeder verdient eigentlich einzeln Bekanntheit (Eigenschaften, Instrument-Design). Die Summe wird wie in einer
     * Zugewinngemeinschaft zu gleichen Teilen verteilt.
     * @return array<int, float> Musiker-ID => Gewinn
     */
    public static function splitFame(array $members, float $total, array $gear, bool $stage = false): array
    {
        $count = count($members);
        if ($count === 0) {
            return [];
        }
        $individual = array_map(static fn ($m) => $total / $count * self::fameShare($m, self::gearOf($m, $gear), $stage), $members);
        $share = round(array_sum($individual) / $count, 3);
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

    /** Label-Ruhm aus dem Ruhm der Bands (Balance::LABEL_FAME). */
    public static function labelFame(array $fames): float
    {
        $p = Balance::LABEL_FAME['power'];
        return round(array_sum(array_map(static fn ($f) => max(0.0, (float) $f) ** $p, $fames)) ** (1 / $p), 1);
    }

    /** Stufe des Managers (er betreut nur so viele, wie er schafft: eine Band oder einige Solokünstler, siehe Balance::MANAGER_STAFF). */
    public static function managerLevel(?array $manager): float
    {
        return $manager === null ? 0.0 : (float) $manager['level'];
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
    public static function gagePerSong(array $venue, float $negotiatorBonus, int $members = Balance::SIZE_BONUS_FROM): int
    {
        return (int) round($venue['gage'] * (0.9 + 0.2 * $venue['progress']) * (1 + $negotiatorBonus)
            * self::sizeBonus($members, Balance::MEMBER_GAGE_BONUS));
    }

    /** Gigs seit der letzten Platte: Mit jedem kennt das Publikum die Lieder besser, Gage und Ruhm sinken. */
    public static function freshness(int $gigsSinceRecord): float
    {
        return max(Balance::GIG_FRESH['min'], 1 - Balance::GIG_FRESH['decay'] * $gigsSinceRecord);
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
