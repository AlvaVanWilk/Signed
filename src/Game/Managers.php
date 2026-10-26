<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Rng;

/**
 * Manager aus dem Posteingang: wie gut einer zu einer Band passt (Schwerpunkt-Genres und Stil) und die Wirkungen, die nicht schon
 * bei den Auftritten stecken (Netzwerker bei Interviews, Vermittler bei der Laune, Gespür bei den Charakterzügen).
 */
final class Managers
{
    /** Passung (Faktor auf die Stufe): gemeinsame Genres und derselbe Charakterzug wie die Mitglieder. Alte Manager ohne Schwerpunkt: 1. */
    public static function fit(array $manager, array $members): float
    {
        $c = Balance::MANAGER_STAFF;
        if (!$members || (!($manager['genres'] ?? []) && ($manager['style'] ?? null) === null)) {
            return 1.0;
        }
        $fit = 1.0;
        if ($manager['genres'] ?? []) {
            $bandGenres = array_unique(array_merge(...array_map(static fn ($m) => $m['genres'] ?? [], $members)));
            $fit = $c['fit'][min(2, count(array_intersect($manager['genres'], $bandGenres)))];
        }
        $style = $manager['style'] ?? null;
        if ($style !== null) {
            $same = 0;
            $clash = 0;
            foreach ($members as $m) {
                foreach ($m['personality'] ?? [] as $trait) {
                    if ($trait === $style) {
                        $same++;
                    } elseif (Generator::contradicts($trait, $style)) {
                        $clash++;
                    }
                }
            }
            $fit += $c['style'] * ($same - $clash) / count($members);
        }
        return max($c['fitRange'][0], min($c['fitRange'][1], $fit));
    }

    /** Ein Manager als Bewerbung: Stufe, Wirkungen, Schwerpunkt-Genres (oft passend zum Label) und Stil. */
    public static function applicant(int $tier, array $labelGenres = []): array
    {
        $c = Balance::MANAGER_STAFF;
        $all = array_keys(Catalog::GENRES);
        $first = $labelGenres && Rng::chance(0.7) ? (string) Rng::pick($labelGenres) : (string) Rng::pick($all);
        $genres = [$first];
        if (Rng::chance(0.5)) {
            $genres[] = (string) Rng::pick(array_values(array_diff($all, [$first])));
        }
        return [
            'genres' => $genres,
            'style' => (string) Rng::pick(array_keys(Catalog::PERSONALITIES)),
            'skills' => Rng::sample($c['skills'], $c['tiers'][$tier]['skills']),
        ];
    }

    /** Manager einer Band mit Stufe (schon geteilt und nach Passung) oder null. */
    public static function of(Repo $repo, ?int $bandId): array
    {
        if ($bandId === null) {
            return [null, 0.0];
        }
        $row = $repo->db->one('SELECT * FROM bands WHERE id = ? AND label_id = ?', [$bandId, $repo->labelId]);
        return $row === null ? [null, 0.0] : $repo->bandManager(Repo::hydrateBand($row));
    }

    /** Gespür: Der Manager kennt seine Musiker, ihre Züge sind alle bekannt. */
    public static function settle(Repo $repo): void
    {
        foreach ($repo->bands() as $band) {
            if ($band['manager_id'] === null) {
                continue;
            }
            $manager = $repo->manager($band['manager_id']);
            if (!in_array('gespuer', $manager['skills'], true)) {
                continue;
            }
            foreach ($repo->members($band['id']) as $m) {
                Traits::reveal($repo, $m, $m['personality'], 'manager', $manager['first_name']);
            }
        }
    }
}
