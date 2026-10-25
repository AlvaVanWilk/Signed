<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;

/**
 * WikiSignia: Artikel zu jeder Band und jedem Künstler („Signie“), die dem Label einmal angehörten – auch zu ehemaligen.
 * Die Artikel entstehen aus dem, was das Spiel ohnehin weiß (Stammdaten, Platten, Chronik-Einträge); der Text wird im Browser gesetzt.
 */
final class Wiki
{
    /** Verzeichnis: alle Bands (außer unbenutzten Solo-Bands) und alle Signies des Labels, aktuelle und ehemalige. */
    public static function index(Repo $repo): array
    {
        $db = $repo->db;
        $counts = [];
        foreach ($db->all('SELECT band_id, COUNT(*) AS n FROM musicians WHERE label_id = ? AND band_id IS NOT NULL GROUP BY band_id', [$repo->labelId]) as $r) {
            $counts[(int) $r['band_id']] = (int) $r['n'];
        }
        $bands = [];
        foreach ($db->all('SELECT id, name, created_at, retired_at, released, records FROM bands WHERE label_id = ? ORDER BY name', [$repo->labelId]) as $b) {
            $retired = $b['retired_at'] !== null;
            // Eine Solo-Band ohne eigene Laufbahn ist nur der Künstler selbst; der hat seinen eigenen Artikel.
            if (!$retired && ($counts[(int) $b['id']] ?? 0) <= 1 && !$b['released'] && (int) $b['records'] === 0) {
                continue;
            }
            $bands[] = ['id' => (int) $b['id'], 'name' => $b['name'], 'status' => $retired ? 'former' : 'active', 'since' => (int) $b['created_at'], 'until' => $retired ? (int) $b['retired_at'] : null];
        }
        $signies = [];
        foreach ($db->all('SELECT id, first_name, last_name, instr1, instr2, band_id, created_at FROM musicians WHERE label_id = ? ORDER BY last_name, first_name', [$repo->labelId]) as $m) {
            $signies[] = ['id' => (int) $m['id'], 'name' => $m['first_name'] . ' ' . $m['last_name'], 'status' => 'current', 'instrument' => $m['instr1']];
        }
        foreach ($db->all(
            'SELECT m.id, m.first_name, m.last_name, m.instr1, a.left_at, a.reason FROM alumni a JOIN musicians m ON m.id = a.musician_id
             WHERE a.label_id = ? AND m.label_id <> ? ORDER BY m.last_name, m.first_name',
            [$repo->labelId, $repo->labelId]
        ) as $m) {
            $signies[] = ['id' => (int) $m['id'], 'name' => $m['first_name'] . ' ' . $m['last_name'], 'status' => 'former', 'instrument' => $m['instr1'], 'leftAt' => (int) $m['left_at'], 'reason' => $m['reason']];
        }
        usort($signies, static fn ($a, $b) => strcmp(mb_strtolower($a['name']), mb_strtolower($b['name'])));
        return ['bands' => $bands, 'signies' => $signies];
    }

    /** Artikel zu einer Band (aktuell oder ehemalig). */
    public static function band(Repo $repo, int $id): array
    {
        $db = $repo->db;
        $row = $db->one('SELECT * FROM bands WHERE id = ? AND label_id = ?', [$id, $repo->labelId]);
        if ($row === null) {
            throw new ApiError('Diese Band gibt es nicht.', 404);
        }
        $band = Repo::hydrateBand($row);
        $retired = $row['retired_at'] !== null;
        $members = $retired ? [] : $repo->members($id);
        $identity = $retired ? null : BandCalc::identity($members, $band['genre'], $band['genre2']);
        $current = array_column($members, 'id');
        $events = $db->all('SELECT musician_id, kind, text, created_at FROM history WHERE band_id = ? AND label_id = ? ORDER BY id ASC', [$id, $repo->labelId]);
        $former = [];
        foreach ($events as $e) {
            if ($e['musician_id'] !== null && !in_array((int) $e['musician_id'], $current, true)) {
                $former[(int) $e['musician_id']] = true;
            }
        }
        $records = [];
        foreach ($db->all("SELECT title, songs_total, perfect, chart_points, rank_best, topped, finished_at FROM records WHERE band_id = ? AND status = 'done' ORDER BY finished_at DESC", [$id]) as $r) {
            $records[] = [
                'title' => $r['title'],
                'format' => (int) $r['songs_total'] >= Balance::CHART_LP_SONGS ? 'LP' : 'EP',
                'perfect' => (bool) $r['perfect'],
                'peak' => round((float) $r['chart_points'], 1),
                'best' => $r['rank_best'] === null ? null : (int) $r['rank_best'],
                'topped' => (bool) $r['topped'],
                'finishedAt' => (int) $r['finished_at'],
            ];
        }
        return [
            'id' => $id,
            'name' => $band['name'],
            'status' => $retired ? 'former' : 'active',
            'foundedAt' => $band['created_at'],
            'endedAt' => $retired ? (int) $row['retired_at'] : null,
            'reason' => $row['retired_reason'],
            'note' => $row['retired_note'],
            'title' => $identity['title'] ?? null,
            'genres' => $identity ? array_values(array_filter([$identity['primary'], $identity['second'], $identity['third']])) : [],
            'fame' => $retired ? round((float) $row['fame_final'], 1) : BandCalc::bandFame($band, $members),
            'gigsPlayed' => $band['gigs_played'],
            'recordCount' => count($records),
            'members' => array_map(static fn ($m) => self::summary($m), $members),
            'formerMembers' => self::summaries($db, array_keys($former)),
            'records' => $records,
            'events' => array_map(static fn ($e) => ['kind' => $e['kind'], 'text' => $e['text'], 'createdAt' => (int) $e['created_at']], array_slice($events, -60)),
        ];
    }

    /** Artikel zu einem Künstler („Signie“): aktuell beim Label oder ausgeschieden. */
    public static function signie(Repo $repo, int $id): array
    {
        $db = $repo->db;
        $row = $db->one('SELECT * FROM musicians WHERE id = ?', [$id]);
        if ($row === null) {
            throw new ApiError('Diesen Künstler gibt es nicht.', 404);
        }
        $current = (int) $row['label_id'] === $repo->labelId;
        $alumni = $db->one('SELECT left_at, reason FROM alumni WHERE label_id = ? AND musician_id = ? ORDER BY id DESC', [$repo->labelId, $id]);
        if (!$current && $alumni === null) {
            throw new ApiError('Diesen Künstler gibt es nicht.', 404);
        }
        $m = Repo::hydrateMusician($row);
        $events = $db->all('SELECT band_id, kind, text, created_at FROM history WHERE musician_id = ? AND label_id = ? ORDER BY id ASC', [$id, $repo->labelId]);
        $bandIds = [];
        foreach ($events as $e) {
            if ($e['band_id'] !== null) {
                $bandIds[(int) $e['band_id']] = true;
            }
        }
        if ($current && $m['band_id'] !== null) {
            $bandIds[$m['band_id']] = true;
        }
        $bands = [];
        foreach ($bandIds as $bandId => $_) {
            $b = $db->one('SELECT id, name, retired_at, released, records FROM bands WHERE id = ? AND label_id = ?', [$bandId, $repo->labelId]);
            if ($b !== null) {
                $bands[] = ['id' => (int) $b['id'], 'name' => $b['name'], 'status' => $b['retired_at'] !== null ? 'former' : 'active', 'now' => $current && $m['band_id'] === (int) $b['id']];
            }
        }
        $out = Present::musician($m);
        unset($out['busyUntil'], $out['value'], $out['favorite'], $out['abilityChoice'], $out['gearIds'], $out['jammed']);
        return $out + [
            'status' => $current ? 'current' : 'former',
            'joinedAt' => (int) $row['created_at'],
            'leftAt' => $alumni !== null && !$current ? (int) $alumni['left_at'] : null,
            'reason' => $alumni !== null && !$current ? $alumni['reason'] : null,
            'bands' => $bands,
            'events' => array_map(static fn ($e) => ['kind' => $e['kind'], 'text' => $e['text'], 'createdAt' => (int) $e['created_at']], array_slice($events, -60)),
        ];
    }

    /** Kurzangaben (für Mitgliederlisten). */
    private static function summary(array $m): array
    {
        $p = Present::musician($m);
        return [
            'id' => $p['id'], 'firstName' => $p['firstName'], 'lastName' => $p['lastName'], 'instruments' => $p['instruments'],
            'genres' => $p['genres'], 'level' => $p['level'], 'look' => $p['look'], 'bandId' => $p['bandId'], 'bandSlot' => $p['bandSlot'],
        ];
    }

    /** @param list<int> $ids */
    private static function summaries(\Signed\Db $db, array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $row = $db->one('SELECT * FROM musicians WHERE id = ?', [$id]);
            if ($row !== null) {
                $out[] = self::summary(Repo::hydrateMusician($row));
            }
        }
        return $out;
    }
}
