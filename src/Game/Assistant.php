<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;

/**
 * Assistenz im Posteingang: Jede sortiert die Post in Reiter und schreibt Notizen auf die Übersicht. Dazu hat jede eine Aufgabe
 * (Balance::ASSISTANT): Kassetten anhören (und nach den Regeln aussortieren) oder Presse (Interview-Anfragen zusagen, sobald der Musiker frei ist).
 * Mehrere mit derselben Aufgabe arbeiten zusammen.
 */
final class Assistant
{
    private const DEFAULT_RULES = ['minLevel' => 0, 'genres' => [], 'kind' => 'any'];

    public function __construct(private Repo $repo)
    {
    }

    /** @return list<array> eingestellte Assistenzen, jede mit ihrer Aufgabe */
    public function staff(): array
    {
        return array_map(static function ($s) {
            $d = json_decode($s['data'], true);
            // Praktikum: nur Post sortieren.
            $task = $s['role'] === 'intern' ? 'post' : (in_array($d['task'] ?? '', Balance::ASSISTANT['tasks'], true) ? $d['task'] : 'demos');
            return $s + ['task' => $task, 'info' => $d];
        }, $this->repo->db->all("SELECT * FROM staff WHERE label_id = ? AND role IN ('assistant', 'intern') ORDER BY id", [$this->repo->labelId]));
    }

    /** Kassetten am Tag: so viele, wie Assistenzen Kassetten anhören. */
    public static function perDay(array $staff): int
    {
        return Balance::ASSISTANT['tiers'][1]['perDay'] * count(array_filter($staff, static fn ($s) => $s['task'] === 'demos'));
    }

    public function setTask(int $staffId, string $task): void
    {
        if (!in_array($task, Balance::ASSISTANT['tasks'], true)) {
            throw new ApiError('Diese Aufgabe gibt es nicht.');
        }
        $row = $this->repo->db->one("SELECT * FROM staff WHERE id = ? AND label_id = ? AND role = 'assistant'", [$staffId, $this->repo->labelId]);
        if ($row === null) {
            throw new ApiError('Diese Assistenz gibt es nicht.');
        }
        // Was bis jetzt liegen blieb, wird noch mit der alten Aufteilung erledigt.
        $this->work(\Signed\Clock::now());
        $d = json_decode($row['data'], true);
        $d['task'] = $task;
        $this->repo->db->update('staff', ['data' => json_encode($d, JSON_UNESCAPED_UNICODE)], ['id' => $staffId]);
    }

    /** Praktikum übernehmen: Aus der Praktikantin wird eine feste Assistenz (Preis der Assistenz). */
    public function adopt(int $staffId): void
    {
        $db = $this->repo->db;
        $db->transaction(function () use ($db, $staffId): void {
            $row = $db->one("SELECT * FROM staff WHERE id = ? AND label_id = ? AND role = 'intern' AND busy_until > ?", [$staffId, $this->repo->labelId, \Signed\Clock::now()]);
            if ($row === null) {
                throw new ApiError('Dieses Praktikum gibt es nicht mehr.');
            }
            $d = json_decode($row['data'], true);
            $this->repo->spend(Balance::ASSISTANT['tiers'][1]['price'], sprintf('Übernommen: %s als Assistenz', $d['first_name']));
            $this->work(\Signed\Clock::now());
            $d['task'] = 'demos';
            // Aus dem Praktikum wird eine richtige Assistenz: mit Nachnamen, und sie sieht auf einmal erwachsener aus.
            if (($d['last_name'] ?? '') === '') {
                $d['last_name'] = (string) \Signed\Rng::pick(Catalog::LAST_NAMES);
            }
            unset($d['look']['young'], $d['job']);
            $db->update('staff', ['role' => 'assistant', 'busy_until' => 0, 'data' => json_encode($d, JSON_UNESCAPED_UNICODE)], ['id' => $staffId]);
            $this->repo->log('staff', sprintf('%s %s bleibt bei uns, jetzt als Assistenz.', $d['first_name'], $d['last_name']));
        });
    }

    /** Auftrag ans Praktikum (auspacken, anhören, sortieren): erledigt alles, was gerade da ist, nacheinander. */
    public function internOrder(int $staffId, string $job): void
    {
        $db = $this->repo->db;
        $db->transaction(function () use ($db, $staffId, $job): void {
            $now = \Signed\Clock::now();
            $row = $db->one("SELECT * FROM staff WHERE id = ? AND label_id = ? AND role = 'intern' AND busy_until > ?", [$staffId, $this->repo->labelId, $now]);
            if ($row === null) {
                throw new ApiError('Dieses Praktikum gibt es nicht mehr.');
            }
            $d = json_decode($row['data'], true);
            if (isset($d['job']) && (int) $d['job']['until'] > $now) {
                throw new ApiError(sprintf('%s ist noch beschäftigt.', $d['first_name']));
            }
            $count = (new Post($this->repo))->order($d, $job, $now);
            $step = ['unpack' => Balance::POST['unpack'], 'listen' => Balance::SUBMISSIONS['listen'], 'sort' => Balance::POST['sort']][$job];
            $d['job'] = ['kind' => $job, 'count' => $count, 'until' => $now + $count * $step];
            $db->update('staff', ['data' => json_encode($d, JSON_UNESCAPED_UNICODE)], ['id' => $staffId]);
        });
    }

    /** Fertig: Das Praktikum meldet sich (Nachricht) und hat wieder Zeit. */
    private function internDone(int $now): void
    {
        foreach ($this->repo->db->all("SELECT * FROM staff WHERE label_id = ? AND role = 'intern'", [$this->repo->labelId]) as $row) {
            $d = json_decode($row['data'], true);
            if (!isset($d['job']) || (int) $d['job']['until'] > $now) {
                continue;
            }
            $what = ['unpack' => '%d Umschläge ausgepackt', 'listen' => '%d Kassetten angehört', 'sort' => '%d Briefe in die Fächer sortiert'][$d['job']['kind']] ?? '%d erledigt';
            $this->repo->log('office', sprintf('%s ist fertig: ' . $what . '.', $d['first_name'], $d['job']['count']));
            unset($d['job']);
            $this->repo->db->update('staff', ['data' => json_encode($d, JSON_UNESCAPED_UNICODE)], ['id' => (int) $row['id']]);
        }
    }

    /** Abgelaufene Praktika: Sie verabschiedet sich. */
    private function endInternships(int $now): void
    {
        foreach ($this->repo->db->all("SELECT * FROM staff WHERE label_id = ? AND role = 'intern' AND busy_until <= ?", [$this->repo->labelId, $now]) as $row) {
            $d = json_decode($row['data'], true);
            $this->repo->db->exec('DELETE FROM staff WHERE id = ?', [(int) $row['id']]);
            $this->repo->log('staff', sprintf('Das Praktikum von %s ist zu Ende.', $d['first_name']));
        }
    }

    /** Eingestellte Regeln fürs Aussortieren. */
    public function rules(): array
    {
        $raw = json_decode((string) $this->repo->db->value('SELECT assist_rules FROM labels WHERE id = ?', [$this->repo->labelId]), true);
        return array_intersect_key((is_array($raw) ? $raw : []) + self::DEFAULT_RULES, self::DEFAULT_RULES);
    }

    public function setRules(array $in): void
    {
        if (!$this->staff()) {
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

    /** Arbeit seit dem letzten Mal: Kassetten anhören und aussortieren, Interviews zusagen. */
    public function work(int $now): void
    {
        $this->endInternships($now);
        $this->internDone($now);
        $staff = $this->staff();
        if (!$staff) {
            return;
        }
        // Eine Assistenz packt laufend aus und sortiert in die Fächer; ein Praktikum nur auf Zuruf.
        if (array_filter($staff, static fn ($s) => $s['role'] === 'assistant')) {
            (new Post($this->repo))->autoSort($now);
        }
        if (array_filter($staff, static fn ($s) => $s['task'] === 'presse')) {
            $this->press($staff);
        }
        $db = $this->repo->db;
        $perDay = self::perDay($staff);
        $at = (int) $db->value('SELECT assist_at FROM labels WHERE id = ?', [$this->repo->labelId]) ?: $now;
        $count = $perDay > 0 ? (int) floor(($now - $at) * $perDay / 86400) : 0;
        if ($count <= 0) {
            // Ohne Kassetten-Assistenz wird nichts aufgespart.
            if ($at === $now || $perDay === 0) {
                $db->update('labels', ['assist_at' => $now], ['id' => $this->repo->labelId]);
            }
            return;
        }
        $rules = $this->rules();
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

    /** Presse: offene Interview-Anfragen zusagen, sobald der Musiker frei ist (gemerkte auch). */
    private function press(array $staff): void
    {
        $name = $staff[array_key_first(array_filter($staff, static fn ($s) => $s['task'] === 'presse'))]['info']['first_name'];
        $inbox = new Inbox($this->repo);
        foreach ($this->repo->db->all("SELECT id FROM mail WHERE label_id = ? AND kind = 'interview' ORDER BY expires_at", [$this->repo->labelId]) as $row) {
            try {
                $activity = $inbox->accept((int) $row['id']);
                $this->repo->log('staff', sprintf('%s hat zugesagt: %s.', $name, $activity['title']));
            } catch (ApiError) {
                // Musiker gerade unterwegs: später noch einmal.
            }
        }
    }

    /** Was heute (seit Mitternacht UTC) angehört und weggeworfen wurde. */
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
        $stats = $this->stats($now);
        return [
            'staff' => array_map(static fn ($s) => [
                'id' => (int) $s['id'], 'firstName' => $s['info']['first_name'], 'lastName' => $s['info']['last_name'], 'look' => $s['info']['look'], 'task' => $s['task'],
                'intern' => $s['role'] === 'intern', 'until' => $s['role'] === 'intern' ? (int) $s['busy_until'] : null,
                'job' => isset($s['info']['job']) && (int) $s['info']['job']['until'] > $now ? $s['info']['job'] : null,
            ], $staff),
            'sorts' => (bool) array_filter($staff, static fn ($s) => $s['role'] === 'assistant'),
            'todo' => (new Post($this->repo))->todo($now),
            'adoptPrice' => Balance::ASSISTANT['tiers'][1]['price'],
            'perDay' => self::perDay($staff),
            'press' => (bool) array_filter($staff, static fn ($s) => $s['task'] === 'presse'),
            'rules' => $this->rules(),
            'today' => ['opened' => $stats['opened'], 'dropped' => $stats['dropped']],
        ];
    }
}
