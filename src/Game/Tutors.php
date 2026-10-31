<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;

/**
 * Privatlehrer und Songwriting-Coaches: bewerben sich im Posteingang und werden für eine Zeit gebucht, immer für genau einen Musiker
 * bzw. eine Band (einmal bezahlt, danach gehen sie wieder). Lehrer geben in der Zeit gleichmäßig Können (ohne Lernkurve),
 * Coaches lassen die Band schneller und besser schreiben.
 */
final class Tutors
{
    public function __construct(private Repo $repo)
    {
    }

    /** @return list<array> laufende Buchungen */
    public function bookings(?string $role = null): array
    {
        $rows = $this->repo->db->all("SELECT * FROM staff WHERE label_id = ? AND role IN ('teacher', 'coach') ORDER BY id", [$this->repo->labelId]);
        $rows = array_map(static fn ($r) => $r + ['info' => json_decode($r['data'], true)], $rows);
        return $role === null ? $rows : array_values(array_filter($rows, static fn ($r) => $r['role'] === $role));
    }

    /** Wer bei diesem Lehrer lernen kann: spielt das Instrument, ist noch nicht zu gut und hat gerade keinen anderen Lehrer. */
    public function canTeach(array $job, array $m): ?string
    {
        $slot = $m['instr1'] === $job['instrument'] ? 1 : ($m['instr2'] === $job['instrument'] ? 2 : 0);
        if ($slot === 0) {
            return sprintf('%s spielt kein %s.', $m['first_name'], Catalog::INSTRUMENTS[$job['instrument']]['name']);
        }
        if (BandCalc::skill($m, $slot) >= Balance::TEACHER['tiers'][(int) $job['tier']]['maxSkill']) {
            return sprintf('%s kann schon mehr, als diese Lehrkraft beibringt.', $m['first_name']);
        }
        foreach ($this->bookings('teacher') as $b) {
            if ((int) $b['info']['musicianId'] === $m['id']) {
                return sprintf('%s hat schon Unterricht.', $m['first_name']);
            }
        }
        return null;
    }

    /** Buchen: Lehrer für einen Musiker, Coach für eine Band. Bezahlt wird in Inbox::hire. */
    public function book(array $job, int $target): void
    {
        $now = Clock::now();
        $person = ['first_name' => $job['first_name'], 'last_name' => $job['last_name'], 'look' => $job['look']];
        if ($job['role'] === 'teacher') {
            $m = $this->repo->musician($target);
            $why = $this->canTeach($job, $m);
            if ($why !== null) {
                throw new ApiError($why);
            }
            $c = Balance::TEACHER;
            $tier = $c['tiers'][(int) $job['tier']];
            $genreMatch = ($job['genre'] ?? null) !== null && in_array($job['genre'], $m['genres'], true);
            $data = $person + [
                'musicianId' => $m['id'], 'slot' => $m['instr1'] === $job['instrument'] ? 1 : 2, 'instrument' => $job['instrument'], 'genre' => $job['genre'] ?? null,
                'gain' => round($tier['gain'] * ($genreMatch ? $c['genreFactor'] : 1.0), 2), 'done' => 0.0, 'from' => $now, 'until' => $now + $tier['seconds'], 'at' => $now,
            ];
            $this->repo->log('staff', sprintf('%s %s gibt %s %s Unterricht.', $job['first_name'], $job['last_name'], $m['first_name'], Catalog::INSTRUMENTS[$job['instrument']]['at']), $m['band_id'], $m['id']);
        } else {
            $band = $this->repo->band($target);
            foreach ($this->bookings('coach') as $b) {
                if ((int) $b['info']['bandId'] === $band['id']) {
                    throw new ApiError(sprintf('%s hat schon einen Coach.', $band['name']));
                }
            }
            $tier = Balance::COACH['tiers'][(int) $job['tier']];
            $data = $person + ['bandId' => $band['id'], 'speed' => $tier['speed'], 'quality' => $tier['quality'], 'from' => $now, 'until' => $now + $tier['seconds']];
            // Bis zum Ende ist der Zuschlag fest; er gilt für die Songs, die in dieser Zeit entstehen.
            $this->repo->db->update('bands', ['coach_bonus' => max($band['coach_bonus'], $tier['quality'])], ['id' => $band['id']]);
            $this->repo->log('staff', sprintf('%s %s hilft %s beim Songschreiben.', $job['first_name'], $job['last_name'], $band['name']), $band['id']);
        }
        $this->repo->db->insert('staff', [
            'label_id' => $this->repo->labelId, 'role' => $job['role'], 'tier' => (int) $job['tier'],
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'hired_at' => $now, 'busy_until' => $data['until'],
        ]);
    }

    /** Unterricht gutschreiben (gleichmäßig über die Zeit) und abgelaufene Buchungen beenden. */
    public function settle(int $now): void
    {
        $db = $this->repo->db;
        foreach ($this->bookings() as $b) {
            $d = $b['info'];
            if ($b['role'] === 'teacher') {
                $row = $db->one('SELECT * FROM musicians WHERE id = ? AND label_id = ?', [(int) $d['musicianId'], $this->repo->labelId]);
                if ($row === null) {
                    $db->exec('DELETE FROM staff WHERE id = ?', [(int) $b['id']]);
                    continue;
                }
                $to = min($now, (int) $d['until']);
                $gain = $d['gain'] * max(0, $to - (int) $d['at']) / max(1, (int) $d['until'] - (int) $d['from']);
                if ($gain > 0) {
                    $m = Repo::hydrateMusician($row);
                    $column = (int) $d['slot'] === 2 ? 'skill2' : 'skill1';
                    $db->update('musicians', [$column => Levels::cap(BandCalc::skill($m, (int) $d['slot']) + $gain)], ['id' => $m['id']]);
                    Levels::check($this->repo, $m['id']);
                    $d['done'] = round((float) $d['done'] + $gain, 4);
                    $d['at'] = $to;
                    $db->update('staff', ['data' => json_encode($d, JSON_UNESCAPED_UNICODE)], ['id' => (int) $b['id']]);
                }
                if ($now >= (int) $d['until']) {
                    $db->exec('DELETE FROM staff WHERE id = ?', [(int) $b['id']]);
                    $this->repo->log('staff', sprintf('Der Unterricht von %s ist zu Ende: +%s Können %s.', $row['first_name'], str_replace('.', ',', (string) round((float) $d['done'], 1)), Catalog::INSTRUMENTS[$d['instrument']]['at']), $row['band_id'] === null ? null : (int) $row['band_id'], (int) $row['id']);
                }
            } elseif ($now >= (int) $d['until']) {
                $db->exec('DELETE FROM staff WHERE id = ?', [(int) $b['id']]);
                $band = $db->one('SELECT name FROM bands WHERE id = ?', [(int) $d['bandId']]);
                if ($band !== null) {
                    $this->repo->log('staff', sprintf('Das Coaching von %s ist zu Ende.', $band['name']), (int) $d['bandId']);
                }
            }
        }
    }

    /**
     * Songwriting-Coach für die Zeit von $from bis $to: Faktor aufs Songtempo, Anteil der Zeit, in der gecoacht wurde, und sein Qualitäts-Zuschlag.
     * @return array{0: float, 1: float, 2: float}
     */
    public static function coaching(Repo $repo, int $bandId, int $from, int $to): array
    {
        if ($to <= $from) {
            return [1.0, 0.0, 0.0];
        }
        $extra = 0.0;
        $share = 0.0;
        $quality = 0.0;
        foreach ($repo->db->all("SELECT data FROM staff WHERE label_id = ? AND role = 'coach'", [$repo->labelId]) as $r) {
            $d = json_decode($r['data'], true);
            if ((int) $d['bandId'] !== $bandId) {
                continue;
            }
            $overlap = max(0, min($to, (int) $d['until']) - max($from, (int) $d['from'])) / ($to - $from);
            $extra += ($d['speed'] - 1) * $overlap;
            $share = max($share, $overlap);
            $quality = max($quality, $overlap > 0 ? (float) $d['quality'] : 0.0);
        }
        return [1.0 + $extra, $share, $quality];
    }

    /** Zuschlag auf die Qualität einer Platte mit so vielen Songs: anteilig nach den gecoachten Songs. */
    public static function recordBonus(array $band, int $songs): float
    {
        return $songs > 0 ? $band['coach_bonus'] * min(1.0, $band['coached_songs'] / $songs) : 0.0;
    }

    public function present(int $now): array
    {
        return array_map(static function ($b) use ($now) {
            $d = $b['info'];
            $base = ['id' => (int) $b['id'], 'role' => $b['role'], 'tier' => (int) $b['tier'], 'firstName' => $d['first_name'], 'lastName' => $d['last_name'], 'look' => $d['look'], 'until' => (int) $d['until']];
            return $b['role'] === 'teacher'
                ? $base + ['musicianId' => (int) $d['musicianId'], 'instrument' => $d['instrument'], 'genre' => $d['genre'], 'gain' => (float) $d['gain'], 'done' => round((float) $d['done'], 2)]
                : $base + ['bandId' => (int) $d['bandId'], 'speed' => (float) $d['speed'], 'quality' => (float) $d['quality']];
        }, $this->bookings());
    }
}
