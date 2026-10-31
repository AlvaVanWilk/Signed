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
            return $memberCount === 1 ? 'Nimmt gerade auf.' : 'Die Band nimmt gerade auf.';
        }
        if ($memberCount < Balance::MIN_MEMBERS) {
            return sprintf('Die Band braucht mindestens %d Musiker.', Balance::MIN_MEMBERS);
        }
        // Neuer Kern: kein Album-Zyklus mehr, nur die Songs zählen
        $cycle = BandCore::$live ? null : self::cycle($band);
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
    private static function returns(array $band, array $members, array $gear, int $songs, float $quality, float $sound, float $coverSales = 0.0): array
    {
        $perfect = $quality >= Balance::RECORD_PERFECT_FROM;
        $lp = 1 + Balance::RECORD_LP_BONUS * max(0, $songs - Balance::RECORD_MIN_SONGS);
        $perfectFactor = $perfect ? Balance::RECORD_PERFECT_BONUS : 1.0;
        $sales = 1 + min(Balance::RECORD_FAME_SALES_MAX, BandCalc::bandFame($band, $members) / Balance::RECORD_FAME_SALES);
        // Egozentrische stehen gern im Mittelpunkt, Kultobjekte verkaufen sich: Die Platte verkauft sich besser
        $sales *= 1 + array_sum(array_map(static fn ($m) => Mood::traitMax($m, Balance::TRAITS['recordSales'], 0.0) + Gear::value(BandCalc::gearOf($m, $gear), 'cult'), $members));
        $money = (int) round($songs * Balance::RECORD_MONEY_PER_SONG * (0.3 + $quality ** 2) * $lp * $perfectFactor * $sales * (1 + $coverSales));
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
                $q = min(1.0, self::quality($members, $gear, $studio, $producer) + Tutors::recordBonus($band, $songs));
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
            'logo' => $band['logo'],
            'logoSales' => $band['logo'] !== null && $band['records'] > 0,
        ];
    }

    /**
     * Neuer Kern: die vier Teile der Qualität für diese Band jetzt (Sound gegen die Erwartung der Ruhmstufe, Songs, Produktion, Stimmung)
     * und Q je Produktionsstufe. $songs: Qualitäten der ausgewählten Songs.
     * @return array{stage: int, sound: float, songs: float, mood: float, levels: list<array>}
     */
    public static function coreQuality(Repo $repo, array $band, array $members, array $gear, array $songs): array
    {
        $stage = Core\Fans::stage(BandCore::fans($repo->db, $band['id']));
        $sound = Core\Quality::soundShare(BandCalc::bandSound($band, $members, $gear), $stage);
        $songShare = Core\Quality::songsShare($songs);
        $mood = Core\Quality::moodShare(array_column($members, 'wellbeing'));
        $levels = [];
        foreach (Balance::CORE_PRODUCTION as $level => $p) {
            $production = Core\Quality::productionShare($level, $stage);
            $q = Core\Quality::album($sound, $songShare, $production, $mood);
            $levels[] = [
                'level' => $level,
                'studio' => Balance::STUDIOS[$p['studio']]['name'],
                'producer' => Balance::PRODUCERS[$p['producer']]['name'],
                'cost' => Balance::CORE_MONEY['production'][$level],
                'production' => round($production, 3),
                'quality' => round($q, 1),
                'perfect' => Core\Quality::isPerfect($q),
                'fits' => $level === $stage,
                'claim' => $level - $stage,
            ];
        }
        return ['stage' => $stage, 'sound' => round($sound, 3), 'songs' => round($songShare, 3), 'mood' => round($mood, 3), 'levels' => $levels];
    }

    /** Neuer Kern: alles für den Aufnahme-Dialog – Songs im Vorrat (beste vorausgewählt), Qualität je Produktionsstufe, was fehlt. */
    public function coreOptions(int $bandId): array
    {
        $band = $this->repo->band($bandId);
        $members = $this->repo->members($bandId);
        $stock = SongStock::stock($this->repo->db, $bandId);
        $picked = array_slice($stock, 0, Balance::CORE_ALBUM_SONGS);
        $blocker = self::blocker($band, count($members), $this->repo->openRecord($bandId));
        if ($blocker === null && count($stock) < Balance::CORE_ALBUM_SONGS) {
            $blocker = sprintf('Für ein Album braucht %s %d Songs (%d von %d).', Bands::who($members, true), Balance::CORE_ALBUM_SONGS, count($stock), Balance::CORE_ALBUM_SONGS);
        }
        return [
            'core' => true,
            'songs' => $stock,
            'picked' => array_column($picked, 'id'),
            'album' => Balance::CORE_ALBUM_SONGS,
            'seconds' => Balance::CORE_ALBUM_SONGS * Balance::RECORD_SECONDS_PER_SONG,
            'blocker' => $blocker,
            'quality' => self::coreQuality($this->repo, $band, $members, $this->repo->gearByMusician(), array_column($picked, 'quality')),
            // Damit der Dialog Q neu rechnen kann, wenn andere Songs ausgewählt werden
            'weights' => Balance::CORE_QUALITY['weights'],
            'perfectFrom' => Balance::CORE_QUALITY['perfect'],
            'logo' => $band['logo'],
            // Gutschein vom Battle: Die Produktion, die zur Ruhmstufe passt, kostet nichts
            'voucher' => Prizes::production($this->repo, $bandId) !== null,
        ];
    }

    /**
     * Neuer Kern: Aufnahme starten mit einer Produktionsstufe (0–3) und 12 ausgewählten Songs. Die Qualität steht beim Start fest;
     * die Platte erscheint, wenn die Studiozeit um ist (dann beginnt ihr Hype).
     * @param list<int> $songIds
     */
    public function coreStart(int $bandId, string $title, int $level, array $songIds, array $cover, string $designer = 'student', mixed $logo = null): array
    {
        $title = Bands::cleanName($title, 'Der Plattentitel', 60);
        $production = Balance::CORE_PRODUCTION[$level] ?? throw new ApiError('Diese Produktion gibt es nicht.');
        $newLogo = Cover::logo($logo);
        $db = $this->repo->db;
        return $db->transaction(function () use ($db, $bandId, $title, $level, $production, $songIds, $cover, $designer, $newLogo): array {
            $now = Clock::now();
            $band = $this->repo->band($bandId);
            $spec = Cover::validate($cover, $designer, $band['logo'] ?? $newLogo);
            $members = $this->repo->members($bandId);
            $blocker = self::blocker($band, count($members), $this->repo->openRecord($bandId)) ?? Activities::blocker($band, $members, $now) ?? Tours::away($db, $bandId);
            if ($blocker !== null) {
                throw new ApiError($blocker);
            }
            $refuser = Mood::refuser($members, 'record');
            if ($refuser !== null) {
                throw new ApiError($refuser['first_name'] . ' hat darauf gerade keine Lust. Eine Anordnung, die der Band gefällt, hebt zuerst die Stimmung.');
            }
            $songs = SongStock::pick($db, $bandId, $songIds);
            $parts = self::coreQuality($this->repo, $band, $members, $this->repo->gearByMusician(), $songs);
            $quality = $parts['levels'][$level]['quality'];
            $studio = Balance::STUDIOS[$production['studio']];
            $p = Balance::PRODUCERS[$production['producer']];
            // Gutschein vom Battle: Die Produktion, die zur Ruhmstufe passt, kostet nichts.
            $voucher = $level === $parts['stage'] ? Prizes::production($this->repo, $bandId) : null;
            if ($voucher !== null) {
                $db->exec('DELETE FROM prizes WHERE id = ?', [$voucher]);
            } else {
                $this->repo->spend(Balance::CORE_MONEY['production'][$level], sprintf('Studio: %s mit %s', $studio['name'], $p['name']));
            }
            $this->repo->spend(Balance::COVER_DESIGNERS[$designer]['fee'], sprintf('Cover: %s', Balance::COVER_DESIGNERS[$designer]['name']));
            if ($band['logo'] === null && $newLogo !== null) {
                $db->update('bands', ['logo' => json_encode($newLogo)], ['id' => $bandId]);
            }
            $count = Balance::CORE_ALBUM_SONGS;
            $endsAt = $now + $count * Balance::RECORD_SECONDS_PER_SONG;
            Events::assertFree($db, $bandId, $endsAt);
            if ($db->exec('UPDATE bands SET busy_until = ?, released = 1, songs_written = ? WHERE id = ? AND busy_until <= ?', [$endsAt, max(0.0, $band['songs_written'] - $count), $bandId, $now]) === 0) {
                throw new ApiError(Bands::who($members) . ' ist gerade beschäftigt.');
            }
            // Anspruch: Produktion unter der Ruhmstufe ärgert, darüber freut; mit Ruben Stille sind alle entspannt.
            $gap = $level - $parts['stage'];
            $c = Balance::RECORD_CLAIM;
            foreach ($members as $m) {
                $delta = ($gap < 0 ? $gap * $c['down'] * Mood::traitMax($m, $c['picky'], 1.0) : $gap * $c['up']) + ($p['mood'] ?? 0.0);
                if ($delta != 0.0) {
                    $db->update('musicians', ['wellbeing' => round(max(0.0, min(100.0, $m['wellbeing'] + $delta)), 3)], ['id' => $m['id']]);
                }
            }
            $recordId = $db->insert('records', [
                'label_id' => $this->repo->labelId,
                'band_id' => $bandId,
                'title' => $title,
                'songs_total' => $count,
                'songs_done' => 0,
                'song_results' => '[]',
                'status' => 'open',
                'cover' => json_encode(['spec' => $spec, 'sales' => 0.0]),
                'studio' => $studio['key'],
                'producer' => $production['producer'],
                'quality' => round($quality / 100, 4),
                'created_at' => $now,
            ]);
            SongStock::assign($db, $bandId, $songIds, $recordId);
            $this->repo->log('record', sprintf('%s geht ins %s mit %s: „%s“.', $band['name'], $studio['name'], $p['name'], $title), $bandId);
            $id = $db->insert('activities', [
                'label_id' => $this->repo->labelId,
                'band_id' => $bandId,
                'musician_id' => null,
                'type' => 'record',
                'started_at' => $now,
                'ends_at' => $endsAt,
                'data' => json_encode([
                    'title' => sprintf('„%s“', $title), 'recordId' => $recordId, 'studio' => $production['studio'], 'producer' => $production['producer'],
                    'songs' => $count, 'quality' => round($quality / 100, 3), 'core' => ['level' => $level, 'parts' => $parts],
                ], JSON_UNESCAPED_UNICODE),
            ]);
            return Present::activity($db->one('SELECT * FROM activities WHERE id = ?', [$id]));
        });
    }

    /** Aufnahme starten: Geld, Songs aus dem Vorrat, Band ins Studio. Die Platte erscheint, wenn die Zeit um ist. */
    public function start(int $bandId, string $title, int $songs, int $studio, string $producer, array $cover, string $designer = 'student', mixed $logo = null): array
    {
        $title = Bands::cleanName($title, 'Der Plattentitel', 60);
        if ($songs < Balance::RECORD_MIN_SONGS || $songs > Balance::RECORD_MAX_SONGS) {
            throw new ApiError(sprintf('Eine Platte hat %d bis %d Lieder.', Balance::RECORD_MIN_SONGS, Balance::RECORD_MAX_SONGS));
        }
        $newLogo = Cover::logo($logo);
        $db = $this->repo->db;
        return $db->transaction(function () use ($db, $bandId, $title, $songs, $studio, $producer, $cover, $designer, $newLogo): array {
            $now = Clock::now();
            $band = $this->repo->band($bandId);
            // Das Bandlogo wird einmal gewählt; danach steht es auf jedem Cover und hilft beim Verkauf (Wiedererkennung).
            $logo = $band['logo'] ?? $newLogo;
            $spec = Cover::validate($cover, $designer, $logo);
            $coverSales = Balance::COVER_DESIGNERS[$designer]['sales'] + ($band['logo'] !== null && $band['records'] > 0 ? Balance::COVER_LOGO_SALES : 0.0);
            $members = $this->repo->members($bandId);
            $blocker = self::blocker($band, count($members), $this->repo->openRecord($bandId)) ?? Activities::blocker($band, $members, $now) ?? Tours::away($db, $bandId);
            if ($blocker !== null) {
                throw new ApiError($blocker);
            }
            $refuser = Mood::refuser($members, 'record');
            if ($refuser !== null) {
                throw new ApiError($refuser['first_name'] . ' hat darauf gerade keine Lust. Eine Anordnung, die der Band gefällt, hebt zuerst die Stimmung.');
            }
            if ((int) floor((float) $band['songs_written']) < $songs) {
                throw new ApiError(sprintf('Dafür hat %s noch nicht genug Songs geschrieben (%d von %d).', Bands::who($members, true), (int) floor((float) $band['songs_written']), $songs));
            }
            $gear = $this->repo->gearByMusician();
            $refusal = self::refusal($studio, $producer, BandCalc::bandSound($band, $members, $gear));
            if ($refusal !== null) {
                throw new ApiError($refusal);
            }
            $p = Balance::PRODUCERS[$producer];
            $this->repo->spend(self::cost($songs, $studio, $producer), $producer === 'none' ? 'Studio: ' . Balance::STUDIOS[$studio]['name'] : sprintf('Studio: %s mit %s', Balance::STUDIOS[$studio]['name'], $p['name']));
            $this->repo->spend(Balance::COVER_DESIGNERS[$designer]['fee'], sprintf('Cover: %s', Balance::COVER_DESIGNERS[$designer]['name']));
            if ($band['logo'] === null && $newLogo !== null) {
                $db->update('bands', ['logo' => json_encode($newLogo)], ['id' => $bandId]);
            }
            $quality = min(1.0, self::quality($members, $gear, $studio, $producer) + Tutors::recordBonus($band, $songs));
            $endsAt = $now + $songs * Balance::RECORD_SECONDS_PER_SONG;
            Events::assertFree($db, $bandId, $endsAt);
            // Die gecoachten Songs sind damit verbraucht; ohne sie gibt es auch keinen Zuschlag mehr.
            $coached = max(0.0, $band['coached_songs'] - $songs);
            if ($db->exec('UPDATE bands SET busy_until = ?, released = 1, songs_written = songs_written - ?, coached_songs = ?, coach_bonus = ? WHERE id = ? AND busy_until <= ?', [$endsAt, $songs, $coached, $coached > 0 ? $band['coach_bonus'] : 0.0, $bandId, $now]) === 0) {
                throw new ApiError(Bands::who($members) . ' ist gerade beschäftigt.');
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
                'cover' => json_encode(['spec' => $spec, 'sales' => $coverSales]),
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
                'data' => json_encode(['title' => sprintf('„%s“', $title), 'recordId' => $recordId, 'studio' => $studio, 'producer' => $producer, 'songs' => $songs, 'quality' => round($quality, 3)], JSON_UNESCAPED_UNICODE),
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
        if (BandCore::$live) {
            return $this->coreComplete($record, $band, $members, $gear);
        }
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
            $db->update('musicians', [$column => Levels::cap(BandCalc::skill($m) + $gain)], ['id' => $m['id']]);
            $levelUp = Levels::check($this->repo, $m['id']);
            if ($levelUp !== null) {
                $levelUps[] = $levelUp;
            }
        }
        Mood::bond($this->repo, $members, 'record');
        Mood::react($this->repo, $this->repo->members($band['id']), 'record');

        [$money, $chartPoints] = self::returns($band, $members, $gear, $songs, $quality, $sound, (float) ($record['cover']['sales'] ?? 0.0));
        $lp = 1 + Balance::RECORD_LP_BONUS * max(0, $songs - Balance::RECORD_MIN_SONGS);
        $fame = $songs * Balance::RECORD_FAME_PER_SONG * BandCalc::soundFactor($sound) * (0.3 + $quality ** 1.5) * $lp * ($perfect ? Balance::RECORD_PERFECT_BONUS : 1.0);
        $gains = BandCalc::splitFame($members, $fame, $gear);
        $this->repo->addBandFame($band['id'], $gains);
        $this->repo->earn($money, sprintf('Erstverkauf „%s“', $record['title']));

        $db->exec(
            'UPDATE bands SET records = records + 1, songs_recorded = songs_recorded + ?, gigs_since_record = 0, live_songs_needed = ?, live_songs = 0, band_promos = 0, perfect_records = perfect_records + ? WHERE id = ?',
            [$songs, Balance::RECORD_CYCLE['plays'] * $songs, $perfect ? 1 : 0, $band['id']]
        );
        $fameGain = round(array_sum($gains), 1);
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
            $line = count($members) === 1
                ? sprintf('%s nimmt „%s“ auf (%s).', Bands::fullName($m), $record['title'], $format)
                : sprintf('%s nimmt mit %s „%s“ auf (%s).', Bands::fullName($m), $band['name'], $record['title'], $format);
            $this->repo->log('record', mb_substr($line, 0, 250), $band['id'], $m['id']);
        }

        return [
            'record' => true,
            'title' => $record['title'],
            'format' => $format,
            'chartPoints' => $chartPoints,
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

    /** Neuer Kern: Platte fertig – Können aus dem Studio, dann erscheint sie und ihr Hype beginnt (kein Erstverkauf, kein Ruhm, keine Chartpunkte). */
    private function coreComplete(array $record, array $band, array $members, array $gear): array
    {
        $db = $this->repo->db;
        $songs = $record['songs_total'];
        $quality = (float) ($record['quality'] ?? 0.5);
        $perfect = Core\Quality::isPerfect($quality * 100);
        $levelUps = [];
        foreach ($members as $m) {
            $gain = BandCalc::trainingGain($m, Balance::RECORD_SONG_SKILL * $songs * $quality, null, BandCalc::gearOf($m, $gear));
            $column = $m['band_slot'] === 2 && $m['instr2'] !== null ? 'skill2' : 'skill1';
            $db->update('musicians', [$column => Levels::cap(BandCalc::skill($m) + $gain)], ['id' => $m['id']]);
            $levelUp = Levels::check($this->repo, $m['id']);
            if ($levelUp !== null) {
                $levelUps[] = $levelUp;
            }
        }
        Mood::bond($this->repo, $members, 'record');
        Mood::react($this->repo, $this->repo->members($band['id']), 'record');
        $now = Clock::now();
        $db->exec('UPDATE bands SET records = records + 1, songs_recorded = songs_recorded + ?, perfect_records = perfect_records + ? WHERE id = ?', [$songs, $perfect ? 1 : 0, $band['id']]);
        $db->update('records', ['status' => 'done', 'songs_done' => $songs, 'perfect' => $perfect ? 1 : 0, 'finished_at' => $now], ['id' => $record['id']]);
        $career = BandCore::release($this->repo, $band, $record['id'], $quality * 100, $now);
        $released = Core\Career::newest($career);
        $format = $songs >= Balance::CHART_LP_SONGS ? 'LP' : 'EP';
        $this->repo->log('record', sprintf('%s veröffentlicht „%s“%s', $band['name'], $record['title'], $perfect ? ' – eine perfekte Platte!' : '.'), $band['id']);
        foreach ($members as $m) {
            $line = count($members) === 1
                ? sprintf('%s nimmt „%s“ auf.', Bands::fullName($m), $record['title'])
                : sprintf('%s nimmt mit %s „%s“ auf.', Bands::fullName($m), $band['name'], $record['title']);
            $this->repo->log('record', mb_substr($line, 0, 250), $band['id'], $m['id']);
        }
        return [
            'record' => true,
            'core' => true,
            'title' => $record['title'],
            'format' => $format,
            'songs' => $songs,
            'quality' => round($quality, 3),
            'perfect' => $perfect,
            'hype' => round($released['hype'] ?? 0.0, 1),
            'soldToday' => (int) round($released['soldRate'] ?? 0.0),
            'money' => 0,
            'fame' => 0,
            'cover' => $record['cover']['spec'] ?? null,
            'producer' => $record['producer'] ?? 'mo',
            'studio' => $record['studio'] ?? 'garage',
            'levelUps' => $levelUps,
        ];
    }
}
