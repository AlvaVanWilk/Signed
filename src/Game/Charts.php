<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Clock;
use Signed\Db;

/**
 * Album-Charts über alle Spieler: Jede fertige Platte bekommt Chartpunkte, die jeden Tag schrumpfen.
 * Solange eine Platte chartet, bringt sie täglich Tantiemen. Dazu eine Label-Gesamtliste nach Ruhm.
 */
final class Charts
{
    private const DAY = 86400;

    /** Chartpunkte einer frisch fertigen Platte. */
    public static function points(int $songs, float $quality, float $sound, bool $perfect, float $bandFame): float
    {
        $format = $songs >= Balance::CHART_LP_SONGS
            ? 1 + Balance::CHART_LP_PER_SONG * ($songs - Balance::CHART_LP_SONGS)
            : Balance::CHART_EP_FACTOR;
        $fame = 1 + min(Balance::CHART_FAME_MAX, $bandFame / Balance::CHART_FAME);
        return round(Balance::CHART_BASE * $quality ** 2 * BandCalc::soundFactor($sound) * $format
            * ($perfect ? Balance::CHART_PERFECT : 1) * $fame, 1);
    }

    /** Punkte nach $seconds seit Erscheinen. */
    public static function current(float $points, int $seconds): float
    {
        return $points * Balance::CHART_DECAY ** (max(0, $seconds) / self::DAY);
    }

    public static function build(Db $db, int $labelId): array
    {
        $now = Clock::now();
        $rows = $db->all(
            "SELECT r.id, r.title, r.songs_total, r.perfect, r.chart_points, r.finished_at, r.topped, r.label_id,
                    b.name AS band_name, l.name AS label_name
             FROM records r
             JOIN labels l ON l.id = r.label_id
             LEFT JOIN bands b ON b.id = r.band_id
             WHERE r.status = 'done' AND r.chart_points > 0 AND r.finished_at >= ?",
            [$now - 90 * self::DAY]
        );
        $albums = [];
        foreach ($rows as $row) {
            $points = self::current((float) $row['chart_points'], $now - (int) $row['finished_at']);
            if ($points < Balance::CHART_MIN_POINTS) {
                continue;
            }
            $albums[] = [
                'recordId' => (int) $row['id'],
                'title' => $row['title'],
                'band' => $row['band_name'] ?? 'aufgelöste Band',
                'label' => $row['label_name'],
                'format' => (int) $row['songs_total'] >= Balance::CHART_LP_SONGS ? 'LP' : 'EP',
                'perfect' => (bool) $row['perfect'],
                'days' => intdiv($now - (int) $row['finished_at'], self::DAY),
                'points' => round($points, 1),
                'perDay' => (int) round($points * Balance::ROYALTY_MONEY),
                'own' => (int) $row['label_id'] === $labelId,
                'topped' => (bool) $row['topped'],
                'labelId' => (int) $row['label_id'],
            ];
        }
        usort($albums, static fn ($a, $b) => $b['points'] <=> $a['points']);

        // Platz 1 gibt einmalig einen Scout-Gutschein – aber nur, wenn es Konkurrenz gibt.
        $competitors = count(array_unique(array_column($albums, 'labelId')));
        if ($albums && !$albums[0]['topped'] && $competitors >= Balance::CHART_TOP_MIN_LABELS) {
            $top = $albums[0];
            if ($db->exec('UPDATE records SET topped = 1 WHERE id = ? AND topped = 0', [$top['recordId']]) > 0) {
                Scout::grant(new Repo($db, $top['labelId']), sprintf('„%s“ steht auf Platz 1 der Charts!', $top['title']));
            }
        }
        foreach ($albums as &$album) {
            unset($album['topped'], $album['labelId']);
        }
        unset($album);

        return ['albums' => self::rank($albums), 'labels' => self::rank(self::labels($db, $labelId))];
    }

    /**
     * Tantiemen laufen ständig: Bei jedem Aufruf wird abgerechnet, was seit dem letzten Mal angefallen ist
     * (Integral der schrumpfenden Chartpunkte). Geld ans Label, Ruhm an die aktuellen Mitglieder der Band.
     * Bruchteile eines ₲ werden aufgehoben, bis ein ganzer zusammenkommt.
     */
    public static function payRoyalties(Db $db, int $labelId): void
    {
        $now = Clock::now();
        $rows = $db->all(
            "SELECT id, band_id, title, chart_points, finished_at, royalties_until, royalty_carry FROM records
             WHERE label_id = ? AND status = 'done' AND chart_points > 0 AND royalties_until < ?",
            [$labelId, $now]
        );
        foreach ($rows as $row) {
            $db->transaction(static function () use ($db, $labelId, $row, $now): void {
                $from = (int) $row['royalties_until'];
                $claimed = $db->exec('UPDATE records SET royalties_until = ? WHERE id = ? AND royalties_until = ?', [$now, $row['id'], $from]);
                if ($claimed === 0) {
                    return;
                }
                $finished = (int) $row['finished_at'];
                $integral = self::pointSeconds((float) $row['chart_points'], max(0, $from - $finished), $now - $finished);
                if ($integral <= 0) {
                    return;
                }
                $money = $integral / self::DAY * Balance::ROYALTY_MONEY + (float) $row['royalty_carry'];
                $paid = (int) floor($money);
                $db->update('records', ['royalty_carry' => $money - $paid], ['id' => (int) $row['id']]);
                $repo = new Repo($db, $labelId);
                $repo->earnDaily($paid, sprintf('Tantiemen „%s“', $row['title']));
                $members = $row['band_id'] === null ? [] : $repo->members((int) $row['band_id']);
                if ($members) {
                    $fame = $integral / self::DAY * Balance::ROYALTY_FAME;
                    $repo->addBandFame((int) $row['band_id'], array_fill_keys(array_column($members, 'id'), $fame / count($members)));
                }
            });
        }
    }

    /** Chartpunkte × Sekunden zwischen $a und $b (Sekunden seit Erscheinen), nur solange die Platte chartet. */
    private static function pointSeconds(float $points, int $a, int $b): float
    {
        $k = log(Balance::CHART_DECAY) / self::DAY;
        if ($points < Balance::CHART_MIN_POINTS) {
            return 0.0;
        }
        $end = log(Balance::CHART_MIN_POINTS / $points) / $k;
        $b = min((float) $b, $end);
        if ($b <= $a) {
            return 0.0;
        }
        return $points / $k * (exp($k * $b) - exp($k * $a));
    }

    /** Regelmäßige Einnahmen: alle eigenen Platten, die gerade charten, und der Kontoauszug. */
    public static function income(Db $db, int $labelId): array
    {
        $now = Clock::now();
        $records = [];
        $bands = [];
        foreach ($db->all(
            "SELECT r.title, r.band_id, r.chart_points, r.finished_at, b.name AS band_name FROM records r
             LEFT JOIN bands b ON b.id = r.band_id
             WHERE r.label_id = ? AND r.status = 'done' AND r.chart_points > 0",
            [$labelId]
        ) as $row) {
            $points = self::current((float) $row['chart_points'], $now - (int) $row['finished_at']);
            if ($points >= Balance::CHART_MIN_POINTS) {
                if ($row['band_id'] !== null) {
                    $band = &$bands[(int) $row['band_id']];
                    $band['moneyPerHour'] = ($band['moneyPerHour'] ?? 0) + $points * Balance::ROYALTY_MONEY / 24;
                    $band['famePerHour'] = ($band['famePerHour'] ?? 0) + $points * Balance::ROYALTY_FAME / 24;
                    unset($band);
                }
                $records[] = [
                    'title' => $row['title'],
                    'band' => $row['band_name'] ?? 'aufgelöste Band',
                    'points' => round($points, 1),
                    'perDay' => (int) round($points * Balance::ROYALTY_MONEY),
                ];
            }
        }
        usort($records, static fn ($a, $b) => $b['perDay'] <=> $a['perDay']);
        $ledger = $db->all('SELECT amount, text, created_at FROM ledger WHERE label_id = ? ORDER BY id DESC LIMIT 40', [$labelId]);
        $points = array_sum(array_column($records, 'points'));
        return [
            'perDay' => array_sum(array_column($records, 'perDay')),
            // Für die laufende Anzeige im Browser (Geld tickt zwischen zwei Abrufen weiter).
            'moneyPerHour' => round($points * Balance::ROYALTY_MONEY / 24, 2),
            'famePerHour' => round($points * Balance::ROYALTY_FAME / 24, 3),
            'bands' => (object) $bands,
            'decay' => Balance::CHART_DECAY,
            'records' => $records,
            'ledger' => array_map(static fn ($l) => ['amount' => (int) $l['amount'], 'text' => $l['text'], 'at' => (int) $l['created_at']], $ledger),
        ];
    }

    /** Label-Gesamtliste: Summe des Ruhms aller eigenen Bands (Bandnamen und Mitglieder). */
    private static function labels(Db $db, int $labelId): array
    {
        $rows = $db->all(
            'SELECT l.id, l.name, COUNT(DISTINCT b.id) AS bands, COALESCE(SUM(m.fame), 0) AS fame
             FROM labels l
             JOIN bands b ON b.label_id = l.id
             LEFT JOIN musicians m ON m.band_id = b.id
             GROUP BY l.id, l.name'
        );
        $own = [];
        foreach ($db->all('SELECT label_id, SUM(fame_own) AS fame FROM bands GROUP BY label_id') as $r) {
            $own[(int) $r['label_id']] = (float) $r['fame'];
        }
        return array_map(static fn ($r) => [
            'label' => $r['name'],
            'bands' => (int) $r['bands'],
            'points' => round((float) $r['fame'] + ($own[(int) $r['id']] ?? 0), 1),
            'own' => (int) $r['id'] === $labelId,
        ], $rows);
    }

    /** Sortiert nach Punkten, vergibt Plätze und kürzt auf die Top-Liste (eigene Einträge bleiben immer). */
    private static function rank(array $list): array
    {
        usort($list, static fn ($a, $b) => $b['points'] <=> $a['points']);
        $out = [];
        foreach ($list as $index => $entry) {
            if ($index < Balance::CHART_SIZE || $entry['own']) {
                $out[] = $entry + ['rank' => $index + 1];
            }
        }
        return $out;
    }
}
