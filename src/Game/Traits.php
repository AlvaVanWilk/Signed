<?php
declare(strict_types=1);

namespace Signed\Game;

/**
 * Verborgene Charakterzüge: Mit dem Label redet niemand persönlich. Die Züge kennt man nur über einen Manager (Managers::settle), oder wenn
 * jemand richtig unzufrieden ist (rot) und laut sagt, was ihm fehlt. Gewirkt wird immer, auch solange ein Zug unbekannt ist.
 */
final class Traits
{
    /** Die Züge, die das Label schon kennt (in der Reihenfolge von personality). */
    public static function known(array $m): array
    {
        $known = $m['known_traits'] ?? [];
        return array_values(array_filter($m['personality'] ?? [], static fn ($t) => in_array($t, $known, true)));
    }

    /** Die Züge, die sich noch nicht gezeigt haben. */
    public static function hidden(array $m): array
    {
        return array_values(array_diff($m['personality'] ?? [], self::known($m)));
    }

    /**
     * Züge aufdecken, die gerade gewirkt haben, mit einer kurzen Nachricht für das Label.
     * @param string[] $traits
     * @param string $why Schlüssel aus Catalog::TRAIT_REVEAL
     */
    public static function reveal(Repo $repo, array $m, array $traits, string $why, string $order = ''): void
    {
        if (($m['id'] ?? 0) <= 0) {
            return;
        }
        $row = $repo->db->one('SELECT personality, known_traits FROM musicians WHERE id = ?', [$m['id']]);
        if ($row === null || $row['known_traits'] === null) {
            return;
        }
        $known = json_decode((string) $row['known_traits'], true) ?: [];
        $new = array_values(array_diff(array_intersect($m['personality'], $traits), $known));
        if (!$new) {
            return;
        }
        $known = array_values(array_intersect($m['personality'], array_merge($known, $new)));
        $repo->db->update('musicians', ['known_traits' => json_encode($known)], ['id' => $m['id']]);
        $words = array_map(static fn ($t) => mb_strtolower(Catalog::PERSONALITIES[$t]), $new);
        $reason = sprintf(Catalog::TRAIT_REVEAL[$why], $m['first_name'], $order);
        $repo->log('trait', sprintf('%s Jetzt weißt du: %s ist %s.', $reason, $m['first_name'], implode(' und ', $words)), $m['band_id'] ?? null, $m['id']);
    }

    /** Wer rot ist, meckert laut: Man erfährt den Zug, der hinter seinem Wunsch steckt. */
    public static function settleBand(Repo $repo, array $members): void
    {
        foreach ($members as $m) {
            if (!self::hidden($m) || !$m['personality']) {
                continue;
            }
            $w = $repo->db->value('SELECT wellbeing FROM musicians WHERE id = ?', [$m['id']]);
            if ($w !== null && Mood::level((float) $w) === 'red') {
                self::reveal($repo, $m, [$m['personality'][0]], 'unhappy');
            }
        }
    }
}
