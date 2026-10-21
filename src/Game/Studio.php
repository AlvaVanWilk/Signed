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
        $missing = $band['live_songs_needed'];
        if ($missing > 0) {
            return sprintf('Erst die letzte Platte live spielen: noch %d %s auf Gigs.', $missing, $missing === 1 ? 'Lied' : 'Lieder');
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
            $this->repo->spend(Balance::RECORD_COST, 'Studiozeit');
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

    /** Ein Lied ist eingespielt. Gibt jedem Mitglied etwas Können und der Band Zusammenspiel. */
    public function songDone(array $record, array $band, array $members, array $gear, int $perfect, int $good, int $miss): array
    {
        $db = $this->repo->db;
        $accuracy = Chart::accuracy($perfect, $good, $miss);
        $results = $record['song_results'];
        $results[] = ['accuracy' => round($accuracy, 3), 'perfect' => $perfect, 'good' => $good, 'miss' => $miss];

        $levelUps = [];
        foreach ($members as $m) {
            $gain = BandCalc::trainingGain($m, Balance::RECORD_SONG_SKILL * $accuracy, null, BandCalc::gearOf($m, $gear));
            $column = $m['band_slot'] === 2 && $m['instr2'] !== null ? 'skill2' : 'skill1';
            $db->update('musicians', [$column => min((float) Balance::MAX_STAT, BandCalc::skill($m) + $gain)], ['id' => $m['id']]);
            $levelUp = Levels::check($this->repo, $m['id']);
            if ($levelUp !== null) {
                $levelUps[] = $levelUp;
            }
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
            'levelUps' => $levelUps,
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
        $sales = 1 + min(Balance::RECORD_FAME_SALES_MAX, BandCalc::bandFame($band, $members) / Balance::RECORD_FAME_SALES);
        $sound = BandCalc::bandSound($band, $members, $gear);

        $money = (int) round($songs * Balance::RECORD_MONEY_PER_SONG * (0.3 + $quality ** 2) * $lp * $perfectFactor * $sales);
        $fame = $songs * Balance::RECORD_FAME_PER_SONG * BandCalc::soundFactor($sound) * (0.3 + $quality ** 1.5) * $lp * $perfectFactor;
        $gains = BandCalc::splitFame($members, $fame, $gear);
        $this->repo->addBandFame($band['id'], $gains);
        $this->repo->earn($money, sprintf('Erstverkauf „%s“', $record['title']));

        $db->exec(
            'UPDATE bands SET records = records + 1, songs_recorded = songs_recorded + ?, gigs_since_record = 0, live_songs_needed = ?, perfect_records = perfect_records + ? WHERE id = ?',
            [$songs, $songs * Balance::LIVE_SONGS_PER_RECORD_SONG, $perfect ? 1 : 0, $band['id']]
        );
        $fameGain = round(array_sum($gains), 1);
        $firstRecord = (int) $db->value("SELECT COUNT(*) FROM records WHERE label_id = ? AND status = 'done'", [$this->repo->labelId]) === 0;
        $studio = count(array_filter($members, static fn ($m) => BandCalc::hasPerk(BandCalc::gearOf($m, $gear), 'studio')));
        $chartPoints = round(Charts::points($songs, $quality, $sound, $perfect, BandCalc::bandFame($band, $members)) * (1 + Balance::PERK_STUDIO * $studio), 1);
        $now = Clock::now();
        $db->update('records', [
            'status' => 'done',
            'money_reward' => $money,
            'fame_reward' => $fameGain,
            'perfect' => $perfect ? 1 : 0,
            'finished_at' => $now,
            'chart_points' => $chartPoints,
            'royalties_until' => $now,
        ], ['id' => $record['id']]);

        $format = $songs >= Balance::CHART_LP_SONGS ? 'LP' : 'EP';
        $this->repo->log('record', sprintf(
            '%s veröffentlicht „%s“ (%s, %d Lieder)%s',
            $band['name'],
            $record['title'],
            $format,
            $songs,
            $perfect ? ' – eine perfekte Platte!' : '.'
        ), $band['id']);

        if ($firstRecord) {
            Scout::grant($this->repo, 'die erste Platte deines Labels.');
        }

        return [
            'title' => $record['title'],
            'format' => $format,
            'chartPoints' => $chartPoints,
            'vouchers' => ($firstRecord ? 1 : 0) + ($perfect ? 1 : 0),
            'songs' => $songs,
            'quality' => round($quality, 3),
            'misses' => $misses,
            'perfect' => $perfect,
            'money' => $money,
            'fame' => $fameGain,
        ];
    }
}
