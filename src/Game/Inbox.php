<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;
use Signed\Rng;

/**
 * Posteingang neben den Demos (Submissions): Interview-Anfragen für die eigenen Musiker und Bewerbungen von Leuten fürs Label.
 * Beides trudelt über die Zeit ein (auch offline), liegt eine Weile und lässt sich merken. Ohne Assistenz liegt alles auf einem Stapel.
 */
final class Inbox
{
    public function __construct(private Repo $repo)
    {
    }

    /** Güte der Bewerber: wächst mit dem Erfolg des Labels, aber immer langsamer (oben offen, nie ganz 1). */
    public static function quality(float $success): float
    {
        return max(0.0, $success) / (max(0.0, $success) + Balance::JOBS['half']);
    }

    /** Weg von dieser Bekanntheitsstufe bis zur nächsten (auf der höchsten Stufe ein fester Wert). */
    public static function span(int $level): float
    {
        $t = Balance::FAME_LEVELS;
        if ($level >= count($t)) {
            return Balance::INTERVIEW['topSpan'];
        }
        return $t[$level] - ($level > 0 ? $t[$level - 1] : 0);
    }

    public function topUp(int $now, float $success): void
    {
        $this->expire($now);
        $this->topUpInterviews($now);
        $this->topUpJobs($now, $success);
    }

    private function expire(int $now): void
    {
        $db = $this->repo->db;
        $db->exec('DELETE FROM mail WHERE label_id = ? AND expires_at <= ?', [$this->repo->labelId, $now]);
        // Wer nicht mehr im Label ist, bekommt hier keine Anfragen mehr.
        foreach ($db->all("SELECT id, data FROM mail WHERE label_id = ? AND kind = 'interview'", [$this->repo->labelId]) as $row) {
            $id = (int) (json_decode($row['data'], true)['musicianId'] ?? 0);
            if ($db->value('SELECT id FROM musicians WHERE id = ? AND label_id = ?', [$id, $this->repo->labelId]) === null) {
                $db->exec('DELETE FROM mail WHERE id = ?', [(int) $row['id']]);
            }
        }
    }

    /** Interview-Anfragen: je Musiker im Schnitt eine am Tag, höchstens eine offene je Musiker. */
    private function topUpInterviews(int $now): void
    {
        $db = $this->repo->db;
        $c = Balance::INTERVIEW;
        $musicians = $this->repo->musicians();
        $at = (int) $db->value('SELECT interview_at FROM labels WHERE id = ?', [$this->repo->labelId]);
        if ($at === 0 || !$musicians) {
            $db->update('labels', ['interview_at' => $now], ['id' => $this->repo->labelId]);
            return;
        }
        $interval = 86400 / ($c['perDay'] * count($musicians));
        $at = max($at, $now - $c['maxCatchUp']);
        $count = (int) floor(($now - $at) / $interval);
        if ($count <= 0) {
            return;
        }
        $open = [];
        foreach ($db->all("SELECT data FROM mail WHERE label_id = ? AND kind = 'interview'", [$this->repo->labelId]) as $row) {
            $open[(int) (json_decode($row['data'], true)['musicianId'] ?? 0)] = true;
        }
        for ($i = 1; $i <= $count; $i++) {
            $created = (int) ($at + $i * $interval);
            $free = array_values(array_filter($musicians, static fn ($m) => !isset($open[$m['id']])));
            if (!$free || $created + $c['ttl'] <= $now) {
                continue;
            }
            $m = Rng::pick($free);
            $open[$m['id']] = true;
            $level = Levels::forFame((float) $m['fame']);
            $medium = Rng::pick(Catalog::INTERVIEW_MEDIA[min(3, $level)]);
            $this->store('interview', ['musicianId' => $m['id'], 'level' => $level, 'medium' => $medium['name'], 'share' => $medium['share']], $created, $c['ttl']);
        }
        $db->update('labels', ['interview_at' => (int) ($at + $count * $interval)], ['id' => $this->repo->labelId]);
    }

    /** Bewerbungen: ein, zwei am Tag; wer sich bewirbt und wie gut, folgt dem Erfolg des Labels. */
    private function topUpJobs(int $now, float $success): void
    {
        $db = $this->repo->db;
        $c = Balance::JOBS;
        $at = (int) $db->value('SELECT job_at FROM labels WHERE id = ?', [$this->repo->labelId]);
        // Solange es keine Assistenz gibt, liegt immer eine einfache Bewerbung dafür da (eine Assistenz hilft früh).
        if ((int) $db->value("SELECT COUNT(*) FROM staff WHERE label_id = ? AND role = 'assistant'", [$this->repo->labelId]) === 0
            && !array_filter($db->all("SELECT data FROM mail WHERE label_id = ? AND kind = 'job'", [$this->repo->labelId]), static fn ($r) => (json_decode($r['data'], true)['role'] ?? '') === 'assistant')) {
            $this->store('job', self::applicant('assistant', 0.0, 1), $now, $c['ttl']);
        }
        if ($at === 0) {
            // Willkommens-Brief: Ein einfacher Scout bewirbt sich gleich zu Beginn (die Assistenz steht oben).
            if ((int) $db->value("SELECT COUNT(*) FROM staff WHERE label_id = ? AND role = 'scout'", [$this->repo->labelId]) === 0) {
                $this->store('job', self::applicant('scout', 0.0, 1), $now, $c['ttl']);
            }
            $db->update('labels', ['job_at' => $now], ['id' => $this->repo->labelId]);
            return;
        }
        $interval = 86400 / $c['perDay'];
        $at = max($at, $now - $c['maxCatchUp']);
        $count = (int) floor(($now - $at) / $interval);
        if ($count <= 0) {
            return;
        }
        $roles = array_filter($c['roles'], static fn ($r) => $success >= $r['minSuccess']);
        $own = [];
        $genres = [];
        foreach ($this->repo->musicians() as $m) {
            $genres = array_merge($genres, $m['genres']);
            $own[] = $m['instr1'];
            if ($m['instr2'] !== null) {
                $own[] = $m['instr2'];
            }
        }
        for ($i = 1; $i <= $count; $i++) {
            $created = (int) ($at + $i * $interval);
            if ($roles && $created + $c['ttl'] > $now) {
                $role = (string) Rng::weighted(array_map(static fn ($r) => $r['weight'], $roles));
                $this->store('job', self::applicant($role, self::quality($success), null, $own, $genres), $created, $c['ttl']);
            }
        }
        $db->update('labels', ['job_at' => (int) ($at + $count * $interval)], ['id' => $this->repo->labelId]);
    }

    /** Eine Bewerbung: Person, Rolle und Stufe. Die beste Stufe, die sich bei dieser Güte schon bewirbt, kommt am häufigsten. */
    public static function applicant(string $role, float $quality, ?int $tier = null, array $ownInstruments = [], array $ownGenres = []): array
    {
        $tiers = self::tiers($role);
        if ($tier === null) {
            $open = array_reverse(array_keys(array_filter($tiers, static fn ($t) => $quality >= $t['from'])));
            $weights = [];
            foreach ($open as $k => $t) {
                $weights[$t] = Balance::JOBS['weights'][min($k, 2)];
            }
            $tier = (int) Rng::weighted($weights ?: [1 => 1]);
        }
        $out = ['role' => $role, 'tier' => $tier] + Generator::person();
        if ($role === 'teacher') {
            // Meist für ein Instrument, das im Label gespielt wird; manchmal mit Lieblingsgenre.
            $c = Balance::TEACHER;
            $out['instrument'] = $ownInstruments && Rng::chance($c['ownInstrument'])
                ? (string) Rng::pick($ownInstruments)
                : (string) Rng::weighted(array_map(static fn ($i) => $i['weight'], Catalog::INSTRUMENTS));
            $out['genre'] = Rng::chance($c['genreChance']) ? (string) Rng::pick(Catalog::INSTRUMENTS[$out['instrument']]['genres']) : null;
        }
        if ($role === 'manager') {
            $out += Managers::applicant($tier, $ownGenres);
        }
        return $out;
    }

    /** @return array<int, array> Stufen einer Rolle mit Preis und Werten */
    public static function tiers(string $role): array
    {
        return match ($role) {
            'assistant' => Balance::ASSISTANT['tiers'],
            'scout' => Balance::SCOUT_STAFF['tiers'],
            'teacher' => Balance::TEACHER['tiers'],
            'coach' => Balance::COACH['tiers'],
            'manager' => Balance::MANAGER_STAFF['tiers'],
            default => throw new ApiError('Unbekannte Bewerbung.'),
        };
    }

    public function store(string $kind, array $data, int $at, int $ttl): void
    {
        $this->repo->db->insert('mail', [
            'label_id' => $this->repo->labelId, 'kind' => $kind, 'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'created_at' => $at, 'expires_at' => $at + $ttl,
        ]);
    }

    /** Was man bei einem Interview bekäme (ohne Bremse). */
    public static function interviewGain(array $data): float
    {
        return round((float) $data['share'] * self::span((int) $data['level']), 2);
    }

    /** @return list<array> Anfragen und Bewerbungen, neueste zuerst */
    public function list(int $now): array
    {
        $rows = $this->repo->db->all('SELECT * FROM mail WHERE label_id = ? AND expires_at > ? ORDER BY created_at DESC, id DESC', [$this->repo->labelId, $now]);
        $bands = null;
        // Manager: wie gut er zu jeder Band passen würde.
        $fits = function (array $d) use (&$bands): object {
            $bands ??= array_map(fn ($b) => $this->repo->members($b['id']), $this->repo->bands());
            return (object) array_map(static fn ($members) => round(Managers::fit($d, $members), 2), $bands);
        };
        return array_map(static function (array $r) use ($fits): array {
            $d = json_decode($r['data'], true);
            $base = ['mailId' => (int) $r['id'], 'kind' => $r['kind'], 'createdAt' => (int) $r['created_at'], 'expiresAt' => (int) $r['expires_at'], 'saved' => (bool) $r['saved']];
            if ($r['kind'] === 'interview') {
                return $base + [
                    'musicianId' => (int) $d['musicianId'], 'medium' => $d['medium'], 'level' => (int) $d['level'],
                    'fame' => self::interviewGain($d), 'seconds' => Balance::INTERVIEW['seconds'][min(3, (int) $d['level'])],
                ];
            }
            if ($r['kind'] === 'report') {
                return $base + Scouting::presentReport($d, (int) $r['id']);
            }
            $tier = self::tiers($d['role'])[(int) $d['tier']];
            return $base + [
                'role' => $d['role'], 'tier' => (int) $d['tier'], 'price' => (int) $tier['price'], 'perDay' => $tier['perDay'] ?? null,
                'seconds' => $tier['seconds'] ?? null, 'candidates' => $tier['candidates'] ?? null, 'maxSkill' => $tier['maxSkill'] ?? (isset($tier['skill']) ? $tier['skill'][1] : null),
                'instrument' => $d['instrument'] ?? null, 'genre' => $d['genre'] ?? null, 'gain' => $tier['gain'] ?? null,
                'speed' => $tier['speed'] ?? null, 'quality' => $tier['quality'] ?? null,
                'level' => $tier['level'] ?? null, 'skills' => $d['skills'] ?? null, 'genres' => $d['genres'] ?? null, 'style' => $d['style'] ?? null,
                'fits' => $d['role'] === 'manager' ? $fits($d) : null,
                'firstName' => $d['first_name'], 'lastName' => $d['last_name'], 'look' => $d['look'],
            ];
        }, $rows);
    }

    private function row(int $id): array
    {
        $row = $this->repo->db->one('SELECT * FROM mail WHERE id = ? AND label_id = ? AND expires_at > ?', [$id, $this->repo->labelId, Clock::now()]);
        if ($row === null) {
            throw new ApiError('Diesen Brief gibt es nicht mehr.');
        }
        return $row;
    }

    /** Interview annehmen: Der Musiker ist kurz weg und kommt bekannter zurück. */
    public function accept(int $id): array
    {
        $db = $this->repo->db;
        return $db->transaction(function () use ($db, $id): array {
            $row = $this->row($id);
            if ($row['kind'] !== 'interview') {
                throw new ApiError('Das ist keine Interview-Anfrage.');
            }
            $d = json_decode($row['data'], true);
            $now = Clock::now();
            $m = $this->repo->musician((int) $d['musicianId']);
            if ($m['busy_until'] > $now || ($m['band_id'] !== null && $this->repo->band($m['band_id'])['busy_until'] > $now)) {
                throw new ApiError(sprintf('%s ist gerade unterwegs. Die Anfrage wartet noch.', $m['first_name']));
            }
            $endsAt = $now + Balance::INTERVIEW['seconds'][min(3, (int) $d['level'])];
            if ($db->exec('UPDATE musicians SET busy_until = ? WHERE id = ? AND busy_until <= ?', [$endsAt, $m['id'], $now]) === 0) {
                throw new ApiError(sprintf('%s ist gerade unterwegs.', $m['first_name']));
            }
            $db->exec('DELETE FROM mail WHERE id = ?', [$id]);
            // Netzwerker: Ein Manager mit guten Kontakten holt mehr aus dem Interview.
            [$manager, $level] = Managers::of($this->repo, $m['band_id']);
            $gain = self::interviewGain($d) * (1 + BandCalc::managerBonus($manager, $level, 'netzwerker', Balance::MANAGER_STAFF['network']));
            return (new Activities($this->repo))->insertSolo($m['id'], 'interview', $now, $endsAt, [
                'title' => sprintf('Interview: %s bei %s', Bands::fullName($m), $d['medium']),
                'songs' => 0,
                'reward' => ['fame' => round($gain, 2), 'medium' => $d['medium']],
            ]);
        });
    }

    /** Gutschrift nach dem Interview (Activities::settleDue). */
    public static function settle(Repo $repo, array $row, array $data): array
    {
        $musicianId = (int) $row['musician_id'];
        $gain = $repo->addMusicianFame($musicianId, (float) ($data['reward']['fame'] ?? 0), false);
        $m = $repo->db->one('SELECT * FROM musicians WHERE id = ?', [$musicianId]);
        if ($m === null) {
            return ['fame' => 0, 'total' => 0];
        }
        if ($m['band_id'] !== null) {
            Charts::boost($repo, (int) $m['band_id'], Balance::CHART_PROMO_BOOST['solo']);
        }
        Mood::react($repo, [Repo::hydrateMusician($m)], 'interview');
        $repo->log('interview', sprintf('%s %s war bei: %s.', $m['first_name'], $m['last_name'], $data['reward']['medium'] ?? 'Interview'), $m['band_id'] === null ? null : (int) $m['band_id'], $musicianId);
        return ['fame' => round($gain, 1), 'total' => round((float) $m['fame'], 1), 'medium' => $data['reward']['medium'] ?? ''];
    }

    /** Bewerbung annehmen: einmal bezahlen, dann gehört die Person zum Label. */
    public function hire(int $id, ?int $target = null): int
    {
        $db = $this->repo->db;
        return $db->transaction(function () use ($db, $id, $target): int {
            $row = $this->row($id);
            if ($row['kind'] !== 'job') {
                throw new ApiError('Das ist keine Bewerbung.');
            }
            $d = json_decode($row['data'], true);
            $tier = self::tiers($d['role'])[(int) $d['tier']];
            $roleName = Catalog::STAFF_ROLES[$d['role']]['tiers'][(int) $d['tier']];
            if (in_array($d['role'], ['teacher', 'coach'], true)) {
                // Gebucht für eine Zeit und genau einen Musiker bzw. eine Band.
                if ($target === null) {
                    throw new ApiError($d['role'] === 'teacher' ? 'Wähle, wer Unterricht bekommt.' : 'Wähle eine Band.');
                }
                $this->repo->spend((int) $tier['price'], sprintf('Gebucht: %s %s (%s)', $d['first_name'], $d['last_name'], $roleName));
                (new Tutors($this->repo))->book($d, $target);
                $db->exec('DELETE FROM mail WHERE id = ?', [$id]);
                return 0;
            }
            $this->repo->spend((int) $tier['price'], sprintf('Eingestellt: %s %s (%s)', $d['first_name'], $d['last_name'], $roleName));
            if ($d['role'] === 'manager') {
                $managerId = $this->repo->insertManager([
                    'first_name' => $d['first_name'], 'last_name' => $d['last_name'], 'look' => $d['look'],
                    'rarity' => [1 => 'common', 2 => 'epic', 3 => 'legendary'][(int) $d['tier']], 'level' => (int) $tier['level'],
                    'skills' => $d['skills'], 'genres' => $d['genres'], 'style' => $d['style'],
                ]);
                $db->exec('DELETE FROM mail WHERE id = ?', [$id]);
                $this->repo->log('staff', sprintf('%s %s ist jetzt Manager bei uns.', $d['first_name'], $d['last_name']));
                return $managerId;
            }
            if ($d['role'] === 'assistant') {
                // Was bisher liegen blieb, erledigen die alten; die neue fängt jetzt an.
                (new Assistant($this->repo))->work(Clock::now());
                $db->update('labels', ['assist_at' => Clock::now()], ['id' => $this->repo->labelId]);
            }
            $staffId = $db->insert('staff', [
                'label_id' => $this->repo->labelId, 'role' => $d['role'], 'tier' => (int) $d['tier'],
                'data' => json_encode(['first_name' => $d['first_name'], 'last_name' => $d['last_name'], 'look' => $d['look']] + ($d['role'] === 'assistant' ? ['task' => 'demos'] : []), JSON_UNESCAPED_UNICODE),
                'hired_at' => Clock::now(),
            ]);
            $db->exec('DELETE FROM mail WHERE id = ?', [$id]);
            $this->repo->log('staff', sprintf('%s %s arbeitet jetzt bei uns (%s).', $d['first_name'], $d['last_name'], $roleName));
            return $staffId;
        });
    }

    public function save(int $id, bool $saved): void
    {
        $this->row($id);
        $this->repo->db->update('mail', ['saved' => $saved ? 1 : 0], ['id' => $id]);
    }

    public function drop(int $id): void
    {
        $this->row($id);
        $this->repo->db->exec('DELETE FROM mail WHERE id = ?', [$id]);
    }

    /** Zum Ausprobieren: je Musiker eine Anfrage und eine Bewerbung je Stufe (Assistenz und Scout). */
    public function testFill(): void
    {
        $now = Clock::now();
        $this->repo->db->exec("DELETE FROM mail WHERE label_id = ? AND kind = 'interview'", [$this->repo->labelId]);
        foreach ($this->repo->musicians() as $m) {
            $level = Levels::forFame((float) $m['fame']);
            $medium = Rng::pick(Catalog::INTERVIEW_MEDIA[min(3, $level)]);
            $this->store('interview', ['musicianId' => $m['id'], 'level' => $level, 'medium' => $medium['name'], 'share' => $medium['share']], $now, Balance::INTERVIEW['ttl']);
        }
        foreach ([1, 2] as $k) {
            $this->store('job', self::applicant('assistant', 1.0, 1), $now - $k, Balance::JOBS['ttl']);
        }
        foreach (array_keys(Balance::SCOUT_STAFF['tiers']) as $tier) {
            $this->store('job', self::applicant('scout', 1.0, $tier), $now - 10 - $tier, Balance::JOBS['ttl']);
        }
        $own = array_values(array_unique(array_column($this->repo->musicians(), 'instr1')));
        foreach ([1, 2, 3] as $tier) {
            $this->store('job', self::applicant('manager', 1.0, $tier, [], ['rock']), $now - 40 - $tier, Balance::JOBS['ttl']);
            $this->store('job', self::applicant('teacher', 1.0, $tier, $own ?: ['guitar']), $now - 20 - $tier, Balance::JOBS['ttl']);
            $this->store('job', self::applicant('coach', 1.0, $tier), $now - 30 - $tier, Balance::JOBS['ttl']);
        }
    }
}
