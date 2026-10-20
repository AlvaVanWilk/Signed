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

        $discography = [];
        foreach ($db->all("SELECT * FROM records WHERE label_id = ? AND status = 'done' ORDER BY finished_at DESC", [$repo->labelId]) as $row) {
            $results = json_decode($row['song_results'], true) ?: [];
            $points = Charts::current((float) $row['chart_points'], $now - (int) $row['finished_at']);
            $discography[(int) $row['band_id']][] = [
                'title' => $row['title'],
                'songs' => (int) $row['songs_total'],
                'format' => (int) $row['songs_total'] >= Balance::CHART_LP_SONGS ? 'LP' : 'EP',
                'quality' => $results ? round(array_sum(array_column($results, 'accuracy')) / count($results), 3) : 0,
                'perfect' => (bool) $row['perfect'],
                'charting' => $points >= Balance::CHART_MIN_POINTS,
                'peak' => round((float) $row['chart_points'], 1),
                'topped' => (bool) $row['topped'],
                'points' => round($points, 1),
                'finishedAt' => (int) $row['finished_at'],
            ];
        }

        $bandsOut = [];
        foreach ($bands as $band) {
            $list = $members[$band['id']] ?? [];
            $identity = (new Bands($repo))->syncIdentity($band, $list);
            $sound = BandCalc::sound($list, $band['cohesion'], $gearMap, $identity['bonus']);
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

            $bandsOut[] = [
                'id' => $band['id'],
                'name' => $band['name'],
                'genres' => array_values(array_filter([$identity['primary'], $identity['second']])),
                'title' => $identity['title'],
                'genreBonus' => $identity['bonus'],
                'genreChecks' => $identity['checks'],
                'managerId' => $band['manager_id'],
                'managerLevel' => $level,
                'memberIds' => array_map(static fn ($m) => $m['id'], $list),
                'sound' => $sound,
                'fame' => $fame,
                'fameOwn' => round($band['fame_own'], 1),
                'cohesion' => round($band['cohesion'], 1),
                'maxCohesion' => BandCalc::maxCohesion($chemistry),
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
                    'rehearsal' => ['songs' => Balance::REHEARSAL['songs']],
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
                        'gagePerSong' => BandCalc::gagePerSong($venue, BandCalc::managerBonus($manager, $level, 'verhandler', Balance::NEGOTIATOR_BONUS)),
                    ],
                ],
                'record' => [
                    'open' => Present::record($open),
                    'blocker' => Studio::blocker($band, count($list), $open),
                ],
                'discography' => $discography[$band['id']] ?? [],
            ];
        }

        $running = $db->all('SELECT * FROM activities WHERE label_id = ? AND settled = 0 ORDER BY ends_at', [$repo->labelId]);
        $results = $db->all('SELECT * FROM activities WHERE label_id = ? AND settled = 1 AND seen = 0 ORDER BY ends_at', [$repo->labelId]);
        $history = $db->all('SELECT id, band_id, musician_id, kind, text, created_at FROM history WHERE label_id = ? ORDER BY id DESC LIMIT 80', [$repo->labelId]);
        $items = $db->all('SELECT id, kind, value FROM items WHERE label_id = ? ORDER BY id', [$repo->labelId]);

        $db->update('labels', ['last_seen' => $now], ['id' => $repo->labelId]);

        return [
            'now' => $now,
            'label' => $label + ['bandSlots' => Bands::slots($repo->labelFame())],
            'scout' => (new Scout($repo))->status(),
            'jamOffers' => (new Jam($repo))->open(),
            'market' => (new Transfers($repo))->market(),
            'packOffer' => (new Packs($repo))->open(),
            'finance' => Charts::income($db, $repo->labelId),
            'musicians' => array_values(array_map(static fn ($m) => Present::musician($m, $gearIds[$m['id']] ?? []), $musicians)),
            'gear' => array_values(array_map([Present::class, 'gear'], $gear)),
            'managers' => array_values(array_map(static fn ($m) => Present::manager($m, $managerBands[$m['id']] ?? []), $managers)),
            'items' => array_map(static fn ($i) => ['id' => (int) $i['id'], 'kind' => $i['kind'], 'value' => (float) $i['value']], $items),
            'bands' => $bandsOut,
            'running' => array_map([Present::class, 'activity'], $running),
            'results' => array_map([Present::class, 'result'], $results),
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
