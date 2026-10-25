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
    /** Ereignisse, die auch andere Labels sehen dürfen (kein Geld, keine Börse). */
    private const PUBLIC_KINDS = ['lineup', 'band', 'record', 'level'];

    /**
     * Bands (außer unbenutzten Solo-Bands) und Signies eines Labels, aktuelle und ehemalige.
     * @return array{bands: list<array>, signies: list<array>}
     */
    private static function lists(\Signed\Db $db, int $labelId): array
    {
        $counts = [];
        foreach ($db->all('SELECT band_id, COUNT(*) AS n FROM musicians WHERE label_id = ? AND band_id IS NOT NULL GROUP BY band_id', [$labelId]) as $r) {
            $counts[(int) $r['band_id']] = (int) $r['n'];
        }
        $bands = [];
        foreach ($db->all('SELECT id, name, created_at, retired_at, released, records FROM bands WHERE label_id = ? ORDER BY name LIMIT 400', [$labelId]) as $b) {
            $retired = $b['retired_at'] !== null;
            // Eine Solo-Band ohne eigene Laufbahn ist nur der Künstler selbst; der hat seinen eigenen Artikel.
            if (!$retired && ($counts[(int) $b['id']] ?? 0) <= 1 && !$b['released'] && (int) $b['records'] === 0) {
                continue;
            }
            $bands[] = ['id' => (int) $b['id'], 'name' => $b['name'], 'status' => $retired ? 'former' : 'active'];
        }
        $signies = [];
        foreach ($db->all('SELECT id, first_name, last_name, instr1 FROM musicians WHERE label_id = ? LIMIT 800', [$labelId]) as $m) {
            $signies[] = ['id' => (int) $m['id'], 'name' => $m['first_name'] . ' ' . $m['last_name'], 'status' => 'current', 'instrument' => $m['instr1']];
        }
        foreach ($db->all(
            'SELECT m.id, m.first_name, m.last_name, m.instr1 FROM alumni a JOIN musicians m ON m.id = a.musician_id
             WHERE a.label_id = ? AND m.label_id <> ? GROUP BY m.id, m.first_name, m.last_name, m.instr1 LIMIT 800',
            [$labelId, $labelId]
        ) as $m) {
            $signies[] = ['id' => (int) $m['id'], 'name' => $m['first_name'] . ' ' . $m['last_name'], 'status' => 'former', 'instrument' => $m['instr1']];
        }
        usort($signies, static fn ($x, $y) => strcmp(mb_strtolower($x['name']), mb_strtolower($y['name'])));
        return ['bands' => $bands, 'signies' => $signies];
    }

    /** Oberste Ebene: die Labels. Das eigene steht zuerst (mit Verzeichnis), bei den anderen nur die Zahlen. */
    public static function index(Repo $repo): array
    {
        $db = $repo->db;
        $labels = [];
        foreach ($db->all('SELECT id, name FROM labels ORDER BY name LIMIT 300') as $l) {
            $id = (int) $l['id'];
            $lists = self::lists($db, $id);
            $labels[] = ['id' => $id, 'name' => $l['name'], 'own' => $id === $repo->labelId, 'bandCount' => count($lists['bands']), 'signieCount' => count($lists['signies'])];
        }
        usort($labels, static fn ($x, $y) => [$x['own'] ? 0 : 1, mb_strtolower($x['name'])] <=> [$y['own'] ? 0 : 1, mb_strtolower($y['name'])]);
        return ['labels' => $labels];
    }

    /** Seite eines Labels: seine Bands und Signies. */
    public static function label(Repo $repo, int $id): array
    {
        $l = $repo->db->one('SELECT id, name FROM labels WHERE id = ?', [$id]);
        if ($l === null) {
            throw new ApiError('Dieses Label gibt es nicht.', 404);
        }
        return ['id' => $id, 'name' => $l['name'], 'own' => $id === $repo->labelId] + self::lists($repo->db, $id);
    }

    /** Suche über alle Labels (Bands und Signies, höchstens je 25 Treffer). */
    public static function search(Repo $repo, string $q): array
    {
        $db = $repo->db;
        $tokens = array_values(array_filter(preg_split('/\s+/u', mb_strtolower(trim($q))) ?: []));
        if (!$tokens || mb_strlen(trim($q)) < 2) {
            return ['bands' => [], 'signies' => []];
        }
        $like = static fn (string $t) => '%' . strtr($t, ['%' => '\\%', '_' => '\\_']) . '%';
        $where = [];
        $params = [];
        foreach ($tokens as $t) {
            $where[] = "(LOWER(m.first_name) LIKE ? ESCAPE '\\' OR LOWER(m.last_name) LIKE ? ESCAPE '\\')";
            array_push($params, $like($t), $like($t));
        }
        $signies = [];
        foreach ($db->all(
            'SELECT m.id, m.first_name, m.last_name, m.label_id, l.name AS label_name FROM musicians m LEFT JOIN labels l ON l.id = m.label_id
             WHERE (m.label_id > 0 OR EXISTS (SELECT 1 FROM alumni a WHERE a.musician_id = m.id)) AND ' . implode(' AND ', $where) . ' ORDER BY m.last_name LIMIT 25',
            $params
        ) as $m) {
            $signies[] = ['id' => (int) $m['id'], 'name' => $m['first_name'] . ' ' . $m['last_name'], 'label' => $m['label_name'], 'labelId' => $m['label_id'] === null ? null : (int) $m['label_id']];
        }
        $bands = [];
        foreach ($db->all(
            "SELECT b.id, b.name, b.retired_at, b.label_id, l.name AS label_name FROM bands b JOIN labels l ON l.id = b.label_id WHERE b.name_key LIKE ? ESCAPE '\\' ORDER BY b.name LIMIT 25",
            [$like(implode(' ', $tokens))]
        ) as $b) {
            $bands[] = ['id' => (int) $b['id'], 'name' => $b['name'], 'status' => $b['retired_at'] !== null ? 'former' : 'active', 'label' => $b['label_name'], 'labelId' => (int) $b['label_id']];
        }
        return ['bands' => $bands, 'signies' => $signies];
    }

    /** Artikel zu einer Band (aktuell oder ehemalig). */
    public static function band(Repo $repo, int $id): array
    {
        $db = $repo->db;
        $row = $db->one('SELECT b.*, l.name AS label_name FROM bands b JOIN labels l ON l.id = b.label_id WHERE b.id = ?', [$id]);
        if ($row === null) {
            throw new ApiError('Diese Band gibt es nicht.', 404);
        }
        $labelId = (int) $row['label_id'];
        $own = $labelId === $repo->labelId;
        $owner = $own ? $repo : new Repo($db, $labelId);
        $band = Repo::hydrateBand($row);
        $retired = $row['retired_at'] !== null;
        $members = $retired ? [] : $owner->members($id);
        $identity = $retired ? null : BandCalc::identity($members, $band['genre'], $band['genre2']);
        $current = array_column($members, 'id');
        $events = array_values(array_filter(
            $db->all('SELECT musician_id, kind, text, created_at FROM history WHERE band_id = ? AND label_id = ? ORDER BY id ASC', [$id, $labelId]),
            static fn ($e) => $own || in_array($e['kind'], self::PUBLIC_KINDS, true)
        ));
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
            'label' => ['id' => $labelId, 'name' => $row['label_name'], 'own' => $own],
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

    /** Artikel zu einem Künstler („Signie“): aktuell bei einem Label oder ausgeschieden; auch fremde Labels. */
    public static function signie(Repo $repo, int $id): array
    {
        $db = $repo->db;
        $row = $db->one('SELECT * FROM musicians WHERE id = ?', [$id]);
        $left = $row === null ? [] : $db->all('SELECT a.label_id, a.left_at, a.reason, l.name AS label_name FROM alumni a JOIN labels l ON l.id = a.label_id WHERE a.musician_id = ? ORDER BY a.id', [$id]);
        if ($row === null || ((int) $row['label_id'] === 0 && !$left)) {
            throw new ApiError('Diesen Künstler gibt es nicht.', 404);
        }
        $m = Repo::hydrateMusician($row);
        $labelId = (int) $row['label_id'];
        $label = $labelId > 0 ? $db->one('SELECT id, name FROM labels WHERE id = ?', [$labelId]) : null;
        $events = array_values(array_filter(
            $db->all('SELECT label_id, band_id, kind, text, created_at FROM history WHERE musician_id = ? ORDER BY id ASC', [$id]),
            static fn ($e) => (int) $e['label_id'] === $repo->labelId || in_array($e['kind'], self::PUBLIC_KINDS, true)
        ));
        $bandIds = [];
        foreach ($events as $e) {
            if ($e['band_id'] !== null) {
                $bandIds[(int) $e['band_id']] = true;
            }
        }
        if ($m['band_id'] !== null) {
            $bandIds[$m['band_id']] = true;
        }
        $bands = [];
        foreach (array_keys($bandIds) as $bandId) {
            $b = $db->one('SELECT b.id, b.name, b.retired_at, b.label_id, l.name AS label_name FROM bands b JOIN labels l ON l.id = b.label_id WHERE b.id = ?', [$bandId]);
            if ($b !== null) {
                $bands[] = ['id' => (int) $b['id'], 'name' => $b['name'], 'status' => $b['retired_at'] !== null ? 'former' : 'active', 'now' => $m['band_id'] === (int) $b['id'], 'label' => $b['label_name']];
            }
        }
        $out = Present::musician($m);
        unset($out['busyUntil'], $out['value'], $out['favorite'], $out['abilityChoice'], $out['gearIds'], $out['jammed']);
        return $out + [
            'status' => $label !== null ? 'current' : 'former',
            'label' => $label === null ? null : ['id' => (int) $label['id'], 'name' => $label['name'], 'own' => (int) $label['id'] === $repo->labelId],
            'formerLabels' => array_map(static fn ($a) => ['id' => (int) $a['label_id'], 'name' => $a['label_name'], 'leftAt' => (int) $a['left_at'], 'reason' => $a['reason'], 'own' => (int) $a['label_id'] === $repo->labelId], $left),
            'joinedAt' => (int) $row['created_at'],
            'leftAt' => $label === null && $left ? (int) end($left)['left_at'] : null,
            'reason' => $label === null && $left ? end($left)['reason'] : null,
            'bands' => $bands,
            'events' => array_map(static fn ($e) => ['kind' => $e['kind'], 'text' => $e['text'], 'createdAt' => (int) $e['created_at']], array_slice($events, -80)),
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
