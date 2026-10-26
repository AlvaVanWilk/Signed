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
        $instruments = [['type' => $m['instr1'], 'skill' => self::down($m['skill1'])]];
        if ($m['instr2'] !== null) {
            $instruments[] = ['type' => $m['instr2'], 'skill' => self::down((float) $m['skill2'])];
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
            'fameNames' => Catalog::FAME_NAMES,
            'personalities' => Catalog::PERSONALITIES,
            'staffRoles' => Catalog::STAFF_ROLES,
            'logoFonts' => Catalog::LOGO_FONTS,
            'logoColors' => Catalog::LOGO_COLORS,
            'coverWords' => Catalog::COVER_WORDS,
            'personalityInfo' => Catalog::PERSONALITY_INFO,
            'managerSkills' => Catalog::MANAGER_SKILLS,
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
                'managerMaxBands' => Balance::MANAGER_MAX_BANDS,
                'studios' => Balance::STUDIOS,
                'producers' => array_map(static fn ($p) => ['name' => $p['name'], 'fee' => $p['fee'], 'unlocks' => $p['unlocks'], 'minSound' => $p['minSound'] ?? 0], Balance::PRODUCERS),
                'recordSecondsPerSong' => Balance::RECORD_SECONDS_PER_SONG,
                'coverRedraw' => Balance::COVER_REDRAW,
                'coverDesigners' => Balance::COVER_DESIGNERS,
                'coverLogoSales' => Balance::COVER_LOGO_SALES,
                'noGearFactor' => Balance::NO_GEAR_FACTOR,
                'negotiatorBonus' => Balance::NEGOTIATOR_BONUS,
                'gigFreshMin' => Balance::GIG_FRESH['min'],
                'prBonus' => Balance::PR_BONUS,
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
                'assistantPerDay' => Balance::ASSISTANT['tiers'][1]['perDay'],
                'reactStep' => Balance::MOOD['reactStep'],
                'moodGreen' => Balance::MOOD['green'],
                'moodYellow' => Balance::MOOD['yellow'],
                'autoPracticeSeconds' => Balance::AUTO_PRACTICE['seconds'],
                'cohesionRemovePenalty' => Balance::COHESION_REMOVE_PENALTY,
                'cohesionSwitchPenalty' => Balance::COHESION_SWITCH_PENALTY,
                'scoutPrice' => Balance::SCOUT_PRICE,
                'skillLevels' => Balance::SKILL_LEVELS,
                'fameLevels' => Balance::FAME_LEVELS,
                'bandPromoSeconds' => Balance::BAND_PROMO['seconds'],
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
