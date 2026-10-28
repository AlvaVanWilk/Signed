<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;
use Signed\Db;
use Signed\Rng;

/**
 * Tournee (Balance::TOUR): Die Band fliegt von Station zu Station durch echte Länder (Catalog::COUNTRIES). Jedes Ziel wird ausgewürfelt,
 * erst wenn es weitergeht. Die Reise dauert (nach Entfernung); bei der Ankunft kommt eine Meldung, und der Auftritt beginnt erst,
 * wenn man ihn startet. Danach entscheidet man: weiterreisen (das nächste Ziel wird ausgewürfelt) oder nach Hause.
 * Mag das Land das Genre der Band, wird es ein besonderer Abend. Gemeinsam unterwegs: Die Beziehungen wachsen.
 * Ablauf (Spalte state): travel → arrived → show → after → travel … → home → ended. Reisen, Auftritt und Heimflug sind Aktionen
 * (tourtravel, tourgig, tour) und werden wie alle anderen gutgeschrieben.
 */
final class Tours
{
    public const TRAVEL = 'tourtravel';
    public const GIG = 'tourgig';
    public const HOME = 'tour';

    /** Laufende Tournee einer Band, sonst null. */
    public static function active(Db $db, int $bandId): ?array
    {
        $row = $db->one("SELECT * FROM tours WHERE band_id = ? AND state <> 'ended' ORDER BY id DESC LIMIT 1", [$bandId]);
        return $row === null ? null : self::hydrate($row);
    }

    private static function hydrate(array $row): array
    {
        return [
            'id' => (int) $row['id'], 'label_id' => (int) $row['label_id'], 'band_id' => (int) $row['band_id'], 'state' => $row['state'],
            'stations' => (int) $row['stations'], 'country' => $row['country'], 'deadline' => (int) $row['deadline'],
            'started_at' => (int) $row['started_at'], 'data' => (json_decode($row['data'], true) ?: []) + ['legs' => [], 'visits' => [], 'roll' => null, 'spent' => 0, 'earned' => 0],
        ];
    }

    private static function save(Db $db, array $tour): void
    {
        $db->update('tours', [
            'state' => $tour['state'], 'country' => $tour['country'], 'deadline' => $tour['deadline'],
            'data' => json_encode($tour['data'], JSON_UNESCAPED_UNICODE),
        ], ['id' => $tour['id']]);
    }

    /** Ein Ort: ein Land der Liste oder „home“ (Berlin). */
    public static function place(string $key): array
    {
        return $key === 'home' ? Catalog::HOME : Catalog::COUNTRIES[$key];
    }

    /** Entfernung in Kilometern (Großkreis). */
    public static function km(string $from, string $to): float
    {
        [$a, $b] = [self::place($from), self::place($to)];
        $r = M_PI / 180;
        $h = sin(($b['lat'] - $a['lat']) * $r / 2) ** 2 + cos($a['lat'] * $r) * cos($b['lat'] * $r) * sin(($b['lon'] - $a['lon']) * $r / 2) ** 2;
        return 2 * 6371 * asin(min(1.0, sqrt($h)));
    }

    public static function travelSeconds(float $km): int
    {
        $c = Balance::TOUR['travel'];
        return (int) round($c['base'] + $km * $c['perKm']);
    }

    public static function legCost(float $km, int $members): int
    {
        $c = Balance::TOUR['cost'];
        return (int) round(max(1, $members) * ($c['perMember'] + $km * $c['perKm']));
    }

    /** Mag das Publikum dieses Landes das Genre der Band? */
    public static function match(string $country, array $band, array $members): bool
    {
        return in_array(Catalog::COUNTRIES[$country]['genre'], Festival::genresOf($band, $members), true);
    }

    /** Ziel auswürfeln: ein Land, in dem die Band auf dieser Tournee noch nicht war. */
    private static function roll(array $tour): string
    {
        $been = array_column($tour['data']['legs'] ?? [], 'to');
        $open = array_values(array_diff(array_keys(Catalog::COUNTRIES), $been));
        return Rng::pick($open ?: array_keys(Catalog::COUNTRIES));
    }

    // ---------- Anordnungen ----------

    /** Los geht's: Länge wählen, das erste Ziel wird ausgewürfelt, die Band fliegt hin. */
    public static function start(Repo $repo, int $bandId, int $stations): array
    {
        $db = $repo->db;
        return $db->transaction(static function () use ($repo, $db, $bandId, $stations): array {
            $now = Clock::now();
            if (!in_array($stations, Balance::TOUR['lengths'], true)) {
                throw new ApiError('So lang kann eine Tournee nicht sein.');
            }
            $band = $repo->band($bandId);
            $members = $repo->members($bandId);
            if (self::active($db, $bandId) !== null) {
                throw new ApiError('Die Band ist schon auf Tournee.');
            }
            $blocker = Activities::blocker($band, $members, $now);
            if ($blocker !== null) {
                throw new ApiError($blocker);
            }
            if ($band['records'] < 1) {
                throw new ApiError('Auf Tournee geht es erst mit einer Platte: Die Band braucht Lieder für die Auftritte.');
            }
            $refuser = Mood::refuser($members, self::HOME);
            if ($refuser !== null) {
                throw new ApiError(sprintf('%s hat auf eine Tournee gerade keine Lust.', $refuser['first_name']));
            }
            // Angemeldet für Battle oder Festival: Eine Tournee dauert länger.
            Events::assertFree($db, $bandId, PHP_INT_MAX);
            $tour = ['id' => 0, 'state' => 'travel', 'stations' => $stations, 'country' => null, 'deadline' => 0,
                'data' => ['legs' => [], 'visits' => [], 'roll' => null, 'spent' => 0, 'earned' => 0]];
            $to = self::roll($tour);
            $cost = self::legCost(self::km('home', $to), count($members));
            if ($repo->label()['money'] < $cost) {
                $c = self::place($to);
                throw new ApiError(sprintf('Ausgewürfelt: %s. Der Flug nach %s kostet %d ₲, so viel hat das Label gerade nicht.', $c['name'], $c['city'], $cost));
            }
            $tour['id'] = $db->insert('tours', [
                'label_id' => $repo->labelId, 'band_id' => $bandId, 'state' => 'travel', 'stations' => $stations, 'country' => $to,
                'deadline' => 0, 'started_at' => $now, 'data' => json_encode($tour['data']),
            ]);
            $repo->log('tour', sprintf('%s geht auf Tournee (%d Stationen). Erstes Ziel: %s.', $band['name'], $stations, self::place($to)['name']), $bandId);
            return self::depart($repo, $tour, $band, $members, 'home', $to, $cost, $now);
        });
    }

    /** Angekommen und bereit: Der Auftritt beginnt (wie ein Gig am Ort nach Ruhm, mit ganzer Setlist). */
    public static function show(Repo $repo, int $bandId): array
    {
        $db = $repo->db;
        return $db->transaction(static function () use ($repo, $db, $bandId): array {
            $now = Clock::now();
            $tour = self::active($db, $bandId);
            if ($tour === null || $tour['state'] !== 'arrived') {
                throw new ApiError('Die Band ist gerade nicht bereit für einen Auftritt.');
            }
            $band = $repo->band($bandId);
            $members = $repo->members($bandId);
            $gear = $repo->gearByMusician();
            $sound = BandCalc::bandSound($band, $members, $gear);
            [$manager, $level] = $repo->bandManager($band);
            $c = Balance::TOUR['match'];
            $abroad = Balance::TOUR['abroad'];
            $country = self::place($tour['country']);
            $match = self::match($tour['country'], $band, $members);
            $venue = BandCalc::venue(BandCalc::bandFame($band, $members));
            $songs = max(1, BandCalc::maxGigSongs($band['songs_recorded'], $level));
            $negotiator = BandCalc::managerBonus($manager, $level, 'verhandler', Balance::NEGOTIATOR_BONUS);
            // Im Ausland kennt noch niemand die Lieder (keine Abnutzung wie bei Gigs zu Hause), und es kommt neues Publikum.
            $gage = (int) round(BandCalc::gagePerSong($venue, $negotiator, count($members)) * $songs * $abroad['money'] * ($match ? $c['money'] : 1.0));
            $sim = Performance::simulate(array_map(static fn ($m) => Activities::player($m, $gear), $members), $sound, $venue['expect'] * ($match ? $c['expect'] : 1.0), $songs);
            $fame = 0.0;
            for ($i = 0; $i < $songs; $i++) {
                $fame += $venue['fame'] * BandCalc::soundFactor($sound) * $sim['perf'] * Rng::float(0.9, 1.1);
            }
            $fame *= $abroad['fame'] * ($match ? $c['fame'] : 1.0) * (1 + BandCalc::managerBonus($manager, $level, 'prprofi', Balance::PR_BONUS));
            $drops = [];
            if ($sim['meter'] >= Balance::FAN_MUSICIAN['minMeter'] && Rng::chance(Balance::FAN_MUSICIAN['chance']) && !FanMusician::onCooldown($repo)) {
                $drops[] = ['kind' => 'musician', 'venue' => $venue['key']];
            }
            $endsAt = $now + $songs * Balance::SONG_SECONDS;
            $db->exec('UPDATE bands SET busy_until = ?, released = 1 WHERE id = ?', [$endsAt, $bandId]);
            $tour['state'] = 'show';
            self::save($db, $tour);
            return (new Activities($repo))->insertBand($bandId, self::GIG, $now, $endsAt, [
                'title' => sprintf('Tournee: %s, %s (%s)', $country['city'], $country['name'], $venue['name']),
                'songs' => $songs,
                'timeline' => $sim['timeline'],
                'reward' => [
                    'money' => $gage,
                    'fameGains' => BandCalc::splitFame($members, $fame, $gear, true),
                    'chartBoost' => round($songs * Balance::CHART_GIG_BOOST, 3),
                    'venue' => $venue['key'],
                    'meter' => $sim['meter'],
                    'drops' => $drops,
                    'tour' => ['id' => $tour['id'], 'country' => $tour['country'], 'match' => $match],
                ],
            ]);
        });
    }

    /**
     * Weiterreisen: Das nächste Ziel wird ausgewürfelt. Reicht das Geld nicht für den Flug, bleibt das Ziel stehen (nochmal versuchen
     * oder nach Hause fliegen).
     * @return array{roll: string, cost: int, short: bool, activity: ?array}
     */
    public static function next(Repo $repo, int $bandId): array
    {
        $db = $repo->db;
        return $db->transaction(static function () use ($repo, $db, $bandId): array {
            $now = Clock::now();
            $tour = self::active($db, $bandId);
            if ($tour === null || $tour['state'] !== 'after') {
                throw new ApiError('Die Band kann gerade nicht weiterreisen.');
            }
            if (count($tour['data']['visits']) >= $tour['stations']) {
                throw new ApiError('Das war die letzte Station. Jetzt geht es nach Hause.');
            }
            $band = $repo->band($bandId);
            $members = $repo->members($bandId);
            $to = $tour['data']['roll'] ?? self::roll($tour);
            $cost = self::legCost(self::km($tour['country'], $to), count($members));
            if ($repo->label()['money'] < $cost) {
                $tour['data']['roll'] = $to;
                self::save($db, $tour);
                return ['roll' => $to, 'cost' => $cost, 'short' => true, 'activity' => null];
            }
            $tour['data']['roll'] = null;
            return ['roll' => $to, 'cost' => $cost, 'short' => false, 'activity' => self::depart($repo, $tour, $band, $members, $tour['country'], $to, $cost, $now)];
        });
    }

    /** Nach Hause fliegen (auch mitten in der Tournee). Der Heimflug ist bezahlt. $at: wann es losgeht (von selbst nach zu langem Warten). */
    public static function home(Repo $repo, int $bandId, ?int $at = null): array
    {
        $db = $repo->db;
        return $db->transaction(static function () use ($repo, $db, $bandId, $at): array {
            $tour = self::active($db, $bandId);
            if ($tour === null || !in_array($tour['state'], ['arrived', 'after'], true)) {
                throw new ApiError('Die Band kann gerade nicht nach Hause fliegen.');
            }
            $band = $repo->band($bandId);
            return self::depart($repo, $tour, $band, $repo->members($bandId), $tour['country'], 'home', 0, $at ?? Clock::now());
        });
    }

    /** Eine Strecke fliegen: Kosten, Reisezeit, die Band ist bis zur Ankunft unterwegs. */
    private static function depart(Repo $repo, array $tour, array $band, array $members, string $from, string $to, int $cost, int $at): array
    {
        $db = $repo->db;
        $km = self::km($from, $to);
        $until = $at + self::travelSeconds($km);
        $place = self::place($to);
        if ($cost > 0) {
            $repo->spend($cost, sprintf('Tournee: Flug nach %s', $place['city']));
        }
        $tour['data']['legs'][] = ['from' => $from, 'to' => $to, 'at' => $at, 'until' => $until, 'km' => (int) round($km), 'cost' => $cost];
        $tour['data']['spent'] += $cost;
        $tour['state'] = $to === 'home' ? 'home' : 'travel';
        $tour['country'] = $to === 'home' ? $tour['country'] : $to;
        $tour['deadline'] = 0;
        self::save($db, $tour);
        $db->exec('UPDATE bands SET busy_until = ? WHERE id = ?', [$until, $band['id']]);
        return (new Activities($repo))->insertBand($band['id'], $to === 'home' ? self::HOME : self::TRAVEL, $at, $until, [
            'title' => $to === 'home' ? sprintf('Heimflug nach %s', $place['city']) : sprintf('Flug nach %s, %s', $place['city'], $place['name']),
            'songs' => 0,
            'reward' => ['tour' => ['id' => $tour['id'], 'from' => $from, 'to' => $to]],
        ]);
    }

    // ---------- Gutschrift (Activities::settleDue) ----------

    /** Angekommen: Meldung, und die Band wartet auf ihren Auftritt. */
    public static function arrived(Repo $repo, array $row, array $data): array
    {
        $tour = self::active($repo->db, (int) $row['band_id']);
        $to = $data['reward']['tour']['to'] ?? null;
        if ($tour === null || $to === null || $tour['state'] !== 'travel') {
            return [];
        }
        $band = $repo->band($tour['band_id']);
        $members = $repo->members($tour['band_id']);
        $tour['state'] = 'arrived';
        $tour['deadline'] = (int) $row['ends_at'] + Balance::TOUR['wait'];
        self::save($repo->db, $tour);
        $repo->db->exec('UPDATE bands SET busy_until = ? WHERE id = ?', [$tour['deadline'], $tour['band_id']]);
        $place = self::place($to);
        $repo->log('tour', sprintf('%s ist in %s (%s) angekommen. Jetzt kann der Auftritt losgehen!', $band['name'], $place['city'], $place['name']), $tour['band_id']);
        return [
            'country' => $to, 'match' => self::match($to, $band, $members), 'genre' => $place['genre'],
            'station' => count($tour['data']['visits']) + 1, 'stations' => $tour['stations'],
        ];
    }

    /** Nach dem Auftritt (Activities::applyBand): Ergebnis merken, die Band wartet auf „weiter“ oder „nach Hause“. */
    public static function afterGig(Repo $repo, int $bandId, array $info, array $result): array
    {
        $tour = self::active($repo->db, $bandId);
        if ($tour === null || $tour['id'] !== (int) ($info['id'] ?? 0)) {
            return $info;
        }
        $tour['data']['visits'][] = ['country' => $info['country'], 'match' => (bool) $info['match'], 'fame' => $result['fame'], 'money' => $result['money'], 'meter' => $result['meter']];
        $tour['data']['earned'] += (int) $result['money'];
        $tour['state'] = 'after';
        $tour['deadline'] = Clock::now() + Balance::TOUR['wait'];
        self::save($repo->db, $tour);
        $repo->db->exec('UPDATE bands SET busy_until = ? WHERE id = ?', [$tour['deadline'], $bandId]);
        return $info + ['station' => count($tour['data']['visits']), 'stations' => $tour['stations'], 'last' => count($tour['data']['visits']) >= $tour['stations']];
    }

    /** Wieder zu Hause: Die Tournee hat die Band zusammengeschweißt. */
    public static function homeArrived(Repo $repo, array $row, array $data): array
    {
        $tour = self::active($repo->db, (int) $row['band_id']);
        if ($tour === null || $tour['state'] !== 'home') {
            return [];
        }
        $repo->db->update('tours', ['state' => 'ended', 'ended_at' => (int) $row['ends_at']], ['id' => $tour['id']]);
        $members = $repo->members($tour['band_id']);
        $visits = $tour['data']['visits'];
        if ($members) {
            Mood::bond($repo, $members, self::HOME);
            $c = Balance::TOUR;
            Mood::lift($repo, $repo->members($tour['band_id']), min($c['liftMax'], $c['lift'] * count($visits)));
            Mood::react($repo, $repo->members($tour['band_id']), self::HOME);
        }
        $name = $repo->db->value('SELECT name FROM bands WHERE id = ?', [$tour['band_id']]) ?? '';
        $repo->log('tour', sprintf(
            '%s ist zurück von der Tournee: %d %s (%s).',
            $name, count($visits), count($visits) === 1 ? 'Auftritt' : 'Auftritte',
            implode(', ', array_map(static fn ($v) => self::place($v['country'])['name'], $visits)) ?: 'keine Station gespielt'
        ), $tour['band_id']);
        return [
            'visits' => $visits,
            'fame' => round(array_sum(array_column($visits, 'fame')), 2),
            'earned' => (int) $tour['data']['earned'],
            'spent' => (int) $tour['data']['spent'],
            'together' => count($members) > 1,
        ];
    }

    /** Wer zu lange nicht weitergemacht hat, fliegt von selbst nach Hause (beim nächsten Spielstand). */
    public static function settle(Repo $repo, int $now): void
    {
        foreach ($repo->db->all("SELECT band_id, deadline FROM tours WHERE label_id = ? AND state IN ('arrived', 'after') AND deadline > 0 AND deadline <= ?", [$repo->labelId, $now]) as $row) {
            self::home($repo, (int) $row['band_id'], (int) $row['deadline']);
            $repo->log('tour', sprintf('%s hat zu lange gewartet und fliegt nach Hause.', $repo->db->value('SELECT name FROM bands WHERE id = ?', [(int) $row['band_id']]) ?? 'Die Band'), (int) $row['band_id']);
        }
    }

    // ---------- Anzeige ----------

    /** Laufende Tourneen des Labels je Band. */
    public static function present(Repo $repo, array $members): array
    {
        $out = [];
        foreach ($repo->db->all("SELECT * FROM tours WHERE label_id = ? AND state <> 'ended'", [$repo->labelId]) as $row) {
            $t = self::hydrate($row);
            $roll = $t['data']['roll'];
            $out[$t['band_id']] = [
                'id' => $t['id'],
                'state' => $t['state'],
                'stations' => $t['stations'],
                'country' => $t['country'],
                'deadline' => $t['deadline'],
                'legs' => $t['data']['legs'],
                'visits' => $t['data']['visits'],
                'spent' => $t['data']['spent'],
                'earned' => $t['data']['earned'],
                // Ausgewürfelt, aber das Geld reichte noch nicht
                'roll' => $roll === null ? null : ['country' => $roll, 'cost' => self::legCost(self::km((string) $t['country'], $roll), count($members[$t['band_id']] ?? []))],
            ];
        }
        return $out;
    }
}
