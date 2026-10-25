<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;

/**
 * Proberaum: Eine Band mit mindestens zwei Mitgliedern hat ihren eigenen Raum, Solokünstler teilen sich die Übungsecke des Labels.
 * Der Raum wird in Stufen ausgebaut; er macht Proben wirksamer, die Band hält länger durch und fühlt sich wohler.
 */
final class Rooms
{
    /** Stufe des Raums, in dem diese Band probt. */
    public static function level(Repo $repo, array $band, int $members): int
    {
        if ($members >= 2) {
            return (int) $band['room'];
        }
        return (int) $repo->db->value('SELECT solo_room FROM labels WHERE id = ?', [$repo->labelId]);
    }

    public static function rules(int $level): array
    {
        $levels = Balance::ROOM['levels'];
        return $levels[max(0, min(count($levels) - 1, $level))];
    }

    /** Angaben für die Bandseite: Stufe, Wirkung, nächster Ausbau. */
    public static function present(Repo $repo, array $band, int $members): array
    {
        $shared = $members < 2;
        $level = self::level($repo, $band, $members);
        $now = self::rules($level);
        $next = Balance::ROOM['levels'][$level + 1] ?? null;
        $factor = $shared ? Balance::ROOM['soloCost'] : 1.0;
        return [
            'level' => $level,
            'shared' => $shared,
            'name' => $shared ? 'Übungsecke des Labels' : $now['name'],
            'stage' => $now['name'],
            'skill' => $now['skill'], 'lust' => $now['lust'], 'mood' => $now['mood'],
            'next' => $next === null ? null : ['name' => $next['name'], 'cost' => (int) round($next['cost'] * $factor), 'skill' => $next['skill'], 'lust' => $next['lust'], 'mood' => $next['mood']],
        ];
    }

    /** Eine Stufe ausbauen (bei Solokünstlern die gemeinsame Übungsecke). */
    public static function upgrade(Repo $repo, int $bandId): void
    {
        $repo->db->transaction(static function () use ($repo, $bandId): void {
            $band = $repo->band($bandId);
            $members = count($repo->members($bandId));
            $info = self::present($repo, $band, $members);
            if ($info['next'] === null) {
                throw new ApiError('Der Proberaum ist schon voll ausgebaut.');
            }
            $repo->spend($info['next']['cost'], sprintf('Proberaum: %s', $info['next']['name']));
            if ($info['shared']) {
                $repo->db->exec('UPDATE labels SET solo_room = solo_room + 1 WHERE id = ?', [$repo->labelId]);
                $repo->log('band', sprintf('Die Übungsecke des Labels ist jetzt: %s.', $info['next']['name']));
            } else {
                $repo->db->exec('UPDATE bands SET room = room + 1 WHERE id = ?', [$bandId]);
                $repo->log('band', sprintf('Der Proberaum von %s ist jetzt: %s.', $band['name'], $info['next']['name']), $bandId);
            }
        });
    }
}
