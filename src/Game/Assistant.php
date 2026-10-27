<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;

/**
 * Assistenz im Posteingang: Jede hat ein Arbeitsgebiet (Balance::ASSISTANT) und arbeitet darin laufend, Stück für Stück, so schnell wie man
 * selbst: Post (auspacken, in die Reiter legen), Kassetten (anhören, in die Genre-Unterreiter legen, nach den Regeln wegwerfen, Suchauftrag)
 * oder Büro (Briefe lesen und auf der Übersicht davon erzählen, auf Wunsch Interviews zusagen). Mehrere im selben Gebiet teilen sich die Arbeit.
 * Das Praktikum dagegen arbeitet nur auf Zuruf.
 */
final class Assistant
{
    /** Wegwerfen (Kassetten) und Interviews selbst zusagen (Büro). */
    private const DEFAULT_RULES = ['minLevel' => 0, 'genres' => [], 'kind' => 'any', 'accept' => false];

    /** Was jedes Arbeitsgebiet tut, in dieser Reihenfolge: erst Kleines fertig machen, dann das Nächste anfangen. */
    private const AREA_JOBS = ['post' => ['autosort', 'unpack'], 'demos' => ['file', 'listen'], 'office' => ['read']];

    public function __construct(private Repo $repo)
    {
    }

    /** @return list<array> eingestellte Assistenzen, jede mit ihrer Aufgabe */
    public function staff(): array
    {
        return array_map(static function ($s) {
            $d = json_decode($s['data'], true);
            // Praktikum: nur auf Zuruf. Alte Aufgaben: „presse“ heißt jetzt Büro.
            $task = $d['task'] ?? 'demos';
            $task = $s['role'] === 'intern' ? 'post' : ($task === 'presse' ? 'office' : (in_array($task, Balance::ASSISTANT['tasks'], true) ? $task : 'none'));
            return $s + ['task' => $task, 'info' => $d];
        }, $this->repo->db->all("SELECT * FROM staff WHERE label_id = ? AND role IN ('assistant', 'intern') ORDER BY id", [$this->repo->labelId]));
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
        // Was bis jetzt liegen blieb, wird noch im alten Gebiet erledigt; das Stück, an dem sie gerade sitzt, legt sie wieder hin.
        $now = \Signed\Clock::now();
        $this->work($now);
        (new Post($this->repo))->cancel($now, 'a' . $staffId);
        $d = json_decode((string) $this->repo->db->value('SELECT data FROM staff WHERE id = ?', [$staffId]), true);
        $d['task'] = $task;
        $d['work'] = ['at' => $now, 'cur' => null];
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
            unset($d['look']['young'], $d['job'], $d['asked'], $d['work']);
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

    /** Eingestellte Regeln: Wegwerfen (Kassetten) und Interviews selbst zusagen (Büro). */
    public function rules(): array
    {
        $raw = json_decode((string) $this->repo->db->value('SELECT assist_rules FROM labels WHERE id = ?', [$this->repo->labelId]), true);
        return array_intersect_key((is_array($raw) ? $raw : []) + self::DEFAULT_RULES, self::DEFAULT_RULES);
    }

    /** Regeln ändern: Was nicht mitkommt, bleibt, wie es war. */
    public function setRules(array $in): void
    {
        if (!$this->staff()) {
            throw new ApiError('Ohne Assistenz gibt es keine Regeln.');
        }
        $rules = $this->rules();
        if (array_key_exists('minLevel', $in)) {
            $rules['minLevel'] = max(0, min(3, (int) $in['minLevel']));
        }
        if (array_key_exists('genres', $in)) {
            $rules['genres'] = array_values(array_intersect(is_array($in['genres']) ? $in['genres'] : [], array_keys(Catalog::GENRES)));
        }
        if (array_key_exists('kind', $in)) {
            $rules['kind'] = in_array($in['kind'], ['any', 'solo', 'band'], true) ? $in['kind'] : 'any';
        }
        if (array_key_exists('accept', $in)) {
            $rules['accept'] = (bool) $in['accept'];
        }
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

    /** Suchauftrag (Kassetten): Instrument, Genre, ab Stufe; null = sucht nichts. */
    public function search(): ?array
    {
        $raw = json_decode((string) $this->repo->db->value('SELECT assist_search FROM labels WHERE id = ?', [$this->repo->labelId]), true);
        return is_array($raw) ? $raw : null;
    }

    public function setSearch(?array $in): void
    {
        if (!$this->staff()) {
            throw new ApiError('Ohne Assistenz sucht niemand für dich.');
        }
        $search = $in === null ? null : [
            'instrument' => isset(Catalog::INSTRUMENTS[$in['instrument'] ?? '']) ? (string) $in['instrument'] : '',
            'genre' => isset(Catalog::GENRES[$in['genre'] ?? '']) ? (string) $in['genre'] : '',
            'minLevel' => max(0, min(3, (int) ($in['minLevel'] ?? 0))),
        ];
        // Ohne Vorgabe passt alles: Dann sucht sie nichts Bestimmtes.
        if ($search !== null && $search['instrument'] === '' && $search['genre'] === '' && $search['minLevel'] === 0) {
            $search = null;
        }
        $this->repo->db->update('labels', ['assist_search' => $search === null ? null : json_encode($search)], ['id' => $this->repo->labelId]);
    }

    /** Passt jemand auf dieser Kassette (bei Bands: ein Mitglied) zum Suchauftrag? */
    public static function fits(array $card, array $search): bool
    {
        foreach (($card['kind'] ?? '') === 'band' ? $card['members'] : [$card] as $c) {
            $plays = array_filter([$c['instr1'] ?? null => $c['skill1'] ?? 0, $c['instr2'] ?? null => $c['skill2'] ?? 0], static fn ($v, $k) => $k !== '', ARRAY_FILTER_USE_BOTH);
            if ($search['instrument'] !== '') {
                $plays = array_intersect_key($plays, [$search['instrument'] => true]);
            }
            if (!$plays || ($search['genre'] !== '' && !in_array($search['genre'], $c['genres'] ?? [], true))) {
                continue;
            }
            if (Levels::forSkill((float) max($plays)) >= $search['minLevel']) {
                return true;
            }
        }
        return false;
    }

    /** Arbeit seit dem letzten Mal: Praktikum (Zurufe), dann die Assistenzen in ihren Gebieten, zuletzt Interviews zusagen. */
    public function work(int $now): void
    {
        $this->endInternships($now);
        $this->internProgress($now);
        $this->askInterns($now);
        $this->run($now);
        if ($this->rules()['accept']) {
            $this->press($now);
        }
    }

    /**
     * Die Assistenzen arbeiten Stück für Stück, auch für die Zeit, in der man offline war (höchstens Balance::ASSISTANT['catchUp']).
     * Wer als Nächstes frei wird, nimmt sich das älteste Stück, das in dem Moment für sein Gebiet bereit liegt; liegt nichts da, wartet er,
     * bis etwas kommt oder jemand anders fertig wird (der Post-Assistenz zuzusehen lohnt sich für die Kassetten-Assistenz).
     */
    private function run(int $now): void
    {
        $post = new Post($this->repo);
        $w = [];
        foreach ($this->staff() as $s) {
            if ($s['role'] !== 'assistant' || !isset(self::AREA_JOBS[$s['task']])) {
                continue;
            }
            $work = $s['info']['work'] ?? null;
            $w[(int) $s['id']] = [
                'area' => $s['task'], 'name' => $s['info']['first_name'], 'data' => $s['info'],
                'at' => max((int) ($work['at'] ?? $now), $now - Balance::ASSISTANT['catchUp']), 'cur' => $work['cur'] ?? null, 'yield' => false, 'done' => false,
            ];
        }
        if (!$w) {
            return;
        }
        $free = static fn (array $x): int => $x['cur'] !== null ? (int) $x['cur'][3] : $x['at'];
        $stats = $this->stats($now);
        for ($guard = 0; $guard < 20000; $guard++) {
            $id = null;
            foreach ($w as $k => $x) {
                if (!$x['done'] && $free($x) <= $now && ($id === null || [$free($x), (int) $x['yield']] < [$free($w[$id]), (int) $w[$id]['yield']])) {
                    $id = $k;
                }
            }
            if ($id === null) {
                break;
            }
            $x = &$w[$id];
            if ($x['cur'] !== null) {
                // Fertig mit dem Stück.
                [$job, $table, $item, $end] = $x['cur'];
                $this->finish($job, $table, (int) $item, (int) $end, $x['name'], $stats);
                $x['at'] = (int) $end;
                $x['cur'] = null;
                unset($x);
                foreach ($w as $k => $y) {
                    $w[$k]['yield'] = false;
                }
                continue;
            }
            $started = false;
            foreach (self::AREA_JOBS[$x['area']] as $job) {
                $next = $post->oldest($job, $x['at']);
                if ($next !== null && ($end = $post->step($job, $next, $x['at'], 'a' . $id)) !== null) {
                    $x['cur'] = [$job, $next[0], $next[1], $end];
                    $started = true;
                    break;
                }
            }
            if ($started) {
                unset($x);
                foreach ($w as $k => $y) {
                    $w[$k]['yield'] = false;
                }
                continue;
            }
            // Nichts da: Wer zur gleichen Zeit frei ist, darf zuerst (vielleicht bringt er etwas); danach warten, bis etwas kommt.
            $others = array_map($free, array_filter($w, static fn ($y, $k) => $k !== $id && !$y['done'], ARRAY_FILTER_USE_BOTH));
            if (!$x['yield'] && in_array($x['at'], $others, true)) {
                $x['yield'] = true;
                unset($x);
                continue;
            }
            $times = array_filter($others, static fn ($t) => $t > $x['at']);
            foreach (self::AREA_JOBS[$x['area']] as $job) {
                $t = $post->readyAt($job, $x['at'], $now);
                if ($t !== null) {
                    $times[] = $t;
                }
            }
            $next = $times ? min($times) : null;
            if ($next === null || $next > $now) {
                $x['at'] = $now;
                $x['done'] = true;
            } else {
                $x['at'] = $next;
                $x['yield'] = false;
            }
            unset($x);
        }
        foreach ($w as $id => $x) {
            $d = $x['data'];
            $d['work'] = ['at' => $x['cur'] !== null ? $x['at'] : min($x['at'], $now), 'cur' => $x['cur']];
            $this->repo->db->update('staff', ['data' => json_encode($d, JSON_UNESCAPED_UNICODE)], ['id' => $id]);
        }
        $this->repo->db->update('labels', ['assist_stats' => json_encode($stats)], ['id' => $this->repo->labelId]);
    }

    /** Ein Stück ist fertig: Angehörte Kassetten nach den Regeln behalten oder wegwerfen, Gefundenes melden. */
    private function finish(string $job, string $table, int $id, int $end, string $name, array &$stats): void
    {
        if ($table !== 'submissions' || !in_array($job, ['listen', 'file'], true)) {
            return;
        }
        $db = $this->repo->db;
        $row = $db->one('SELECT * FROM submissions WHERE id = ?', [$id]);
        if ($row === null) {
            return;
        }
        $card = json_decode($row['card'], true);
        if ($job === 'listen') {
            // Gemerktes wirft sie nie weg.
            if (!(int) $row['saved'] && !self::matches($card, $this->rules())) {
                $db->exec('DELETE FROM submissions WHERE id = ?', [$id]);
                $stats['dropped']++;
                return;
            }
            $stats['opened']++;
            $db->update('submissions', ['filed_at' => $end], ['id' => $id]);
        }
        $search = $this->search();
        if ($search !== null && !(int) $row['found'] && self::fits($card, $search)) {
            $db->update('submissions', ['found' => 1, 'saved' => 1], ['id' => $id]);
            $who = ($card['kind'] ?? '') === 'band' ? sprintf('die Band %s', $card['name']) : trim($card['first_name'] . ' ' . $card['last_name']);
            $this->repo->log('office', sprintf('%s hat etwas gefunden, das zu deiner Suche passt: %s. Die Kassette liegt bei den gemerkten.', $name, $who));
        }
    }

    /** Büro: gelesene Interview-Anfragen zusagen, sobald der Musiker frei ist (gemerkte auch). */
    private function press(int $now): void
    {
        $office = array_values(array_filter($this->staff(), static fn ($s) => $s['role'] === 'assistant' && $s['task'] === 'office'));
        if (!$office) {
            return;
        }
        $inbox = new Inbox($this->repo);
        foreach ($this->repo->db->all("SELECT id FROM mail WHERE label_id = ? AND kind = 'interview' AND read_at <= ? ORDER BY expires_at", [$this->repo->labelId, $now]) as $row) {
            try {
                $activity = $inbox->accept((int) $row['id']);
                $this->repo->log('staff', sprintf('%s hat zugesagt: %s.', $office[0]['info']['first_name'], $activity['title']));
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
        $areas = [];
        foreach (array_keys(self::AREA_JOBS) as $area) {
            $areas[$area] = (bool) array_filter($staff, static fn ($s) => $s['role'] === 'assistant' && $s['task'] === $area);
        }
        return [
            'staff' => array_map(static fn ($s) => [
                'id' => (int) $s['id'], 'firstName' => $s['info']['first_name'], 'lastName' => $s['info']['last_name'], 'look' => $s['info']['look'], 'task' => $s['task'],
                'intern' => $s['role'] === 'intern', 'until' => $s['role'] === 'intern' ? (int) $s['busy_until'] : null,
                // Praktikum vorbei: wartet bis `leaves` auf eine Antwort (übernehmen oder verabschieden)
                'ended' => $s['role'] === 'intern' && (int) $s['busy_until'] <= $now, 'leaves' => $s['role'] === 'intern' ? (int) $s['busy_until'] + Balance::INTERN['decide'] : null,
                // Was es gerade tut: Art, voraussichtlich fertig, wann das aktuelle Stück fertig ist (dann geht es zum nächsten)
                'job' => isset($s['info']['job']['queue']) ? ['kind' => $s['info']['job']['kind'], 'until' => (int) $s['info']['job']['until'], 'next' => (int) $s['info']['job']['end'], 'count' => (int) $s['info']['job']['count']] : null,
                // Assistenz: woran sie gerade sitzt (null: nichts da)
                'doing' => $s['role'] === 'assistant' && ($s['info']['work']['cur'] ?? null) !== null && (int) $s['info']['work']['cur'][3] > $now ? $s['info']['work']['cur'][0] : null,
                'doingUntil' => $s['role'] === 'assistant' && ($s['info']['work']['cur'] ?? null) !== null && (int) $s['info']['work']['cur'][3] > $now ? (int) $s['info']['work']['cur'][3] : null,
            ], $staff),
            'areas' => $areas,
            'todo' => (new Post($this->repo))->todo($now),
            'adoptPrice' => Balance::ASSISTANT['tiers'][1]['price'],
            'rules' => $this->rules(),
            'search' => $this->search(),
            'today' => ['opened' => $stats['opened'], 'dropped' => $stats['dropped']],
        ];
    }
}
