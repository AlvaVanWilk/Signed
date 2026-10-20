<?php
declare(strict_types=1);

namespace Signed;

use Signed\Game\Catalog;
use Signed\Game\Levels;

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
            $known = array_values(array_filter($known, static fn ($a) => isset(Catalog::ABILITIES[$a])));
            $abilities = array_slice($known, 0, $level);
            $instruments = array_filter([$row['instr1'], $row['instr2']]);
            while (count($abilities) < $level) {
                $abilities[] = Levels::randomAbility($instruments, $abilities);
            }
            $db->update('musicians', ['level' => $level, 'abilities' => json_encode($abilities)], ['id' => (int) $row['id']]);
        }
        foreach ($db->all('SELECT id, model FROM gear') as $row) {
            $model = Catalog::GEAR[$row['model']] ?? null;
            if ($model !== null) {
                $db->update('gear', ['klang' => $model['klang'], 'spielbarkeit' => $model['spielbarkeit'], 'design' => $model['design']], ['id' => (int) $row['id']]);
            }
        }
    }
}
