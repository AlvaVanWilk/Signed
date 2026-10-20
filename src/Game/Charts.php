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

        // Platz 1 gibt einmalig einen Scout-Gutschein.
        if ($albums && !$albums[0]['topped']) {
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
     * Zahlt die Tantiemen aller vollen Tage seit der letzten Abrechnung: Geld ans Label,
     * Ruhm gleichmäßig an die aktuellen Mitglieder der Band.
     */
    public static function payRoyalties(Db $db, int $labelId): void
    {
        $now = Clock::now();
        $rows = $db->all(
            "SELECT id, band_id, title, chart_points, finished_at, royalties_until FROM records
             WHERE label_id = ? AND status = 'done' AND chart_points > 0 AND royalties_until <= ?",
            [$labelId, $now - self::DAY]
        );
        foreach ($rows as $row) {
            $db->transaction(static function () use ($db, $labelId, $row, $now): void {
                $from = (int) $row['royalties_until'];
                $days = intdiv($now - $from, self::DAY);
                $claimed = $db->exec('UPDATE records SET royalties_until = ? WHERE id = ? AND royalties_until = ?', [$from + $days * self::DAY, $row['id'], $from]);
                if ($days < 1 || $claimed === 0) {
                    return;
                }
                $money = 0.0;
                $fame = 0.0;
                for ($day = 1; $day <= $days; $day++) {
                    $points = self::current((float) $row['chart_points'], $from + $day * self::DAY - (int) $row['finished_at']);
                    if ($points < Balance::CHART_MIN_POINTS) {
                        break;
                    }
                    $money += $points * Balance::ROYALTY_MONEY;
                    $fame += $points * Balance::ROYALTY_FAME;
                }
                if ($money < 1) {
                    return;
                }
                $repo = new Repo($db, $labelId);
                $repo->earn((int) round($money));
                $members = $row['band_id'] === null ? [] : $repo->members((int) $row['band_id']);
                foreach ($members as $m) {
                    $db->exec('UPDATE musicians SET fame = fame + ? WHERE id = ?', [$fame / count($members), $m['id']]);
                }
                $repo->log('royalty', sprintf(
                    'Tantiemen für „%s“: +%s ₲%s.',
                    $row['title'],
                    number_format($money, 0, ',', '.'),
                    $members ? sprintf(' und +%s Ruhm', number_format($fame, 1, ',', '.')) : ''
                ), $row['band_id'] === null ? null : (int) $row['band_id']);
            });
        }
    }

    /** Label-Gesamtliste: Summe des Ruhms aller eigenen Bands. */
    private static function labels(Db $db, int $labelId): array
    {
        $rows = $db->all(
            'SELECT l.id, l.name, COUNT(DISTINCT b.id) AS bands, COALESCE(SUM(m.fame), 0) AS fame
             FROM labels l
             JOIN bands b ON b.label_id = l.id
             LEFT JOIN musicians m ON m.band_id = b.id
             GROUP BY l.id, l.name'
        );
        return array_map(static fn ($r) => [
            'label' => $r['name'],
            'bands' => (int) $r['bands'],
            'points' => round((float) $r['fame'], 1),
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
