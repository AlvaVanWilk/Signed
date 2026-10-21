<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Clock;
use Signed\Rng;

/**
 * Fan will einsteigen: Nach einem begeisterten Gig wartet ganz selten jemand am Bühnenausgang, der die Band toll findet.
 * Er mag ihr Genre, spielt ein Instrument, das ihr noch fehlt, verträgt sich mit allen und kommt ins Label –
 * in die Band holt man ihn selbst (wenn Platz ist). Werte in Balance::FAN_MUSICIAN.
 */
final class FanMusician
{
    public static function onCooldown(Repo $repo): bool
    {
        $last = (int) $repo->db->value(
            "SELECT COALESCE(MAX(created_at), 0) FROM history WHERE label_id = ? AND kind = 'fanjoin'",
            [$repo->labelId]
        );
        return $last > 0 && Clock::now() - $last < Balance::FAN_MUSICIAN['cooldown'];
    }

    /** Legt den Fan im Label an; null, wenn es gerade keinen passenden gibt (oder schon einer da war). */
    public static function join(Repo $repo, array $band, string $venue): ?array
    {
        if (self::onCooldown($repo)) {
            return null;
        }
        $members = $repo->members($band['id']);
        if (!$members) {
            return null;
        }
        $genre = $band['genre'] !== '' ? $band['genre'] : self::sharedGenre($members);
        $played = array_map([BandCalc::class, 'instrument'], $members);
        $weights = [];
        foreach (Catalog::INSTRUMENTS as $type => $instrument) {
            if (in_array($type, $played, true)) {
                continue;
            }
            // Lieber ein Instrument, das zum Genre passt.
            $weights[$type] = $instrument['weight'] * (in_array($genre, $instrument['genres'], true) ? 4 : 1);
        }
        if (!$weights) {
            return null;
        }
        $type = (string) Rng::weighted($weights);
        $skills = array_map(static fn ($m) => BandCalc::skill($m), $members);
        $traits = array_merge(...array_map(static fn ($m) => $m['personality'], $members));
        $card = Generator::musician(Generator::rarity(Balance::FAN_MUSICIAN['odds'][$venue] ?? Balance::FAN_MUSICIAN['odds']['kneipe']), [
            'instrument' => $type,
            'genre' => $genre,
            'single' => true,
            'maxSkill' => max(5.0, array_sum($skills) / count($skills) * Balance::FAN_MUSICIAN['skillShare']),
            'avoid' => $repo->lastNames(),
            'getsAlongWith' => $traits,
        ]);
        $id = $repo->insertMusician($card, 'fan');
        $repo->log('fanjoin', sprintf(
            'Nach dem Gig wartet %s %s am Bühnenausgang: großer Fan von %s – und jetzt in deinem Label.',
            $card['first_name'],
            $card['last_name'],
            $band['name']
        ), $band['id'], $id);
        $lineup = $members;
        $lineup[] = $repo->musician($id);
        return [
            'kind' => 'musician',
            'id' => $id,
            'name' => $card['first_name'] . ' ' . $card['last_name'],
            'instrument' => $type,
            'canJoin' => !BandCalc::lineupProblems($lineup),
        ];
    }

    /** Ein Genre, das alle mögen, sonst das Lieblingsgenre des ersten Mitglieds. */
    private static function sharedGenre(array $members): string
    {
        $shared = array_values(array_intersect(...array_map(static fn ($m) => $m['genres'], $members)));
        return $shared[0] ?? $members[0]['genres'][0];
    }
}
