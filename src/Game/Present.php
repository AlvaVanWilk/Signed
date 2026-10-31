<?php
declare(strict_types=1);

namespace Signed\Game;

/** Wandelt interne Daten in das JSON-Format für den Browser (camelCase). */
final class Present
{
    /**
     * Abgerundet auf eine Stelle: 34,96 zeigt 34,9 und nicht 35,0 – sonst stünde die Schwelle einer Stufe da,
     * obwohl der Musiker sie noch nicht erreicht hat.
     */
    public static function down(float $value): float
    {
        return floor($value * 10 + 1e-9) / 10;
    }

    public static function musician(array $m, array $gearIds = []): array
    {
        // Können wie angezeigt (im neuen Kern 100 / 400 / 1.600 …, siehe Levels::shown)
        $instruments = [['type' => $m['instr1'], 'skill' => self::down(Levels::shown((float) $m['skill1']))]];
        if ($m['instr2'] !== null) {
            $instruments[] = ['type' => $m['instr2'], 'skill' => self::down(Levels::shown((float) $m['skill2']))];
        }
        return [
            'id' => $m['id'],
            'firstName' => $m['first_name'],
            'lastName' => $m['last_name'],
            'stageName' => $m['stage_name'] ?? null,
            'rarity' => $m['rarity'],
            'instruments' => $instruments,
            'fame' => self::down($m['fame']),
            'genres' => $m['genres'],
            'level' => $m['level'],
            'fameLevel' => Levels::forFame($m['fame']),
            'abilities' => $m['abilities'],
            'abilityChoice' => $m['ability_choice'],
            'favorite' => $m['favorite'],
            // Hat über das Jam-Café schon eine Band gegründet (kann nicht noch einmal hin).
            'jammed' => ($m['jam_at'] ?? 0) > 0,
            // Nur die Züge, die sich schon gezeigt haben; die übrigen erscheinen als „?“.
            'personality' => Traits::known($m),
            'hiddenTraits' => count(Traits::hidden($m)),
            'unusual' => $m['unusual'],
            'look' => (object) $m['look'],
            'bandId' => $m['band_id'],
            'bandSlot' => $m['band_slot'],
            'busyUntil' => $m['busy_until'],
            'gearIds' => $gearIds,
            'value' => Transfers::value($m),
        ];
    }

    public static function gear(array $g): array
    {
        return [
            'id' => $g['id'], 'model' => $g['model'], 'musicianId' => $g['musician_id'],
            'tier' => Gear::modelTier($g['model']),
            'bonuses' => $g['bonuses'] ?? [],
            'sellPrice' => Bands::sellPrice($g),
        ];
    }

    public static function manager(array $m, array $bandIds = []): array
    {
        return [
            'id' => $m['id'],
            'firstName' => $m['first_name'],
            'lastName' => $m['last_name'],
            'rarity' => $m['rarity'],
            'level' => $m['level'],
            'skills' => $m['skills'],
            'look' => (object) $m['look'],
            'genres' => $m['genres'] ?? [],
            'style' => $m['style'] ?? null,
            'bandIds' => $bandIds,
        ];
    }

    public static function record(?array $r): ?array
    {
        if ($r === null) {
            return null;
        }
        return [
            'id' => $r['id'],
            'title' => $r['title'],
            'songsTotal' => $r['songs_total'],
            'songsDone' => $r['songs_done'],
            'studio' => $r['studio'] ?? null,
            'producer' => $r['producer'] ?? null,
            'cover' => $r['cover']['spec'] ?? null,
        ];
    }

    public static function activity(array $row): array
    {
        $data = json_decode($row['data'], true) ?: [];
        return [
            'id' => (int) $row['id'],
            'type' => $row['type'],
            'title' => $data['title'] ?? '',
            'bandId' => $row['band_id'] === null ? null : (int) $row['band_id'],
            'musicianId' => $row['musician_id'] === null ? null : (int) $row['musician_id'],
            'startedAt' => (int) $row['started_at'],
            'endsAt' => (int) $row['ends_at'],
            'songs' => $data['songs'] ?? 0,
            'timeline' => $data['timeline'] ?? [],
            // Ort des Auftritts (Kneipe … Arena): bestimmt, wie die Bühne aussieht.
            'venue' => $data['reward']['venue'] ?? null,
        ] + (isset($data['recordId']) ? ['record' => ['studio' => (int) $data['studio'], 'producer' => (string) $data['producer'], 'quality' => (float) ($data['quality'] ?? 0)]] : []) + (isset($data['reward']['rehearsal']) ? ['rehearsal' => [
            'done' => (int) $data['reward']['rehearsal']['done'],
            'credited' => (object) $data['reward']['rehearsal']['credited'],
            'limit' => $data['reward']['rehearsal']['limit'],
        ]] : []);
    }

    public static function result(array $row): array
    {
        $data = json_decode($row['data'], true) ?: [];
        return [
            'id' => (int) $row['id'],
            'type' => $row['type'],
            'title' => $data['title'] ?? '',
            'bandId' => $row['band_id'] === null ? null : (int) $row['band_id'],
            'musicianId' => $row['musician_id'] === null ? null : (int) $row['musician_id'],
            'result' => json_decode((string) $row['result'], true) ?: (object) [],
        ];
    }

    /** Statische Daten, die der Browser zum Anzeigen braucht. */
    public static function catalog(): array
    {
        $packs = [];
        foreach (Balance::PACKS as $key => $pack) {
            $packs[$key] = [
                'name' => $pack['name'], 'price' => $pack['price'], 'desc' => $pack['desc'],
                'choices' => count($pack['slots'] ?? []) ?: $pack['choices'], 'kinds' => $pack['slots'] ?? array_keys($pack['kinds']),
                'odds' => $pack['odds'], 'chooseType' => !empty($pack['chooseType']), 'tip' => !empty($pack['tip']),
            ];
        }
        return [
            'rarities' => Catalog::RARITIES,
            'genres' => Catalog::GENRES,
            // Tournee: Länder mit Lieblings-Genre und Lage, dazu der Heimatort
            'countries' => array_map(static fn ($c) => $c + ['city' => $c['cities'][0]], Catalog::COUNTRIES),
            // Verkaufte Stück für Gold, Platin, Diamant
            // Neuer Kern: Gold, Platin und Diamant nach den neuen Grenzen
            'awards' => BandCore::$live ? ['records' => Balance::CORE_AWARDS] + Balance::AWARDS : Balance::AWARDS,
            'home' => Catalog::HOME,
            'tour' => [
                'regions' => Balance::TOUR['regions'], 'wait' => Balance::TOUR['wait'], 'match' => Balance::TOUR['match'], 'abroad' => Balance::TOUR['abroad'],
                'cost' => Balance::TOUR['cost'], 'travel' => Balance::TOUR['travel'], 'stadium' => Balance::TOUR['stadium'],
            ],
            'vehicles' => Balance::VEHICLES,
            'instruments' => array_map(
                static fn ($i) => ['name' => $i['name'], 'at' => $i['at'], 'icon' => $i['icon'], 'genres' => $i['genres'], 'techniques' => $i['techniques']],
                Catalog::INSTRUMENTS
            ),
            // Spieltechniken: Beschreibung mit ihren beiden Genres
            'abilities' => array_map(static fn ($a) => empty($a['technique']) ? $a : $a + [
                'desc' => sprintf(Catalog::TECHNIQUE_DESC, Catalog::GENRES[$a['genres'][0]]['name'], Catalog::GENRES[$a['genres'][1]]['name'], (int) round(Balance::TECHNIQUE_SOUND * 100)),
            ], Catalog::ABILITIES),
            'techniqueDesc' => 'Spezialtechnik.',
            'levelNames' => Catalog::LEVEL_NAMES,
            'levelWords' => Catalog::LEVEL_WORDS,
            'genreLineup' => Catalog::GENRE_LINEUP,
            // Neuer Kern: Ruhmstufen nach Fans (die Grenzen entsprechen sich, siehe BandCore::fameEquivalent)
            'fameNames' => BandCore::$live ? Balance::CORE_STAGE_NAMES : Catalog::FAME_NAMES,
            // Neuer Kern: Arten von Anfragen (Interview, Fotoserie, Artikel, Reportage, Werbung)
            'requests' => Catalog::REQUESTS,
            'personalities' => Catalog::PERSONALITIES,
            'staffRoles' => Catalog::STAFF_ROLES,
            'logoFonts' => Catalog::LOGO_FONTS,
            'logoColors' => Catalog::LOGO_COLORS,
            'coverWords' => Catalog::COVER_WORDS,
            'personalityInfo' => Catalog::PERSONALITY_INFO,
            'managerSkills' => BandCore::$live ? array_replace_recursive(Catalog::MANAGER_SKILLS, Catalog::MANAGER_SKILLS_CORE) : Catalog::MANAGER_SKILLS,
            'gear' => Catalog::GEAR,
            'gearBonusInfo' => Catalog::GEAR_BONUS_INFO,
            'music' => Music::manifest(),
            'musicParts' => Music::parts(),
            'musicStems' => Music::STEMS,
            'packs' => $packs,
            'rules' => [
                'buskingHat' => Balance::BUSKING_HAT,
                'startMoney' => Balance::START_MONEY,
                'songSeconds' => Balance::SONG_SECONDS,
                'minMembers' => Balance::MIN_MEMBERS,
                'maxMembers' => Balance::MAX_MEMBERS,
                'maxPerInstrument' => Balance::MAX_PER_INSTRUMENT,
                'managerSolos' => Balance::MANAGER_STAFF['solos'],
                'studios' => Balance::STUDIOS,
                'producers' => array_map(static fn ($p) => ['name' => $p['name'], 'fee' => $p['fee'], 'unlocks' => $p['unlocks'], 'minSound' => $p['minSound'] ?? 0], Balance::PRODUCERS),
                'recordSecondsPerSong' => Balance::RECORD_SECONDS_PER_SONG,
                'coverRedraw' => Balance::COVER_REDRAW,
                'coverDesigners' => Balance::COVER_DESIGNERS,
                'coverLogoSales' => Balance::COVER_LOGO_SALES,
                'noGearFactor' => Balance::NO_GEAR_FACTOR,
                'negotiatorBonus' => Balance::NEGOTIATOR_BONUS,
                // Neuer Kern: höchstens so viele Fans je Art der Band (gemischt, Genre, hybrid, Crossover); Fans beim Zusammentun und Umbenennen
                'coreRoof' => Balance::CORE_ROOF,
                'coreFanMoves' => ['mergeSmall' => Balance::CORE_FAN_MOVES['mergeSmall'], 'rename' => Balance::CORE_FAN_MOVES['rename']],
                'gigFreshMin' => Balance::GIG_FRESH['min'],
                'prBonus' => Balance::PR_BONUS,
                'managerNetwork' => Balance::MANAGER_STAFF['network'],
                'scoutShift' => Balance::SCOUT_SHIFT,
                'managerSongsPerLevel' => Balance::MANAGER_SONGS_PER_LEVEL,
                'soundMeanWeight' => Balance::SOUND_MEAN_WEIGHT,
                'lineupBonus' => Balance::LINEUP_BONUS,
                'lineupBonusMaxTypes' => Balance::LINEUP_BONUS_MAX_TYPES,
                'memberSoundBonus' => Balance::MEMBER_SOUND_BONUS,
                'mixedBonus' => Balance::MIXED_BONUS,
                'nameComboKeep' => Balance::NAME_COMBO_KEEP,
                'crossoverBonus' => Balance::CROSSOVER_BONUS,
                'cohesionByGenre' => Balance::COHESION_BY_GENRE,
                'memberGageBonus' => Balance::MEMBER_GAGE_BONUS,
                'minMembers' => Balance::MIN_MEMBERS,
                'cohesionFloor' => Balance::COHESION_FLOOR,
                'recordMinSongs' => Balance::RECORD_MIN_SONGS,
                'recordMaxSongs' => Balance::RECORD_MAX_SONGS,
                'gigMinSongs' => Balance::GIG_MIN_SONGS,
                'genreBonus' => Balance::GENRE_BONUS,
                'soloGenres' => Balance::SOLO_GENRES,
                'soloGenreShare' => Balance::SOLO_GENRE_SHARE,
                'hybridBonus' => Balance::HYBRID_BONUS,
                'promoSeconds' => Balance::PROMO_SECONDS,
                'rehearsalSeconds' => Balance::REHEARSAL['seconds'],
                'submissionListen' => Balance::SUBMISSIONS['listen'],
                'postUnpack' => Balance::POST['unpack'],
                'postRefresh' => Balance::POST['refresh'],
                'assistSoon' => Balance::ASSISTANT['soon'],
                'reactStep' => Balance::MOOD['reactStep'],
                'moodGreen' => Balance::MOOD['green'],
                'moodYellow' => Balance::MOOD['yellow'],
                'autoPracticeSeconds' => Balance::AUTO_PRACTICE['seconds'],
                'cohesionRemovePenalty' => Balance::COHESION_REMOVE_PENALTY,
                'cohesionSwitchPenalty' => Balance::COHESION_SWITCH_PENALTY,
                'scoutPrice' => Balance::SCOUT_PRICE,
                'skillLevels' => Levels::skillLevels(),
                'fameLevels' => Balance::FAME_LEVELS,
                'bandPromoSeconds' => Balance::BAND_PROMO['seconds'],
                'retreat' => Balance::RETREAT,
                'scoutCards' => Balance::SCOUT_CARDS,
                'chartLpSongs' => Balance::CHART_LP_SONGS,
                'chartSize' => Balance::CHART_SIZE,
                'chartDecay' => Balance::CHART_DECAY,
                'chartIntervalHours' => Balance::CHART_INTERVAL / 3600,
                'chartBoostHalfHours' => Balance::CHART_BOOST_HALF / 3600,
                'chartBoostMax' => Balance::CHART_BOOST_MAX,
                'chartGigBoost' => Balance::CHART_GIG_BOOST,
                'chartPromoBoost' => Balance::CHART_PROMO_BOOST['band'],
                'chartMaxAgeDays' => Balance::CHART_MAX_AGE / 86400,
                'chartPerfect' => Balance::CHART_PERFECT,
                'chartList' => Balance::CHART_LIST,
                'chartTopMinLabels' => Balance::CHART_TOP_MIN_LABELS,
            ],
        ];
    }
}
