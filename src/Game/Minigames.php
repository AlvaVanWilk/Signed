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
                $genre = $m['genres'][0];
                $bpm = Catalog::GENRES[$genre]['bpm'];
                $stem = $this->practiceStem(['band_slot' => $slot] + $m);
                $instrument = $slot === 2 && $m['instr2'] !== null ? $m['instr2'] : $m['instr1'];
                // Allein üben: eine Etüde im Genre (Synthesizer), jedes Mal neu, Schwierigkeit nach Können.
                // Gesang wird im Hip-Hop gerappt (Rhythmus-Spiel), sonst gesungen (Tonhöhen-Spiel).
                $genre = isset(Styles::GENRES[$stem['genre']]) ? $stem['genre'] : 'pop';
                $rap = $instrument === 'vocals' && in_array($genre, Catalog::RAP_GENRES, true);
                $chart = Etude::game($instrument, $genre, BandCalc::skill($m, $slot), random_int(1, 999999), false, $rap);
                $bpm = $chart['bpm'];

                $db->update('musicians', ['busy_until' => $expires], ['id' => $m['id']]);
                $row = ['musician_id' => $m['id'], 'band_id' => null, 'record_id' => null, 'slot' => $slot];
                $title = 'Solo-Probe';
                $subtitle = sprintf('%s – %s', Bands::fullName($m), Catalog::INSTRUMENTS[$instrument]['name']);
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
            throw new ApiError('Unbekanntes Minispiel.');
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

    /**
     * Spiel für einen einzelnen Musiker (Solo-Probe, Einspielen im Studio): seine Spur aus den Aufnahmen, dazu ein Metronom.
     * Gesang und Geige spielen nach Tonhöhe, alle anderen nach Rhythmus – Rap ebenfalls nach Rhythmus.
     */
    private function soloChart(array $m, int $slot, array $stem, int $bpm): array
    {
        $instrument = $slot === 2 && $m['instr2'] !== null ? $m['instr2'] : $m['instr1'];
        $skill = BandCalc::skill($m, $slot);
        $perfectPitch = false;
        $resolved = Music::resolve($stem);
        $source = $resolved === null ? null : Music::notes($resolved);
        $music = $resolved === null ? null : ['own' => sprintf('assets/music/%s/%s.mp3', $resolved['folder'], $resolved['file'])];
        $rap = $instrument === 'vocals' && $resolved !== null && in_array(explode('-', $resolved['folder'])[0], Catalog::RAP_GENRES, true);
        if (in_array($instrument, Catalog::PITCH_INSTRUMENTS, true) && !$rap) {
            $melody = $source === null ? null : Music::melodyChart($source, Balance::SONG_SECONDS);
            $chart = [
                'mode' => 'pitch',
                'notes' => $melody['notes'] ?? Chart::melody($bpm, Balance::SONG_SECONDS),
                'tolerance' => Chart::pitchTolerance($skill, $perfectPitch),
                'instrument' => $instrument,
            ];
            if ($melody !== null) {
                $chart['tonality'] = $melody['tonality'];
                $chart['music'] = $music;
            }
        } else {
            $rhythm = $source === null ? null : Music::rhythmChart($source, $instrument, Balance::SONG_SECONDS);
            $chart = [
                'mode' => 'rhythm',
                'notes' => $rhythm['notes'] ?? Chart::generate($bpm, Chart::density($skill), Balance::SONG_SECONDS),
                'windows' => Chart::windows($skill, $perfectPitch),
                'lanes' => array_fill(0, Chart::LANES, $instrument),
                'laneNames' => $rhythm['laneNames'] ?? array_fill(0, Chart::LANES, $m['first_name']),
            ];
            if ($rhythm !== null) {
                $chart['music'] = $music;
            }
        }
        return $chart + ['instrument' => $instrument, 'count' => count($chart['notes'])];
    }

    /** Die eigene Spur für die Solo-Probe: in der Band im Genre der Band, sonst in einem Lieblingsgenre. */
    private function practiceStem(array $m): array
    {
        $gear = $this->repo->gearByMusician();
        if ($m['band_id'] === null) {
            return Music::stem($m, $gear);
        }
        $band = $this->repo->band($m['band_id']);
        $members = $this->repo->members($band['id']);
        $identity = BandCalc::identity($members, $band['genre'], $band['genre2']);
        $genres = array_values(array_filter([$identity['primary'], $identity['second']]));
        // Übt er sein Band-Instrument, dieselbe Aufnahme wie in der Band (zweite Gitarre: die andere).
        $own = array_values(array_filter($members, static fn ($x) => $x['id'] === $m['id']))[0] ?? null;
        if ($own !== null && BandCalc::instrument($own) === BandCalc::instrument($m)) {
            return Music::bandStems($members, $gear, $genres)[$m['id']];
        }
        return Music::stem($m, $gear, $genres);
    }

    private function practiceResult(array $session, int $perfect, int $good, int $miss): array
    {
        $m = $this->repo->musician((int) $session['musician_id']);
        $slot = (int) $session['slot'];
        $skill = BandCalc::skill($m, $slot);
        $accuracy = Chart::accuracy($perfect, $good, $miss);
        $type = $slot === 2 ? $m['instr2'] : $m['instr1'];
        $model = $this->repo->gearByMusician()[$m['id']][$type] ?? null;
        $gain = BandCalc::practiceGain($m, $slot, $accuracy, $model);
        $after = min((float) Balance::MAX_STAT, $skill + $gain);
        $this->repo->db->update('musicians', [$slot === 2 ? 'skill2' : 'skill1' => $after], ['id' => $m['id']]);
        $levelUp = Levels::check($this->repo, $m['id']);
        return [
            'accuracy' => round($accuracy, 3),
            'musicianId' => $m['id'],
            'instrument' => $slot === 2 ? $m['instr2'] : $m['instr1'],
            'skillBefore' => floor($skill * 100) / 100,
            'skillAfter' => floor($after * 100) / 100,
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
