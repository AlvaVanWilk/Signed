<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;

/**
 * Proberaum: Jede Band hat ihren eigenen Raum, auch jeder Solokünstler (dann eine Übungsecke, die genauso ausgebaut wird).
 * Der Raum wird in Stufen ausgebaut; er macht Proben wirksamer, die Band hält länger durch und fühlt sich wohler.
 */
final class Rooms
{
    /** Stufe des Raums, in dem diese Band probt. */
    public static function level(Repo $repo, array $band, int $members): int
    {
        return (int) $band['room'];
    }

    /** Wo die Band gerade probt: auf Tournee im Fahrzeug (dessen Werte), sonst im Proberaum. */
    public static function here(Repo $repo, array $band, int $members): array
    {
        return Tours::rehearsalRules($repo->db, $band) ?? self::rules(self::level($repo, $band, $members));
    }

    public static function rules(int $level): array
    {
        $levels = Balance::ROOM['levels'];
        return $levels[max(0, min(count($levels) - 1, $level))];
    }

    /** Angaben für die Bandseite: Stufe, Wirkung, nächster Ausbau. */
    public static function present(Repo $repo, array $band, int $members): array
    {
        $solo = $members < 2;
        $level = self::level($repo, $band, $members);
        $now = self::rules($level);
        $next = Balance::ROOM['levels'][$level + 1] ?? null;
        return [
            'level' => $level,
            'solo' => $solo,
            'name' => $now['name'],
            'stage' => $now['name'],
            'skill' => $now['skill'], 'lust' => $now['lust'], 'mood' => $now['mood'],
            'next' => $next === null ? null : ['name' => $next['name'], 'cost' => $next['cost'], 'skill' => $next['skill'], 'lust' => $next['lust'], 'mood' => $next['mood']],
        ];
    }

    /** Eine Stufe ausbauen. */
    public static function upgrade(Repo $repo, int $bandId): void
    {
        $repo->db->transaction(static function () use ($repo, $bandId): void {
            $band = $repo->band($bandId);
            $info = self::present($repo, $band, count($repo->members($bandId)));
            if ($info['next'] === null) {
                throw new ApiError('Der Proberaum ist schon voll ausgebaut.');
            }
            $repo->spend($info['next']['cost'], sprintf('Proberaum: %s', $info['next']['name']));
            $repo->db->exec('UPDATE bands SET room = room + 1 WHERE id = ?', [$bandId]);
            $repo->log('band', sprintf('Der Proberaum von %s ist jetzt: %s.', $band['name'], $info['next']['name']), $bandId);
        });
    }
}
