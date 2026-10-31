<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;
use Signed\Db;

/**
 * Übergang alter Labels ins neue Spiel (einmalig, Schema-Schritt 53, Zahlen in Balance::TRANSITION):
 * Wechselbörse schließen (Musiker zurück, Gebote zurück aufs Konto), alte Karten und Gutscheine in Geld, offene Jam-Angebote verfallen,
 * Beziehungen aus der gemeinsamen Bandzeit, und die Kartei (Musiker ohne Band) kommt beiseite: Beim ersten Login sucht man sich
 * bis zu `keep` davon aus, die bleiben. Laufende alte Aktionen rechnet das neue Spiel selbst ab (Activities::settleDue).
 * Was passiert ist, steht in labels.transition (JSON); der Browser zeigt damit einmal „Was ist neu?“.
 */
final class Transition
{
    /** Ab diesem Schema-Stand lief schon der Umbau (Testversion): Dann gilt der Übergang nur für Labels mit altem Kram. */
    private const FIRST_NEW = 23;
    /** Herkunft der Musiker, die es auch im neuen Spiel gibt (in der Testversion kein Fall für die Kartei). */
    private const NEW_ORIGINS = ['submission', 'scout'];

    public static function prepare(Db $db, int $from): void
    {
        $upgrade = $from < self::FIRST_NEW;
        $market = self::closeMarket($db);
        foreach ($db->all('SELECT id, scout_vouchers FROM labels ORDER BY id') as $row) {
            $labelId = (int) $row['id'];
            $repo = new Repo($db, $labelId);
            if (!$upgrade && !isset($market[$labelId]) && !self::hasLegacy($repo)) {
                continue;
            }
            if ($upgrade) {
                self::settleOld($repo);
            }
            $summary = ['market' => $market[$labelId] ?? 0, 'money' => self::cashIn($repo, (int) $row['scout_vouchers'])];
            self::bondBands($repo);
            $summary['pool'] = self::poolCatalog($repo, $upgrade);
            $db->update('labels', ['transition' => json_encode($summary)], ['id' => $labelId]);
        }
    }

    /** Alte Dinge, die es im neuen Spiel nicht mehr gibt (Testversion: nur dann gilt der Übergang). */
    private static function hasLegacy(Repo $repo): bool
    {
        $db = $repo->db;
        $l = $repo->labelId;
        $new = implode(',', array_map(static fn ($o) => "'$o'", self::NEW_ORIGINS));
        return (int) $db->value('SELECT COUNT(*) FROM items WHERE label_id = ?', [$l]) > 0
            || (int) $db->value("SELECT COUNT(*) FROM pack_offers WHERE label_id = ? AND status = 'open'", [$l]) > 0
            || (int) $db->value("SELECT COUNT(*) FROM scout_offers WHERE label_id = ? AND status = 'open'", [$l]) > 0
            || (int) $db->value("SELECT COUNT(*) FROM musicians WHERE label_id = ? AND origin NOT IN ($new)", [$l]) > 0;
    }

    /** Wechselbörse schließen: Wer angeboten war, bleibt beim Label; wer geboten hat, bekommt sein Geld zurück. Liefert Label-ID => Anzahl. */
    private static function closeMarket(Db $db): array
    {
        $back = [];
        foreach ($db->all("SELECT * FROM transfers WHERE status = 'open'") as $t) {
            $db->exec("UPDATE transfers SET status = 'withdrawn', closed_at = ? WHERE id = ?", [Clock::now(), $t['id']]);
            $db->update('musicians', ['busy_until' => 0], ['id' => (int) $t['musician_id']]);
            $m = $db->one('SELECT first_name, last_name FROM musicians WHERE id = ?', [$t['musician_id']]);
            $name = $m === null ? '' : $m['first_name'] . ' ' . $m['last_name'];
            $seller = new Repo($db, (int) $t['label_id']);
            $seller->log('label', sprintf('Die Wechselbörse gibt es nicht mehr: %s bleibt bei dir.', $name), null, (int) $t['musician_id']);
            $back[(int) $t['label_id']] = ($back[(int) $t['label_id']] ?? 0) + 1;
            if ($t['best_label_id'] !== null) {
                $bidder = new Repo($db, (int) $t['best_label_id']);
                $bidder->earn((int) $t['best_amount'], sprintf('Gebot für %s zurück (Wechselbörse geschlossen)', $name));
                $bidder->log('label', sprintf('Die Wechselbörse gibt es nicht mehr: Dein Gebot für %s ist zurück auf dem Konto.', $name));
            }
        }
        return $back;
    }

    /**
     * Alte Aktionen: Ergebnisse von früher nicht noch einmal zeigen. Alte Proben und Jam-Treffen rechnet das neue Spiel anders,
     * deshalb hier gleich mit ihrem schon berechneten Können-Gewinn; bei Auftritten fallen die Karten-Funde weg, der Rest läuft normal.
     */
    private static function settleOld(Repo $repo): void
    {
        $db = $repo->db;
        $db->exec('UPDATE activities SET seen = 1 WHERE label_id = ? AND settled = 1', [$repo->labelId]);
        foreach ($db->all('SELECT * FROM activities WHERE label_id = ? AND settled = 0', [$repo->labelId]) as $a) {
            $data = json_decode($a['data'], true) ?: [];
            if ($a['type'] !== 'rehearsal' && $a['type'] !== 'jam') {
                if (!empty($data['reward']['drops'])) {
                    $data['reward']['drops'] = [];
                    $db->update('activities', ['data' => json_encode($data, JSON_UNESCAPED_UNICODE)], ['id' => (int) $a['id']]);
                }
                continue;
            }
            foreach ($data['reward']['skill'] ?? [] as $id => $gain) {
                $m = $db->one('SELECT skill1, skill2, instr2, band_slot FROM musicians WHERE id = ? AND label_id = ?', [(int) $id, $repo->labelId]);
                if ($m === null) {
                    continue;
                }
                $col = (int) $m['band_slot'] === 2 && $m['instr2'] !== null ? 'skill2' : 'skill1';
                $skill = ['skill1' => (float) $m['skill1'], 'skill2' => (float) ($m['skill2'] ?? 0)];
                $skill[$col] = min(100.0, $skill[$col] + (float) $gain);
                $db->update('musicians', [$col => round($skill[$col], 4), 'level' => Levels::forSkill(max($skill))], ['id' => (int) $id]);
            }
            $db->update('activities', ['settled' => 1, 'seen' => 1], ['id' => (int) $a['id']]);
            if ($a['band_id'] !== null) {
                $db->update('bands', ['busy_until' => 0], ['id' => (int) $a['band_id']]);
            }
            if ($a['musician_id'] !== null) {
                $db->update('musicians', ['busy_until' => 0], ['id' => (int) $a['musician_id']]);
            }
        }
    }

    /** Fan-Karten, Scout-Gutscheine, ungeöffnete Packs und offene Scout-Angebote werden zu Geld. */
    private static function cashIn(Repo $repo, int $vouchers): int
    {
        $db = $repo->db;
        $l = $repo->labelId;
        $c = Balance::TRANSITION;
        $fame = (float) $db->value('SELECT COALESCE(SUM(value), 0) FROM items WHERE label_id = ?', [$l]);
        $scouts = (int) $db->value("SELECT COUNT(*) FROM scout_offers WHERE label_id = ? AND status = 'open'", [$l]);
        $packs = 0;
        foreach ($db->all("SELECT pack FROM pack_offers WHERE label_id = ? AND status = 'open'", [$l]) as $p) {
            $packs += (int) (Balance::PACKS[$p['pack']]['price'] ?? 0);
        }
        $total = (int) round($fame * $c['fanFame']) + ($vouchers + $scouts) * $c['voucher'] + $packs;
        $db->exec('DELETE FROM items WHERE label_id = ?', [$l]);
        $db->exec("UPDATE pack_offers SET status = 'refunded' WHERE label_id = ? AND status = 'open'", [$l]);
        $db->exec("UPDATE scout_offers SET status = 'refunded' WHERE label_id = ? AND status = 'open'", [$l]);
        $db->exec("UPDATE jam_offers SET status = 'declined' WHERE label_id = ? AND status = 'open'", [$l]);
        $db->exec('UPDATE labels SET scout_vouchers = 0 WHERE id = ?', [$l]);
        if ($total > 0) {
            $repo->earn($total, 'Umbau: alte Karten und Gutscheine eingetauscht');
        }
        return $total;
    }

    /**
     * Wer schon lange zusammen spielt, kennt sich: Startwert einer Beziehung = Zielwert der Passung × (1 − e^(−Tage / bondDays)),
     * gezählt ab dem Zeitpunkt, seit beide in der Band sein können (Gründung, Unterschrift). Bestehende Beziehungen bleiben.
     */
    private static function bondBands(Repo $repo): void
    {
        $now = Clock::now();
        foreach ($repo->db->all('SELECT id, created_at FROM bands WHERE label_id = ?', [$repo->labelId]) as $band) {
            $members = $repo->members((int) $band['id']);
            if (count($members) < 2) {
                continue;
            }
            $since = array_column($repo->db->all('SELECT id, created_at FROM musicians WHERE band_id = ?', [$band['id']]), 'created_at', 'id');
            foreach ($members as $i => $x) {
                foreach (array_slice($members, $i + 1) as $y) {
                    [$a, $b] = $x['id'] < $y['id'] ? [$x['id'], $y['id']] : [$y['id'], $x['id']];
                    if ($repo->db->value('SELECT id FROM relations WHERE a_id = ? AND b_id = ?', [$a, $b]) !== null) {
                        continue;
                    }
                    $start = max((int) $band['created_at'], (int) ($since[$a] ?? 0), (int) ($since[$b] ?? 0));
                    $share = 1 - exp(-max(0, $now - $start) / 86400 / Balance::TRANSITION['bondDays']);
                    $value = Mood::relationTarget(Mood::fit($x, $y)) * $share;
                    $repo->db->insert('relations', ['label_id' => $repo->labelId, 'a_id' => $a, 'b_id' => $b, 'value' => round($value, 4), 'at' => $now]);
                }
            }
            Mood::syncCohesion($repo, (int) $band['id']);
        }
    }

    /**
     * Die Kartei gibt es nicht mehr: Musiker ohne Band (oder allein in einer Solo-Band) kommen beiseite, wenn es mehr als `keep` sind.
     * Sie verlassen vorerst das Label (label_id 0) und geben ihre Instrumente ins Lager; laufende Aktionen verfallen.
     * @return list<int> beiseitegelegte Musiker
     */
    private static function poolCatalog(Repo $repo, bool $upgrade): array
    {
        $db = $repo->db;
        $rows = $db->all(
            'SELECT m.id, m.band_id, m.origin FROM musicians m
             WHERE m.label_id = ? AND (m.band_id IS NULL OR (SELECT COUNT(*) FROM musicians o WHERE o.band_id = m.band_id) = 1)
             ORDER BY m.id',
            [$repo->labelId]
        );
        if (!$upgrade) {
            $rows = array_values(array_filter($rows, static fn ($r) => !in_array($r['origin'], self::NEW_ORIGINS, true)));
        }
        if (count($rows) <= Balance::TRANSITION['keep']) {
            return [];
        }
        $pool = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $bandId = $r['band_id'] === null ? null : (int) $r['band_id'];
            $db->exec('UPDATE activities SET settled = 1, seen = 1 WHERE settled = 0 AND (musician_id = ? OR band_id = ?)', [$id, $bandId ?? 0]);
            if ($bandId !== null) {
                $db->update('bands', ['busy_until' => 0], ['id' => $bandId]);
                (new Bands($repo))->disband($bandId, 'left');
            }
            $db->exec('UPDATE gear SET musician_id = NULL WHERE musician_id = ?', [$id]);
            $db->update('musicians', ['label_id' => 0, 'band_id' => null, 'band_slot' => 1, 'busy_until' => 0, 'favorite' => 0], ['id' => $id]);
            $pool[] = $id;
        }
        return $pool;
    }

    /** Für den Spielstand: was der Übergang gebracht hat und wer aus der Kartei zur Wahl steht. */
    public static function present(Repo $repo): ?array
    {
        $raw = $repo->db->value('SELECT transition FROM labels WHERE id = ?', [$repo->labelId]);
        if ($raw === null || $raw === '') {
            return null;
        }
        $t = json_decode((string) $raw, true) ?: [];
        $pool = [];
        foreach (self::poolRows($repo->db, $t['pool'] ?? []) as $row) {
            $pool[] = Present::musician(Repo::hydrateMusician($row));
        }
        // `core`: Übergang ins neue Spiel mit Fans und Hype (CoreTransition), sonst null
        return ['market' => (int) ($t['market'] ?? 0), 'money' => (int) ($t['money'] ?? 0), 'pool' => $pool, 'keep' => Balance::TRANSITION['keep'], 'core' => $t['core'] ?? null, 'old' => isset($t['pool']) || isset($t['market'])];
    }

    /**
     * Abschluss beim ersten Login: Die ausgewählten Musiker (höchstens `keep`) bleiben als Solokünstler, die übrigen verlassen das Label
     * (wie Entlassene: Sie bleiben in WikiSignia).
     * @param list<int> $keep
     */
    public static function finish(Repo $repo, array $keep): void
    {
        $db = $repo->db;
        $db->transaction(function () use ($db, $repo, $keep): void {
            $raw = $db->value('SELECT transition FROM labels WHERE id = ?', [$repo->labelId]);
            if ($raw === null || $raw === '') {
                return;
            }
            $pool = array_map('intval', (json_decode((string) $raw, true) ?: [])['pool'] ?? []);
            $keep = array_values(array_unique(array_map('intval', $keep)));
            if (count($keep) > Balance::TRANSITION['keep']) {
                throw new ApiError(sprintf('Du kannst höchstens %d Musiker behalten.', Balance::TRANSITION['keep']));
            }
            if (array_diff($keep, $pool)) {
                throw new ApiError('Diese Musiker standen nicht zur Wahl.');
            }
            foreach (self::poolRows($db, $pool) as $row) {
                $id = (int) $row['id'];
                $name = $row['first_name'] . ' ' . $row['last_name'];
                if (in_array($id, $keep, true)) {
                    $db->update('musicians', ['label_id' => $repo->labelId], ['id' => $id]);
                    $repo->log('lineup', sprintf('%s bleibt beim Label.', $name), null, $id);
                } else {
                    $db->insert('alumni', ['label_id' => $repo->labelId, 'musician_id' => $id, 'left_at' => Clock::now(), 'reason' => 'released']);
                }
            }
            $left = count($pool) - count($keep);
            if ($left > 0) {
                $repo->log('lineup', sprintf('Die Kartei gibt es nicht mehr: %d %s das Label.', $left, $left === 1 ? 'Musiker verlässt' : 'Musiker verlassen'));
            }
            $db->update('labels', ['transition' => null], ['id' => $repo->labelId]);
        });
    }

    /** Beiseitegelegte Musiker (label_id 0, noch nicht in alumni). */
    private static function poolRows(Db $db, array $ids): array
    {
        $ids = array_map('intval', $ids);
        if (!$ids) {
            return [];
        }
        $in = implode(',', $ids);
        return $db->all("SELECT * FROM musicians WHERE id IN ($in) AND label_id = 0 ORDER BY id");
    }
}
