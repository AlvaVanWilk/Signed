<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Rng;

/**
 * Manager aus dem Posteingang: wie gut einer zu einer Band passt (Schwerpunkt-Genres und Stil), die Wirkungen, die nicht schon
 * bei den Auftritten stecken (Netzwerker bei Interviews, Vermittler bei der Laune), und vor allem: Er kennt seine Leute. Nur mit Manager
 * sieht das Label Wünsche, Gründe der Stimmung und Charakterzüge, und er schreibt Notizen auf die Übersicht.
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

    /** Der Manager kennt seine Leute: Ihre Charakterzüge sind alle bekannt (bleiben es auch, wenn er geht). */
    public static function settle(Repo $repo): void
    {
        foreach ($repo->bands() as $band) {
            if ($band['manager_id'] === null) {
                continue;
            }
            $manager = $repo->manager($band['manager_id']);
            foreach ($repo->members($band['id']) as $m) {
                Traits::reveal($repo, $m, $m['personality'], 'manager', $manager['first_name']);
            }
        }
    }

    /**
     * Notizen des Managers über seine Band (für die Übersicht), wichtigste zuerst: Wünsche (rot, gelb, sonst), wer ein besseres
     * Instrument gebrauchen könnte, wer sich nicht versteht, lange ohne Bühne, bereit fürs Studio.
     * @param list<array> $wishes aus Mood::wishes
     * @param array<string, float> $relations Paare "a-b" => −10…+10
     * @return list<array{level: int, text: string, musicianId: ?int, action: ?string}>
     */
    public static function notes(array $band, array $members, array $gearMap, array $relations, array $wishes, float $stageIdle): array
    {
        $c = Balance::MANAGER_STAFF['notes'];
        $name = static fn (array $m) => $m['stage_name'] ?? $m['first_name'];
        $byId = array_column($members, null, 'id');
        $solo = count($members) === 1;
        $out = [];
        foreach ($wishes as $w) {
            $m = $byId[$w['musicianId']] ?? null;
            if ($m === null) {
                continue;
            }
            $who = !empty($w['band']) && !$solo ? $band['name'] : $name($m);
            $out[] = ['level' => ['red' => 0, 'yellow' => 1][$w['level']] ?? 2, 'text' => sprintf('%s: „%s“', $who, $w['text']), 'musicianId' => $m['id'], 'action' => $w['action']];
        }
        foreach ($members as $m) {
            $type = BandCalc::instrument($m);
            $gear = $gearMap[$m['id']][$type] ?? null;
            if ($gear !== null && Gear::modelTier($gear['key']) < Gear::levelFor($m, $type)) {
                $out[] = ['level' => 2, 'text' => sprintf('%s spielt inzwischen besser, als %s es hergibt. Ein besseres Instrument wäre gut.', $name($m), $gear['name']), 'musicianId' => $m['id'], 'action' => 'gear'];
            }
        }
        foreach ($relations as $pair => $value) {
            [$a, $b] = array_map('intval', explode('-', (string) $pair));
            if ($value <= $c['conflict'] && isset($byId[$a], $byId[$b])) {
                $out[] = ['level' => 1, 'text' => sprintf('%s und %s verstehen sich gerade nicht gut.', $name($byId[$a]), $name($byId[$b])), 'musicianId' => null, 'action' => null];
            }
        }
        if ($stageIdle >= $c['stageIdle']) {
            $out[] = ['level' => 2, 'text' => sprintf('%s stand schon lange nicht mehr auf der Bühne.', $band['name']), 'musicianId' => null, 'action' => 'gig'];
        }
        $songs = (int) floor($band['songs_written']);
        $cycle = Studio::cycle($band);
        if ($songs >= Balance::RECORD_MIN_SONGS && $songs < Balance::MOOD['stau']['from'] && ($cycle === null || $cycle['done'])) {
            $out[] = ['level' => 2, 'text' => sprintf('%s ist bereit fürs Studio (%d Songs).', $band['name'], $songs), 'musicianId' => null, 'action' => 'record'];
        }
        usort($out, static fn ($x, $y) => $x['level'] <=> $y['level']);
        return $out;
    }
}
