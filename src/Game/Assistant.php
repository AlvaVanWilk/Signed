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

    /** Praktikum übernehmen: Aus der Praktikantin wird eine feste Assistenz (Preis der Assistenz). Erst, wenn das Praktikum vorbei ist. */
    public function adopt(int $staffId): void
    {
        $db = $this->repo->db;
        $db->transaction(function () use ($db, $staffId): void {
            $row = $this->internRow($staffId);
            $d = json_decode($row['data'], true);
            if ((int) $row['busy_until'] > \Signed\Clock::now()) {
                throw new ApiError(sprintf('Übernehmen kannst du %s erst, wenn das Praktikum vorbei ist.', $d['first_name']));
            }
            $this->repo->spend(Balance::ASSISTANT['tiers'][1]['price'], sprintf('Übernommen: %s als Assistenz', $d['first_name']));
            $this->work(\Signed\Clock::now());
            // Ein Arbeitsgebiet bekommt sie erst, wenn man es ihr gibt.
            $d['task'] = 'none';
            // Aus dem Praktikum wird eine richtige Assistenz: mit Nachnamen, und sie sieht auf einmal erwachsener aus.
            if (($d['last_name'] ?? '') === '') {
                $d['last_name'] = (string) \Signed\Rng::pick(Catalog::LAST_NAMES);
            }
            unset($d['look']['young'], $d['job'], $d['asked']);
            $db->update('staff', ['role' => 'assistant', 'busy_until' => 0, 'data' => json_encode($d, JSON_UNESCAPED_UNICODE)], ['id' => $staffId]);
            $this->repo->log('staff', sprintf('%s %s bleibt bei uns, jetzt als Assistenz.', $d['first_name'], $d['last_name']));
        });
    }

    /** Nach dem Praktikum verabschieden, statt zu übernehmen. */
    public function dismiss(int $staffId): void
    {
        $row = $this->internRow($staffId);
        $d = json_decode($row['data'], true);
        if ((int) $row['busy_until'] > \Signed\Clock::now()) {
            throw new ApiError(sprintf('%s ist noch im Praktikum.', $d['first_name']));
        }
        $this->repo->db->exec('DELETE FROM staff WHERE id = ?', [$staffId]);
        $this->repo->log('staff', sprintf('%s ist verabschiedet. Das Praktikum ist zu Ende.', $d['first_name']));
    }

    /** Ein Praktikum dieses Labels (auch nach dem Ende, solange es noch auf eine Antwort wartet). */
    private function internRow(int $staffId): array
    {
        $row = $this->repo->db->one("SELECT * FROM staff WHERE id = ? AND label_id = ? AND role = 'intern'", [$staffId, $this->repo->labelId]);
        if ($row === null) {
            throw new ApiError('Dieses Praktikum gibt es nicht mehr.');
        }
        return $row;
    }

    /** Auftrag ans Praktikum (auspacken, anhören, sortieren): erledigt alles, was gerade da ist, nacheinander. */
    public function internOrder(int $staffId, string $job): void
    {
        $db = $this->repo->db;
        $db->transaction(function () use ($db, $staffId, $job): void {
            $now = \Signed\Clock::now();
            $row = $this->internRow($staffId);
            $d = json_decode($row['data'], true);
            if ((int) $row['busy_until'] <= $now) {
                throw new ApiError(sprintf('Das Praktikum von %s ist vorbei.', $d['first_name']));
            }
            if (isset($d['job'])) {
                throw new ApiError(sprintf('%s ist noch beschäftigt.', $d['first_name']));
            }
            $queue = (new Post($this->repo))->order($job, $now);
            $d['job'] = ['kind' => $job, 'queue' => $queue, 'at' => $now, 'current' => null, 'end' => 0, 'count' => 0, 'until' => $now];
            $this->advance($staffId, $d, $now);
        });
    }

    /**
     * Das Praktikum arbeitet seine Liste ab, ein Stück nach dem anderen (auch für die Zeit, in der man offline war). Was inzwischen jemand
     * anders erledigt hat, überspringt es. Ist die Liste leer, meldet es sich.
     */
    private function advance(int $staffId, array $d, int $now): void
    {
        $post = new Post($this->repo);
        $j = &$d['job'];
        while (true) {
            if ($j['current'] !== null) {
                if ($j['end'] > $now) {
                    break;
                }
                $j['count']++;
                $j['at'] = $j['end'];
                $j['current'] = null;
            }
            if (!$j['queue']) {
                break;
            }
            $item = array_shift($j['queue']);
            $end = $post->step($j['kind'], $item, $j['at']);
            if ($end !== null) {
                $j['current'] = $item;
                $j['end'] = $end;
            }
        }
        $j['until'] = ($j['current'] !== null ? $j['end'] : $j['at']) + count($j['queue']) * Post::seconds($j['kind']);
        if ($j['current'] === null) {
            $this->internDone($d);
        }
        unset($j);
        $this->repo->db->update('staff', ['data' => json_encode($d, JSON_UNESCAPED_UNICODE)], ['id' => $staffId]);
    }

    /** Zuruf abbrechen: Das Praktikum hört sofort auf; was es noch nicht geschafft hat, liegt wieder da wie vorher. */
    public function internCancel(int $staffId): void
    {
        $db = $this->repo->db;
        $db->transaction(function () use ($db, $staffId): void {
            $now = \Signed\Clock::now();
            $row = $db->one("SELECT * FROM staff WHERE id = ? AND label_id = ? AND role = 'intern'", [$staffId, $this->repo->labelId]);
            $d = $row === null ? null : json_decode($row['data'], true);
            if ($d === null || !isset($d['job'])) {
                throw new ApiError('Das Praktikum hat gerade nichts zu tun.');
            }
            (new Post($this->repo))->cancel($now);
            unset($d['job']);
            $db->update('staff', ['data' => json_encode($d, JSON_UNESCAPED_UNICODE)], ['id' => $staffId]);
        });
    }

    /** Fertig: Das Praktikum meldet sich (Nachricht) und hat wieder Zeit. */
    private function internDone(array &$d): void
    {
        $n = (int) $d['job']['count'];
        $what = ['unpack' => ['einen Brief geöffnet', '%d Briefe geöffnet'], 'listen' => ['eine Demo angehört', '%d Demos angehört'], 'sort' => ['ein Stück Post sortiert', '%d Stück Post sortiert']][$d['job']['kind']] ?? ['etwas erledigt', '%d Sachen erledigt'];
        $this->repo->log('office', $n === 0
            ? sprintf('%s ist fertig: Es war schon alles erledigt.', $d['first_name'])
            : sprintf('%s ist fertig: %s.', $d['first_name'], $n === 1 ? $what[0] : sprintf($what[1], $n)));
        unset($d['job']);
    }

    /** Laufende Zurufe weiterführen. */
    private function internProgress(int $now): void
    {
        foreach ($this->repo->db->all("SELECT * FROM staff WHERE label_id = ? AND role = 'intern'", [$this->repo->labelId]) as $row) {
            $d = json_decode($row['data'], true);
            if (!isset($d['job'])) {
                continue;
            }
            if (!isset($d['job']['queue'])) {
                // Auftrag aus der Zeit vor der Liste: einfach beenden.
                unset($d['job']);
                $this->repo->db->update('staff', ['data' => json_encode($d, JSON_UNESCAPED_UNICODE)], ['id' => (int) $row['id']]);
                continue;
            }
            // Gearbeitet wird nur bis zum Ende des Praktikums; was dann noch offen ist, liegt wieder da wie vorher.
            $end = (int) $row['busy_until'];
            $this->advance((int) $row['id'], $d, min($now, $end));
            $d = json_decode((string) $this->repo->db->value('SELECT data FROM staff WHERE id = ?', [(int) $row['id']]), true);
            if ($now >= $end && isset($d['job'])) {
                (new Post($this->repo))->cancel($end);
                unset($d['job']);
                $this->repo->db->update('staff', ['data' => json_encode($d, JSON_UNESCAPED_UNICODE)], ['id' => (int) $row['id']]);
            }
        }
    }

    /** Abgelaufene Praktika: Wer nicht übernommen oder verabschiedet wurde, geht nach der Bedenkzeit. */
    private function endInternships(int $now): void
    {
        foreach ($this->repo->db->all("SELECT * FROM staff WHERE label_id = ? AND role = 'intern' AND busy_until <= ?", [$this->repo->labelId, $now - Balance::INTERN['decide']]) as $row) {
            $d = json_decode($row['data'], true);
            $this->repo->db->exec('DELETE FROM staff WHERE id = ?', [(int) $row['id']]);
            $this->repo->log('staff', sprintf('%s ist gegangen. Das Praktikum war vorbei, und niemand hat geantwortet.', $d['first_name']));
        }
    }

    /** Gerade zu Ende gegangene Praktika fragen einmal, ob sie bleiben dürfen. */
    private function askInterns(int $now): void
    {
        foreach ($this->repo->db->all("SELECT * FROM staff WHERE label_id = ? AND role = 'intern' AND busy_until <= ?", [$this->repo->labelId, $now]) as $row) {
            $d = json_decode($row['data'], true);
            if (!empty($d['asked'])) {
                continue;
            }
            $d['asked'] = true;
            $this->repo->db->update('staff', ['data' => json_encode($d, JSON_UNESCAPED_UNICODE)], ['id' => (int) $row['id']]);
            $this->repo->log('staff', sprintf('Das Praktikum von %s ist vorbei. %s möchte gern bleiben: Im Posteingang kannst du übernehmen oder verabschieden.', $d['first_name'], $d['first_name']));
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
        $this->internProgress($now);
        $this->askInterns($now);
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
                // Praktikum vorbei: wartet bis `leaves` auf eine Antwort (übernehmen oder verabschieden)
                'ended' => $s['role'] === 'intern' && (int) $s['busy_until'] <= $now, 'leaves' => $s['role'] === 'intern' ? (int) $s['busy_until'] + Balance::INTERN['decide'] : null,
                // Was es gerade tut: Art, voraussichtlich fertig, wann das aktuelle Stück fertig ist (dann geht es zum nächsten)
                'job' => isset($s['info']['job']['queue']) ? ['kind' => $s['info']['job']['kind'], 'until' => (int) $s['info']['job']['until'], 'next' => (int) $s['info']['job']['end'], 'count' => (int) $s['info']['job']['count']] : null,
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
