<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;

/**
 * Rhythmus-Minispiel für Solo-Probe und Plattenaufnahme. Der Server erzeugt die Noten,
 * der Browser spielt sie, und der Server prüft das Ergebnis auf Plausibilität.
 * Es läuft immer nur ein Minispiel pro Label.
 */
final class Minigames
{
    public function __construct(private Repo $repo)
    {
    }

    public function start(string $kind, array $in): array
    {
        $db = $this->repo->db;
        return $db->transaction(function () use ($db, $kind, $in): array {
            $this->cancelOpen();
            $now = Clock::now();
            $expires = $now + Balance::MINIGAME_TTL;

            if ($kind === 'practice') {
                $m = $this->repo->musician((int) ($in['musicianId'] ?? 0));
                $slot = (int) ($in['slot'] ?? 1) === 2 && $m['instr2'] !== null ? 2 : 1;
                if ($m['busy_until'] > $now) {
                    throw new ApiError(sprintf('%s ist gerade beschäftigt.', $m['first_name']));
                }
                if ($m['band_id'] !== null && $this->repo->band($m['band_id'])['busy_until'] > $now) {
                    throw new ApiError(sprintf('Die Band von %s ist gerade beschäftigt.', $m['first_name']));
                }
                $instrument = $slot === 2 ? $m['instr2'] : $m['instr1'];
                $skill = BandCalc::skill($m, $slot);
                $genre = $m['genres'][0];
                $bpm = Catalog::GENRES[$genre]['bpm'];
                $perfectPitch = Levels::has($m, 'gehoer');
                // Gesang und Geige spielen nach Tonhöhe, alle anderen nach Rhythmus.
                if (in_array($instrument, Catalog::PITCH_INSTRUMENTS, true)) {
                    $chart = [
                        'mode' => 'pitch',
                        'notes' => Chart::melody($bpm, Balance::SONG_SECONDS),
                        'tolerance' => Chart::pitchTolerance($skill, $perfectPitch),
                        'instrument' => $instrument,
                    ];
                } else {
                    $chart = [
                        'mode' => 'rhythm',
                        'notes' => Chart::generate($bpm, Chart::density($skill), Balance::SONG_SECONDS),
                        'windows' => Chart::windows($skill, $perfectPitch),
                        'lanes' => array_fill(0, Chart::LANES, $instrument),
                        'laneNames' => array_fill(0, Chart::LANES, $m['first_name']),
                    ];
                }
                $chart['count'] = count($chart['notes']);

                $db->update('musicians', ['busy_until' => $expires], ['id' => $m['id']]);
                $row = ['musician_id' => $m['id'], 'band_id' => null, 'record_id' => null, 'slot' => $slot];
                $title = 'Solo-Probe';
                $subtitle = sprintf('%s – %s', Bands::fullName($m), Catalog::INSTRUMENTS[$instrument]['name']);
            } elseif ($kind === 'record') {
                $band = $this->repo->band((int) ($in['bandId'] ?? 0));
                $record = $this->repo->openRecord($band['id']);
                if ($record === null) {
                    throw new ApiError('Diese Band hat keine Platte in Produktion.');
                }
                $members = $this->repo->members($band['id']);
                $blocker = Activities::blocker($band, $members, $now);
                if ($blocker !== null) {
                    throw new ApiError($blocker);
                }
                $sound = BandCalc::bandSound($band, $members, $this->repo->gearByMusician());
                $genre = BandCalc::identity($members, $band['genre'], $band['genre2'])['music'];
                $bpm = Catalog::GENRES[$genre]['bpm'];
                // Am Mischpult: Die Spuren schwanken je nach Können, der Sound bestimmt, wie viel verziehen wird.
                $tracks = Chart::mixerTracks(array_map(static fn ($m) => [
                    'name' => $m['first_name'], 'instrument' => BandCalc::instrument($m), 'skill' => BandCalc::skill($m),
                ], $members));
                $chart = [
                    'mode' => 'mixer',
                    'tracks' => array_map(static fn ($t) => ['name' => $t['name'], 'instruments' => $t['instruments']], $tracks),
                    'drifts' => Chart::mixerDrifts(array_column($tracks, 'skill'), Balance::SONG_SECONDS),
                    'zone' => Chart::mixerZone($sound) + (array_filter($members, static fn ($m) => Levels::has($m, 'studiofuchs')) ? 0.04 : 0),
                    'segments' => Chart::MIXER_SEGMENTS,
                    'count' => count($tracks) * Chart::MIXER_SEGMENTS,
                ];

                $db->update('bands', ['busy_until' => $expires], ['id' => $band['id']]);
                $row = ['musician_id' => null, 'band_id' => $band['id'], 'record_id' => $record['id'], 'slot' => null];
                $title = sprintf('Studio: „%s“', $record['title']);
                $subtitle = sprintf('%s – Lied %d von %d', $band['name'], $record['songs_done'] + 1, $record['songs_total']);
            } else {
                throw new ApiError('Unbekanntes Minispiel.');
            }

            $id = $db->insert('minigames', $row + [
                'label_id' => $this->repo->labelId,
                'kind' => $kind,
                'chart' => json_encode($chart + ['bpm' => $bpm]),
                'started_at' => $now,
                'expires_at' => $expires,
                'status' => 'open',
            ]);

            return [
                'sessionId' => $id,
                'kind' => $kind,
                'title' => $title,
                'subtitle' => $subtitle,
                'genre' => $genre,
                'bpm' => $bpm,
                'duration' => Balance::SONG_SECONDS,
            ] + $chart;
        });
    }

    public function finish(int $sessionId, int $perfect, int $good, int $miss): array
    {
        $db = $this->repo->db;
        $outcome = $db->transaction(function () use ($db, $sessionId, $perfect, $good, $miss): array {
            $session = $this->openSession($sessionId);
            $now = Clock::now();
            if ($now > (int) $session['expires_at']) {
                $this->close($session, 'expired');
                return ['expired' => true];
            }
            $chart = json_decode($session['chart'], true);
            if (min($perfect, $good, $miss) < 0 || $perfect + $good + $miss !== (int) ($chart['count'] ?? count($chart['notes']))) {
                throw new ApiError('Ungültiges Ergebnis.');
            }
            if ($now - (int) $session['started_at'] < Balance::SONG_SECONDS - 1) {
                throw new ApiError('Das Lied ist noch nicht vorbei.');
            }
            $this->close($session, 'done');

            $base = ['kind' => $session['kind'], 'perfect' => $perfect, 'good' => $good, 'miss' => $miss];
            if ($session['kind'] === 'practice') {
                return $base + $this->practiceResult($session, $perfect, $good, $miss);
            }
            $record = $db->one("SELECT * FROM records WHERE id = ? AND status = 'open'", [(int) $session['record_id']]);
            if ($record === null) {
                throw new ApiError('Diese Produktion gibt es nicht mehr.');
            }
            $band = $this->repo->band((int) $session['band_id']);
            return $base + (new Studio($this->repo))->songDone(
                Repo::hydrateRecord($record),
                $band,
                $this->repo->members($band['id']),
                $this->repo->gearByMusician(),
                $perfect,
                $good,
                $miss
            );
        });
        if (!empty($outcome['expired'])) {
            throw new ApiError('Das Minispiel ist abgelaufen. Starte es noch einmal.');
        }
        return $outcome;
    }

    public function abort(int $sessionId): void
    {
        $this->repo->db->transaction(function () use ($sessionId): void {
            $session = $this->repo->db->one(
                "SELECT * FROM minigames WHERE id = ? AND label_id = ? AND status = 'open'",
                [$sessionId, $this->repo->labelId]
            );
            if ($session !== null) {
                $this->close($session, 'aborted');
            }
        });
    }

    private function practiceResult(array $session, int $perfect, int $good, int $miss): array
    {
        $m = $this->repo->musician((int) $session['musician_id']);
        $slot = (int) $session['slot'];
        $skill = BandCalc::skill($m, $slot);
        $accuracy = Chart::accuracy($perfect, $good, $miss);
        $gain = BandCalc::trainingGain($m, Balance::PRACTICE_GAIN * $accuracy ** 1.5, $slot);
        $after = min((float) Balance::MAX_STAT, $skill + $gain);
        $this->repo->db->update('musicians', [$slot === 2 ? 'skill2' : 'skill1' => $after], ['id' => $m['id']]);
        $levelUp = Levels::check($this->repo, $m['id']);
        return [
            'accuracy' => round($accuracy, 3),
            'musicianId' => $m['id'],
            'instrument' => $slot === 2 ? $m['instr2'] : $m['instr1'],
            'skillBefore' => round($skill, 1),
            'skillAfter' => round($after, 1),
            'gain' => round($after - $skill, 1),
            'levelUps' => $levelUp === null ? [] : [$levelUp],
        ];
    }

    private function openSession(int $sessionId): array
    {
        $session = $this->repo->db->one(
            "SELECT * FROM minigames WHERE id = ? AND label_id = ? AND status = 'open'",
            [$sessionId, $this->repo->labelId]
        );
        if ($session === null) {
            throw new ApiError('Dieses Minispiel ist nicht mehr aktiv.');
        }
        return $session;
    }

    /** Ein neues Minispiel beendet ein offenes: Es läuft immer nur eins zur Zeit. */
    private function cancelOpen(): void
    {
        $open = $this->repo->db->all("SELECT * FROM minigames WHERE label_id = ? AND status = 'open'", [$this->repo->labelId]);
        foreach ($open as $session) {
            $this->close($session, 'aborted');
        }
    }

    /** Schließt die Sitzung und gibt Musiker bzw. Band wieder frei. */
    private function close(array $session, string $status): void
    {
        $db = $this->repo->db;
        $db->update('minigames', ['status' => $status], ['id' => (int) $session['id']]);
        $expires = (int) $session['expires_at'];
        if ($session['kind'] === 'practice') {
            $db->exec('UPDATE musicians SET busy_until = 0 WHERE id = ? AND busy_until = ?', [(int) $session['musician_id'], $expires]);
        } else {
            $db->exec('UPDATE bands SET busy_until = 0 WHERE id = ? AND busy_until = ?', [(int) $session['band_id'], $expires]);
        }
    }
}
