<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Clock;
use Signed\Db;

/**
 * Album-Charts über alle Spieler, gerechnet in Stunden: Eine Platte steigt mit ihren vollen Punkten ein und verliert
 * dann jede Stunde denselben Anteil; höher kommt sie nur durch Schub aus Gigs und Promo. Weil jede Platte ihren eigenen Verlauf hat,
 * überholen sie sich gegenseitig. Gigs und Promo der Band schieben die neueste Platte zusätzlich an (Schub, der
 * mit der Zeit nachlässt). Solange eine Platte chartet, laufen Tantiemen. Dazu eine Label-Gesamtliste nach Ruhm.
 */
final class Charts
{
    private const HOUR = 3600;

    /**
     * Chartpunkte einer frisch fertigen Platte – nur aus der Platte selbst (Qualität, Sound, Länge, perfekt).
     * Bewusst ohne Bandruhm: Sonst bringt Ruhm Plätze und Plätze bringen Ruhm, und wer oben ist, bleibt oben.
     */
    public static function points(int $songs, float $quality, float $sound, bool $perfect): float
    {
        $format = $songs >= Balance::CHART_LP_SONGS
            ? 1 + Balance::CHART_LP_PER_SONG * ($songs - Balance::CHART_LP_SONGS)
            : Balance::CHART_EP_FACTOR;
        return round(Balance::CHART_BASE * $quality ** 2 * BandCalc::soundFactor($sound) * $format
            * ($perfect ? Balance::CHART_PERFECT : 1), 1);
    }

    /** Verlauf ohne Schub: Einstieg mit den vollen Punkten, dann jede Stunde CHART_DECAY weniger – für jede Platte gleich. */
    public static function curve(int $age): float
    {
        if ($age < 0) {
            return 0.0;
        }
        return (1 - Balance::CHART_DECAY) ** ($age / self::HOUR);
    }

    /** Chartpunkte einer Platte zum Zeitpunkt $t: Grundpunkte × Verlauf × (1 + nachlassender Schub aus Gigs und Promo). */
    public static function pointsAt(array $row, int $t): float
    {
        return (float) $row['chart_points'] * self::curve($t - (int) $row['finished_at']) * (1 + self::push($row, $t));
    }

    /** Schub zum Zeitpunkt $t (Anteil, höchstens CHART_BOOST_MAX). */
    private static function push(array $row, int $t): float
    {
        $boost = (float) ($row['boost'] ?? 0);
        $boostAt = (int) ($row['boost_at'] ?? 0);
        return $boost > 0 && $t >= $boostAt ? $boost * 0.5 ** (($t - $boostAt) / Balance::CHART_BOOST_HALF) : 0.0;
    }

    /**
     * Alle Platten, die gerade charten, nach Platz. Die Plätze werden alle CHART_INTERVAL Sekunden neu vergeben
     * (eine „Chartwoche“) – oder sofort, wenn eine Platte einsteigt oder Schub bekommt. Dazwischen bleiben sie stehen,
     * die Punkte laufen weiter. „Vorher“ ist der Platz zu Beginn der laufenden Chartwoche.
     */
    public static function albums(Db $db, ?int $labelId = null): array
    {
        if (BandCore::$live) {
            return self::coreAlbums($db, $labelId);
        }
        $now = Clock::now();
        $rows = $db->all(
            "SELECT r.id, r.title, r.cover, r.songs_total, r.perfect, r.chart_points, r.finished_at, r.boost, r.boost_at, r.topped,
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
                'cover' => (json_decode((string) ($row['cover'] ?? ''), true) ?: [])['spec'] ?? null,
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
                'perHour' => round($points * Balance::ROYALTY_MONEY, 1),
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
        // Alle Platten zählen; jede Band spielt und bewirbt ihre neueste (promoted), ältere fallen so von selbst.
        $ranks = [];
        $rank = 0;
        foreach (array_keys($points) as $i) {
            if ($rank >= Balance::CHART_LIST) {
                break;
            }
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

    /** Platz 1 wird einmal vermerkt (Meilenstein, WikiSignia, Auszeichnung) – aber nur, wenn es Konkurrenz gibt. */
    public static function markTop(Db $db, array $albums): void
    {
        $competitors = count(array_unique(array_column($albums, 'labelId')));
        if (!$albums || $albums[0]['rank'] !== 1 || $albums[0]['topped'] || $competitors < Balance::CHART_TOP_MIN_LABELS) {
            return;
        }
        $top = $albums[0];
        if ($db->exec('UPDATE records SET topped = 1 WHERE id = ? AND topped = 0', [$top['recordId']]) === 1 && $top['bandId'] !== null) {
            $repo = new Repo($db, $top['labelId']);
            Awards::numberOne($repo, $top['bandId'], $top['recordId'], $top['title']);
            if (BandCore::$live) {
                Inbox::eventRequest($repo, $top['bandId'], sprintf('weil „%s“ auf Platz 1 der Charts steht', $top['title']));
            }
        }
    }

    public static function build(Db $db, int $labelId): array
    {
        $albums = self::albums($db, $labelId);
        if (BandCore::$live) {
            self::markTop($db, $albums);
            foreach ($albums as &$album) {
                unset($album['topped'], $album['labelId']);
            }
            unset($album);
            // Neuer Kern: eine Label-Bestenliste (alle Fans), dazu die Künstler-Listen Fan-Lieblinge (Fans) und Sound-Monster (Sound)
            return [
                'core' => true,
                'albums' => $albums,
                'nextAt' => (intdiv(Clock::now(), 60) + 1) * 60,
                'labels' => self::rank(self::coreLabels($db, $labelId)),
                'bands' => self::rank(self::coreActs($db, $labelId, 'fans'), Balance::CHART_LIST),
                'sound' => self::rank(self::coreActs($db, $labelId, 'sound'), Balance::CHART_LIST),
            ];
        }

        self::markTop($db, $albums);
        foreach ($albums as &$album) {
            unset($album['topped'], $album['labelId']);
        }
        unset($album);

        return [
            'albums' => $albums,
            'labels' => self::rank(self::labels($db, $labelId)),
            'bands' => self::rank(self::hallOfFame($db, $labelId), Balance::CHART_LIST),
        ];
    }

    /**
     * Gigs und Promo schieben die neueste Platte der Band an, solange sie chartet. Vorher werden die Tantiemen
     * bis jetzt abgerechnet, damit der Schub erst ab jetzt zählt.
     */
    /** Die Platte, die die Band gerade auf Gigs spielt und bewirbt: ihre neueste, solange sie sich noch verkauft. */
    public static function promoted(Db $db, int $bandId, ?int $now = null): ?array
    {
        $now ??= Clock::now();
        // Immer die neueste Platte; ältere spielt die Band erst wieder, wenn sie remastert sind (kommt später).
        $rows = $db->all(
            "SELECT r.id, r.chart_points, r.finished_at, r.boost, r.boost_at FROM records r
             WHERE r.band_id = ? AND r.status = 'done' AND r.chart_points > 0 AND r.finished_at >= ? ORDER BY r.finished_at DESC, r.id DESC",
            [$bandId, $now - Balance::CHART_MAX_AGE]
        );
        return $rows[0] ?? null;
    }

    public static function boost(Repo $repo, int $bandId, float $amount): void
    {
        // Neuer Kern: keine Chartpunkte mehr; Schübe gehen über den Hype (BandCore::boost)
        if ($amount <= 0 || BandCore::$live) {
            return;
        }
        $now = Clock::now();
        $row = self::promoted($repo->db, $bandId, $now);
        if ($row === null) {
            return;
        }
        self::payRoyalties($repo->db, $repo->labelId);
        $boost = min(Balance::CHART_BOOST_MAX, self::push($row, $now) + $amount);
        $repo->db->update('records', ['boost' => $boost, 'boost_at' => $now], ['id' => (int) $row['id']]);
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
            "SELECT id, band_id, title, chart_points, finished_at, boost, boost_at, rank_last, royalties_until, royalty_carry, sold FROM records
             WHERE label_id = ? AND status = 'done' AND chart_points > 0 AND royalties_until < ? AND finished_at >= ?
               AND (rank_last IS NULL OR rank_last <= ?)",
            [$labelId, $now, $now - Balance::CHART_MAX_AGE - self::HOUR, Balance::ROYALTY_RANKS]
        );
        foreach ($rows as $row) {
            $db->transaction(static function () use ($db, $labelId, $row, $now): void {
                $from = max((int) $row['royalties_until'], (int) $row['finished_at']);
                $claimed = $db->exec('UPDATE records SET royalties_until = ? WHERE id = ? AND royalties_until = ?', [$now, $row['id'], $row['royalties_until']]);
                if ($claimed === 0) {
                    return;
                }
                // Geld und Ruhm nur nach Punkten: Die Punkte sind schon die Platzierung, ein Platz-Bonus wäre doppelt.
                $pointHours = self::pointSeconds($row, $from, $now) / self::HOUR;
                if ($pointHours <= 0) {
                    return;
                }
                $money = $pointHours * Balance::ROYALTY_MONEY + (float) $row['royalty_carry'];
                $paid = (int) floor($money);
                // Verkaufte Stück (für Gold, Platin, Diamant)
                $sold = (float) $row['sold'] + $pointHours * Balance::AWARDS['copies'];
                $db->update('records', ['royalty_carry' => $money - $paid, 'sold' => round($sold)], ['id' => (int) $row['id']]);
                $repo = new Repo($db, $labelId);
                if ($row['band_id'] !== null) {
                    Awards::sales($repo, (int) $row['band_id'], (int) $row['id'], $row['title'], (float) $row['sold'], $sold);
                }
                $repo->earnDaily($paid, sprintf('Tantiemen „%s“', $row['title']));
                $members = $row['band_id'] === null ? [] : $repo->members((int) $row['band_id']);
                if ($members) {
                    $fame = $pointHours * Balance::ROYALTY_FAME;
                    $repo->addBandFame((int) $row['band_id'], array_fill_keys(array_column($members, 'id'), $fame / count($members)));
                }
            });
        }
        self::payCatalog($db, $labelId, $now);
    }

    /** Katalog: Alle Platten des Labels verkaufen sich dauerhaft ein wenig (siehe Balance::CATALOG_MONEY). */
    private static function payCatalog(Db $db, int $labelId, int $now): void
    {
        $rows = $db->all(
            "SELECT id, finished_at, chart_points, catalog_until FROM records WHERE label_id = ? AND status = 'done' AND chart_points > 0 AND catalog_until < ?",
            [$labelId, $now - 60]
        );
        $total = 0.0;
        foreach ($rows as $row) {
            $from = max((int) $row['catalog_until'], (int) $row['finished_at']);
            if ($db->exec('UPDATE records SET catalog_until = ? WHERE id = ? AND catalog_until = ?', [$now, $row['id'], $row['catalog_until']]) === 0) {
                continue;
            }
            $total += ($now - $from) / self::HOUR * (float) $row['chart_points'] * Balance::CATALOG_MONEY;
        }
        // Bruchteile sammeln sich im Label, ein Eintrag pro Tag im Kontoauszug.
        $carry = (float) ($db->value('SELECT v FROM meta WHERE k = ?', ["catalog_carry_$labelId"]) ?? 0) + $total;
        $paid = (int) floor($carry);
        self::setMeta($db, "catalog_carry_$labelId", (string) ($carry - $paid));
        (new Repo($db, $labelId))->earnDaily($paid, 'Katalog: ältere Platten');
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
        if (BandCore::$live) {
            return self::coreIncome($db, $labelId, $albums ?? self::albums($db, $labelId));
        }
        $albums ??= self::albums($db, $labelId);
        $records = [];
        $bands = [];
        foreach ($albums as $album) {
            if (!$album['own']) {
                continue;
            }
            if ($album['bandId'] !== null) {
                $band = &$bands[$album['bandId']];
                $band['moneyPerHour'] = ($band['moneyPerHour'] ?? 0) + $album['points'] * Balance::ROYALTY_MONEY;
                $band['famePerHour'] = ($band['famePerHour'] ?? 0) + $album['points'] * Balance::ROYALTY_FAME;
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
            ];
        }
        // Eigene Platten ohne Chartplatz (eine pro Band chartet) verkaufen sich trotzdem weiter.
        $now = Clock::now();
        $listed = array_column(array_filter($albums, static fn ($a) => $a['own']), 'recordId');
        $catalog = 0.0;
        foreach ($db->all(
            "SELECT r.id, r.band_id, r.title, r.chart_points, r.finished_at, r.boost, r.boost_at, b.name AS band_name FROM records r
             LEFT JOIN bands b ON b.id = r.band_id WHERE r.label_id = ? AND r.status = 'done' AND r.chart_points > 0",
            [$labelId]
        ) as $row) {
            $perHour = (float) $row['chart_points'] * Balance::CATALOG_MONEY;
            $catalog += $perHour;
            if ($row['band_id'] !== null) {
                $bands[(int) $row['band_id']]['moneyPerHour'] = ($bands[(int) $row['band_id']]['moneyPerHour'] ?? 0) + $perHour;
            }
            $p = self::pointsAt($row, $now);
            if (in_array((int) $row['id'], $listed, true) || (int) $row['finished_at'] < $now - Balance::CHART_MAX_AGE || $p < Balance::CHART_MIN_POINTS) {
                continue;
            }
            if ($row['band_id'] !== null) {
                $band = &$bands[(int) $row['band_id']];
                $band['moneyPerHour'] = ($band['moneyPerHour'] ?? 0) + $p * Balance::ROYALTY_MONEY;
                $band['famePerHour'] = ($band['famePerHour'] ?? 0) + $p * Balance::ROYALTY_FAME;
                unset($band);
            }
            $records[] = [
                'title' => $row['title'], 'band' => $row['band_name'] ?? 'aufgelöste Band', 'points' => round($p, 1),
                'rank' => null, 'trend' => null, 'perHour' => round($p * Balance::ROYALTY_MONEY, 1), 'placed' => false,
            ];
        }
        $points = array_sum(array_column($records, 'points'));
        $ledger = $db->all('SELECT amount, text, created_at FROM ledger WHERE label_id = ? ORDER BY id DESC LIMIT 40', [$labelId]);
        return [
            // Für die laufende Anzeige im Browser (Geld tickt zwischen zwei Abrufen weiter).
            'moneyPerHour' => round($points * Balance::ROYALTY_MONEY + $catalog, 2),
            'catalogPerHour' => round($catalog, 2),
            'famePerHour' => round($points * Balance::ROYALTY_FAME, 3),
            'bands' => (object) $bands,
            'records' => $records,
            'ledger' => array_map(static fn ($l) => ['amount' => (int) $l['amount'], 'text' => $l['text'], 'at' => (int) $l['created_at']], $ledger),
        ];
    }

    /** Label-Gesamtliste: Label-Ruhm wie überall im Spiel (BandCalc::labelFame: große Bands zählen stärker). */
    private static function labels(Db $db, int $labelId): array
    {
        $bands = $db->all(
            'SELECT b.label_id, l.name, b.fame_own + COALESCE(SUM(m.fame), 0) AS fame
             FROM bands b
             JOIN labels l ON l.id = b.label_id
             LEFT JOIN musicians m ON m.band_id = b.id
             WHERE b.retired_at IS NULL
             GROUP BY b.id, b.label_id, l.name, b.fame_own'
        );
        $labels = [];
        foreach ($bands as $r) {
            $labels[(int) $r['label_id']]['name'] = $r['name'];
            $labels[(int) $r['label_id']]['fames'][] = (float) $r['fame'];
        }
        $out = [];
        foreach ($labels as $id => $l) {
            $out[] = ['label' => $l['name'], 'bands' => count($l['fames']), 'points' => BandCalc::labelFame($l['fames']), 'own' => $id === $labelId];
        }
        return $out;
    }

    /** Hall of Fame: alle Bands aller Labels nach Ruhm (am Namen plus Bekanntheit der Mitglieder, wie BandCalc::bandFame). */
    private static function hallOfFame(Db $db, int $labelId): array
    {
        $rows = $db->all(
            'SELECT b.id, b.name, b.fame_own, b.label_id, l.name AS label_name, COALESCE(SUM(m.fame), 0) AS member_fame
             FROM bands b
             JOIN labels l ON l.id = b.label_id
             LEFT JOIN musicians m ON m.band_id = b.id
             WHERE b.retired_at IS NULL
             GROUP BY b.id, b.name, b.fame_own, b.label_id, l.name'
        );
        return array_map(static fn ($r) => [
            'bandId' => (int) $r['id'],
            'band' => $r['name'],
            'label' => $r['label_name'],
            'points' => round((float) $r['fame_own'] + (float) $r['member_fame'], 1),
            'own' => (int) $r['label_id'] === $labelId,
        ], $rows);
    }

    /** Sortiert nach Punkten, vergibt Plätze und kürzt auf die Top-Liste (eigene Einträge bleiben immer). */
    private static function rank(array $list, int $size = Balance::CHART_SIZE): array
    {
        usort($list, static fn ($a, $b) => $b['points'] <=> $a['points']);
        $out = [];
        foreach ($list as $index => $entry) {
            if ($index < $size || $entry['own']) {
                $out[] = $entry + ['rank' => $index + 1];
            }
        }
        return $out;
    }

    // ---------- Neuer Kern ----------

    /**
     * Neuer Kern: Platten nach ihren Verkäufen der letzten 24 Stunden (durch den Hype, ohne Katalog), jede Minute neu sortiert und sofort,
     * wenn eine Platte erscheint. Jede volle Stunde werden vorher alle Bands mit laufenden Platten weitergerechnet (das kostet bei vielen
     * Labels spürbar Zeit, darum nicht jede Minute); dazwischen zählt jede Platte bis dahin, wo ihre Band zuletzt gerechnet wurde – die
     * eigenen bei jedem Aufruf. „Vorher“ ist der Platz zur letzten vollen Stunde; Nachrichten (Einstieg, Platz 1, herausgefallen) zur vollen Stunde.
     */
    private static function coreAlbums(Db $db, ?int $labelId): array
    {
        $now = Clock::now();
        $hour = intdiv($now, self::HOUR);
        $minute = intdiv($now, 60);
        $storedHour = (int) self::meta($db, 'core_chart_hour');
        $storedMinute = (int) self::meta($db, 'core_chart_minute');
        if ($storedHour !== $hour && self::claimMeta($db, 'core_chart_hour', (string) $storedHour, (string) $hour)) {
            BandCore::settleAll($db, $hour * self::HOUR);
            self::coreRerank($db, true);
            self::setMeta($db, 'core_chart_minute', (string) $minute);
            self::setMeta($db, 'chart_dirty', '0');
        } elseif (($storedMinute !== $minute && self::claimMeta($db, 'core_chart_minute', (string) $storedMinute, (string) $minute))
            || (self::meta($db, 'chart_dirty') === '1' && self::claimMeta($db, 'chart_dirty', '1', '0'))) {
            self::coreRerank($db, false);
        }
        $sales = self::coreSales($db);
        $rows = $db->all(
            "SELECT r.id, r.title, r.cover, r.songs_total, r.perfect, r.finished_at, r.topped, r.core, r.label_id, r.band_id,
                    r.rank_last, r.rank_prev, r.rank_best, b.name AS band_name, l.name AS label_name
             FROM records r
             JOIN labels l ON l.id = r.label_id
             LEFT JOIN bands b ON b.id = r.band_id
             WHERE r.status = 'done' AND r.core IS NOT NULL AND r.rank_last IS NOT NULL"
        );
        $out = [];
        foreach ($rows as $row) {
            $rank = (int) $row['rank_last'];
            $prev = $row['rank_prev'] === null ? null : (int) $row['rank_prev'];
            $hype = (float) ((json_decode((string) $row['core'], true) ?: [])['hype'] ?? 0.0);
            $out[] = [
                'recordId' => (int) $row['id'],
                'bandId' => $row['band_id'] === null ? null : (int) $row['band_id'],
                'title' => $row['title'],
                'cover' => (json_decode((string) ($row['cover'] ?? ''), true) ?: [])['spec'] ?? null,
                'band' => $row['band_name'] ?? 'aufgelöste Band',
                'label' => $row['label_name'],
                'perfect' => (bool) $row['perfect'],
                'hours' => intdiv($now - (int) $row['finished_at'], self::HOUR),
                'sold' => (int) round($sales[(int) $row['id']] ?? 0.0),
                'hype' => round($hype, 1),
                'flame' => Core\Hype::flame($hype),
                'rank' => $rank,
                'placed' => $rank <= Balance::CHART_SIZE,
                'prev' => $prev,
                'best' => $row['rank_best'] === null ? $rank : (int) $row['rank_best'],
                'trend' => $prev === null || $prev > Balance::CHART_SIZE ? 'new' : ($rank < $prev ? 'up' : ($rank > $prev ? 'down' : 'same')),
                'own' => $labelId !== null && (int) $row['label_id'] === $labelId,
                'topped' => (bool) $row['topped'],
                'labelId' => (int) $row['label_id'],
            ];
        }
        usort($out, static fn ($a, $b) => $a['rank'] <=> $b['rank']);
        return $out;
    }

    /**
     * Verkäufe durch den Hype je laufender Platte in den 24 Stunden bis dahin, wo ihre Band zuletzt gerechnet wurde (aus den Chart-Stunden;
     * die angebrochene Stunde am Anfang zählt anteilig, die Verkäufe sind über die Stunde gleich verteilt). Dazu, was in den letzten 24 Stunden
     * erschienen ist, auch ohne Verkäufe: Eine neue Platte steht sofort in den Charts.
     * @return array<int, float> Platte => Stück
     */
    private static function coreSales(Db $db): array
    {
        $now = Clock::now();
        $ends = [];
        $young = [];
        foreach ($db->all(
            'SELECT r.id, r.finished_at, b.core_at FROM records r LEFT JOIN bands b ON b.id = r.band_id
             WHERE r.status = ? AND r.core IS NOT NULL AND (r.core_active = 1 OR r.finished_at > ?)',
            ['done', $now - 86400]
        ) as $r) {
            $id = (int) $r['id'];
            $ends[$id] = min($now, max((int) ($r['core_at'] ?? 0), (int) $r['finished_at']));
            $young[$id] = (int) $r['finished_at'] > $now - 86400;
        }
        if (!$ends) {
            return [];
        }
        $out = array_fill_keys(array_keys($ends), 0.0);
        $from = intdiv(min($ends) - 86400, self::HOUR);
        foreach ($db->all('SELECT record_id, hour, sold FROM chart_hours WHERE hour >= ?', [$from]) as $h) {
            $id = (int) $h['record_id'];
            if (!isset($ends[$id])) {
                continue;
            }
            $start = $ends[$id] - 86400;
            $a = (int) $h['hour'] * self::HOUR;
            if ($a + self::HOUR <= $start || $a >= $ends[$id]) {
                continue;
            }
            $out[$id] += (float) $h['sold'] * ($a < $start ? ($a + self::HOUR - $start) / self::HOUR : 1.0);
        }
        // Ausgelaufen (weniger als endBelow in 24 Stunden) heißt: nicht mehr in den Charts – außer am ersten Tag.
        return array_filter($out, static fn ($sold, $id) => $sold >= Balance::CORE_SALES['endBelow'] || $young[$id], ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Neuer Kern: Plätze neu vergeben (jede Minute und sofort nach einer neuen Platte). Zur vollen Stunde ($hourly) auch „Vorher“ setzen,
     * Nachrichten schreiben (verglichen mit der Stunde davor) und alte Chart-Stunden aufräumen.
     */
    private static function coreRerank(Db $db, bool $hourly): void
    {
        $sales = self::coreSales($db);
        $finished = [];
        if ($sales) {
            foreach ($db->all('SELECT id, finished_at FROM records WHERE id IN (' . implode(',', array_map('intval', array_keys($sales))) . ')') as $r) {
                $finished[(int) $r['id']] = (int) $r['finished_at'];
            }
        }
        // Mehr verkauft zuerst; gleich viel (etwa zwei neue ohne Verkäufe): die neuere zuerst.
        $ids = array_keys($sales);
        usort($ids, static fn ($a, $b) => [$sales[$b], $finished[$b] ?? 0] <=> [$sales[$a], $finished[$a] ?? 0]);
        $ranks = [];
        foreach (array_slice($ids, 0, Balance::CHART_LIST) as $i => $recordId) {
            $ranks[$recordId] = $i + 1;
        }
        $size = Balance::CHART_SIZE;
        $ranked = $ranks ? ' OR id IN (' . implode(',', array_map('intval', array_keys($ranks))) . ')' : '';
        foreach ($db->all("SELECT id, title, label_id, band_id, rank_last, rank_prev, rank_best FROM records
                           WHERE core IS NOT NULL AND (rank_last IS NOT NULL OR rank_prev IS NOT NULL$ranked)") as $row) {
            $recordId = (int) $row['id'];
            $last = $row['rank_last'] === null ? null : (int) $row['rank_last'];
            $new = $ranks[$recordId] ?? null;
            $best = $row['rank_best'] === null ? $new : ($new === null ? (int) $row['rank_best'] : min((int) $row['rank_best'], $new));
            if (!$hourly) {
                if ($new !== $last || $best !== ($row['rank_best'] === null ? null : (int) $row['rank_best'])) {
                    $db->update('records', ['rank_last' => $new, 'rank_best' => $best], ['id' => $recordId]);
                }
                continue;
            }
            // Zur vollen Stunde: verglichen wird mit dem Platz zur vollen Stunde davor.
            $old = $row['rank_prev'] === null ? null : (int) $row['rank_prev'];
            $db->update('records', ['rank_last' => $new, 'rank_prev' => $new, 'rank_best' => $best], ['id' => $recordId]);
            $repo = new Repo($db, (int) $row['label_id']);
            $bandId = $row['band_id'] === null ? null : (int) $row['band_id'];
            if ($new === 1 && $old !== 1) {
                $repo->log('chart1', sprintf('„%s“ steht auf Platz 1 der Charts!', $row['title']), $bandId);
            } elseif ($new !== null && $new <= $size && ($old === null || $old > $size)) {
                $repo->log('chart', sprintf('„%s“ steigt auf Platz %d der Charts ein.', $row['title'], $new), $bandId);
            } elseif ($old !== null && $old <= $size && ($new === null || $new > $size)) {
                $repo->log('chart', sprintf('„%s“ ist aus den Top %d gefallen.', $row['title'], $size), $bandId);
            }
        }
        if ($hourly) {
            $db->exec('DELETE FROM chart_hours WHERE hour < ?', [intdiv(Clock::now(), self::HOUR) - 48]);
        }
    }

    /**
     * Neuer Kern: was die Platten des Labels gerade einbringen (Verkäufe des laufenden Tages durch den Hype und den Katalog, je Stunde),
     * je Band und je Platte, dazu der Kontoauszug. Das Geld tickt im Browser zwischen zwei Abrufen weiter.
     */
    private static function coreIncome(Db $db, int $labelId, array $albums): array
    {
        $ranks = array_column(array_filter($albums, static fn ($a) => $a['own']), null, 'recordId');
        $bands = [];
        $records = [];
        $total = $catalog = 0.0;
        foreach ($db->all(
            'SELECT r.id, r.title, r.band_id, r.core, b.name AS band_name FROM records r LEFT JOIN bands b ON b.id = r.band_id
             WHERE r.label_id = ? AND r.core_active = 1 ORDER BY r.finished_at DESC',
            [$labelId]
        ) as $row) {
            $core = json_decode((string) $row['core'], true) ?: [];
            $price = Core\Sales::price((int) ($core['songs'] ?? Balance::CORE_ALBUM_SONGS));
            $hype = (float) ($core['soldRate'] ?? 0) * $price / 24;
            $cat = (float) ($core['catalogRate'] ?? 0) * $price / 24;
            $total += $hype + $cat;
            $catalog += $cat;
            if ($row['band_id'] !== null) {
                $bands[(int) $row['band_id']]['moneyPerHour'] = round(($bands[(int) $row['band_id']]['moneyPerHour'] ?? 0) + $hype + $cat, 2);
            }
            $album = $ranks[(int) $row['id']] ?? null;
            $records[] = [
                'title' => $row['title'], 'band' => $row['band_name'] ?? 'aufgelöste Band', 'perHour' => round($hype + $cat, 1),
                'soldToday' => (int) round((float) ($core['soldRate'] ?? 0)), 'hype' => round((float) ($core['hype'] ?? 0), 1),
                'flame' => Core\Hype::flame((float) ($core['hype'] ?? 0)), 'ended' => (bool) ($core['ended'] ?? false),
                'rank' => $album['rank'] ?? null, 'trend' => $album['trend'] ?? null, 'placed' => $album['placed'] ?? false,
            ];
        }
        $ledger = $db->all('SELECT amount, text, created_at FROM ledger WHERE label_id = ? ORDER BY id DESC LIMIT 40', [$labelId]);
        return [
            'core' => true,
            'moneyPerHour' => round($total, 2),
            'catalogPerHour' => round($catalog, 2),
            'famePerHour' => 0.0,
            'bands' => (object) $bands,
            'records' => $records,
            'ledger' => array_map(static fn ($l) => ['amount' => (int) $l['amount'], 'text' => $l['text'], 'at' => (int) $l['created_at']], $ledger),
        ];
    }

    /** Neuer Kern: Label-Bestenliste – alle Fans aller Bands des Labels zusammen. */
    private static function coreLabels(Db $db, int $labelId): array
    {
        return array_map(static fn ($r) => [
            'label' => $r['name'], 'bands' => (int) $r['bands'], 'points' => round((float) $r['fans'], 1), 'own' => (int) $r['label_id'] === $labelId,
        ], $db->all(
            'SELECT b.label_id, l.name, COUNT(*) AS bands, SUM(b.fans) AS fans FROM bands b JOIN labels l ON l.id = b.label_id
             WHERE b.retired_at IS NULL GROUP BY b.label_id, l.name'
        ));
    }

    /** Neuer Kern: Künstler-Listen über alle Labels – Fan-Lieblinge (`fans`) oder Sound-Monster (`sound`). */
    private static function coreActs(Db $db, int $labelId, string $by): array
    {
        $column = $by === 'sound' ? 'b.sound' : 'b.fans';
        return array_map(static fn ($r) => [
            'bandId' => (int) $r['id'], 'band' => $r['name'], 'label' => $r['label_name'], 'points' => round((float) $r['points'], 1), 'own' => (int) $r['label_id'] === $labelId,
        ], $db->all(
            "SELECT b.id, b.name, b.label_id, l.name AS label_name, $column AS points FROM bands b JOIN labels l ON l.id = b.label_id WHERE b.retired_at IS NULL"
        ));
    }
}
