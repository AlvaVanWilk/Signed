<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Clock;
use Signed\Db;

/**
 * Album-Charts über alle Spieler, gerechnet in Stunden: Eine Platte steigt mit rund zwei Dritteln ihrer Punkte ein,
 * erreicht nach einigen Stunden ihren Höhepunkt und fällt dann. Weil jede Platte ihren eigenen Verlauf hat,
 * überholen sie sich gegenseitig. Gigs und Promo der Band schieben die neueste Platte zusätzlich an (Schub, der
 * mit der Zeit nachlässt). Solange eine Platte chartet, laufen Tantiemen. Dazu eine Label-Gesamtliste nach Ruhm.
 */
final class Charts
{
    private const HOUR = 3600;

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

    /** Bester aktueller Platz einer Band in den Top 10 oder null. */
    public static function bandRank(Db $db, int $bandId): ?int
    {
        $rank = $db->value(
            "SELECT MIN(rank_last) FROM records WHERE band_id = ? AND status = 'done' AND rank_last IS NOT NULL AND rank_last <= ? AND finished_at >= ?",
            [$bandId, Balance::CHART_SIZE, Clock::now() - Balance::CHART_MAX_AGE]
        );
        return $rank === null ? null : (int) $rank;
    }

    /** Bonus nach Platz: Platz 1 × (1 + $bonus), gleichmäßig weniger bis Platz 10, außerhalb × 1. */
    public static function rankFactor(?int $rank, float $bonus): float
    {
        return $rank === null || $rank > Balance::CHART_SIZE ? 1.0 : 1 + $bonus * (Balance::CHART_SIZE + 1 - $rank) / Balance::CHART_SIZE;
    }

    /** Verlauf ohne Schub: Einstieg bei 65 %, Höhepunkt nach CHART_PEAK, danach fallend. */
    public static function curve(int $age): float
    {
        if ($age < 0) {
            return 0.0;
        }
        $x = $age / Balance::CHART_PEAK;
        return ($x + 0.5) / 1.5 * exp((1 - $x) / 1.5);
    }

    /** Chartpunkte einer Platte zum Zeitpunkt $t: Verlauf × Grundpunkte + nachlassender Schub aus Gigs und Promo. */
    public static function pointsAt(array $row, int $t): float
    {
        $boost = (float) ($row['boost'] ?? 0);
        $boostAt = (int) ($row['boost_at'] ?? 0);
        $push = $boost > 0 && $t >= $boostAt ? $boost * 0.5 ** (($t - $boostAt) / Balance::CHART_BOOST_HALF) : 0.0;
        return (float) $row['chart_points'] * self::curve($t - (int) $row['finished_at']) + $push;
    }

    /**
     * Alle Platten, die gerade charten, nach Platz. Die Plätze werden alle CHART_INTERVAL Sekunden neu vergeben
     * (eine „Chartwoche“) – oder sofort, wenn eine Platte einsteigt oder Schub bekommt. Dazwischen bleiben sie stehen,
     * die Punkte laufen weiter. „Vorher“ ist der Platz zu Beginn der laufenden Chartwoche.
     */
    public static function albums(Db $db, ?int $labelId = null): array
    {
        $now = Clock::now();
        $rows = $db->all(
            "SELECT r.id, r.title, r.songs_total, r.perfect, r.chart_points, r.finished_at, r.boost, r.boost_at, r.topped,
                    r.label_id, r.band_id, r.rank_last, r.rank_prev, r.rank_best, b.name AS band_name, l.name AS label_name
             FROM records r
             JOIN labels l ON l.id = r.label_id
             LEFT JOIN bands b ON b.id = r.band_id
             WHERE r.status = 'done' AND r.chart_points > 0 AND r.finished_at >= ?",
            [$now - Balance::CHART_MAX_AGE]
        );
        $epoch = intdiv($now, Balance::CHART_INTERVAL);
        $stored = (int) self::meta($db, 'chart_epoch');
        if ($stored !== $epoch && self::claimMeta($db, 'chart_epoch', (string) $stored, (string) $epoch)) {
            self::rerank($db, $rows, $now, true);
        } elseif (self::claimMeta($db, 'chart_dirty', '1', '0')) {
            self::rerank($db, $rows, $now, false);
        }

        $out = [];
        foreach ($rows as $row) {
            if ($row['rank_last'] === null) {
                continue;
            }
            $rank = (int) $row['rank_last'];
            $prev = $row['rank_prev'] === null ? null : (int) $row['rank_prev'];
            $points = self::pointsAt($row, $now);
            $out[] = [
                'recordId' => (int) $row['id'],
                'bandId' => $row['band_id'] === null ? null : (int) $row['band_id'],
                'title' => $row['title'],
                'band' => $row['band_name'] ?? 'aufgelöste Band',
                'label' => $row['label_name'],
                'format' => (int) $row['songs_total'] >= Balance::CHART_LP_SONGS ? 'LP' : 'EP',
                'perfect' => (bool) $row['perfect'],
                'hours' => intdiv($now - (int) $row['finished_at'], self::HOUR),
                'points' => round($points, 1),
                'rank' => $rank,
                'placed' => $rank <= Balance::CHART_SIZE,
                'prev' => $prev,
                'best' => $row['rank_best'] === null ? $rank : (int) $row['rank_best'],
                'trend' => $prev === null || $prev > Balance::CHART_SIZE ? 'new' : ($rank < $prev ? 'up' : ($rank > $prev ? 'down' : 'same')),
                'perHour' => round($points * Balance::ROYALTY_MONEY * self::rankFactor($rank, Balance::CHART_RANK_BONUS), 1),
                'own' => $labelId !== null && (int) $row['label_id'] === $labelId,
                'topped' => (bool) $row['topped'],
                'labelId' => (int) $row['label_id'],
            ];
        }
        usort($out, static fn ($a, $b) => $a['rank'] <=> $b['rank']);
        return $out;
    }

    /** Beim nächsten Aufruf neu sortieren (neue Platte, Schub). */
    public static function markDirty(Db $db): void
    {
        self::setMeta($db, 'chart_dirty', '1');
    }

    /**
     * Plätze neu vergeben und Nachrichten schreiben: Einstieg in die Top 10, Platz 1, aus den Top 10 gefallen.
     * @param list<array> $rows werden mit den neuen Plätzen aktualisiert
     */
    private static function rerank(Db $db, array &$rows, int $now, bool $newWeek): void
    {
        $points = [];
        foreach ($rows as $i => $row) {
            $p = self::pointsAt($row, $now);
            if ($p >= Balance::CHART_MIN_POINTS) {
                $points[$i] = $p;
            }
        }
        arsort($points);
        $ranks = [];
        $rank = 0;
        foreach (array_keys($points) as $i) {
            $ranks[$i] = ++$rank;
        }
        $size = Balance::CHART_SIZE;
        foreach ($rows as $i => &$row) {
            $old = $row['rank_last'] === null ? null : (int) $row['rank_last'];
            $new = $ranks[$i] ?? null;
            $prev = $newWeek ? $old : ($row['rank_prev'] === null ? null : (int) $row['rank_prev']);
            $best = $row['rank_best'] === null ? $new : ($new === null ? (int) $row['rank_best'] : min((int) $row['rank_best'], $new));
            if ($old !== $new || $newWeek) {
                $db->update('records', ['rank_last' => $new, 'rank_prev' => $prev, 'rank_best' => $best], ['id' => (int) $row['id']]);
            }
            $repo = new Repo($db, (int) $row['label_id']);
            $bandId = $row['band_id'] === null ? null : (int) $row['band_id'];
            if ($new === 1 && $old !== 1) {
                $repo->log('chart1', sprintf('„%s“ steht auf Platz 1 der Charts!', $row['title']), $bandId);
            } elseif ($new !== null && $new <= $size && ($old === null || $old > $size)) {
                $repo->log('chart', sprintf('„%s“ steigt auf Platz %d der Charts ein.', $row['title'], $new), $bandId);
            } elseif ($old !== null && $old <= $size && ($new === null || $new > $size)) {
                $repo->log('chart', sprintf('„%s“ ist aus den Top %d gefallen.', $row['title'], $size), $bandId);
            }
            $row['rank_last'] = $new;
            $row['rank_prev'] = $prev;
            $row['rank_best'] = $best;
        }
        unset($row);
    }

    private static function meta(Db $db, string $name): ?string
    {
        $value = $db->value('SELECT v FROM meta WHERE k = ?', [$name]);
        return $value === null ? null : (string) $value;
    }

    private static function setMeta(Db $db, string $name, string $value): void
    {
        if ($db->exec('UPDATE meta SET v = ? WHERE k = ?', [$value, $name]) === 0 && self::meta($db, $name) === null) {
            $db->insert('meta', ['k' => $name, 'v' => $value]);
        }
    }

    /** Setzt den Wert nur, wenn er noch $from ist – so sortiert bei gleichzeitigen Aufrufen nur einer neu. */
    private static function claimMeta(Db $db, string $name, string $from, string $to): bool
    {
        if (self::meta($db, $name) === null) {
            $db->insert('meta', ['k' => $name, 'v' => $from]);
        }
        return $db->exec('UPDATE meta SET v = ? WHERE k = ? AND v = ?', [$to, $name, $from]) > 0;
    }

    public static function build(Db $db, int $labelId): array
    {
        $albums = self::albums($db, $labelId);

        // Platz 1 gibt einmalig einen Scout-Gutschein – aber nur, wenn es Konkurrenz gibt.
        $competitors = count(array_unique(array_column($albums, 'labelId')));
        if ($albums && $albums[0]['rank'] === 1 && !$albums[0]['topped'] && $competitors >= Balance::CHART_TOP_MIN_LABELS) {
            $top = $albums[0];
            if ($db->exec('UPDATE records SET topped = 1 WHERE id = ? AND topped = 0', [$top['recordId']]) > 0) {
                Scout::grant(new Repo($db, $top['labelId']), sprintf('„%s“ steht auf Platz 1 der Charts!', $top['title']));
            }
        }
        foreach ($albums as &$album) {
            unset($album['topped'], $album['labelId'], $album['bandId']);
        }
        unset($album);
        $albums = array_values(array_filter($albums, static fn ($a) => $a['placed'] || $a['own']));

        return ['albums' => $albums, 'labels' => self::rank(self::labels($db, $labelId))];
    }

    /**
     * Gigs und Promo schieben die neueste Platte der Band an, solange sie chartet. Vorher werden die Tantiemen
     * bis jetzt abgerechnet, damit der Schub erst ab jetzt zählt.
     */
    public static function boost(Repo $repo, int $bandId, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }
        $now = Clock::now();
        $row = $repo->db->one(
            "SELECT id, chart_points, finished_at, boost, boost_at FROM records
             WHERE band_id = ? AND status = 'done' AND chart_points > 0 AND finished_at >= ? ORDER BY finished_at DESC",
            [$bandId, $now - Balance::CHART_MAX_AGE]
        );
        if ($row === null || self::pointsAt($row, $now) < Balance::CHART_MIN_POINTS) {
            return;
        }
        self::payRoyalties($repo->db, $repo->labelId);
        $left = (float) $row['boost'] > 0 ? (float) $row['boost'] * 0.5 ** (($now - (int) $row['boost_at']) / Balance::CHART_BOOST_HALF) : 0.0;
        $repo->db->update('records', ['boost' => $left + $amount, 'boost_at' => $now], ['id' => (int) $row['id']]);
        self::markDirty($repo->db);
    }

    /**
     * Tantiemen laufen ständig: Bei jedem Aufruf wird abgerechnet, was seit dem letzten Mal angefallen ist
     * (Summe der Chartpunkte über die Zeit). Geld ans Label, Ruhm an die aktuellen Mitglieder der Band.
     * Bruchteile eines ₲ werden aufgehoben, bis ein ganzer zusammenkommt.
     */
    public static function payRoyalties(Db $db, int $labelId): void
    {
        $now = Clock::now();
        $rows = $db->all(
            "SELECT id, band_id, title, chart_points, finished_at, boost, boost_at, rank_last, royalties_until, royalty_carry FROM records
             WHERE label_id = ? AND status = 'done' AND chart_points > 0 AND royalties_until < ? AND finished_at >= ?",
            [$labelId, $now, $now - Balance::CHART_MAX_AGE - self::HOUR]
        );
        foreach ($rows as $row) {
            $db->transaction(static function () use ($db, $labelId, $row, $now): void {
                $from = max((int) $row['royalties_until'], (int) $row['finished_at']);
                $claimed = $db->exec('UPDATE records SET royalties_until = ? WHERE id = ? AND royalties_until = ?', [$now, $row['id'], $row['royalties_until']]);
                if ($claimed === 0) {
                    return;
                }
                // Höhere Platzierung, mehr Tantiemen: Platz 1 doppelt, bis Platz 10 abnehmend (Platz zum Abrechnungszeitpunkt).
                $rank = $row['rank_last'] === null ? null : (int) $row['rank_last'];
                $pointHours = self::pointSeconds($row, $from, $now) / self::HOUR * self::rankFactor($rank, Balance::CHART_RANK_BONUS);
                if ($pointHours <= 0) {
                    return;
                }
                $money = $pointHours * Balance::ROYALTY_MONEY + (float) $row['royalty_carry'];
                $paid = (int) floor($money);
                $db->update('records', ['royalty_carry' => $money - $paid], ['id' => (int) $row['id']]);
                $repo = new Repo($db, $labelId);
                $repo->earnDaily($paid, sprintf('Tantiemen „%s“', $row['title']));
                $members = $row['band_id'] === null ? [] : $repo->members((int) $row['band_id']);
                if ($members) {
                    $fame = $pointHours * Balance::ROYALTY_FAME;
                    $repo->addBandFame((int) $row['band_id'], array_fill_keys(array_column($members, 'id'), $fame / count($members)));
                }
            });
        }
    }

    /** Chartpunkte × Sekunden zwischen $a und $b, nur solange die Platte chartet (Simpson-Regel, höchstens 2 Minuten je Schritt). */
    private static function pointSeconds(array $row, int $a, int $b): float
    {
        if ($b <= $a) {
            return 0.0;
        }
        $steps = max(2, min(4000, (int) ceil(($b - $a) / 120)));
        $steps += $steps % 2;
        $h = ($b - $a) / $steps;
        $f = static function (float $t) use ($row): float {
            $p = self::pointsAt($row, (int) round($t));
            return $p >= Balance::CHART_MIN_POINTS ? $p : 0.0;
        };
        $sum = $f($a) + $f($b);
        for ($i = 1; $i < $steps; $i++) {
            $sum += $f($a + $i * $h) * ($i % 2 ? 4 : 2);
        }
        return $sum * $h / 3;
    }

    /** Regelmäßige Einnahmen: alle eigenen Platten, die gerade charten, und der Kontoauszug. */
    public static function income(Db $db, int $labelId, ?array $albums = null): array
    {
        $records = [];
        $bands = [];
        foreach ($albums ?? self::albums($db, $labelId) as $album) {
            if (!$album['own']) {
                continue;
            }
            if ($album['bandId'] !== null) {
                $band = &$bands[$album['bandId']];
                $factor = self::rankFactor($album['rank'], Balance::CHART_RANK_BONUS);
                $band['moneyPerHour'] = ($band['moneyPerHour'] ?? 0) + $album['points'] * Balance::ROYALTY_MONEY * $factor;
                $band['famePerHour'] = ($band['famePerHour'] ?? 0) + $album['points'] * Balance::ROYALTY_FAME * $factor;
                unset($band);
            }
            $records[] = [
                'title' => $album['title'],
                'band' => $album['band'],
                'points' => $album['points'],
                'rank' => $album['rank'],
                'trend' => $album['trend'],
                'perHour' => $album['perHour'],
                'placed' => $album['placed'],
                'factor' => self::rankFactor($album['rank'], Balance::CHART_RANK_BONUS),
            ];
        }
        $points = array_sum(array_map(static fn ($r) => $r['points'] * $r['factor'], $records));
        $ledger = $db->all('SELECT amount, text, created_at FROM ledger WHERE label_id = ? ORDER BY id DESC LIMIT 40', [$labelId]);
        return [
            // Für die laufende Anzeige im Browser (Geld tickt zwischen zwei Abrufen weiter).
            'moneyPerHour' => round($points * Balance::ROYALTY_MONEY, 2),
            'famePerHour' => round($points * Balance::ROYALTY_FAME, 3),
            'bands' => (object) $bands,
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
