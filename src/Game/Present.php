<?php
declare(strict_types=1);

namespace Signed\Game;

/** Wandelt interne Daten in das JSON-Format für den Browser (camelCase). */
final class Present
{
    public static function musician(array $m, array $gearIds = []): array
    {
        $instruments = [['type' => $m['instr1'], 'skill' => round($m['skill1'], 1)]];
        if ($m['instr2'] !== null) {
            $instruments[] = ['type' => $m['instr2'], 'skill' => round((float) $m['skill2'], 1)];
        }
        return [
            'id' => $m['id'],
            'firstName' => $m['first_name'],
            'lastName' => $m['last_name'],
            'rarity' => $m['rarity'],
            'instruments' => $instruments,
            'fame' => round($m['fame'], 1),
            'genres' => $m['genres'],
            'level' => $m['level'],
            'fameLevel' => Levels::forFame($m['fame']),
            'abilities' => $m['abilities'],
            'abilityChoice' => $m['ability_choice'],
            'favorite' => $m['favorite'],
            'personality' => $m['personality'],
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
            'klang' => $g['klang'], 'spielbarkeit' => $g['spielbarkeit'], 'design' => $g['design'],
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
            'songResults' => $r['song_results'],
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
        ];
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
                static fn ($i) => ['name' => $i['name'], 'icon' => $i['icon'], 'genres' => $i['genres'], 'techniques' => $i['techniques']],
                Catalog::INSTRUMENTS
            ),
            'abilities' => Catalog::ABILITIES,
            'techniqueDesc' => Catalog::TECHNIQUE_DESC,
            'levelNames' => Catalog::LEVEL_NAMES,
            'genreLineup' => Catalog::GENRE_LINEUP,
            'fameNames' => Catalog::FAME_NAMES,
            'personalities' => Catalog::PERSONALITIES,
            'managerSkills' => Catalog::MANAGER_SKILLS,
            'gear' => Catalog::GEAR,
            'music' => Music::manifest(),
            'musicStems' => Music::STEMS,
            'packs' => $packs,
            'rules' => [
                'startMoney' => Balance::START_MONEY,
                'songSeconds' => Balance::SONG_SECONDS,
                'minMembers' => Balance::MIN_MEMBERS,
                'maxMembers' => Balance::MAX_MEMBERS,
                'maxPerInstrument' => Balance::MAX_PER_INSTRUMENT,
                'managerMaxBands' => Balance::MANAGER_MAX_BANDS,
                'recordCost' => Balance::RECORD_COST,
                'recordMinSongs' => Balance::RECORD_MIN_SONGS,
                'recordMaxSongs' => Balance::RECORD_MAX_SONGS,
                'gigMinSongs' => Balance::GIG_MIN_SONGS,
                'genreBonus' => Balance::GENRE_BONUS,
                'hybridBonus' => Balance::HYBRID_BONUS,
                'bandSlots' => Balance::BAND_SLOTS,
                'promoSeconds' => Balance::PROMO_SECONDS,
                'cohesionRemovePenalty' => Balance::COHESION_REMOVE_PENALTY,
                'scoutPrice' => Balance::SCOUT_PRICE,
                'skillLevels' => Balance::SKILL_LEVELS,
                'fameLevels' => Balance::FAME_LEVELS,
                'bandPromoSeconds' => Balance::BAND_PROMO['seconds'],
                'scoutCards' => Balance::SCOUT_CARDS,
                'chartLpSongs' => Balance::CHART_LP_SONGS,
                'chartSize' => Balance::CHART_SIZE,
            ],
        ];
    }
}
