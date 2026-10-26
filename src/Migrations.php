<?php
declare(strict_types=1);

namespace Signed;

use Signed\Game\Catalog;
use Signed\Game\Levels;
use Signed\Game\Balance;

/** Datenumbauten, die sich nicht in reinem SQL ausdrücken lassen. */
final class Migrations
{
    /**
     * 3: Bisherige Eigenschaften und Techniken werden zu Fähigkeiten, die Stufe ergibt sich aus dem Können.
     * Instrumente bekommen ihre Werte vom Modell.
     */
    public static function levelsAndGear(Db $db): void
    {
        foreach ($db->all('SELECT id, instr1, instr2, skill1, skill2, traits, techniques FROM musicians') as $row) {
            $level = Levels::forSkill(max((float) $row['skill1'], (float) ($row['skill2'] ?? 0)));
            $known = array_merge(json_decode($row['traits'], true) ?: [], json_decode($row['techniques'], true) ?: []);
            $known = array_map(static fn ($a) => $a === 'attraktiv' ? 'ausstrahlung' : $a, $known);
            // Heute gibt es nur noch Spieltechniken (eine je Musiker); fehlt sie, leitet Repo::legacyTechnique sie beim Lesen ab.
            $abilities = array_slice(array_values(array_filter($known, static fn ($a) => isset(Catalog::ABILITIES[$a]))), 0, 1);
            $db->update('musicians', ['level' => $level, 'abilities' => json_encode($abilities)], ['id' => (int) $row['id']]);
        }
        foreach ($db->all('SELECT id, model FROM gear') as $row) {
            $model = Catalog::GEAR[$row['model']] ?? null;
            if ($model !== null) {
                $db->update('gear', ['klang' => $model['klang'], 'spielbarkeit' => $model['spielbarkeit'], 'design' => $model['design']], ['id' => (int) $row['id']]);
            }
        }
    }

    /**
     * 8: Synthesizer werden zu Keyboards (Musiker und Instrumente). Pro Genre darf es jetzt mehrere Bands geben,
     * deshalb fällt die Sperre „ein Genre pro Label“ weg; das Genre einer Band ergibt sich ab jetzt aus der Besetzung.
     */
    public static function keysAndGenres(Db $db): void
    {
        $db->raw($db->driver() === 'mysql' ? 'DROP INDEX bands_label_genre ON bands' : 'DROP INDEX IF EXISTS bands_label_genre');
        $db->raw('CREATE INDEX bands_label ON bands (label_id)');

        foreach ($db->all("SELECT id, instr1, instr2, skill1, skill2, band_slot FROM musicians WHERE instr1 = 'synth' OR instr2 = 'synth'") as $m) {
            $instr1 = $m['instr1'] === 'synth' ? 'keys' : $m['instr1'];
            $instr2 = $m['instr2'] === 'synth' ? 'keys' : $m['instr2'];
            $skill1 = (float) $m['skill1'];
            $skill2 = $m['skill2'] === null ? null : (float) $m['skill2'];
            if ($instr1 === $instr2) {
                // Wer beides spielte, spielt jetzt einmal Keyboard – mit dem besseren Können.
                $skill1 = max($skill1, (float) $skill2);
                $instr2 = null;
                $skill2 = null;
            }
            $db->update('musicians', ['instr1' => $instr1, 'instr2' => $instr2, 'skill1' => $skill1, 'skill2' => $skill2, 'band_slot' => $instr2 === null ? 1 : (int) $m['band_slot']], ['id' => (int) $m['id']]);
        }

        $models = ['syn_mini' => 'key_home', 'syn_poly' => 'key_stage', 'syn_modular' => 'key_organ', 'syn_neon' => 'key_neon'];
        foreach ($models as $old => $new) {
            $db->exec('UPDATE gear SET model = ? WHERE model = ?', [$new, $old]);
        }
        foreach ($db->all('SELECT id, model FROM gear WHERE klang = 0') as $row) {
            $model = Catalog::GEAR[$row['model']] ?? null;
            if ($model !== null) {
                $db->update('gear', ['klang' => $model['klang'], 'spielbarkeit' => $model['spielbarkeit'], 'design' => $model['design']], ['id' => (int) $row['id']]);
            }
        }
        // Wer jetzt zwei Keyboards hält, behält eins am Musiker.
        foreach ($db->all("SELECT musician_id FROM gear WHERE musician_id IS NOT NULL AND model LIKE 'key_%' GROUP BY musician_id HAVING COUNT(*) > 1") as $row) {
            $ids = array_column($db->all("SELECT id FROM gear WHERE musician_id = ? AND model LIKE 'key_%' ORDER BY klang DESC", [$row['musician_id']]), 'id');
            foreach (array_slice($ids, 1) as $id) {
                $db->update('gear', ['musician_id' => null], ['id' => (int) $id]);
            }
        }
    }

    /** 9: Epische und legendäre Instrumente, die es schon gibt, bekommen ihre Eigenschaften. */
    /**
     * 20: Album-Zyklus. Wer nach der alten Regel (5 Gigs) schon frei war, bleibt frei; sonst zählen die bisherigen Gigs
     * mit je 3 Liedern, Promos fangen bei 0 an.
     */
    public static function recordCycle(Db $db): void
    {
        foreach ($db->all('SELECT id, gigs_since_record FROM bands WHERE records > 0') as $band) {
            $songs = (int) $db->value(
                "SELECT songs_total FROM records WHERE band_id = ? AND status = 'done' ORDER BY finished_at DESC LIMIT 1",
                [(int) $band['id']]
            );
            $needed = \Signed\Game\Balance::RECORD_CYCLE['plays'] * $songs;
            $free = (int) $band['gigs_since_record'] >= 5;
            $db->update('bands', [
                'live_songs_needed' => $needed,
                'live_songs' => $free ? $needed : 3 * (int) $band['gigs_since_record'],
                'band_promos' => $free ? \Signed\Game\Balance::RECORD_CYCLE['promos'] : 0,
            ], ['id' => (int) $band['id']]);
        }
    }

    /** 19: Plätze sofort neu vergeben, damit Platten, die bisher nur wegen „eine pro Band“ fehlten, gleich einsteigen. */
    public static function chartsRerank(Db $db): void
    {
        \Signed\Game\Charts::markDirty($db);
    }

    /** 15: Widersprüchliche Eigenschaften (z. B. Teamplayer und Diva) – die zweite fällt weg. */
    public static function personalityContradictions(Db $db): void
    {
        foreach ($db->all('SELECT id, personality FROM musicians') as $row) {
            $traits = json_decode((string) $row['personality'], true) ?: [];
            if (count($traits) === 2 && \Signed\Game\Generator::contradicts($traits[0], $traits[1])) {
                $db->update('musicians', ['personality' => json_encode([$traits[0]])], ['id' => (int) $row['id']]);
            }
        }
    }

    public static function gearPerks(Db $db): void
    {
        foreach ($db->all('SELECT id, model FROM gear') as $row) {
            $rarity = Catalog::GEAR[$row['model']]['rarity'] ?? 'common';
            $perks = \Signed\Rng::sample(array_keys(Catalog::GEAR_PERKS), \Signed\Game\Balance::GEAR_PERKS[$rarity]);
            $db->update('gear', ['perks' => json_encode($perks)], ['id' => (int) $row['id']]);
        }
    }

    /** Offene Produktionen aus der Zeit der Minispiele: Studiozeit erstatten und die Platte verwerfen (die Band nimmt neu auf). */
    public static function dropOpenRecords(Db $db): void
    {
        foreach ($db->all("SELECT id, label_id, band_id, songs_total FROM records WHERE status = 'open'") as $r) {
            $refund = Balance::RECORD_COST + Balance::RECORD_COST_PER_SONG * (int) $r['songs_total'];
            $db->exec('UPDATE labels SET money = money + ? WHERE id = ?', [$refund, (int) $r['label_id']]);
            $db->exec('DELETE FROM records WHERE id = ?', [(int) $r['id']]);
        }
        $db->exec("DELETE FROM minigames WHERE kind = 'record' AND status = 'open'");
    }
}
