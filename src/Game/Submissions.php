<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;
use Signed\Rng;

/**
 * Einsendungen: Musiker bewerben sich von selbst beim Label (docs/label-konzept.md). Jedes Label hat seine eigene Auswahl,
 * sie trudelt über die Zeit ein (auch offline) und verfällt nach ein paar Tagen. Aufnehmen kostet eine einmalige Unterschriftsprämie.
 */
final class Submissions
{
    public function __construct(private Repo $repo)
    {
    }

    /** Einsendungen pro Tag bei diesem Erfolg des Labels. */
    public static function perDay(float $erfolg): float
    {
        $points = Balance::SUBMISSIONS['perDay'];
        foreach ($points as $i => [$x, $y]) {
            if ($erfolg <= $x) {
                if ($i === 0) {
                    return (float) $y;
                }
                [$x0, $y0] = $points[$i - 1];
                return $y0 + ($y - $y0) * ($erfolg - $x0) / ($x - $x0);
            }
        }
        return (float) end($points)[1];
    }

    public static function goodShare(float $erfolg): float
    {
        $g = Balance::SUBMISSIONS['good'];
        return $g['from'] + ($g['to'] - $g['from']) * min(1.0, $erfolg / $g['at']);
    }

    public static function price(array $card): int
    {
        $p = Balance::SUBMISSIONS['price'];
        $skill = max($card['skill1'], (float) ($card['skill2'] ?? 0));
        return (int) round($p['base'] + $p['skill'] * $skill ** 2 + $p['fame'] * $card['fame']);
    }

    /** Eine Bewerbung der gewünschten Können-Stufe (0–3) mit dem Rest eines gewöhnlichen Musikers. */
    public static function make(int $level, float $fame = 0.0, array $opts = []): array
    {
        $card = Generator::musician('common', $opts + ['minSkill' => 0]);
        [$lo, $hi] = Balance::SUBMISSIONS['skill'][$level];
        $best = round(Rng::float($lo, $hi), 1);
        $card['skill1'] = $best;
        $card['skill2'] = $card['instr2'] === null ? null : round($best * Rng::float(...Balance::MULTI_SECOND_FACTOR), 1);
        $card['fame'] = round($fame, 1);
        $card['level'] = Levels::forSkill($best);
        $instruments = array_filter([$card['instr1'], $card['instr2']]);
        $abilities = [];
        for ($l = 1; $l <= min(2, $card['level']); $l++) {
            $abilities[] = Levels::randomAbility($instruments, $abilities);
        }
        $card['abilities'] = $abilities;
        $card['ability_choice'] = $card['level'] >= 3 ? Levels::options($instruments, $abilities) : null;
        return $card;
    }

    private function store(array $card, int $at, ?int $openedAt = null): void
    {
        $this->repo->db->insert('submissions', [
            'label_id' => $this->repo->labelId,
            'card' => json_encode($card, JSON_UNESCAPED_UNICODE),
            'created_at' => $at,
            'expires_at' => $at + Balance::SUBMISSIONS['ttl'],
            'opened_at' => $openedAt,
        ]);
    }

    /** Erfolg des Labels: Summe des Ruhms aller Bands. */
    public function success(): float
    {
        $total = 0.0;
        foreach ($this->repo->bands() as $band) {
            $total += BandCalc::bandFame($band, $this->repo->members($band['id']));
        }
        return $total;
    }

    /** Neue Einsendungen für die vergangene Zeit; zu Beginn der Willkommens-Stapel (drei brauchbare, zwei Mist). */
    public function topUp(int $now): void
    {
        $db = $this->repo->db;
        $at = (int) $db->value('SELECT submit_at FROM labels WHERE id = ?', [$this->repo->labelId]);
        if ($at === 0) {
            foreach ([['vocals', 15, 34], ['guitar', 15, 34], ['drums', 15, 34], [null, 5, 12], [null, 5, 12]] as $i => [$instrument, $lo, $hi]) {
                $card = self::make(0, 0.0, $instrument === null ? [] : ['instrument' => $instrument, 'genre' => 'rock', 'single' => true]);
                $card['skill1'] = round(Rng::float($lo, $hi), 1);
                $card['skill2'] = null;
                $card['instr2'] = null;
                $card['level'] = Levels::forSkill($card['skill1']);
                $card['abilities'] = $card['level'] ? [Levels::randomAbility([$card['instr1']], [])] : [];
                $this->store($card, $now - $i * 60);
            }
            $db->update('labels', ['submit_at' => $now], ['id' => $this->repo->labelId]);
            return;
        }
        $db->exec('DELETE FROM submissions WHERE label_id = ? AND expires_at <= ?', [$this->repo->labelId, $now]);
        $erfolg = $this->success();
        $interval = 86400 / self::perDay($erfolg);
        $at = max($at, $now - Balance::SUBMISSIONS['maxCatchUp']);
        $count = min(Balance::SUBMISSIONS['maxPerCall'], (int) floor(($now - $at) / $interval));
        $good = self::goodShare($erfolg);
        for ($i = 1; $i <= $count; $i++) {
            $level = Rng::chance($good) ? (int) Rng::weighted(Balance::SUBMISSIONS['goodLevels']) : 0;
            $fame = $level >= 1 && Rng::chance(Balance::SUBMISSIONS['fameChance']) ? Rng::float(...Balance::SUBMISSIONS['fame']) : 0.0;
            $this->store(self::make($level, $fame), (int) ($at + $i * $interval));
        }
        $db->update('labels', ['submit_at' => (int) ($at + $count * $interval)], ['id' => $this->repo->labelId]);
    }

    /** Zum Ausprobieren: acht Bewerbungen, zwei je Können-Stufe, darunter bekannte. */
    public function testFill(): void
    {
        $now = Clock::now();
        foreach ([0, 0, 1, 1, 2, 2, 3, 3] as $i => $level) {
            $fame = match ($i) {
                3 => 30.0, 5 => 150.0, 7 => 650.0,
                default => 0.0,
            };
            $this->store(self::make($level, $fame), $now - $i * 30, $now);
        }
    }

    /**
     * Die Einsendungen im Briefkasten, neueste zuerst. Ungehörte verraten nichts (nur Verfall); wer gerade anhört, bekommt es ab opensAt zu sehen.
     * @return list<array>
     */
    public function list(int $now): array
    {
        $rows = $this->repo->db->all('SELECT * FROM submissions WHERE label_id = ? AND expires_at > ? ORDER BY created_at DESC, id DESC', [$this->repo->labelId, $now]);
        return array_map(static function (array $r) use ($now): array {
            $base = ['submissionId' => (int) $r['id'], 'expiresAt' => (int) $r['expires_at']];
            if ($r['opened_at'] === null) {
                return $base + ['state' => 'closed'];
            }
            if ((int) $r['opened_at'] > $now) {
                return $base + ['state' => 'listening', 'opensAt' => (int) $r['opened_at']];
            }
            $card = json_decode($r['card'], true);
            $m = $card + ['id' => -(int) $r['id'], 'favorite' => false, 'jam_at' => 0, 'band_id' => null, 'band_slot' => 1, 'busy_until' => 0];
            return Present::musician($m) + $base + ['state' => 'open', 'price' => self::price($card)];
        }, $rows);
    }

    /** Anhören starten: Nach ein paar Sekunden Arbeit sieht man, was drauf ist. */
    public function open(int $id): void
    {
        $row = $this->row($id);
        if ($row['opened_at'] === null) {
            $this->repo->db->update('submissions', ['opened_at' => Clock::now() + Balance::SUBMISSIONS['listen']], ['id' => $id]);
        }
    }

    private function row(int $id): array
    {
        $row = $this->repo->db->one('SELECT * FROM submissions WHERE id = ? AND label_id = ? AND expires_at > ?', [$id, $this->repo->labelId, Clock::now()]);
        if ($row === null) {
            throw new ApiError('Diese Einsendung gibt es nicht mehr.');
        }
        return $row;
    }

    /** Unterschreiben: Prämie zahlen, der Musiker ist im Label (zunächst als Solokünstler ohne Band). */
    public function sign(int $id): int
    {
        return $this->repo->db->transaction(function () use ($id): int {
            $row = $this->row($id);
            if ($row['opened_at'] === null || (int) $row['opened_at'] > Clock::now()) {
                throw new ApiError('Die Einsendung musst du dir erst anhören.');
            }
            $card = json_decode($row['card'], true);
            $this->repo->spend(self::price($card), sprintf('Unterschrift: %s %s', $card['first_name'], $card['last_name']));
            $musicianId = $this->repo->insertMusician($card, 'submission');
            if (!empty($card['ability_choice'])) {
                $this->repo->db->update('musicians', ['ability_choice' => json_encode($card['ability_choice'])], ['id' => $musicianId]);
            }
            $this->repo->db->exec('DELETE FROM submissions WHERE id = ?', [$id]);
            $this->repo->log('submission', sprintf('%s %s hat bei uns unterschrieben.', $card['first_name'], $card['last_name']), null, $musicianId);
            return $musicianId;
        });
    }

    public function drop(int $id): void
    {
        $this->row($id);
        $this->repo->db->exec('DELETE FROM submissions WHERE id = ?', [$id]);
    }
}
