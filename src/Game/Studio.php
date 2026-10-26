<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;

/**
 * Platte aufnehmen: Das Label wählt Songzahl, Studio, Produzent und Cover; dann ist die Band eine Weile im Studio
 * (eine Aktivität „record“ wie die Probe) und die Platte erscheint von selbst. Keine Minispiele.
 * Studios und Produzenten in Balance::STUDIOS / Balance::PRODUCERS. Ein neues Album gibt das Label erst frei,
 * wenn das letzte oft genug live gespielt und beworben ist (cycle).
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
            return 'Die Band nimmt gerade auf.';
        }
        if ($memberCount < Balance::MIN_MEMBERS) {
            return sprintf('Die Band braucht mindestens %d Musiker.', Balance::MIN_MEMBERS);
        }
        $cycle = self::cycle($band);
        if ($cycle !== null && !$cycle['done']) {
            return 'Das Label gibt ein neues Album erst frei, wenn das letzte live gespielt und beworben ist.';
        }
        return null;
    }

    /** Album-Zyklus seit der letzten Platte (null vor der ersten): Lieder live und Band-Promos, jeweils geschafft/nötig. */
    public static function cycle(array $band): ?array
    {
        if ($band['records'] === 0) {
            return null;
        }
        $songs = ['have' => min($band['live_songs'], $band['live_songs_needed']), 'need' => $band['live_songs_needed']];
        $promos = ['have' => min($band['band_promos'], Balance::RECORD_CYCLE['promos']), 'need' => Balance::RECORD_CYCLE['promos']];
        return [
            'songs' => $songs,
            'promos' => $promos,
            'plays' => Balance::RECORD_CYCLE['plays'],
            'done' => $songs['have'] >= $songs['need'] && $promos['have'] >= $promos['need'],
        ];
    }

    /** Was eine Aufnahme kostet: Studio je Lied plus Honorar des Produzenten. */
    public static function cost(int $songs, int $studio = 0, string $producer = 'none'): int
    {
        return Balance::STUDIOS[$studio]['perSong'] * $songs + Balance::PRODUCERS[$producer]['fee'];
    }

    /** Warum dieser Produzent in diesem Studio nicht geht (oder nicht mit dieser Band), sonst null. */
    public static function refusal(int $studio, string $producer, float $sound): ?string
    {
        if (!isset(Balance::STUDIOS[$studio])) {
            return 'Dieses Studio gibt es nicht.';
        }
        $p = Balance::PRODUCERS[$producer] ?? null;
        if ($p === null) {
            return 'Diesen Produzenten gibt es nicht.';
        }
        if ($studio > $p['unlocks']) {
            $need = array_key_first(array_filter(Balance::PRODUCERS, static fn ($x) => $x['unlocks'] >= $studio && empty($x['minSound'])));
            return sprintf('Ins %s kommt man nur mit einem Produzenten wie %s.', Balance::STUDIOS[$studio]['name'], Balance::PRODUCERS[$need]['name']);
        }
        if (isset($p['minSound']) && $sound < $p['minSound']) {
            return sprintf('%s nimmt nur Bands, die schon richtig gut klingen (Sound ab %d).', $p['name'], $p['minSound']);
        }
        return null;
    }

    /** Qualität der Platte (0–1): Grundwert, Studio, Produzent, Perfektionisten und goldene Studio-Instrumente. */
    public static function quality(array $members, array $gear, int $studio, string $producer): float
    {
        $q = Balance::RECORD_QUALITY_BASE + Balance::STUDIOS[$studio]['quality'] + Balance::PRODUCERS[$producer]['quality'];
        foreach ($members as $m) {
            $q += Mood::traitMax($m, Balance::TRAITS['recordQuality'], 0.0) + Gear::value(BandCalc::gearOf($m, $gear), 'studio');
        }
        return min(1.0, $q);
    }

    /** Anspruch der Band (Ruhm-Stufe 0–3) gegen das Produktionsniveau (Mittel aus Studio und Produzent): < 0 ärgert, > 0 freut. */
    public static function claimGap(array $band, array $members, int $studio, string $producer): int
    {
        $claim = Levels::forFame(BandCalc::bandFame($band, $members));
        $level = (int) round(($studio + Balance::PRODUCERS[$producer]['level']) / 2);
        return $level - $claim;
    }

    /**
     * Was die Platte voraussichtlich bringt: Erstverkauf plus Tantiemen, solange sie chartet (als Spanne), und die Chartpunkte.
     * @return array{money: int, royalties: array{0: int, 1: int}, chartPoints: float}
     */
    public static function estimate(array $band, array $members, array $gear, int $songs, float $quality): array
    {
        $sound = BandCalc::bandSound($band, $members, $gear);
        [$money, $points] = self::returns($band, $members, $gear, $songs, $quality, $sound);
        $hours = (1 - (1 - Balance::CHART_DECAY) ** (Balance::CHART_MAX_AGE / 3600)) / Balance::CHART_DECAY;
        $royalties = $points * Balance::ROYALTY_MONEY * $hours;
        return ['money' => $money, 'royalties' => [(int) round($royalties * Balance::RECORD_ESTIMATE['low']), (int) round($royalties)], 'chartPoints' => $points];
    }

    /** Erstverkauf und Chartpunkte einer Platte dieser Qualität. */
    private static function returns(array $band, array $members, array $gear, int $songs, float $quality, float $sound): array
    {
        $perfect = $quality >= Balance::RECORD_PERFECT_FROM;
        $lp = 1 + Balance::RECORD_LP_BONUS * max(0, $songs - Balance::RECORD_MIN_SONGS);
        $perfectFactor = $perfect ? Balance::RECORD_PERFECT_BONUS : 1.0;
        $sales = 1 + min(Balance::RECORD_FAME_SALES_MAX, BandCalc::bandFame($band, $members) / Balance::RECORD_FAME_SALES);
        // Egozentrische stehen gern im Mittelpunkt, Kultobjekte verkaufen sich: Die Platte verkauft sich besser
        $sales *= 1 + array_sum(array_map(static fn ($m) => Mood::traitMax($m, Balance::TRAITS['recordSales'], 0.0) + Gear::value(BandCalc::gearOf($m, $gear), 'cult'), $members));
        $money = (int) round($songs * Balance::RECORD_MONEY_PER_SONG * (0.3 + $quality ** 2) * $lp * $perfectFactor * $sales);
        $hit = array_sum(array_map(static fn ($m) => Gear::value(BandCalc::gearOf($m, $gear), 'hit'), $members));
        $points = round(Charts::points($songs, $quality, $sound, $perfect) * (1 + $hit), 1);
        return [$money, $points];
    }

    /**
     * Alle Möglichkeiten für den Aufnahme-Dialog: je Produzent und Studio Kosten, Qualität, erwarteter Ertrag, ob es geht und wie die Band es findet.
     */
    public function options(int $bandId, int $songs): array
    {
        $band = $this->repo->band($bandId);
        $members = $this->repo->members($bandId);
        $gear = $this->repo->gearByMusician();
        $sound = BandCalc::bandSound($band, $members, $gear);
        $have = (int) floor((float) $band['songs_written']);
        $songs = max(Balance::RECORD_MIN_SONGS, min(Balance::RECORD_MAX_SONGS, $songs));
        $out = [];
        foreach (array_keys(Balance::PRODUCERS) as $producer) {
            foreach (array_keys(Balance::STUDIOS) as $studio) {
                $q = self::quality($members, $gear, $studio, $producer);
                $est = self::estimate($band, $members, $gear, $songs, $q);
                $out[] = [
                    'producer' => $producer,
                    'studio' => $studio,
                    'cost' => self::cost($songs, $studio, $producer),
                    'quality' => round($q, 3),
                    'perfect' => $q >= Balance::RECORD_PERFECT_FROM,
                    'money' => $est['money'],
                    'royalties' => $est['royalties'],
                    'claim' => self::claimGap($band, $members, $studio, $producer),
                    'refusal' => self::refusal($studio, $producer, $sound),
                ];
            }
        }
        return [
            'songs' => $songs,
            'songsWritten' => $have,
            'seconds' => $songs * Balance::RECORD_SECONDS_PER_SONG,
            'sound' => round($sound, 1),
            'blocker' => self::blocker($band, count($members), $this->repo->openRecord($bandId)),
            'options' => $out,
        ];
    }

    /** Aufnahme starten: Geld, Songs aus dem Vorrat, Band ins Studio. Die Platte erscheint, wenn die Zeit um ist. */
    public function start(int $bandId, string $title, int $songs, int $studio, string $producer, array $cover): array
    {
        $title = Bands::cleanName($title, 'Der Plattentitel', 60);
        if ($songs < Balance::RECORD_MIN_SONGS || $songs > Balance::RECORD_MAX_SONGS) {
            throw new ApiError(sprintf('Eine Platte hat %d bis %d Lieder.', Balance::RECORD_MIN_SONGS, Balance::RECORD_MAX_SONGS));
        }
        $spec = Cover::validate($cover);
        $db = $this->repo->db;
        return $db->transaction(function () use ($db, $bandId, $title, $songs, $studio, $producer, $spec): array {
            $now = Clock::now();
            $band = $this->repo->band($bandId);
            $members = $this->repo->members($bandId);
            $blocker = self::blocker($band, count($members), $this->repo->openRecord($bandId)) ?? Activities::blocker($band, $members, $now);
            if ($blocker !== null) {
                throw new ApiError($blocker);
            }
            $refuser = Mood::refuser($members, 'record');
            if ($refuser !== null) {
                throw new ApiError($refuser['first_name'] . ' hat darauf gerade keine Lust. Eine Anordnung, die der Band gefällt, hebt zuerst die Stimmung.');
            }
            if ((int) floor((float) $band['songs_written']) < $songs) {
                throw new ApiError(sprintf('Dafür hat die Band noch nicht genug Songs geschrieben (%d von %d).', (int) floor((float) $band['songs_written']), $songs));
            }
            $gear = $this->repo->gearByMusician();
            $refusal = self::refusal($studio, $producer, BandCalc::bandSound($band, $members, $gear));
            if ($refusal !== null) {
                throw new ApiError($refusal);
            }
            $p = Balance::PRODUCERS[$producer];
            $this->repo->spend(self::cost($songs, $studio, $producer), $producer === 'none' ? 'Studio: ' . Balance::STUDIOS[$studio]['name'] : sprintf('Studio: %s mit %s', Balance::STUDIOS[$studio]['name'], $p['name']));
            $quality = self::quality($members, $gear, $studio, $producer);
            $endsAt = $now + $songs * Balance::RECORD_SECONDS_PER_SONG;
            if ($db->exec('UPDATE bands SET busy_until = ?, released = 1, songs_written = songs_written - ? WHERE id = ? AND busy_until <= ?', [$endsAt, $songs, $bandId, $now]) === 0) {
                throw new ApiError('Die Band ist gerade beschäftigt.');
            }
            // Anspruch: Ist die Produktion unter der Würde der Band, ärgert sie sich; darüber freut sie sich. Mit der Legende sind alle entspannt.
            $gap = self::claimGap($band, $members, $studio, $producer);
            $c = Balance::RECORD_CLAIM;
            foreach ($members as $m) {
                $delta = $gap < 0 ? $gap * $c['down'] * Mood::traitMax($m, $c['picky'], 1.0) : $gap * $c['up'];
                $delta += $p['mood'] ?? 0.0;
                if ($delta != 0.0) {
                    $db->update('musicians', ['wellbeing' => round(max(0.0, min(100.0, $m['wellbeing'] + $delta)), 3)], ['id' => $m['id']]);
                }
            }
            $recordId = $db->insert('records', [
                'label_id' => $this->repo->labelId,
                'band_id' => $bandId,
                'title' => $title,
                'songs_total' => $songs,
                'songs_done' => 0,
                'song_results' => '[]',
                'status' => 'open',
                'cover' => json_encode(['spec' => $spec]),
                'studio' => Balance::STUDIOS[$studio]['key'],
                'producer' => $producer,
                'quality' => round($quality, 4),
                'created_at' => $now,
            ]);
            $this->repo->log('record', sprintf('%s geht ins %s%s: „%s“ mit %d Liedern.', $band['name'], Balance::STUDIOS[$studio]['name'], $producer === 'none' ? '' : ' mit ' . $p['name'], $title, $songs), $bandId);
            $id = $db->insert('activities', [
                'label_id' => $this->repo->labelId,
                'band_id' => $bandId,
                'musician_id' => null,
                'type' => 'record',
                'started_at' => $now,
                'ends_at' => $endsAt,
                'data' => json_encode(['title' => sprintf('Studio: „%s“', $title), 'recordId' => $recordId, 'studio' => $studio, 'producer' => $producer, 'songs' => $songs], JSON_UNESCAPED_UNICODE),
            ]);
            return Present::activity($db->one('SELECT * FROM activities WHERE id = ?', [$id]));
        });
    }

    /** Die Studiozeit ist um (Activities::settleDue): Platte fertig. */
    public static function settle(Repo $repo, array $row, array $data): array
    {
        $record = $repo->db->one("SELECT * FROM records WHERE id = ? AND status = 'open'", [(int) ($data['recordId'] ?? 0)]);
        if ($record === null) {
            return [];
        }
        $bandId = (int) $row['band_id'];
        return (new self($repo))->complete(Repo::hydrateRecord($record), $repo->band($bandId), $repo->members($bandId), $repo->gearByMusician());
    }

    /** Platte fertig: Können, Erstverkauf, Ruhm, Charts. */
    public function complete(array $record, array $band, array $members, array $gear): array
    {
        $db = $this->repo->db;
        $songs = $record['songs_total'];
        $quality = (float) ($record['quality'] ?? Balance::RECORD_QUALITY_BASE);
        $perfect = $quality >= Balance::RECORD_PERFECT_FROM;
        $producer = Balance::PRODUCERS[$record['producer'] ?? 'none'] ?? Balance::PRODUCERS['none'];
        $sound = BandCalc::bandSound($band, $members, $gear);

        // Im Studio lernt jeder etwas dazu (je Lied, nach Qualität).
        $levelUps = [];
        foreach ($members as $m) {
            $gain = BandCalc::trainingGain($m, Balance::RECORD_SONG_SKILL * $songs * $quality, null, BandCalc::gearOf($m, $gear));
            $column = $m['band_slot'] === 2 && $m['instr2'] !== null ? 'skill2' : 'skill1';
            $db->update('musicians', [$column => min((float) Balance::MAX_STAT, BandCalc::skill($m) + $gain)], ['id' => $m['id']]);
            $levelUp = Levels::check($this->repo, $m['id']);
            if ($levelUp !== null) {
                $levelUps[] = $levelUp;
            }
        }
        Mood::bond($this->repo, $members, 'record');
        Mood::react($this->repo, $this->repo->members($band['id']), 'record');

        [$money, $chartPoints] = self::returns($band, $members, $gear, $songs, $quality, $sound);
        $lp = 1 + Balance::RECORD_LP_BONUS * max(0, $songs - Balance::RECORD_MIN_SONGS);
        $fame = $songs * Balance::RECORD_FAME_PER_SONG * BandCalc::soundFactor($sound) * (0.3 + $quality ** 1.5) * $lp * ($perfect ? Balance::RECORD_PERFECT_BONUS : 1.0);
        $gains = BandCalc::splitFame($members, $fame, $gear);
        // Mit der Legende ist der Ruhm nicht durch die Stufen-Bremse gebremst: ein Weg nach oben.
        $this->repo->addBandFame($band['id'], $gains, empty($producer['unpaced']));
        $this->repo->earn($money, sprintf('Erstverkauf „%s“', $record['title']));

        $db->exec(
            'UPDATE bands SET records = records + 1, songs_recorded = songs_recorded + ?, gigs_since_record = 0, live_songs_needed = ?, live_songs = 0, band_promos = 0, perfect_records = perfect_records + ? WHERE id = ?',
            [$songs, Balance::RECORD_CYCLE['plays'] * $songs, $perfect ? 1 : 0, $band['id']]
        );
        $fameGain = round(array_sum($gains), 1);
        $firstRecord = (int) $db->value("SELECT COUNT(*) FROM records WHERE label_id = ? AND status = 'done'", [$this->repo->labelId]) === 0;
        $now = Clock::now();
        $db->update('records', [
            'status' => 'done',
            'songs_done' => $songs,
            'money_reward' => $money,
            'fame_reward' => $fameGain,
            'perfect' => $perfect ? 1 : 0,
            'finished_at' => $now,
            'chart_points' => $chartPoints,
            'royalties_until' => $now,
        ], ['id' => $record['id']]);
        Charts::markDirty($db);

        $format = $songs >= Balance::CHART_LP_SONGS ? 'LP' : 'EP';
        $this->repo->log('record', sprintf('%s veröffentlicht „%s“ (%s, %d Lieder)%s', $band['name'], $record['title'], $format, $songs, $perfect ? ' – eine perfekte Platte!' : '.'), $band['id']);
        // In der WikiSignia steht bei jedem Künstler, an welchen Platten er beteiligt war.
        foreach ($members as $m) {
            $this->repo->log('record', mb_substr(sprintf('%s nimmt mit %s „%s“ auf (%s).', Bands::fullName($m), $band['name'], $record['title'], $format), 0, 250), $band['id'], $m['id']);
        }
        if ($firstRecord) {
            Scout::grant($this->repo, 'die erste Platte deines Labels.');
        }

        return [
            'record' => true,
            'title' => $record['title'],
            'format' => $format,
            'chartPoints' => $chartPoints,
            'vouchers' => ($firstRecord ? 1 : 0) + ($perfect ? 1 : 0),
            'songs' => $songs,
            'quality' => round($quality, 3),
            'perfect' => $perfect,
            'money' => $money,
            'fame' => $fameGain,
            'cover' => $record['cover']['spec'] ?? null,
            'producer' => $record['producer'] ?? 'none',
            'studio' => $record['studio'] ?? 'garage',
            'levelUps' => $levelUps,
        ];
    }
}
