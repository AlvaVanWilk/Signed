<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Clock;
use Signed\Db;

/**
 * Übergang ins neue Spiel mit Fans und Hype (einmalig beim Umschalten, für alte Labels und die Test-Labels; beschlossen, Sandra):
 * Alle Bands fangen bei 0 Fans an; Können und Beziehungen bleiben (das Können wird nur im neuen Maß gezeigt, siehe Levels::shown).
 * Jedes Label hat das neue Startgeld. Jeder Musiker behält sein eigenes Instrument, höchstens in der Farbe seiner Können-Stufe;
 * alles andere im Lager verfällt ohne Ausgleich, wer keins hat, bekommt eins (grau, ab Können-Stufe 1 grün).
 * Der alte Ruhm bleibt als Erinnerung in der Chronik und in WikiSignia. Songs im Vorrat bekommen ihre Qualität. Fan-Karten (Ruhm) verfallen.
 * Was passiert ist, steht in labels.transition (Schlüssel `core`) für „Was ist neu?“.
 */
final class CoreTransition
{
    public static function run(Db $db): void
    {
        $now = Clock::now();
        // Die alten Charts enden; neue Plätze gibt es nur für Platten des neuen Kerns.
        $db->exec('UPDATE records SET rank_last = NULL, rank_prev = NULL WHERE core IS NULL');
        // Laufende Tourneen enden, die Bands sind wieder zu Hause (Tourneen gibt es im neuen Kern erst ab 5.000 Fans).
        $db->exec("UPDATE tours SET state = 'ended', ended_at = ? WHERE state <> 'ended'", [$now]);
        foreach ($db->all('SELECT id, money, transition FROM labels ORDER BY id') as $row) {
            $repo = new Repo($db, (int) $row['id']);
            $summary = ['money' => (int) $row['money'], 'start' => Balance::CORE_MONEY['start'], 'fame' => [], 'gear' => ['kept' => 0, 'swapped' => 0, 'expired' => 0, 'given' => 0]];
            foreach ($repo->bands() as $band) {
                $summary['fame'] = array_merge($summary['fame'], self::band($repo, $band, $now));
            }
            self::musicians($repo);
            self::money($repo, (int) $row['money']);
            $summary['gear'] = self::gear($repo);
            // Fan-Karten brachten Ruhm; den gibt es nicht mehr.
            $summary['cards'] = $db->exec("DELETE FROM items WHERE label_id = ? AND kind = 'fan'", [$repo->labelId]);
            $old = json_decode((string) ($row['transition'] ?? ''), true) ?: [];
            $db->update('labels', ['transition' => json_encode($old + ['core' => $summary], JSON_UNESCAPED_UNICODE)], ['id' => $repo->labelId]);
        }
    }

    /** Eine Band: Erinnerung an den alten Ruhm, 0 Fans, Songs im Vorrat mit Qualität. */
    private static function band(Repo $repo, array $band, int $now): array
    {
        $db = $repo->db;
        $fame = (float) $band['fame_own'] + (float) $db->value('SELECT COALESCE(SUM(fame), 0) FROM musicians WHERE band_id = ?', [$band['id']]);
        $out = [];
        if ($fame >= 1) {
            $repo->log('band', sprintf('Vor dem Umbau: %s Ruhm.', number_format($fame, 0, ',', '.')), $band['id']);
            $out[] = ['band' => $band['name'], 'fame' => (int) round($fame)];
        }
        $db->update('bands', ['fans' => 0, 'fresh' => 1, 'last_gig_at' => null, 'core_at' => $now, 'money_carry' => 0, 'fame_own' => 0], ['id' => $band['id']]);
        $members = $repo->members($band['id']);
        $have = (int) $db->value('SELECT COUNT(*) FROM songs WHERE band_id = ? AND record_id IS NULL', [$band['id']]);
        SongStock::write($repo, $band, $members, (int) floor($band['songs_written']) - $have);
        return $out;
    }

    /** Bekanntheit der Musiker: als Erinnerung in ihrer Geschichte, ab jetzt die Fans ihrer Band. */
    private static function musicians(Repo $repo): void
    {
        foreach ($repo->db->all('SELECT id, band_id, fame FROM musicians WHERE label_id = ? AND fame >= 1', [$repo->labelId]) as $m) {
            $repo->log('level', sprintf('Vor dem Umbau: %s Bekanntheit.', number_format((float) $m['fame'], 0, ',', '.')), $m['band_id'] === null ? null : (int) $m['band_id'], (int) $m['id']);
        }
        $repo->db->exec('UPDATE musicians SET fame = 0 WHERE label_id = ?', [$repo->labelId]);
    }

    /** Neues Startgeld; altes Geld wird nicht umgerechnet. */
    private static function money(Repo $repo, int $old): void
    {
        $start = Balance::CORE_MONEY['start'];
        $repo->db->update('labels', ['money' => $start], ['id' => $repo->labelId]);
        $repo->db->insert('ledger', [
            'label_id' => $repo->labelId, 'amount' => $start - $old, 'text' => sprintf('Umbau: neues Startgeld %s ₲', number_format($start, 0, ',', '.')), 'created_at' => Clock::now(),
        ]);
    }

    /**
     * Instrumente: eigene behalten (höchstens in der Farbe der Können-Stufe, ein besseres tauscht der Music Store um), Lager verfällt,
     * wer für sein Instrument keins hat, bekommt eins.
     * @return array{kept: int, swapped: int, expired: int, given: int}
     */
    private static function gear(Repo $repo): array
    {
        $db = $repo->db;
        $out = ['kept' => 0, 'swapped' => 0, 'expired' => 0, 'given' => 0];
        $musicians = $repo->musicians();
        foreach ($db->all('SELECT * FROM gear WHERE label_id = ?', [$repo->labelId]) as $g) {
            $m = $g['musician_id'] === null ? null : ($musicians[(int) $g['musician_id']] ?? null);
            if ($m === null) {
                $db->exec('DELETE FROM gear WHERE id = ?', [(int) $g['id']]);
                $out['expired']++;
                continue;
            }
            $base = Catalog::GEAR[$g['model']] ?? null;
            $allowed = $base === null ? 0 : Gear::levelFor($m, $base['type']);
            if ($base !== null && Gear::modelTier($g['model']) <= $allowed) {
                $out['kept']++;
                continue;
            }
            $model = self::model($base['type'] ?? $m['instr1'], $allowed, $base['electric'] ?? null);
            $stats = Generator::gearStats($model);
            $db->update('gear', [
                'model' => $model, 'klang' => $stats['klang'], 'spielbarkeit' => $stats['spielbarkeit'], 'design' => $stats['design'],
                'perks' => json_encode([]), 'bonuses' => json_encode($stats['bonuses']),
            ], ['id' => (int) $g['id']]);
            $out['swapped']++;
        }
        $owned = [];
        foreach ($db->all('SELECT model, musician_id FROM gear WHERE label_id = ? AND musician_id IS NOT NULL', [$repo->labelId]) as $g) {
            $owned[(int) $g['musician_id']][Catalog::GEAR[$g['model']]['type'] ?? ''] = true;
        }
        foreach ($musicians as $m) {
            $type = BandCalc::instrument($m);
            if (isset($owned[$m['id']][$type])) {
                continue;
            }
            $id = $repo->insertGear(self::model($type, min(1, Gear::levelFor($m, $type))));
            $db->update('gear', ['musician_id' => $m['id']], ['id' => $id]);
            $out['given']++;
        }
        return $out;
    }

    /** Ein Modell dieser Art in dieser Farbe (0 grau … 3 gold); elektrisch oder akustisch wie das alte, wenn es das gibt. */
    private static function model(string $type, int $tier, ?bool $electric = null): string
    {
        $rarity = array_search($tier, Gear::TIER, true) ?: 'common';
        $fits = array_keys(array_filter(Catalog::GEAR, static fn ($g) => $g['type'] === $type && $g['rarity'] === $rarity));
        foreach ($fits as $key) {
            if ($electric === null || (Catalog::GEAR[$key]['electric'] ?? null) === $electric) {
                return $key;
            }
        }
        return $fits[0] ?? array_key_first(array_filter(Catalog::GEAR, static fn ($g) => $g['type'] === $type));
    }
}
