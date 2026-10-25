<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Clock;

/** Der komplette Spielstand eines Labels für den Browser. */
final class State
{
    public static function build(Repo $repo): array
    {
        $db = $repo->db;
        $now = Clock::now();
        Mood::settle($repo, $now);
        (new Submissions($repo))->topUp($now);
        Bands::ensureSoloBands($repo);
        $label = $repo->label();
        $musicians = $repo->musicians();
        $gear = $repo->gear();
        $gearMap = $repo->gearByMusician($gear);
        $managers = $repo->managers();
        $bands = $repo->bands();

        $gearIds = [];
        foreach ($gear as $item) {
            if ($item['musician_id'] !== null) {
                $gearIds[$item['musician_id']][] = $item['id'];
            }
        }
        $members = [];
        foreach ($musicians as $m) {
            if ($m['band_id'] !== null) {
                $members[$m['band_id']][] = $m;
            }
        }
        // Proberaum: Faktor auf das Können je Probe, je Band
        $roomSkill = [];
        foreach ($bands as $band) {
            $roomSkill[$band['id']] = Rooms::rules(Rooms::level($repo, $band, count($members[$band['id']] ?? [])))['skill'];
        }
        $managerBands = [];
        foreach ($bands as $band) {
            if ($band['manager_id'] !== null) {
                $managerBands[$band['manager_id']][] = $band['id'];
            }
        }
        $openRecords = [];
        foreach ($db->all("SELECT * FROM records WHERE label_id = ? AND status = 'open'", [$repo->labelId]) as $row) {
            $record = Repo::hydrateRecord($row);
            $openRecords[$record['band_id']] = $record;
        }

        $albums = Charts::albums($db, $repo->labelId);
        $chartEntry = array_column($albums, null, 'recordId');
        $discography = [];
        $promoted = [];
        foreach ($db->all("SELECT id FROM bands WHERE label_id = ? AND retired_at IS NULL", [$repo->labelId]) as $b) {
            $promoted[(int) $b['id']] = (int) (Charts::promoted($db, (int) $b['id'])['id'] ?? 0);
        }
        foreach ($db->all("SELECT * FROM records WHERE label_id = ? AND status = 'done' ORDER BY finished_at DESC", [$repo->labelId]) as $row) {
            $results = json_decode($row['song_results'], true) ?: [];
            $entry = $chartEntry[(int) $row['id']] ?? null;
            $discography[(int) $row['band_id']][] = [
                'id' => (int) $row['id'],
                'promotable' => (float) $row['chart_points'] > 0 && (int) $row['finished_at'] >= Clock::now() - Balance::CHART_MAX_AGE,
                'promoted' => ($promoted[(int) $row['band_id']] ?? 0) === (int) $row['id'],
                'title' => $row['title'],
                'cover' => (json_decode((string) ($row['cover'] ?? ''), true) ?: [])['spec'] ?? null,
                'songs' => (int) $row['songs_total'],
                'format' => (int) $row['songs_total'] >= Balance::CHART_LP_SONGS ? 'LP' : 'EP',
                'quality' => $results ? round(array_sum(array_column($results, 'accuracy')) / count($results), 3) : 0,
                'perfect' => (bool) $row['perfect'],
                'charting' => $entry !== null,
                'peak' => round((float) $row['chart_points'], 1),
                'topped' => (bool) $row['topped'],
                'points' => $entry['points'] ?? 0,
                'rank' => $entry['rank'] ?? null,
                'prev' => $entry['prev'] ?? null,
                'trend' => $entry['trend'] ?? null,
                'best' => $entry['best'] ?? ($row['rank_best'] === null ? null : (int) $row['rank_best']),
                'finishedAt' => (int) $row['finished_at'],
            ];
        }

        $bandsOut = [];
        $bandRelations = [];
        foreach ($bands as $band) {
            $bandRelations[$band['id']] = Mood::relations($repo, $members[$band['id']] ?? [], $now);
            $list = $members[$band['id']] ?? [];
            $identity = (new Bands($repo))->syncIdentity($band, $list);
            $band['cohesion'] = min($band['cohesion'], BandCalc::maxCohesion($identity));
            $identityGenres = array_values(array_filter([$identity['primary'] ?? null, $identity['second'] ?? null, $identity['third'] ?? null]));
            $sound = BandCalc::sound($list, $band['cohesion'], $gearMap, $identity['bonus'] * BandCalc::techniqueBonus($list, $identityGenres));
            if (abs($sound - $band['sound']) >= 0.05) {
                // Zwischenspeicher für die Charts (Kritikerwertung).
                $db->update('bands', ['sound' => $sound], ['id' => $band['id']]);
            }
            $fame = BandCalc::bandFame($band, $list);
            $chemistry = BandCalc::chemistry($list);
            $manager = $band['manager_id'] !== null ? ($managers[$band['manager_id']] ?? null) : null;
            $level = BandCalc::managerLevel($manager, count($managerBands[$band['manager_id']] ?? []));
            $venue = BandCalc::venue($fame);
            $open = $openRecords[$band['id']] ?? null;
            $draw = BandCalc::freshness($band['gigs_since_record']);
            // Vorschau für Instrumentwechsel: Sound danach, null = Besetzung geht so nicht.
            $slotPreview = [];
            foreach ($list as $i => $m) {
                if ($m['instr2'] === null) {
                    continue;
                }
                $try = $list;
                $try[$i]['band_slot'] = $m['band_slot'] === 2 ? 1 : 2;
                $slotPreview[$m['id']] = BandCalc::lineupProblems(array_values($try)) ? null : BandCalc::sound(
                    $try,
                    $band['cohesion'],
                    $gearMap,
                    BandCalc::identity($try, $band['genre'], $band['genre2'])['bonus']
                );
            }

            $bandsOut[] = [
                'id' => $band['id'],
                'name' => $band['name'],
                'genres' => array_values(array_filter([$identity['primary'], $identity['second'], $identity['third']])),
                'title' => $identity['title'],
                'genreBonus' => $identity['bonus'],
                'genreChecks' => $identity['checks'],
                'managerId' => $band['manager_id'],
                'managerLevel' => $level,
                'memberIds' => array_map(static fn ($m) => $m['id'], $list),
                'room' => Rooms::present($repo, $band, count($list)),
                'sound' => $sound,
                'slotPreview' => $slotPreview,
                'slotReturn' => array_map('strval', array_keys($band['slot_return'])),
                'fame' => $fame,
                'fameOwn' => round($band['fame_own'], 1),
                // Label-Umbau: Stimmung der Band (Ampel + Wert), geschriebene Songs, Beziehungen der Mitglieder (Paare "a-b" => −10…+10)
                'mood' => Mood::bandMood(array_column($list, 'wellbeing')) + [
                    'songs' => (int) floor($band['songs_written']),
                    'patience' => round($band['patience']),
                    'relations' => $bandRelations[$band['id']] ?? [],
                    'togetherness' => Mood::togetherness($bandRelations[$band['id']] ?? []),
                    // Songs je Stunde, gerade jetzt (nach Mitgliedern und ihrem Charakter; 0 bei Rot, Auftritt, Promo; langsamer beim Proben, schneller bei bester Laune)
                    'songRate' => round(Mood::songRate(Mood::bandMood(array_column($list, 'wellbeing'))) * Mood::crewFactor($list)
                        * Mood::activityFactor($db->value('SELECT type FROM activities WHERE band_id = ? AND settled = 0 AND started_at <= ? AND ends_at > ? ORDER BY id DESC LIMIT 1', [$band['id'], $now, $now])), 3),
                    'reactions' => Mood::reactions($list),
                    'wishes' => Mood::wishes($list, $gearMap, (int) floor($band['songs_written'])),
                ],
                'cohesion' => round($band['cohesion'], 1),
                'maxCohesion' => BandCalc::maxCohesion($identity),
                'chemistryNotes' => BandCalc::chemistryNotes($list),
                'chemistry' => $chemistry,
                'songsRecorded' => $band['songs_recorded'],
                'records' => $band['records'],
                'perfectRecords' => $band['perfect_records'],
                'gigsPlayed' => $band['gigs_played'],
                'gigsSinceRecord' => $band['gigs_since_record'],
                'released' => $band['released'],
                'busyUntil' => $band['busy_until'],
                'options' => [
                    'busking' => ['songs' => Balance::BUSKING['songs']],
                    'rehearsal' => (static function () use ($list, $repo, $band): array {
                        [$seconds, $limit] = Mood::rehearsalLimits($list, Rooms::rules(Rooms::level($repo, $band, count($list)))['lust']);
                        return ['seconds' => $seconds, 'limit' => $limit];
                    })(),
                    'openstage' => [
                        'songs' => Balance::OPENSTAGE['songs'],
                        'worthIt' => BandCalc::falloff($fame, Balance::OPENSTAGE['falloff']) > 0,
                    ],
                    'gig' => [
                        'available' => $band['records'] > 0,
                        'venue' => $venue['name'],
                        'venueKey' => $venue['key'],
                        'progress' => round($venue['progress'], 3),
                        'nextVenue' => $venue['next'],
                        'nextFame' => $venue['nextFame'],
                        'maxSongs' => BandCalc::maxGigSongs($band['songs_recorded'], $level),
                        'minSongs' => min(Balance::GIG_MIN_SONGS, BandCalc::maxGigSongs($band['songs_recorded'], $level)),
                        'songCap' => BandCalc::gigSongCap($level),
                        'gagePerSong' => (int) round(BandCalc::gagePerSong($venue, BandCalc::managerBonus($manager, $level, 'verhandler', Balance::NEGOTIATOR_BONUS)) * $draw),
                        'fresh' => round(BandCalc::freshness($band['gigs_since_record']), 2),
                    ],
                ],
                'record' => [
                    'open' => Present::record($open),
                    'blocker' => Studio::blocker($band, count($list), $open),
                    'cycle' => Studio::cycle($band),
                ],
                'discography' => $discography[$band['id']] ?? [],
                // Was die Werte oben konkret bewirken (für die Kacheln der Bandseite).
                'effects' => [
                    'soundFame' => round(BandCalc::soundFactor($sound), 2),
                    'expect' => $venue['expect'],
                    'venue' => $venue['name'],
                    'gage' => (int) round(BandCalc::gagePerSong($venue, BandCalc::managerBonus($manager, $level, 'verhandler', Balance::NEGOTIATOR_BONUS), count($list)) * $draw),
                    'sales' => round(min(Balance::RECORD_FAME_SALES_MAX, $fame / Balance::RECORD_FAME_SALES), 3),
                    'cohesionSound' => round(Balance::COHESION_FLOOR + (1 - Balance::COHESION_FLOOR) * $band['cohesion'] / 100, 3),
                    'chemistry' => round($chemistry, 2),
                ],
            ];
        }

        $running = $db->all('SELECT * FROM activities WHERE label_id = ? AND settled = 0 ORDER BY ends_at', [$repo->labelId]);
        $results = $db->all('SELECT * FROM activities WHERE label_id = ? AND settled = 1 AND seen = 0 ORDER BY ends_at', [$repo->labelId]);
        $history = $db->all('SELECT id, band_id, musician_id, kind, text, created_at FROM history WHERE label_id = ? ORDER BY id DESC LIMIT 80', [$repo->labelId]);
        $items = $db->all('SELECT id, kind, value FROM items WHERE label_id = ? ORDER BY id', [$repo->labelId]);

        // Nachrichten: Was ist passiert, während man weg war (ab einer Viertelstunde Pause)?
        $lastSeen = (int) $db->value('SELECT last_seen FROM labels WHERE id = ?', [$repo->labelId]);
        $news = [];
        if ($lastSeen > 0 && $now - $lastSeen >= Balance::NEWS_AWAY) {
            $kinds = Balance::NEWS_KINDS;
            $placeholders = implode(', ', array_fill(0, count($kinds), '?'));
            $news = $db->all(
                "SELECT id, kind, text, created_at FROM history WHERE label_id = ? AND created_at > ? AND kind IN ($placeholders) ORDER BY id DESC LIMIT 30",
                array_merge([$repo->labelId, $lastSeen], $kinds)
            );
        }
        $db->update('labels', ['last_seen' => $now], ['id' => $repo->labelId]);

        // Freie Instrumente (Lager und offene Ladenauswahl): wem würden sie helfen?
        $advice = static fn (string $model, array $stats) => GearAdvice::upgrades($model, $stats, $musicians, $bands, $members, $gearMap);
        $gearOut = [];
        foreach ($gear as $item) {
            $out = Present::gear($item);
            if ($item['musician_id'] === null) {
                $out['upgrades'] = $advice($item['model'], ['klang' => $item['klang'], 'spielbarkeit' => $item['spielbarkeit'], 'design' => $item['design']]);
            }
            $gearOut[] = $out;
        }
        $packOffer = (new Packs($repo))->open();
        foreach ($packOffer['cards'] ?? [] as $i => $card) {
            if ($card['kind'] === 'gear') {
                $g = $card['gear'];
                $packOffer['cards'][$i]['gear']['upgrades'] = $advice($g['model'], ['klang' => $g['klang'], 'spielbarkeit' => $g['spielbarkeit'], 'design' => $g['design']]);
            }
        }

        return [
            'now' => $now,
            'label' => $label + ['bandSlots' => Bands::slots($repo->labelFame())],
            'scout' => (new Scout($repo))->status(),
            'submissions' => (new Submissions($repo))->list($now),
            'course' => (new Courses($repo))->status($musicians),
            'jamOffers' => (new Jam($repo))->open(),
            'market' => (new Transfers($repo))->market(),
            'packOffer' => $packOffer,
            'finance' => Charts::income($db, $repo->labelId, $albums),
            'musicians' => array_values(array_map(static function ($m) use ($gearIds, $gearMap, $roomSkill) {
                // Was Üben bringt, damit man entscheiden kann: Solo-Probe (bei 100 % Treffern) je Instrument, Bandprobe fürs Band-Instrument.
                $practice = [];
                foreach ([1 => $m['instr1'], 2 => $m['instr2']] as $slot => $type) {
                    if ($type !== null) {
                        $practice[] = round(BandCalc::practiceGain($m, $slot, 1.0, $gearMap[$m['id']][$type] ?? null), 3);
                    }
                }
                return Present::musician($m, $gearIds[$m['id']] ?? []) + ['wellbeing' => ['value' => round($m['wellbeing'], 1), 'level' => Mood::level($m['wellbeing'])], 'gains' => [
                    'practice' => $practice,
                    'auto' => array_map(static fn ($g) => round($g * Balance::AUTO_PRACTICE['share'], 3), $practice),
                    'rehearsal' => $m['band_id'] === null ? null : round(BandCalc::rehearsalGain($m, BandCalc::gearOf($m, $gearMap)) * ($roomSkill[$m['band_id']] ?? 1.0), 3),
                ]];
            }, $musicians)),
            'gear' => $gearOut,
            'store' => (new Store($repo))->present(),
            'managers' => array_values(array_map(static fn ($m) => Present::manager($m, $managerBands[$m['id']] ?? []), $managers)),
            'items' => array_map(static fn ($i) => ['id' => (int) $i['id'], 'kind' => $i['kind'], 'value' => (float) $i['value']], $items),
            'bands' => $bandsOut,
            'running' => array_map([Present::class, 'activity'], $running),
            'results' => array_map([Present::class, 'result'], $results),
            'news' => array_map(static fn ($h) => ['id' => (int) $h['id'], 'kind' => $h['kind'], 'text' => $h['text'], 'createdAt' => (int) $h['created_at']], $news),
            'history' => array_map(static fn ($h) => [
                'id' => (int) $h['id'],
                'bandId' => $h['band_id'] === null ? null : (int) $h['band_id'],
                'musicianId' => $h['musician_id'] === null ? null : (int) $h['musician_id'],
                'kind' => $h['kind'],
                'text' => $h['text'],
                'createdAt' => (int) $h['created_at'],
            ], $history),
        ];
    }
}
