<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;
use Signed\Rng;

/**
 * Platte produzieren: Pauschalpreis, so viele Minispiele wie Lieder, Belohnung erst bei Fertigstellung.
 * Die Minispiele kommen gemischt (siehe plan()): Einspielen einzelner Instrumente, Mischpult, zum Schluss der Master-Mix.
 */
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
        $missing = $band['records'] > 0 ? Balance::RECORD_GIGS_BETWEEN - $band['gigs_since_record'] : 0;
        if ($missing > 0) {
            return sprintf('Erst die letzte Platte live spielen: noch %d %s.', $missing, $missing === 1 ? 'Gig' : 'Gigs');
        }
        return null;
    }

    public static function cost(int $songs): int
    {
        return Balance::RECORD_COST + Balance::RECORD_COST_PER_SONG * $songs;
    }

    /**
     * Reihenfolge der Minispiele einer Platte. Im Topf: pro Instrument der Band einmal Einspielen („take“) und einige
     * Mischpult-Runden mit 2–3 zufälligen Mitgliedern („mix“). Daraus wird gemischt gezogen, nie zweimal dasselbe
     * direkt hintereinander; ist der Topf leer, wird er neu gefüllt. Das letzte ist immer der Master-Mix.
     * @return list<array{type: string, musician?: int, musicians?: list<int>}>
     */
    public static function plan(array $members, int $songs): array
    {
        $byInstrument = [];
        foreach ($members as $m) {
            $byInstrument[BandCalc::instrument($m)][] = $m['id'];
        }
        $pool = [];
        foreach ($byInstrument as $ids) {
            $pool[] = ['type' => 'take', 'musician' => Rng::pick($ids)];
        }
        $ids = array_column($members, 'id');
        $mixes = [];
        $wanted = count($ids) <= 2 ? 1 : max(2, (int) ceil(count($pool) / 2));
        for ($try = 0; count($mixes) < $wanted && $try < 30; $try++) {
            $pick = Rng::sample($ids, min(count($ids), Rng::int(2, 3)));
            sort($pick);
            $mixes[implode(',', $pick)] = ['type' => 'mix', 'musicians' => $pick];
        }
        $pool = array_merge($pool, array_values($mixes));

        $plan = [];
        $bag = [];
        while (count($plan) < $songs - 1) {
            if (!$bag) {
                $bag = Rng::sample($pool, count($pool));
                // Nie dasselbe Minispiel direkt hintereinander, auch nicht über die Neufüllung hinweg.
                if ($plan && count($bag) > 1 && $bag[0] == end($plan)) {
                    [$bag[0], $bag[1]] = [$bag[1], $bag[0]];
                }
            }
            $plan[] = array_shift($bag);
        }
        $plan[] = ['type' => 'master'];
        return $plan;
    }

    /** Der Plan einer offenen Platte; ältere Produktionen ohne Plan bekommen jetzt einen. */
    public function planFor(array $record, array $members): array
    {
        $plan = $record['plan'];
        if (!is_array($plan) || count($plan) < $record['songs_total']) {
            $plan = self::plan($members, $record['songs_total']);
            $this->repo->db->update('records', ['plan' => json_encode($plan)], ['id' => $record['id']]);
        }
        return $plan;
    }

    public function start(int $bandId, string $title, int $songs): void
    {
        $title = Bands::cleanName($title, 'Der Plattentitel', 60);
        if ($songs < Balance::RECORD_MIN_SONGS || $songs > Balance::RECORD_MAX_SONGS) {
            throw new ApiError(sprintf('Eine Platte hat %d bis %d Lieder.', Balance::RECORD_MIN_SONGS, Balance::RECORD_MAX_SONGS));
        }
        $this->repo->db->transaction(function () use ($bandId, $title, $songs): void {
            $band = $this->repo->band($bandId);
            $members = $this->repo->members($bandId);
            $blocker = self::blocker($band, count($members), $this->repo->openRecord($bandId));
            if ($blocker !== null) {
                throw new ApiError($blocker);
            }
            $this->repo->spend(self::cost($songs), 'Studiozeit');
            $this->repo->db->insert('records', [
                'label_id' => $this->repo->labelId,
                'band_id' => $bandId,
                'title' => $title,
                'songs_total' => $songs,
                'songs_done' => 0,
                'song_results' => '[]',
                'plan' => json_encode(self::plan($members, $songs)),
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
            'UPDATE bands SET records = records + 1, songs_recorded = songs_recorded + ?, gigs_since_record = 0, live_songs_needed = 0, perfect_records = perfect_records + ? WHERE id = ?',
            [$songs, $perfect ? 1 : 0, $band['id']]
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
        Charts::markDirty($db);

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
