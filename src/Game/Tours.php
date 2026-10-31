<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;
use Signed\Db;
use Signed\Rng;

/**
 * Tournee in Stufen (Balance::TOUR, Balance::VEHICLES): Wer bekannt genug ist, tourt statt zu Hause zu spielen. Europa mit dem Bus,
 * die Kontinente und die Welt mit dem Flugzeug. Jedes Land wird ausgewürfelt, erst wenn es weitergeht; dort spielt die Band in jeder Stadt
 * des Landes einmal (Catalog::COUNTRIES 'cities'), ohne Fahrt dazwischen. Jeden Auftritt startet man selbst, danach geht es weiter oder heim.
 * Unterwegs lebt die Band im Fahrzeug: Sie ist nicht beschäftigt, probt dort (das Fahrzeug ist ihr Proberaum), schreibt Songs, gibt Interviews.
 * Zu Hause bleibt, was nur zu Hause geht: Auftritte buchen, aufnehmen, Promo-Tour, Auszeit, Kurse, Battle und Festival.
 * Ablauf (Spalte state): travel → arrived (Städte offen) → after (alle Städte gespielt) → travel … → home → ended.
 * Reisen und Heimreise sind Aktionen (tourtravel, tour), die die Band nicht beschäftigen; ein Auftritt ist eine Aktion tourgig.
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
        $data = (json_decode($row['data'], true) ?: []) + ['legs' => [], 'visits' => [], 'roll' => null, 'spent' => 0, 'earned' => 0];
        // Tourneen von vor den Stufen flogen um die ganze Welt.
        $data += ['region' => 'welt', 'vehicle' => 'plane'];
        return [
            'id' => (int) $row['id'], 'label_id' => (int) $row['label_id'], 'band_id' => (int) $row['band_id'], 'state' => $row['state'],
            'stations' => (int) $row['stations'], 'country' => $row['country'], 'deadline' => (int) $row['deadline'],
            'started_at' => (int) $row['started_at'], 'data' => $data,
        ];
    }

    private static function save(Db $db, array $tour): void
    {
        $db->update('tours', [
            'state' => $tour['state'], 'country' => $tour['country'], 'deadline' => $tour['deadline'],
            'data' => json_encode($tour['data'], JSON_UNESCAPED_UNICODE),
        ], ['id' => $tour['id']]);
    }

    // ---------- Länder, Wege, Fahrzeuge ----------

    /** Ein Ort: ein Land der Liste oder „home“ (Berlin), mit `city` (größte Stadt). */
    public static function place(string $key): array
    {
        if ($key === 'home') {
            return Catalog::HOME;
        }
        $c = Catalog::COUNTRIES[$key];
        return $c + ['city' => $c['cities'][0]];
    }

    /** Länder einer Region (Welttournee: alle). */
    public static function countriesOf(string $region): array
    {
        return array_keys(array_filter(Catalog::COUNTRIES, static fn ($c) => $region === 'welt' || $c['region'] === $region));
    }

    /** Städte eines Landes auf dieser Tournee (Welttournee: nur die größte). */
    public static function citiesOf(array $tour, string $country): array
    {
        $cities = Catalog::COUNTRIES[$country]['cities'];
        return !empty(Balance::TOUR['regions'][$tour['data']['region']]['stadium']) ? [$cities[0]] : $cities;
    }

    /** Entfernung in Kilometern (Großkreis). */
    public static function km(string $from, string $to): float
    {
        [$a, $b] = [self::place($from), self::place($to)];
        $r = M_PI / 180;
        $h = sin(($b['lat'] - $a['lat']) * $r / 2) ** 2 + cos($a['lat'] * $r) * cos($b['lat'] * $r) * sin(($b['lon'] - $a['lon']) * $r / 2) ** 2;
        return 2 * 6371 * asin(min(1.0, sqrt($h)));
    }

    /** Reisezeit mit diesem Fahrzeug (Bus oder Flugzeug, Stufe 1–4). */
    public static function travelSeconds(float $km, string $vehicle = 'plane', int $level = 1): int
    {
        $c = Balance::TOUR['travel'][$vehicle];
        return (int) round(($c['base'] + $km * $c['perKm']) * self::vehicle($vehicle, $level)['speed']);
    }

    public static function legCost(float $km, int $members): int
    {
        $c = Balance::TOUR['cost'];
        return (int) round(max(1, $members) * ($c['perMember'] + $km * $c['perKm']));
    }

    /** Werte einer Fahrzeug-Stufe (1–4; darunter die erste). */
    public static function vehicle(string $kind, int $level): array
    {
        $levels = Balance::VEHICLES[$kind];
        return $levels[max(0, min(count($levels), $level) - 1)];
    }

    /** Mag das Publikum dieses Landes das Genre der Band? */
    public static function match(string $country, array $band, array $members): bool
    {
        return in_array(Catalog::COUNTRIES[$country]['genre'], Festival::genresOf($band, $members), true);
    }

    /** Ziel auswürfeln: ein Land der Region, in dem die Band auf dieser Tournee noch nicht war. */
    private static function roll(array $tour): string
    {
        $pool = self::countriesOf($tour['data']['region']);
        $been = array_column($tour['data']['legs'] ?? [], 'to');
        $open = array_values(array_diff($pool, $been));
        return Rng::pick($open ?: $pool);
    }

    /** Was die Band gerade für Tourneen hat: je Region Name, nötiger Ruhm, Fahrzeug, ob es geht (sonst warum), Länder und Städte. */
    public static function options(array $band, array $members): array
    {
        $fame = BandCalc::bandFame($band, $members);
        $out = [];
        foreach (Balance::TOUR['regions'] as $key => $r) {
            $countries = self::countriesOf($key);
            $owned = (int) $band[$r['vehicle']];
            $why = BandCore::$live ? self::coreBlocked($key, $band) : null;
            $reached = BandCore::$live ? $why === null : $fame >= $r['fame'];
            if ($why !== null) {
                // Neuer Kern: Ruhmstufe nach Fans (siehe coreBlocked)
            } elseif ($fame < $r['fame'] && !BandCore::$live) {
                $why = sprintf('Für die %s braucht die Band %s Ruhm (hat %s).', $r['name'], number_format($r['fame'], 0, ',', '.'), number_format($fame, 0, ',', '.'));
            } elseif ($owned < 1) {
                $why = $r['vehicle'] === 'bus' ? 'Dafür braucht die Band einen Bus.' : 'Dafür braucht die Band ein Flugzeug.';
            }
            $out[] = [
                'key' => $key, 'name' => $r['name'], 'fame' => $r['fame'], 'vehicle' => $r['vehicle'], 'owned' => $owned,
                'lengths' => $r['lengths'], 'countries' => $countries, 'stadium' => !empty($r['stadium']),
                'cities' => !empty($r['stadium']) ? count($countries) : array_sum(array_map(static fn ($c) => count(Catalog::COUNTRIES[$c]['cities']), $countries)),
                'reached' => $reached, 'blocked' => $why,
                // Neuer Kern: ab so vielen Fans (null: geparkt)
                'fans' => BandCore::$live ? self::coreFans($key) : null,
            ];
        }
        return $out;
    }

    /** Neuer Kern: ab wie vielen Fans eine Tournee dieser Region geht (null: geparkt). */
    private static function coreFans(string $region): ?int
    {
        if (in_array($region, Balance::CORE_TOUR['parked'], true)) {
            return null;
        }
        return Balance::CORE_STAGES[Balance::CORE_TOUR['stage'][Balance::TOUR['regions'][$region]['vehicle']]];
    }

    /** Neuer Kern: warum die Band diese Tournee noch nicht machen kann (null: sie kann). */
    private static function coreBlocked(string $region, array $band): ?string
    {
        $need = self::coreFans($region);
        if ($need === null) {
            return sprintf('Die %s kommt später.', Balance::TOUR['regions'][$region]['name']);
        }
        $fans = (float) $band['fans'];
        if ($fans < $need) {
            return sprintf('Für die %s braucht die Band %s Fans (hat %s).', Balance::TOUR['regions'][$region]['name'], number_format($need, 0, ',', '.'), number_format($fans, 0, ',', '.'));
        }
        return null;
    }

    /** Ist die Band auf Tournee? Dann geht, was nur zu Hause geht, nicht (Text für die Ablehnung, sonst null). */
    public static function away(Db $db, ?int $bandId): ?string
    {
        if ($bandId === null) {
            return null;
        }
        $tour = self::active($db, $bandId);
        return $tour === null ? null : 'Die Band ist auf Tournee. Das geht erst wieder zu Hause.';
    }

    public static function assertHome(Db $db, ?int $bandId): void
    {
        $why = self::away($db, $bandId);
        if ($why !== null) {
            throw new ApiError($why);
        }
    }

    /** Unterwegs probt die Band im Fahrzeug: dessen Werte statt der des Proberaums (null = zu Hause). */
    public static function rehearsalRules(Db $db, array $band): ?array
    {
        $tour = self::active($db, $band['id']);
        if ($tour === null) {
            return null;
        }
        $kind = $tour['data']['vehicle'];
        return self::vehicle($kind, max(1, (int) $band[$kind]));
    }

    // ---------- Fahrzeuge ----------

    /** Fahrzeug kaufen oder eine Stufe ausbauen (Bus erst ab Europa-Ruhm, Flugzeug ab Übersee-Ruhm). */
    public static function buyVehicle(Repo $repo, int $bandId, string $kind): array
    {
        if (!isset(Balance::VEHICLES[$kind])) {
            throw new ApiError('Dieses Fahrzeug gibt es nicht.');
        }
        return $repo->db->transaction(static function () use ($repo, $bandId, $kind): array {
            $band = $repo->band($bandId);
            $members = $repo->members($bandId);
            $level = (int) $band[$kind];
            $next = Balance::VEHICLES[$kind][$level] ?? null;
            if ($next === null) {
                throw new ApiError($kind === 'bus' ? 'Der Bus ist schon voll ausgebaut.' : 'Das Flugzeug ist schon voll ausgebaut.');
            }
            if (BandCore::$live) {
                // Neuer Kern: erst ab der Ruhmstufe der ersten Tournee damit
                $need = Balance::CORE_STAGES[Balance::CORE_TOUR['stage'][$kind]];
                if (BandCore::fans($repo->db, $bandId) < $need) {
                    throw new ApiError(sprintf('%s lohnt sich erst ab %s Fans: Vorher geht die Band damit nicht auf Tournee.', $kind === 'bus' ? 'Ein Bus' : 'Ein Flugzeug', number_format($need, 0, ',', '.')));
                }
            }
            $need = min(array_column(array_filter(Balance::TOUR['regions'], static fn ($r) => $r['vehicle'] === $kind), 'fame'));
            if (!BandCore::$live && BandCalc::bandFame($band, $members) < $need) {
                throw new ApiError(sprintf('%s lohnt sich erst ab %s Ruhm: Vorher ist die Band für Tourneen damit nicht bekannt genug.', $kind === 'bus' ? 'Ein Bus' : 'Ein Flugzeug', number_format($need, 0, ',', '.')));
            }
            $repo->spend($next['cost'], sprintf('%s für %s', $next['name'], $band['name']));
            $repo->db->exec("UPDATE bands SET $kind = $kind + 1 WHERE id = ?", [$bandId]);
            $repo->log('band', sprintf($level === 0 ? '%s hat jetzt einen eigenen %s.' : '%s fährt jetzt im %s.', $band['name'], $next['name']), $bandId);
            return $next;
        });
    }

    // ---------- Anordnungen ----------

    /** Los geht's: Region und Länge wählen, das erste Land wird ausgewürfelt, die Band reist hin. */
    public static function start(Repo $repo, int $bandId, string $region, int $stations): array
    {
        $db = $repo->db;
        return $db->transaction(static function () use ($repo, $db, $bandId, $region, $stations): array {
            $now = Clock::now();
            $r = Balance::TOUR['regions'][$region] ?? throw new ApiError('Diese Tournee gibt es nicht.');
            $band = $repo->band($bandId);
            $members = $repo->members($bandId);
            if (self::active($db, $bandId) !== null) {
                throw new ApiError('Die Band ist schon auf Tournee.');
            }
            $countries = self::countriesOf($region);
            if ($r['lengths'] === null) {
                $stations = count($countries);
            } elseif (!in_array($stations, $r['lengths'], true)) {
                throw new ApiError('So lang kann diese Tournee nicht sein.');
            }
            $blocker = Activities::blocker($band, $members, $now);
            if ($blocker !== null) {
                throw new ApiError($blocker);
            }
            if ($band['records'] < 1) {
                throw new ApiError('Auf Tournee geht es erst mit einer Platte: Die Band braucht Lieder für die Auftritte.');
            }
            $option = array_column(self::options($band, $members), null, 'key')[$region];
            if ($option['blocked'] !== null) {
                throw new ApiError($option['blocked']);
            }
            $refuser = Mood::refuser($members, self::HOME);
            if ($refuser !== null) {
                throw new ApiError(sprintf('%s hat auf eine Tournee gerade keine Lust.', $refuser['first_name']));
            }
            // Angemeldet für Battle oder Festival: Eine Tournee dauert länger.
            Events::assertFree($db, $bandId, PHP_INT_MAX);
            $tour = ['id' => 0, 'state' => 'travel', 'stations' => $stations, 'country' => null, 'deadline' => 0,
                'data' => ['region' => $region, 'vehicle' => $r['vehicle'], 'legs' => [], 'visits' => [], 'roll' => null, 'spent' => 0, 'earned' => 0]];
            $to = self::roll($tour);
            $cost = self::legCost(self::km('home', $to), count($members));
            if ($repo->label()['money'] < $cost) {
                $c = self::place($to);
                throw new ApiError(sprintf('Ausgewürfelt: %s. Die Reise nach %s kostet %d ₲, so viel hat das Label gerade nicht.', $c['name'], $c['city'], $cost));
            }
            $tour['id'] = $db->insert('tours', [
                'label_id' => $repo->labelId, 'band_id' => $bandId, 'state' => 'travel', 'stations' => $stations, 'country' => $to,
                'deadline' => 0, 'started_at' => $now, 'data' => json_encode($tour['data']),
            ]);
            $repo->log('tour', sprintf('%s geht auf %s (%d %s). Erstes Ziel: %s.', $band['name'], $r['name'], $stations, $stations === 1 ? 'Land' : 'Länder', self::place($to)['name']), $bandId);
            return self::depart($repo, $tour, $band, $members, 'home', $to, $cost, $now);
        });
    }

    /** Im Land angekommen: Der Auftritt in der nächsten Stadt beginnt (Ort nach Ruhm, auf der Welttournee das Stadion). */
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
            $blocker = Activities::blocker($band, $members, $now);
            if ($blocker !== null) {
                throw new ApiError($blocker);
            }
            $city = self::nextCity($tour);
            $gear = $repo->gearByMusician();
            $sound = BandCalc::bandSound($band, $members, $gear);
            [$manager, $level] = $repo->bandManager($band);
            $c = Balance::TOUR['match'];
            $abroad = Balance::TOUR['abroad'];
            $country = self::place($tour['country']);
            $match = self::match($tour['country'], $band, $members);
            if (BandCore::$live) {
                return self::coreShow($repo, $tour, $band, $members, $gear, $sound, $manager, $level, $city, $country, $match, $now);
            }
            $venue = self::venue($tour, BandCalc::bandFame($band, $members));
            $songs = min($venue['songs'], Gigs::songsAvailable($band));
            if ($songs < Balance::GIG_MIN_SONGS) {
                throw new ApiError(sprintf('Für einen Auftritt braucht %s mindestens %d Songs (hat %d).', Bands::who($members, true), Balance::GIG_MIN_SONGS, $songs));
            }
            $negotiator = BandCalc::managerBonus($manager, $level, 'verhandler', Balance::NEGOTIATOR_BONUS);
            // Im Ausland kennt noch niemand die Lieder (keine Abnutzung wie bei Gigs zu Hause), und es kommt neues Publikum.
            $gage = (int) round(BandCalc::gagePerSong($venue, $negotiator, count($members)) * $songs * $abroad['money'] * ($match ? $c['money'] : 1.0));
            $sim = Performance::simulate(array_map(static fn ($m) => Activities::player($m, $gear), $members), $sound, $venue['expect'] * ($match ? $c['expect'] : 1.0), $songs);
            $fame = 0.0;
            for ($i = 0; $i < $songs; $i++) {
                $fame += $venue['fame'] * BandCalc::soundFactor($sound) * $sim['perf'] * Rng::float(0.9, 1.1);
            }
            $fame *= $abroad['fame'] * ($match ? $c['fame'] : 1.0) * (1 + BandCalc::managerBonus($manager, $level, 'prprofi', Balance::PR_BONUS));
            $endsAt = $now + $songs * Balance::SONG_SECONDS;
            $db->exec('UPDATE bands SET busy_until = ?, released = 1 WHERE id = ?', [$endsAt, $bandId]);
            $tour['state'] = 'show';
            self::save($db, $tour);
            return (new Activities($repo))->insertBand($bandId, self::GIG, $now, $endsAt, [
                'title' => sprintf('Tournee: %s, %s (%s)', $city, $country['name'], $venue['name']),
                'songs' => $songs,
                'timeline' => $sim['timeline'],
                'reward' => [
                    'money' => $gage,
                    'fameGains' => BandCalc::splitFame($members, $fame, $gear, true),
                    'chartBoost' => round($songs * Balance::CHART_GIG_BOOST, 3),
                    // Bühne des Landes (stage-world.js); Größe und Gage richten sich nach dem Ort nach Ruhm.
                    'venue' => 'tour-' . $tour['country'],
                    'size' => $venue['key'],
                    'meter' => $sim['meter'],
                    'drops' => [],
                    // first: erster Auftritt im Land (nur der zählt für die Laune, siehe Activities::applyBand)
                    'tour' => ['id' => $tour['id'], 'country' => $tour['country'], 'city' => $city, 'match' => $match, 'first' => !self::playedHere($tour)],
                ],
            ]);
        });
    }

    /**
     * Neuer Kern: Auftritt auf Tournee wie zu Hause (Fans, Hype, Frische, Ort wählt das Spiel), nur mit Halle und Arena. Passt das
     * Lieblingsgenre des Landes, gibt es mehr Geld und mehr neue Fans; Manager wie zu Hause.
     */
    private static function coreShow(Repo $repo, array $tour, array $band, array $members, array $gear, float $sound, ?array $manager, float $level, string $city, array $country, bool $match, int $now): array
    {
        $db = $repo->db;
        $c = Balance::TOUR['match'];
        $preview = BandCore::preview($repo, $band, true, $now);
        $songs = Balance::GIG_BOOKING['songs'][$preview['venue']] ?? Balance::GIG_MIN_SONGS;
        $expect = Balance::CORE_SOUND_EXPECT[BandCore::stage($db, $band['id'])] * ($match ? $c['expect'] : 1.0);
        $sim = Performance::simulate(array_map(static fn ($m) => Activities::player($m, $gear), $members), $sound, $expect, $songs);
        $gig = BandCore::startGig($repo, $band, $sim['meter'], true, $now);
        $gage = (int) round($gig['earnings'] * (1 + BandCalc::managerBonus($manager, $level, 'verhandler', Balance::NEGOTIATOR_BONUS)) * ($match ? $c['money'] : 1.0));
        $newFans = $gig['newFans'] * (1 + BandCalc::managerBonus($manager, $level, 'prprofi', Balance::PR_BONUS)) * ($match ? $c['fame'] : 1.0);
        $endsAt = $now + $songs * Balance::SONG_SECONDS;
        $db->exec('UPDATE bands SET busy_until = ?, released = 1 WHERE id = ?', [$endsAt, $band['id']]);
        $tour['state'] = 'show';
        self::save($db, $tour);
        return (new Activities($repo))->insertBand($band['id'], self::GIG, $now, $endsAt, [
            'title' => sprintf('Tournee: %s, %s (%s)', $city, $country['name'], Balance::CORE_VENUES[$gig['venue']]['name']),
            'songs' => $songs,
            'timeline' => $sim['timeline'],
            'reward' => [
                'money' => $gage,
                'fameGains' => [],
                'chartBoost' => 0,
                'venue' => 'tour-' . $tour['country'],
                'size' => $gig['venue'],
                'meter' => $sim['meter'],
                'drops' => [],
                'core' => ['tickets' => round($gig['tickets'], 2), 'size' => $gig['size'], 'newFans' => round($newFans, 2), 'demand' => round($gig['demand'], 2)],
                'tour' => ['id' => $tour['id'], 'country' => $tour['country'], 'city' => $city, 'match' => $match, 'first' => !self::playedHere($tour)],
            ],
        ]);
    }

    /** Ort eines Auftritts: der Ort nach Ruhm (bis zur Arena), mit Länge in Songs; auf der Welttournee ab Stadion-Ruhm das Stadion. */
    private static function venue(array $tour, float $fame): array
    {
        $stadium = Balance::TOUR['stadium'];
        if (!empty(Balance::TOUR['regions'][$tour['data']['region']]['stadium']) && $fame >= $stadium['minFame']) {
            return $stadium + ['progress' => 0.5];
        }
        $venue = BandCalc::venue($fame);
        return $venue + ['songs' => Balance::GIG_BOOKING['songs'][$venue['key']]];
    }

    /** Wie viele Länder die Tournee schon angesteuert hat (das aktuelle mitgezählt). */
    private static function stationOf(array $tour): int
    {
        return count(array_diff(array_unique(array_column($tour['data']['legs'], 'to')), ['home']));
    }

    /** Nächste Stadt im Land, in der die Band noch nicht gespielt hat. */
    private static function nextCity(array $tour): string
    {
        $played = self::playedHere($tour);
        $open = array_values(array_diff(self::citiesOf($tour, (string) $tour['country']), $played));
        return $open[0] ?? throw new ApiError('In diesem Land hat die Band schon überall gespielt.');
    }

    /** Städte, in denen die Band im aktuellen Land schon gespielt hat. */
    private static function playedHere(array $tour): array
    {
        return array_column(array_filter($tour['data']['visits'], static fn ($v) => $v['country'] === $tour['country']), 'city');
    }

    /**
     * Weiterreisen: Das nächste Land wird ausgewürfelt (auch wenn noch Städte offen sind). Reicht das Geld nicht für die Reise, bleibt das Ziel
     * stehen (nochmal versuchen oder nach Hause).
     * @return array{roll: string, cost: int, short: bool, activity: ?array}
     */
    public static function next(Repo $repo, int $bandId): array
    {
        $db = $repo->db;
        return $db->transaction(static function () use ($repo, $db, $bandId): array {
            $now = Clock::now();
            $tour = self::active($db, $bandId);
            if ($tour === null || !in_array($tour['state'], ['arrived', 'after'], true)) {
                throw new ApiError('Die Band kann gerade nicht weiterreisen.');
            }
            if (self::stationOf($tour) >= $tour['stations']) {
                throw new ApiError('Das war das letzte Land. Jetzt geht es nach Hause.');
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

    /** Nach Hause (auch mitten in der Tournee). Die Heimreise ist bezahlt. $at: wann es losgeht (von selbst nach zu langem Warten). */
    public static function home(Repo $repo, int $bandId, ?int $at = null): array
    {
        $db = $repo->db;
        return $db->transaction(static function () use ($repo, $db, $bandId, $at): array {
            $tour = self::active($db, $bandId);
            if ($tour === null || !in_array($tour['state'], ['arrived', 'after'], true)) {
                throw new ApiError('Die Band kann gerade nicht nach Hause.');
            }
            $band = $repo->band($bandId);
            return self::depart($repo, $tour, $band, $repo->members($bandId), $tour['country'], 'home', 0, $at ?? Clock::now());
        });
    }

    /** Eine Strecke reisen: Kosten, Reisezeit nach Fahrzeug. Die Band ist unterwegs nicht beschäftigt (sie lebt im Fahrzeug). */
    private static function depart(Repo $repo, array $tour, array $band, array $members, string $from, string $to, int $cost, int $at): array
    {
        $db = $repo->db;
        $km = self::km($from, $to);
        $kind = $tour['data']['vehicle'];
        $vehicle = self::vehicle($kind, max(1, (int) $band[$kind]));
        $until = $at + self::travelSeconds($km, $kind, max(1, (int) $band[$kind]));
        $place = self::place($to);
        $by = $kind === 'bus' ? 'Busfahrt' : 'Flug';
        if ($cost > 0) {
            $repo->spend($cost, sprintf('Tournee: %s nach %s', $by, $place['city']));
        }
        $tour['data']['legs'][] = ['from' => $from, 'to' => $to, 'at' => $at, 'until' => $until, 'km' => (int) round($km), 'cost' => $cost];
        $tour['data']['spent'] += $cost;
        $tour['state'] = $to === 'home' ? 'home' : 'travel';
        $tour['country'] = $to === 'home' ? $tour['country'] : $to;
        $tour['deadline'] = 0;
        self::save($db, $tour);
        return (new Activities($repo))->insertBand($band['id'], $to === 'home' ? self::HOME : self::TRAVEL, $at, $until, [
            'title' => $to === 'home' ? sprintf('Heimreise nach %s (%s)', $place['city'], $vehicle['name']) : sprintf('%s nach %s (%s)', $by, $place['name'], $vehicle['name']),
            'songs' => 0,
            'reward' => ['tour' => ['id' => $tour['id'], 'from' => $from, 'to' => $to, 'vehicle' => $kind]],
        ]);
    }

    // ---------- Gutschrift (Activities::settleDue) ----------

    /** Angekommen: Meldung, und die Band wartet auf ihren ersten Auftritt im Land. */
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
        $place = self::place($to);
        $cities = self::citiesOf($tour, $to);
        $repo->log('tour', sprintf('%s ist in %s angekommen. %s', $band['name'], $place['name'], count($cities) === 1
            ? sprintf('Jetzt kann der Auftritt in %s losgehen!', $cities[0])
            : sprintf('%d Auftritte warten: %s.', count($cities), implode(', ', $cities))), $tour['band_id']);
        return [
            'country' => $to, 'match' => self::match($to, $band, $members), 'genre' => $place['genre'], 'cities' => $cities,
            'station' => self::stationOf($tour), 'stations' => $tour['stations'],
        ];
    }

    /** Nach dem Auftritt (Activities::applyBand): Ergebnis merken; noch offene Städte oder weiter bzw. nach Hause. */
    public static function afterGig(Repo $repo, int $bandId, array $info, array $result): array
    {
        $tour = self::active($repo->db, $bandId);
        if ($tour === null || $tour['id'] !== (int) ($info['id'] ?? 0)) {
            return $info;
        }
        $city = $info['city'] ?? self::place($info['country'])['city'];
        $tour['data']['visits'][] = ['country' => $info['country'], 'city' => $city, 'match' => (bool) $info['match'], 'fame' => $result['fame'] ?? 0, 'money' => $result['money'], 'meter' => $result['meter'],
            // Neuer Kern: neue Fans und Zuschauer statt Ruhm
            'fans' => $result['fans'] ?? null, 'tickets' => $result['tickets'] ?? null];
        $tour['data']['earned'] += (int) $result['money'];
        $left = array_values(array_diff(self::citiesOf($tour, $info['country']), self::playedHere($tour)));
        $tour['state'] = $left ? 'arrived' : 'after';
        $tour['deadline'] = Clock::now() + Balance::TOUR['wait'];
        self::save($repo->db, $tour);
        $station = self::stationOf($tour);
        return $info + [
            'city' => $city, 'next' => $left[0] ?? null, 'citiesLeft' => count($left),
            'station' => $station, 'stations' => $tour['stations'], 'last' => !$left && $station >= $tour['stations'],
            'world' => self::worldAward($repo, $bandId),
            'played' => count(self::played($repo->db, $repo->labelId)[$bandId] ?? []),
        ];
    }

    /** In welchen Ländern die Bands eines Labels schon auf Tournee gespielt haben (Band-ID => Länder). */
    public static function played(Db $db, int $labelId): array
    {
        $out = [];
        foreach ($db->all('SELECT band_id, data FROM tours WHERE label_id = ?', [$labelId]) as $row) {
            foreach ((json_decode($row['data'], true) ?: [])['visits'] ?? [] as $v) {
                $out[(int) $row['band_id']][$v['country']] = true;
            }
        }
        return array_map('array_keys', $out);
    }

    /** In allen Ländern gespielt: einmal je Band eine besondere Auszeichnung für den Proberaum. Liefert true, wenn sie gerade dazukam. */
    private static function worldAward(Repo $repo, int $bandId): bool
    {
        $played = self::played($repo->db, $repo->labelId)[$bandId] ?? [];
        if (count(array_intersect($played, array_keys(Catalog::COUNTRIES))) < count(Catalog::COUNTRIES)
            || $repo->db->value("SELECT id FROM awards WHERE band_id = ? AND kind = 'world'", [$bandId]) !== null) {
            return false;
        }
        $repo->db->insert('awards', [
            'label_id' => $repo->labelId, 'band_id' => $bandId, 'kind' => 'world', 'place' => 1,
            'title' => sprintf('Weltenbummler: in allen %d Ländern gespielt', count(Catalog::COUNTRIES)), 'created_at' => Clock::now(),
        ]);
        $repo->log('tour', sprintf('%s hat in allen %d Ländern gespielt! Dafür steht jetzt ein goldener Globus im Proberaum.', $repo->db->value('SELECT name FROM bands WHERE id = ?', [$bandId]) ?? 'Die Band', count(Catalog::COUNTRIES)), $bandId);
        return true;
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
        $countries = array_values(array_unique(array_column($visits, 'country')));
        if ($members) {
            Mood::bond($repo, $members, self::HOME);
            $c = Balance::TOUR;
            Mood::lift($repo, $repo->members($tour['band_id']), min($c['liftMax'], $c['lift'] * count($countries)));
            Mood::react($repo, $repo->members($tour['band_id']), self::HOME);
        }
        $name = $repo->db->value('SELECT name FROM bands WHERE id = ?', [$tour['band_id']]) ?? '';
        $repo->log('tour', sprintf(
            '%s ist zurück von der %s: %d %s (%s).',
            $name, Balance::TOUR['regions'][$tour['data']['region']]['name'], count($visits), count($visits) === 1 ? 'Auftritt' : 'Auftritte',
            implode(', ', array_map(static fn ($c) => self::place($c)['name'], $countries)) ?: 'keine Station gespielt'
        ), $tour['band_id']);
        return [
            'region' => $tour['data']['region'],
            'visits' => $visits,
            'fame' => round(array_sum(array_column($visits, 'fame')), 2),
            'fans' => BandCore::$live ? round(array_sum(array_map(static fn ($v) => (float) ($v['fans'] ?? 0), $visits)), 1) : null,
            'earned' => (int) $tour['data']['earned'],
            'spent' => (int) $tour['data']['spent'],
            'together' => count($members) > 1,
        ];
    }

    /** Wer zu lange nicht weitergemacht hat, reist von selbst nach Hause (beim nächsten Spielstand). */
    public static function settle(Repo $repo, int $now): void
    {
        foreach ($repo->db->all("SELECT band_id, deadline FROM tours WHERE label_id = ? AND state IN ('arrived', 'after') AND deadline > 0 AND deadline <= ?", [$repo->labelId, $now]) as $row) {
            self::home($repo, (int) $row['band_id'], (int) $row['deadline']);
            $repo->log('tour', sprintf('%s hat zu lange gewartet und reist nach Hause.', $repo->db->value('SELECT name FROM bands WHERE id = ?', [(int) $row['band_id']]) ?? 'Die Band'), (int) $row['band_id']);
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
            $country = $t['country'];
            $out[$t['band_id']] = [
                'id' => $t['id'],
                'state' => $t['state'],
                'region' => $t['data']['region'],
                'vehicle' => $t['data']['vehicle'],
                'stations' => $t['stations'],
                'station' => self::stationOf($t),
                'country' => $country,
                // Städte im aktuellen Land und welche schon gespielt sind
                'cities' => $country === null ? [] : self::citiesOf($t, $country),
                'playedHere' => $country === null ? [] : self::playedHere($t),
                'deadline' => $t['deadline'],
                'legs' => $t['data']['legs'],
                'visits' => $t['data']['visits'],
                'spent' => $t['data']['spent'],
                'earned' => $t['data']['earned'],
                // Ausgewürfelt, aber das Geld reichte noch nicht
                'roll' => $roll === null ? null : ['country' => $roll, 'cost' => self::legCost(self::km((string) $country, $roll), count($members[$t['band_id']] ?? []))],
            ];
        }
        return $out;
    }
}
