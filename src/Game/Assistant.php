<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;

/**
 * Assistenz im Posteingang: öffnet jeden Tag eine feste Zahl Demos (die ältesten zuerst, gemerkte und laufende nicht) und sortiert die Post in Reiter.
 * Ab höheren Stufen wirft sie nach Regeln weg, was nicht passt (Können-Stufe, Genres, Solo oder Band). Mehrere Assistenzen arbeiten zusammen.
 */
final class Assistant
{
    private const DEFAULT_RULES = ['minLevel' => 0, 'genres' => [], 'kind' => 'any'];

    public function __construct(private Repo $repo)
    {
    }

    /** @return list<array> eingestellte Assistenzen */
    public function staff(): array
    {
        return $this->repo->db->all("SELECT * FROM staff WHERE label_id = ? AND role = 'assistant' ORDER BY tier DESC, id", [$this->repo->labelId]);
    }

    public static function perDay(array $staff): int
    {
        return (int) array_sum(array_map(static fn ($s) => Balance::ASSISTANT['tiers'][(int) $s['tier']]['perDay'], $staff));
    }

    /** Welche Regeln die beste Assistenz kann. */
    public static function allowed(int $tier): array
    {
        return array_map(static fn ($from) => $tier >= $from, Balance::ASSISTANT['rules']);
    }

    /** Eingestellte Regeln, beschränkt auf das, was die beste Assistenz kann. */
    public function rules(?int $tier = null): array
    {
        $tier ??= (int) ($this->staff()[0]['tier'] ?? 0);
        $raw = json_decode((string) $this->repo->db->value('SELECT assist_rules FROM labels WHERE id = ?', [$this->repo->labelId]), true);
        $rules = (is_array($raw) ? $raw : []) + self::DEFAULT_RULES;
        $allowed = self::allowed($tier);
        foreach (self::DEFAULT_RULES as $key => $default) {
            if (!$allowed[$key]) {
                $rules[$key] = $default;
            }
        }
        return array_intersect_key($rules, self::DEFAULT_RULES);
    }

    public function setRules(array $in): void
    {
        $staff = $this->staff();
        if (!$staff) {
            throw new ApiError('Ohne Assistenz gibt es keine Regeln.');
        }
        $rules = [
            'minLevel' => max(0, min(3, (int) ($in['minLevel'] ?? 0))),
            'genres' => array_values(array_intersect(is_array($in['genres'] ?? null) ? $in['genres'] : [], array_keys(Catalog::GENRES))),
            'kind' => in_array($in['kind'] ?? 'any', ['any', 'solo', 'band'], true) ? $in['kind'] : 'any',
        ];
        $this->repo->db->update('labels', ['assist_rules' => json_encode($rules)], ['id' => $this->repo->labelId]);
    }

    /** Passt diese Demo zu den Regeln? */
    public static function matches(array $card, array $rules): bool
    {
        $isBand = ($card['kind'] ?? '') === 'band';
        if (($rules['kind'] === 'solo' && $isBand) || ($rules['kind'] === 'band' && !$isBand)) {
            return false;
        }
        $people = $isBand ? $card['members'] : [$card];
        $level = max(array_map(static fn ($c) => Levels::forSkill(max((float) $c['skill1'], (float) ($c['skill2'] ?? 0))), $people));
        if ($level < $rules['minLevel']) {
            return false;
        }
        if ($rules['genres']) {
            $genres = array_merge(...array_map(static fn ($c) => $c['genres'] ?? [], $people));
            if (!array_intersect($genres, $rules['genres'])) {
                return false;
            }
        }
        return true;
    }

    /** Arbeit seit dem letzten Mal: Demos öffnen und nach Regeln aussortieren. Ungenutzte Zeit verfällt (nichts zum Öffnen = nichts aufgespart). */
    public function work(int $now): void
    {
        $staff = $this->staff();
        if (!$staff) {
            return;
        }
        $db = $this->repo->db;
        $perDay = self::perDay($staff);
        $at = (int) $db->value('SELECT assist_at FROM labels WHERE id = ?', [$this->repo->labelId]) ?: $now;
        $count = (int) floor(($now - $at) * $perDay / 86400);
        if ($count <= 0) {
            if ($at === $now) {
                $db->update('labels', ['assist_at' => $now], ['id' => $this->repo->labelId]);
            }
            return;
        }
        $rules = $this->rules((int) $staff[0]['tier']);
        $rows = $db->all('SELECT id, card FROM submissions WHERE label_id = ? AND opened_at IS NULL AND saved = 0 AND expires_at > ? ORDER BY created_at, id LIMIT ' . $count, [$this->repo->labelId, $now]);
        $dropped = 0;
        foreach ($rows as $r) {
            if (self::matches(json_decode($r['card'], true), $rules)) {
                $db->update('submissions', ['opened_at' => $now - 1], ['id' => (int) $r['id']]);
            } else {
                $db->exec('DELETE FROM submissions WHERE id = ?', [(int) $r['id']]);
                $dropped++;
            }
        }
        $next = count($rows) < $count ? $now : (int) ($at + $count * 86400 / $perDay);
        $stats = $this->stats($now);
        $stats['opened'] += count($rows);
        $stats['dropped'] += $dropped;
        $db->update('labels', ['assist_at' => $next, 'assist_stats' => json_encode($stats)], ['id' => $this->repo->labelId]);
    }

    /** Was heute (seit Mitternacht UTC) geöffnet und weggeworfen wurde. */
    public function stats(int $now): array
    {
        $day = intdiv($now, 86400);
        $raw = json_decode((string) $this->repo->db->value('SELECT assist_stats FROM labels WHERE id = ?', [$this->repo->labelId]), true);
        return is_array($raw) && ($raw['day'] ?? null) === $day ? $raw : ['day' => $day, 'opened' => 0, 'dropped' => 0];
    }

    public function present(int $now): ?array
    {
        $staff = $this->staff();
        if (!$staff) {
            return null;
        }
        $tier = (int) $staff[0]['tier'];
        $stats = $this->stats($now);
        return [
            'staff' => array_map(static function ($s) {
                $d = json_decode($s['data'], true);
                return [
                    'id' => (int) $s['id'], 'tier' => (int) $s['tier'], 'firstName' => $d['first_name'], 'lastName' => $d['last_name'], 'look' => $d['look'],
                    'perDay' => Balance::ASSISTANT['tiers'][(int) $s['tier']]['perDay'],
                ];
            }, $staff),
            'tier' => $tier,
            'perDay' => self::perDay($staff),
            'rules' => $this->rules($tier),
            'allowed' => self::allowed($tier),
            'today' => ['opened' => $stats['opened'], 'dropped' => $stats['dropped']],
        ];
    }
}
