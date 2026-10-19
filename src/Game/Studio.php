<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;

/** Platte produzieren: Pauschalpreis, ein Minispiel pro Lied, Belohnung erst bei Fertigstellung. */
final class Studio
{
    public function __construct(private Repo $repo)
    {
    }

    /** Grund, warum keine neue Platte begonnen werden kann, sonst null. */
    public static function blocker(array $band, int $memberCount, ?array $openRecord): ?string
    {
        if ($openRecord !== null) {
            return 'Es läuft schon eine Produktion.';
        }
        if ($memberCount < Balance::MIN_MEMBERS) {
            return sprintf('Die Band braucht mindestens %d Musiker.', Balance::MIN_MEMBERS);
        }
        $missing = Balance::GIGS_BETWEEN_RECORDS - $band['gigs_since_record'];
        if ($band['records'] > 0 && $missing > 0) {
            return sprintf('Erst live spielen: noch %d %s bis zur nächsten Platte.', $missing, $missing === 1 ? 'Gig' : 'Gigs');
        }
        return null;
    }

    public function start(int $bandId, string $title, int $songs): void
    {
        $title = Bands::cleanName($title, 'Der Plattentitel', 60);
        if ($songs < Balance::RECORD_MIN_SONGS || $songs > Balance::RECORD_MAX_SONGS) {
            throw new ApiError(sprintf('Eine Platte hat %d bis %d Lieder.', Balance::RECORD_MIN_SONGS, Balance::RECORD_MAX_SONGS));
        }
        $this->repo->db->transaction(function () use ($bandId, $title, $songs): void {
            $band = $this->repo->band($bandId);
            $blocker = self::blocker($band, count($this->repo->members($bandId)), $this->repo->openRecord($bandId));
            if ($blocker !== null) {
                throw new ApiError($blocker);
            }
            $this->repo->spend(Balance::RECORD_COST);
            $this->repo->db->insert('records', [
                'label_id' => $this->repo->labelId,
                'band_id' => $bandId,
                'title' => $title,
                'songs_total' => $songs,
                'songs_done' => 0,
                'song_results' => '[]',
                'status' => 'open',
                'created_at' => Clock::now(),
            ]);
            $this->repo->log('record', sprintf('%s geht ins Studio: „%s“ mit %d Liedern.', $band['name'], $title, $songs), $bandId);
        });
    }

    /** Ein Lied ist eingespielt. Gibt jedem Mitglied etwas Können und der Band Eingespieltheit. */
    public function songDone(array $record, array $band, array $members, array $gear, int $perfect, int $good, int $miss): array
    {
        $db = $this->repo->db;
        $accuracy = Chart::accuracy($perfect, $good, $miss);
        $results = $record['song_results'];
        $results[] = ['accuracy' => round($accuracy, 3), 'perfect' => $perfect, 'good' => $good, 'miss' => $miss];

        foreach ($members as $m) {
            $skill = BandCalc::skill($m);
            $talent = in_array('talent', $m['traits'], true) ? Balance::TALENT_FACTOR : 1.0;
            $gain = Balance::RECORD_SONG_SKILL * $accuracy * $talent * max(0.08, 1 - $skill / 110);
            $column = $m['band_slot'] === 2 && $m['instr2'] !== null ? 'skill2' : 'skill1';
            $db->update('musicians', [$column => min((float) Balance::MAX_STAT, $skill + $gain)], ['id' => $m['id']]);
        }

        $chemistry = BandCalc::chemistry($members);
        $max = BandCalc::maxCohesion($chemistry);
        $cohesion = $band['cohesion'] >= $max
            ? $band['cohesion']
            : min($max, $band['cohesion'] + Balance::RECORD_SONG_COHESION * $accuracy * $chemistry);
        $db->update('bands', ['cohesion' => $cohesion, 'released' => 1], ['id' => $band['id']]);
        $db->update('records', ['songs_done' => count($results), 'song_results' => json_encode($results)], ['id' => $record['id']]);

        $out = [
            'accuracy' => round($accuracy, 3),
            'songsDone' => count($results),
            'songsTotal' => $record['songs_total'],
            'cohesion' => round($cohesion - $band['cohesion'], 1),
            'completed' => null,
        ];
        if (count($results) >= $record['songs_total']) {
            $record['song_results'] = $results;
            $band['cohesion'] = $cohesion;
            $out['completed'] = $this->complete($record, $band, $this->repo->members($band['id']), $gear);
        }
        return $out;
    }

    private function complete(array $record, array $band, array $members, array $gear): array
    {
        $db = $this->repo->db;
        $results = $record['song_results'];
        $songs = count($results);
        $quality = array_sum(array_column($results, 'accuracy')) / max(1, $songs);
        $misses = (int) array_sum(array_column($results, 'miss'));
        $perfect = $misses === 0;

        $lp = 1 + Balance::RECORD_LP_BONUS * max(0, $songs - Balance::RECORD_MIN_SONGS);
        $perfectFactor = $perfect ? Balance::RECORD_PERFECT_BONUS : 1.0;
        $sales = 1 + min(Balance::RECORD_FAME_SALES_MAX, BandCalc::fame($members) / Balance::RECORD_FAME_SALES);
        $sound = BandCalc::sound($members, $band['genre'], $band['cohesion'], $gear);

        $money = (int) round($songs * Balance::RECORD_MONEY_PER_SONG * (0.3 + $quality ** 2) * $lp * $perfectFactor * $sales);
        $fame = $songs * Balance::RECORD_FAME_PER_SONG * BandCalc::soundFactor($sound) * (0.3 + $quality ** 1.5) * $lp * $perfectFactor;
        $gains = BandCalc::splitFame($members, $fame, $gear);
        foreach ($gains as $musicianId => $gain) {
            $db->exec('UPDATE musicians SET fame = fame + ? WHERE id = ?', [$gain, $musicianId]);
        }
        $this->repo->earn($money);

        $db->exec(
            'UPDATE bands SET records = records + 1, songs_recorded = songs_recorded + ?, gigs_since_record = 0, perfect_records = perfect_records + ? WHERE id = ?',
            [$songs, $perfect ? 1 : 0, $band['id']]
        );
        $fameGain = round(array_sum($gains), 1);
        $db->update('records', [
            'status' => 'done',
            'money_reward' => $money,
            'fame_reward' => $fameGain,
            'perfect' => $perfect ? 1 : 0,
            'finished_at' => Clock::now(),
        ], ['id' => $record['id']]);

        $format = $songs >= 7 ? 'LP' : 'EP';
        $this->repo->log('record', sprintf(
            '%s veröffentlicht „%s“ (%s, %d Lieder)%s',
            $band['name'],
            $record['title'],
            $format,
            $songs,
            $perfect ? ' – eine perfekte Platte!' : '.'
        ), $band['id']);

        return [
            'title' => $record['title'],
            'format' => $format,
            'songs' => $songs,
            'quality' => round($quality, 3),
            'misses' => $misses,
            'perfect' => $perfect,
            'money' => $money,
            'fame' => $fameGain,
        ];
    }
}
