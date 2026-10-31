<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;
use Signed\Db;
use Signed\Rng;
use Signed\Game\Core\Fans;

/**
 * Automatische und passive Aktionen von Bands und einzelnen Musikern: Straßenmusik, Open Stage, Gigs,
 * Bandprobe, Promo und Jam-Café. Das Ergebnis wird beim Start berechnet und gutgeschrieben, sobald die Zeit abgelaufen ist.
 */
final class Activities
{
    public const BAND_TYPES = ['busking', 'openstage', 'gig', 'rehearsal'];
    public const SOLO_TYPES = ['busking', 'jam'];

    public function __construct(private Repo $repo)
    {
    }

    /** Grund, warum die Band gerade nichts starten kann, sonst null. */
    public static function blocker(array $band, array $members, int $now): ?string
    {
        if ($band['busy_until'] > $now) {
            return Bands::who($members) . ' ist gerade beschäftigt.';
        }
        if (count($members) < Balance::MIN_MEMBERS) {
            return sprintf('Die Band braucht mindestens %d Musiker.', Balance::MIN_MEMBERS);
        }
        foreach ($members as $m) {
            if ($m['busy_until'] > $now) {
                return sprintf('%s ist gerade unterwegs.', $m['first_name']);
            }
        }
        return null;
    }

    public function start(int $bandId, string $type, int $songs = 0, ?string $venue = null): array
    {
        if (!in_array($type, self::BAND_TYPES, true)) {
            throw new ApiError('Unbekannte Aktivität.');
        }
        $db = $this->repo->db;
        return $db->transaction(function () use ($db, $bandId, $type, $songs, $venue): array {
            $now = Clock::now();
            $band = $this->repo->band($bandId);
            $members = $this->repo->members($bandId);
            $blocker = self::blocker($band, $members, $now);
            if ($blocker !== null) {
                throw new ApiError($blocker);
            }
            // Auf Tournee probt die Band im Fahrzeug; alles andere geht erst wieder zu Hause.
            if ($type !== 'rehearsal') {
                Tours::assertHome($db, $bandId);
            }
            $refuser = Mood::refuser($members, $type);
            if ($refuser !== null) {
                throw new ApiError($refuser['first_name'] . ' hat darauf gerade keine Lust. Eine Anordnung, die der Band gefällt, hebt zuerst die Stimmung.');
            }
            $gear = $this->repo->gearByMusician();
            $sound = BandCalc::bandSound($band, $members, $gear);
            $fame = BandCalc::bandFame($band, $members);
            [$manager, $level] = $this->repo->bandManager($band);
            $venueKey = null;
            $gage = 0;
            $skill = [];
            $coreGig = null;

            $rehearsal = null;
            if ($type === 'rehearsal') {
                [$duration, $limit] = Mood::rehearsalLimits($members, Rooms::here($this->repo, $band, count($members))['lust'], $gear);
                $songs = max(1, intdiv($duration, Balance::REHEARSAL['songSpan']));
                $title = 'Bandprobe';
                $sim = ['timeline' => self::rehearsalTimeline($members, $songs, $duration), 'meter' => 0.0, 'perf' => 0.0];
                $fameTotal = 0.0;
                $rehearsal = ['done' => 0, 'limit' => $limit, 'credited' => (object) [], 'levelUps' => []];
            } else {
                if ($type === 'busking') {
                    $cfg = Balance::BUSKING;
                    $songs = $cfg['songs'];
                    $fameBase = $cfg['fame'] * BandCalc::falloff($fame, $cfg['falloff'], $cfg['minFactor']);
                    $expect = $cfg['expect'];
                    $title = 'Straßenmusik: ' . Rng::pick(Catalog::STREETS);
                } elseif ($type === 'openstage') {
                    $cfg = Balance::OPENSTAGE;
                    $songs = $cfg['songs'];
                    $fameBase = $cfg['fame'] * BandCalc::falloff($fame, $cfg['falloff']);
                    $expect = $cfg['expect'];
                    $title = 'Open Stage: ' . Rng::pick(Catalog::OPEN_STAGES);
                } elseif ($type === 'gig' && BandCore::$live) {
                    // Neuer Kern: nur „Auftreten“. Das Spiel nimmt die kleinste Größe, in die alle passen; keine Kosten,
                    // das Label verdient je Zuschauer. Wie gut die Show ist, entscheidet nur, wie viele neue Fans es werden.
                    $coreGig = BandCore::preview($this->repo, $band);
                    $venueKey = $coreGig['venue'];
                    $songs = Balance::GIG_BOOKING['songs'][$venueKey];
                    $fameBase = 0.0;
                    $expect = Balance::CORE_SOUND_EXPECT[Fans::stage(BandCore::fans($db, $bandId))];
                    $title = sprintf('Auftritt: %s (%s)', Rng::pick(Catalog::VENUE_NAMES[$venueKey]), Balance::CORE_VENUES[$venueKey]['name']);
                } elseif ($venue !== null) {
                    // Auftritt buchen: fester Ort mit Kosten; die Gage hängt am Abend (siehe Gigs::gage)
                    $options = array_column(Gigs::options($this->repo, $band, $members)['options'], null, 'key');
                    $opt = $options[$venue] ?? throw new ApiError('Diesen Ort kann die Band nicht buchen.');
                    if ($opt['blocked'] !== null) {
                        throw new ApiError($opt['blocked']);
                    }
                    // Die Rechnung kommt nach dem Auftritt und wird mit der Gage verrechnet; vorher nur, was die Gage sicher nicht deckt.
                    $this->repo->spend($opt['deposit'], sprintf('Auftritt: %s (Anzahlung)', $opt['place']));
                    $songs = $opt['songs'];
                    $venueRow = array_values(array_filter(Balance::VENUES, static fn ($v) => $v['key'] === $venue))[0];
                    $negotiator = BandCalc::managerBonus($manager, $level, 'verhandler', Balance::NEGOTIATOR_BONUS);
                    $draw = BandCalc::freshness($band['gigs_since_record']);
                    $booking = ['venue' => $venueRow, 'base' => (int) round(BandCalc::gagePerSong($venueRow + ['progress' => 0.5], $negotiator, count($members)) * $songs * $draw), 'claim' => $opt['claim'], 'cost' => $opt['cost'], 'deposit' => $opt['deposit']];
                    $fameBase = $venueRow['fame'] * $draw;
                    $expect = $venueRow['expect'];
                    $venueKey = $venue;
                    $title = sprintf('Auftritt: %s (%s)', $opt['place'], $venueRow['name']);
                    // Gespielt werden aufgenommene und geschriebene Songs; geschriebene bleiben für die nächste Platte erhalten.
                    Gigs::moodOnBooking($this->repo, $members, $opt['claim']);
                } else {
                    if ($band['records'] < 1) {
                        throw new ApiError('Richtige Gigs gibt es erst, wenn die Band eine Platte produziert hat.');
                    }
                    $max = BandCalc::maxGigSongs($band['songs_recorded'], $level);
                    $min = min($max, Balance::GIG_MIN_SONGS);
                    if ($songs < $min || $songs > $max) {
                        throw new ApiError($min === $max
                            ? sprintf('Ein Gig dieser Band hat %d Lieder.', $max)
                            : sprintf('Ein Gig dieser Band kann %d bis %d Lieder lang sein.', $min, $max));
                    }
                    $venue = BandCalc::venue($fame);
                    $negotiator = BandCalc::managerBonus($manager, $level, 'verhandler', Balance::NEGOTIATOR_BONUS);
                    // Frisches Material macht Gigs voller; das Publikum kennt die Lieder sonst schon.
                    $draw = BandCalc::freshness($band['gigs_since_record']);
                    $gage = (int) round(BandCalc::gagePerSong($venue, $negotiator, count($members)) * $songs * $draw);
                    $fameBase = $venue['fame'] * $draw;
                    $expect = $venue['expect'];
                    $venueKey = $venue['key'];
                    $title = sprintf('Gig: %s (%s)', Rng::pick(Catalog::VENUE_NAMES[$venue['key']]), $venue['name']);
                }
                $sim = Performance::simulate(array_map(static fn ($m) => self::player($m, $gear), $members), $sound, (float) $expect, $songs);
                if ($coreGig !== null) {
                    // Live-Qualität = Publikumsleiste am Ende des Live-Tickers
                    $coreGig = BandCore::startGig($this->repo, $band, $sim['meter']);
                    $gage = (int) round($coreGig['earnings']);
                }
                if ($type === 'busking') {
                    $gage = self::hat($this->repo, $sim['perf']);
                }
                if (isset($booking)) {
                    $gage = Gigs::gage($booking['base'], Gigs::fill($sound, $booking['venue'], $fame), $sim['perf']);
                }
                // Ruhm: Größe des Auftritts × Sound × Publikum, plus etwas Zufall.
                $fameTotal = 0.0;
                for ($i = 0; $i < $songs; $i++) {
                    $fameTotal += $fameBase * BandCalc::soundFactor($sound) * $sim['perf'] * Rng::float(0.9, 1.1);
                }
                $fameTotal *= 1 + BandCalc::managerBonus($manager, $level, 'prprofi', Balance::PR_BONUS);
            }

            $drops = [];
            if ($type === 'gig' && $sim['meter'] >= Balance::FAN_MUSICIAN['minMeter']
                && Rng::chance(Balance::FAN_MUSICIAN['chance']) && !FanMusician::onCooldown($this->repo)) {
                $drops[] = ['kind' => 'musician', 'venue' => $venueKey];
            }
            if ($type === 'openstage') {
                if (Rng::chance(Balance::OPENSTAGE['managerChance'])) {
                    $drops[] = ['kind' => 'manager'];
                } elseif (Rng::chance(Balance::OPENSTAGE['fanChance'])) {
                    $drops[] = ['kind' => 'fan', 'value' => Rng::int(...Balance::OPENSTAGE['fanValue'])];
                }
            }

            $endsAt = $now + ($rehearsal !== null ? $duration : $songs * Balance::SONG_SECONDS);
            Events::assertFree($db, $bandId, $endsAt);
            $claimed = $db->exec('UPDATE bands SET busy_until = ?, released = 1 WHERE id = ? AND busy_until <= ?', [$endsAt, $bandId, $now]);
            if ($claimed === 0) {
                throw new ApiError(Bands::who($members) . ' ist gerade beschäftigt.');
            }
            return $this->insert($bandId, null, $type, $now, $endsAt, [
                'title' => $title,
                'songs' => $songs,
                'timeline' => $sim['timeline'],
                'reward' => [
                    'money' => $gage,
                    // Rest der Rechnung, nach dem Auftritt von der Gage bezahlt (deckt die Gage immer).
                    'bill' => isset($booking) ? $booking['cost'] - $booking['deposit'] : 0,
                    'fameGains' => BandCalc::splitFame($members, $fameTotal, $gear, $rehearsal === null),
                    'skill' => $skill,
                    'rehearsal' => $rehearsal,
                    'meter' => $sim['meter'],
                    'drops' => $drops,
                    'venue' => $venueKey,
                    // Jedes gespielte Lied schiebt die neueste Platte in den Charts an – egal wo (nur im bisherigen Spiel).
                    'chartBoost' => $type === 'gig' && $coreGig === null ? round($songs * Balance::CHART_GIG_BOOST, 3) : 0,
                    // Neuer Kern: Zuschauer, Ortsgröße und neue Fans (gutgeschrieben nach dem Auftritt)
                    'core' => $coreGig === null ? null : [
                        'tickets' => round($coreGig['tickets'], 2), 'size' => $coreGig['size'], 'newFans' => $coreGig['newFans'], 'demand' => round($coreGig['demand'], 2),
                    ],
                ],
            ]);
        });
    }

    /** Ein Musiker ohne Band: Straßenmusik oder Jam-Café. */
    public function startSolo(int $musicianId, string $type): array
    {
        if (!in_array($type, self::SOLO_TYPES, true)) {
            throw new ApiError('Unbekannte Aktivität.');
        }
        $db = $this->repo->db;
        return $db->transaction(function () use ($db, $musicianId, $type): array {
            $now = Clock::now();
            $m = $this->repo->musician($musicianId);
            if ($m['band_id'] !== null) {
                throw new ApiError(sprintf('%s spielt in einer Band. Auftritte laufen über die Band.', $m['first_name']));
            }
            if ($m['busy_until'] > $now) {
                throw new ApiError(sprintf('%s ist gerade beschäftigt.', $m['first_name']));
            }
            $gear = $this->repo->gearByMusician();
            $sound = BandCalc::sound([$m], 0, $gear);
            if ($type === 'jam') {
                // Im Jam-Café geht es ums Gründen: ohne freien Band-Platz gar nicht erst hin.
                (new Bands($this->repo))->assertSlotFree();
                Jam::assertNotJammed($m);
                $songs = Balance::JAM['songs'];
                $fameTotal = Balance::JAM['fame'] * $songs;
                $expect = Balance::JAM['expect'];
                $title = 'Jam-Café: ' . Rng::pick(Catalog::JAM_CAFES);
            } else {
                $cfg = Balance::BUSKING;
                $songs = $cfg['songs'];
                $fameTotal = $cfg['fame'] * $songs * BandCalc::falloff($m['fame'], $cfg['falloff'], $cfg['minFactor']);
                $expect = $cfg['expect'];
                $title = 'Straßenmusik: ' . Rng::pick(Catalog::STREETS);
            }
            $sim = Performance::simulate([self::player($m, $gear)], $sound, (float) $expect, $songs);
            $fameTotal *= BandCalc::soundFactor($sound) * $sim['perf'];
            $hat = $type === 'busking' ? self::hat($this->repo, $sim['perf']) : 0;

            $endsAt = $now + $songs * Balance::SONG_SECONDS;
            $claimed = $db->exec('UPDATE musicians SET busy_until = ? WHERE id = ? AND busy_until <= ?', [$endsAt, $musicianId, $now]);
            if ($claimed === 0) {
                throw new ApiError(sprintf('%s ist gerade beschäftigt.', $m['first_name']));
            }
            return $this->insert(null, $musicianId, $type, $now, $endsAt, [
                'title' => $title,
                'songs' => $songs,
                'timeline' => $sim['timeline'],
                'reward' => [
                    'money' => $hat,
                    'fameGains' => BandCalc::splitFame([$m], $fameTotal, $gear, true),
                    'meter' => $sim['meter'],
                    'jam' => $type === 'jam',
                ],
            ]);
        });
    }

    /** Promo-Mission: ein Musiker ist eine Weile unterwegs und wird bekannter. Läuft offline. */
    /** Allein üben lassen: läuft von selbst (auch offline), bringt aber nur einen Teil einer Solo-Probe. */
    public function autoPractice(int $musicianId, int $slot): array
    {
        $db = $this->repo->db;
        return $db->transaction(function () use ($db, $musicianId, $slot): array {
            $now = Clock::now();
            $m = $this->repo->musician($musicianId);
            $slot = $slot === 2 && $m['instr2'] !== null ? 2 : 1;
            if ($m['busy_until'] > $now) {
                throw new ApiError(sprintf('%s ist gerade beschäftigt.', $m['first_name']));
            }
            if ($m['band_id'] !== null && $this->repo->band($m['band_id'])['busy_until'] > $now) {
                throw new ApiError(sprintf('Die Band von %s ist gerade beschäftigt.', $m['first_name']));
            }
            $type = $slot === 2 ? $m['instr2'] : $m['instr1'];
            $gain = BandCalc::practiceGain($m, $slot, 1.0, $this->repo->gearByMusician()[$m['id']][$type] ?? null) * Balance::AUTO_PRACTICE['share'];
            $endsAt = $now + Balance::AUTO_PRACTICE['seconds'];
            Events::assertFree($db, $m['band_id'], $endsAt);
            if ($db->exec('UPDATE musicians SET busy_until = ? WHERE id = ? AND busy_until <= ?', [$endsAt, $musicianId, $now]) === 0) {
                throw new ApiError(sprintf('%s ist gerade beschäftigt.', $m['first_name']));
            }
            return $this->insert(null, $musicianId, 'practice', $now, $endsAt, [
                'title' => sprintf('Übt allein: %s – %s', Bands::fullName($m), Catalog::INSTRUMENTS[$type]['name']),
                'songs' => 0,
                'reward' => ['slot' => $slot, 'skill' => round($gain, 4)],
            ]);
        });
    }

    public function promo(int $musicianId): array
    {
        $db = $this->repo->db;
        return $db->transaction(function () use ($db, $musicianId): array {
            $now = Clock::now();
            $m = $this->repo->musician($musicianId);
            if ($m['busy_until'] > $now) {
                throw new ApiError(sprintf('%s ist gerade beschäftigt.', $m['first_name']));
            }
            if ($m['band_id'] !== null && $this->repo->band($m['band_id'])['busy_until'] > $now) {
                throw new ApiError(sprintf('Die Band von %s ist gerade beschäftigt.', $m['first_name']));
            }
            $endsAt = $now + Balance::PROMO_SECONDS;
            Events::assertFree($db, $m['band_id'], $endsAt);
            $claimed = $db->exec('UPDATE musicians SET busy_until = ? WHERE id = ? AND busy_until <= ?', [$endsAt, $musicianId, $now]);
            if ($claimed === 0) {
                throw new ApiError(sprintf('%s ist gerade beschäftigt.', $m['first_name']));
            }
            return $this->insert(null, $musicianId, 'promo', $now, $endsAt, [
                'title' => 'Promo-Mission: ' . Bands::fullName($m),
                'songs' => 0,
                'reward' => ['fameGains' => [$m['id'] => round(self::promoFame($m, Balance::PROMO_FAME), 2)]],
            ]);
        });
    }

    /** Was eine Auszeit für diese Band kostet (je Mitglied). */
    public static function retreatCost(string $option, int $members): int
    {
        return Balance::RETREAT['options'][$option]['perMember'] * max(1, $members);
    }

    /** Auszeit: Die Band ist weg und erholt sich; danach geht es allen besser (auch aus dem Roten heraus). */
    public function retreat(int $bandId, string $option): array
    {
        $db = $this->repo->db;
        return $db->transaction(function () use ($db, $bandId, $option): array {
            $now = Clock::now();
            $cfg = Balance::RETREAT['options'][$option] ?? throw new ApiError('Diese Auszeit gibt es nicht.');
            $band = $this->repo->band($bandId);
            $members = $this->repo->members($bandId);
            $blocker = self::blocker($band, $members, $now);
            if ($blocker !== null) {
                throw new ApiError($blocker);
            }
            Tours::assertHome($db, $bandId);
            $endsAt = $now + $cfg['seconds'];
            Events::assertFree($db, $bandId, $endsAt);
            if ($db->exec('UPDATE bands SET busy_until = ? WHERE id = ? AND busy_until <= ?', [$endsAt, $bandId, $now]) === 0) {
                throw new ApiError(Bands::who($members) . ' ist gerade beschäftigt.');
            }
            $this->repo->spend(self::retreatCost($option, count($members)), sprintf('Auszeit: %s (%s)', $band['name'], $cfg['name']));
            $gains = [];
            foreach ($members as $m) {
                $db->update('musicians', ['busy_until' => $endsAt], ['id' => $m['id']]);
                $gains[$m['id']] = round($cfg['gain'] * Mood::traitMax($m, Balance::RETREAT['traits'], 1.0), 1);
            }
            return $this->insert($bandId, null, 'retreat', $now, $endsAt, [
                'title' => sprintf('%s: %s', $cfg['name'], $band['name']),
                'songs' => 0,
                'reward' => ['wellbeing' => $gains],
            ]);
        });
    }

    /** Band-Promo: Alle Mitglieder sind unterwegs und werden bekannter. */
    public function bandPromo(int $bandId): array
    {
        $db = $this->repo->db;
        return $db->transaction(function () use ($db, $bandId): array {
            $now = Clock::now();
            $band = $this->repo->band($bandId);
            $members = $this->repo->members($bandId);
            $blocker = self::blocker($band, $members, $now);
            if ($blocker !== null) {
                throw new ApiError($blocker);
            }
            Tours::assertHome($db, $bandId);
            $cfg = Balance::BAND_PROMO;
            $endsAt = $now + $cfg['seconds'];
            Events::assertFree($db, $bandId, $endsAt);
            if ($db->exec('UPDATE bands SET busy_until = ? WHERE id = ? AND busy_until <= ?', [$endsAt, $bandId, $now]) === 0) {
                throw new ApiError(Bands::who($members) . ' ist gerade beschäftigt.');
            }
            $fame = 0.0;
            foreach ($members as $m) {
                $fame += self::promoFame($m, $cfg['fame']);
                $db->update('musicians', ['busy_until' => $endsAt], ['id' => $m['id']]);
            }
            return $this->insert($bandId, null, 'bandpromo', $now, $endsAt, [
                'title' => 'Band-Promo: ' . $band['name'],
                'songs' => 0,
                'reward' => [
                    'fameGains' => BandCalc::splitFame($members, $fame, $this->repo->gearByMusician()),
                    'chartBoost' => Balance::CHART_PROMO_BOOST['band'],
                ],
            ]);
        });
    }

    /**
     * Promo abbrechen (z. B. aus Versehen gestartet): Musiker bzw. Band sind sofort wieder frei,
     * es gibt keine Belohnung. Auftritte laufen dagegen immer bis zum Ende.
     */
    public function cancelPromo(int $activityId): void
    {
        $db = $this->repo->db;
        $db->transaction(function () use ($db, $activityId): void {
            $row = $db->one(
                "SELECT * FROM activities WHERE id = ? AND label_id = ? AND settled = 0 AND type IN ('promo', 'bandpromo', 'practice', 'course', 'interview')",
                [$activityId, $this->repo->labelId]
            );
            if ($row === null) {
                throw new ApiError('Das läuft nicht mehr.');
            }
            $endsAt = (int) $row['ends_at'];
            if ($row['type'] === 'course') {
                // Kurs abgebrochen: Geld zurück, und er darf heute noch einmal hin.
                $visit = $db->one('SELECT id, price FROM course_visits WHERE activity_id = ?', [(int) $row['id']]);
                if ($visit !== null) {
                    $this->repo->earn((int) $visit['price'], 'Kurs abgesagt: Geld zurück');
                    $db->exec('DELETE FROM course_visits WHERE id = ?', [(int) $visit['id']]);
                }
            }
            $db->update('activities', ['settled' => 1, 'seen' => 1, 'ends_at' => Clock::now(), 'result' => json_encode(['cancelled' => true])], ['id' => (int) $row['id']]);
            if ($row['band_id'] !== null) {
                $db->exec('UPDATE bands SET busy_until = 0 WHERE id = ? AND busy_until = ?', [(int) $row['band_id'], $endsAt]);
                $db->exec('UPDATE musicians SET busy_until = 0 WHERE band_id = ? AND busy_until = ?', [(int) $row['band_id'], $endsAt]);
            } else {
                $db->exec('UPDATE musicians SET busy_until = 0 WHERE id = ? AND busy_until = ?', [(int) $row['musician_id'], $endsAt]);
            }
        });
    }

    /** Ergebnis gesehen: taucht nicht mehr als Benachrichtigung auf. */
    public function ack(int $activityId): void
    {
        $this->repo->db->exec(
            'UPDATE activities SET seen = 1 WHERE id = ? AND label_id = ? AND settled = 1',
            [$activityId, $this->repo->labelId]
        );
    }

    /** Schreibt alle abgelaufenen Aktionen gut. Ohne $labelId für alle Labels (z. B. vor den Charts). */
    public static function settleDue(Db $db, ?int $labelId = null, int $limit = 200): void
    {
        $sql = 'SELECT * FROM activities WHERE settled = 0 AND ends_at <= ?';
        $params = [Clock::now()];
        if ($labelId !== null) {
            $sql .= ' AND label_id = ?';
            $params[] = $labelId;
        }
        $sql .= ' ORDER BY ends_at LIMIT ' . $limit;

        foreach ($db->all($sql, $params) as $row) {
            $db->transaction(static function () use ($db, $row): void {
                if ($db->exec('UPDATE activities SET settled = 1 WHERE id = ? AND settled = 0', [$row['id']]) === 0) {
                    return;
                }
                $repo = new Repo($db, (int) $row['label_id']);
                $data = json_decode($row['data'], true) ?: [];
                $result = match (true) {
                    $row['type'] === 'promo' => self::applyPromo($repo, $row, $data),
                    $row['type'] === 'interview' => Inbox::settle($repo, $row, $data),
                    $row['type'] === 'rehearsal' => self::applyRehearsal($repo, $row),
                    $row['type'] === 'record' => Studio::settle($repo, $row, $data),
                    $row['type'] === Tours::TRAVEL => Tours::arrived($repo, $row, $data),
                    $row['type'] === Tours::HOME => Tours::homeArrived($repo, $row, $data),
                    $row['type'] === 'practice', $row['type'] === 'course' => self::applyPractice($repo, $row, $data),
                    $row['band_id'] === null => self::applySolo($repo, $row, $data),
                    default => self::applyBand($repo, $row, $data),
                };
                $db->update('activities', ['result' => json_encode($result, JSON_UNESCAPED_UNICODE)], ['id' => (int) $row['id']]);
            });
        }
    }

    // ---------- Gutschrift ----------

    private static function applyRehearsal(Repo $repo, array $row): array
    {
        self::advanceRehearsal($repo, $row, (int) $row['ends_at']);
        return self::rehearsalResult($repo, (int) $row['id'], 'ended');
    }

    private static function applyBand(Repo $repo, array $row, array $data): array
    {
        $db = $repo->db;
        $reward = $data['reward'];
        $bandId = (int) $row['band_id'];
        $repo->earn((int) ($reward['money'] ?? 0), sprintf(match (true) {
            $row['type'] === Battle::KIND => 'Preisgeld: %s',
            isset($reward['core']) => '%s',
            default => 'Gage: %s',
        }, $data['title']));
        $bill = min((int) ($reward['bill'] ?? 0), (int) ($reward['money'] ?? 0));
        $repo->spend($bill, sprintf('Rechnung: %s', $data['title']));
        $fameGain = $repo->addBandFame($bandId, $reward['fameGains'] ?? []);
        if ($row['type'] === 'bandpromo') {
            // Album-Zyklus: Band-Promo zählt (abgebrochene kommen hier nicht an).
            $db->exec('UPDATE bands SET band_promos = band_promos + 1 WHERE id = ?', [$bandId]);
        }
        Charts::boost($repo, $bandId, (float) ($reward['chartBoost'] ?? 0));
        // Gemeinsam erlebt: Die Beziehungen der Mitglieder wachsen (je nach Passung).
        Mood::bond($repo, $repo->members($bandId), $row['type']);
        // Ein Auftritt auf Tournee gefällt, wem Auftritte gefallen – einmal je Land, nicht in jeder Stadt neu.
        if ($row['type'] !== Tours::GIG || ($reward['tour']['first'] ?? true)) {
            Mood::react($repo, $repo->members($bandId), $row['type'] === Tours::GIG ? 'gig' : $row['type']);
        }
        // Auszeit: erholt zurück
        foreach ($reward['wellbeing'] ?? [] as $id => $gain) {
            $w = $db->value('SELECT wellbeing FROM musicians WHERE id = ? AND band_id = ?', [(int) $id, $bandId]);
            if ($w !== null) {
                $db->update('musicians', ['wellbeing' => round(min(100.0, (float) $w + (float) $gain), 3)], ['id' => (int) $id]);
            }
        }
        $result = [
            'money' => (int) ($reward['money'] ?? 0) - $bill,
            'bill' => $bill,
            'mood' => ($reward['wellbeing'] ?? null) ? round(array_sum($reward['wellbeing']) / count($reward['wellbeing']), 1) : null,
            'fame' => round($fameGain, 2),
            'meter' => $reward['meter'] ?? null,
            // Ort des Auftritts (für den passenden Applaus)
            'venue' => $reward['venue'] ?? null,
            'drops' => [],
            'venueUp' => null,
            'levelUps' => self::applySkill($repo, $reward['skill'] ?? []),
            // Können je Mitglied (Bandprobe), damit das Ergebnis zeigt, was sie gebracht hat.
            'skill' => array_values(array_map(static fn ($g) => (float) $g, $reward['skill'] ?? [])),
            // Battle of the Bands: Platz, Siege, Spiele
            'battle' => $reward['battle'] ?? null,
            // Festival: Bühne, Headliner, Interview-Anfrage danach
            'festival' => isset($reward['festival']) ? Festival::settled($repo, $bandId, $reward['festival']) : null,
        ];
        if (isset($reward['battle'])) {
            Battle::settled($repo, $bandId, $reward['battle']);
        }
        if (isset($reward['core']) && ($coreBand = $db->one('SELECT * FROM bands WHERE id = ?', [$bandId])) !== null) {
            // Neuer Kern: neue Fans nach dem Auftritt; steigt die Band dabei eine Ruhmstufe auf, feiert das Ergebnis es
            $fansBefore = BandCore::fans($db, $bandId);
            BandCore::finishGig($repo, Repo::hydrateBand($coreBand), (float) $reward['core']['newFans'], (int) $row['ends_at']);
            $fansAfter = BandCore::fans($db, $bandId);
            $result['fans'] = round($fansAfter - $fansBefore, 1);
            $result['tickets'] = (int) round($reward['core']['tickets']);
            $result['size'] = $reward['core']['size'];
            $stage = Fans::stage($fansAfter);
            if ($stage > Fans::stage($fansBefore)) {
                $result['stageUp'] = Balance::CORE_STAGE_NAMES[$stage];
                $repo->log('fame', sprintf('%s ist jetzt %s.', $coreBand['name'], Balance::CORE_STAGE_NAMES[$stage]), $bandId);
            }
        }

        $bandRow = $db->one('SELECT * FROM bands WHERE id = ?', [$bandId]);
        if ($bandRow !== null && !in_array($row['type'], ['bandpromo', 'retreat'], true)) {
            $band = Repo::hydrateBand($bandRow);
            $updates = [];
            if ($row['type'] === 'gig') {
                $updates['gigs_played'] = $band['gigs_played'] + 1;
                $updates['gigs_since_record'] = $band['gigs_since_record'] + 1;
                // Album-Zyklus: jedes Lied, das die Band vor Publikum spielt, zählt.
                $updates['live_songs'] = $band['live_songs'] + (int) ($data['songs'] ?? 0);
                if ($band['gigs_played'] === 0) {
                    $repo->log('gig', sprintf('%s spielt den ersten richtigen Gig.', $band['name']), $bandId);
                }
            } elseif ($row['type'] === Tours::GIG) {
                // Auf Tournee: ein Gig, aber im Ausland kennt noch niemand die Lieder (keine Abnutzung).
                $updates['gigs_played'] = $band['gigs_played'] + 1;
                $updates['live_songs'] = $band['live_songs'] + (int) ($data['songs'] ?? 0);
            } elseif ($row['type'] === Festival::KIND) {
                // Auch das Festival zählt für den Album-Zyklus.
                $updates['live_songs'] = $band['live_songs'] + (int) ($data['songs'] ?? 0);
            }
            if ($updates) {
                $db->update('bands', $updates, ['id' => $bandId]);
            }

            $fameNow = (float) $db->value('SELECT COALESCE(SUM(fame), 0) FROM musicians WHERE band_id = ?', [$bandId]);
            $before = BandCalc::venue($fameNow - $fameGain);
            $after = BandCalc::venue($fameNow);
            if ($after['key'] !== $before['key'] && !BandCore::$live) {
                $result['venueUp'] = $after['name'];
                $repo->log('fame', sprintf('%s ist bekannt genug für Gigs der Stufe %s.', $band['name'], $after['name']), $bandId);
            }
        }

        foreach ($reward['drops'] ?? [] as $drop) {
            if ($drop['kind'] === 'manager') {
                $manager = $repo->manager($repo->insertManager(Generator::manager('common', $repo->names())));
                $result['drops'][] = ['kind' => 'manager', 'name' => $manager['first_name'] . ' ' . $manager['last_name'], 'level' => $manager['level'], 'skills' => $manager['skills']];
                $repo->log('card', sprintf('Nach der Open Stage bietet sich %s %s als Manager an.', $manager['first_name'], $manager['last_name']), $bandId);
            } elseif ($drop['kind'] === 'musician') {
                $fan = $bandRow === null ? null : FanMusician::join($repo, Repo::hydrateBand($bandRow), (string) $drop['venue']);
                if ($fan !== null) {
                    $result['drops'][] = $fan;
                }
            } elseif ($drop['kind'] === 'fan') {
                $db->insert('items', ['label_id' => $repo->labelId, 'kind' => 'fan', 'value' => (float) $drop['value'], 'created_at' => Clock::now()]);
                $result['drops'][] = ['kind' => 'fan', 'value' => $drop['value']];
            }
        }
        // Erster Auftritt in einer Halle oder Arena (auf Tournee zählt die Größe des Ortes, nicht die Länder-Bühne)
        if (in_array($row['type'], ['gig', Tours::GIG], true)) {
            Awards::bigStage($repo, $bandId, (string) ($reward['size'] ?? $reward['venue'] ?? ''));
        }
        if ($row['type'] === Tours::GIG && isset($reward['tour'])) {
            $result['tour'] = Tours::afterGig($repo, $bandId, $reward['tour'], $result);
        }
        return $result;
    }

    private static function applySolo(Repo $repo, array $row, array $data): array
    {
        $reward = $data['reward'];
        $repo->earn((int) ($reward['money'] ?? 0), sprintf('Hut: %s', $data['title']));
        $result = [
            'money' => (int) ($reward['money'] ?? 0),
            'fame' => round(self::applyFame($repo, $reward['fameGains'] ?? []), 2),
            'meter' => $reward['meter'] ?? null,
        ];
        if (!empty($reward['jam'])) {
            $result['jamOffer'] = Jam::createOffer($repo, (int) $row['musician_id'], (float) ($reward['meter'] ?? 50));
        }
        return $result;
    }

    private static function applyPractice(Repo $repo, array $row, array $data): array
    {
        $m = $repo->musician((int) $row['musician_id']);
        $slot = (int) $data['reward']['slot'];
        $column = $slot === 2 ? 'skill2' : 'skill1';
        $before = BandCalc::skill($m, $slot);
        $after = min((float) Balance::MAX_STAT, $before + (float) $data['reward']['skill']);
        $repo->db->update('musicians', [$column => $after], ['id' => $m['id']]);
        $levelUp = Levels::check($repo, $m['id']);
        return [
            'instrument' => $slot === 2 ? $m['instr2'] : $m['instr1'],
            'skill' => round($after - $before, 3),
            'skillAfter' => Present::down($after),
            'levelUps' => $levelUp === null ? [] : [$levelUp],
        ];
    }

    private static function applyPromo(Repo $repo, array $row, array $data): array
    {
        $gain = self::applyFame($repo, $data['reward']['fameGains'] ?? []);
        $m = $repo->db->one('SELECT fame, band_id FROM musicians WHERE id = ?', [(int) $row['musician_id']]);
        if ($m !== null && $m['band_id'] !== null) {
            Charts::boost($repo, (int) $m['band_id'], Balance::CHART_PROMO_BOOST['solo']);
        }
        return ['fame' => round($gain, 1), 'total' => $m === null ? 0 : round((float) $m['fame'], 1)];
    }

    // ---------- Hilfen ----------

    /** Notgroschen: Hut-Geld bei der Straßenmusik, nur solange das Label sich keine Platte leisten kann. */
    public static function hat(Repo $repo, float $perf): int
    {
        if ($repo->label()['money'] >= Studio::cost(Balance::RECORD_MIN_SONGS)) {
            return 0;
        }
        [$low, $high] = Balance::BUSKING_HAT;
        return (int) round($low + ($high - $low) * max(0.0, min(1.0, ($perf - 0.4) / 1.2)));
    }

    /** Aktion einer Band anlegen, die anderswo geplant wurde (z. B. Battle of the Bands). */
    public function insertBand(int $bandId, string $type, int $startedAt, int $endsAt, array $data): array
    {
        return $this->insert($bandId, null, $type, $startedAt, $endsAt, $data);
    }

    /** Aktion eines einzelnen Musikers anlegen (z. B. Tageskurs). */
    public function insertSolo(int $musicianId, string $type, int $now, int $endsAt, array $data): array
    {
        return $this->insert(null, $musicianId, $type, $now, $endsAt, $data);
    }

    private function insert(?int $bandId, ?int $musicianId, string $type, int $now, int $endsAt, array $data): array
    {
        $id = $this->repo->db->insert('activities', [
            'label_id' => $this->repo->labelId,
            'band_id' => $bandId,
            'musician_id' => $musicianId,
            'type' => $type,
            'started_at' => $now,
            'ends_at' => $endsAt,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);
        return Present::activity($this->repo->db->one('SELECT * FROM activities WHERE id = ?', [$id]));
    }

    /** Wer auf der Bühne steht, für die Simulation (mit Charakter und dem goldenen Bonus „Showstück“ seines Instruments). */
    public static function player(array $m, array $gear = []): array
    {
        return [
            'name' => $m['first_name'],
            'instrument' => BandCalc::instrument($m),
            'skill' => BandCalc::skill($m),
            'abilities' => $m['abilities'],
            'personality' => $m['personality'] ?? [],
            'show' => Gear::value(BandCalc::gearOf($m, $gear), 'show'),
        ];
    }

    /** Promo bringt Bekanntheit; je bekannter jemand schon ist, desto weniger. */
    private static function promoFame(array $m, array $range): float
    {
        return Rng::float(...$range) / (1 + $m['fame'] / Balance::PROMO_FAME_FALLOFF);
    }

    private static function rehearsalTimeline(array $members, int $songs, int $seconds): array
    {
        $timeline = [];
        $span = $seconds / max(1, $songs);
        for ($s = 0; $s < $songs; $s++) {
            $start = $s * $span;
            $timeline[] = ['t' => $start + 0.5, 'kind' => 'song', 'text' => sprintf('Lied %d: „%s“', $s + 1, Generator::songTitle()), 'meter' => 50];
            foreach ([0.3 * $span, 0.7 * $span] as $offset) {
                $text = sprintf(Rng::pick(Catalog::REHEARSAL_LINES), Rng::pick($members)['first_name']);
                $timeline[] = ['t' => $start + $offset, 'kind' => 'highlight', 'text' => $text, 'meter' => 50];
            }
        }
        return $timeline;
    }

    // ---------- Offene Bandprobe: laufend gutgeschrieben ----------

    /**
     * Schreibt den Fortschritt einer laufenden Bandprobe bis `$upTo` gut (höchstens bis zum natürlichen Ende):
     * Können (mit Stufenaufstieg), Zusammenspiel, Beziehungen und Wohlbefinden – alles anteilig zur vergangenen Zeit.
     */
    public static function advanceRehearsal(Repo $repo, array $row, int $upTo): void
    {
        $data = json_decode($row['data'], true) ?: [];
        $r = $data['reward']['rehearsal'] ?? null;
        if ($r === null || $row['band_id'] === null) {
            return;
        }
        $dt = min($upTo, (int) $row['ends_at']) - ((int) $row['started_at'] + (int) $r['done']);
        if ($dt <= 0) {
            return;
        }
        $bandId = (int) $row['band_id'];
        $f = $dt / Balance::REHEARSAL['seconds'];
        $members = $repo->members($bandId);
        if (!$members) {
            return;
        }
        $gear = $repo->gearByMusician();
        $room = Rooms::here($repo, $repo->band($bandId), count($members));
        $gains = [];
        foreach ($members as $m) {
            $gains[$m['id']] = BandCalc::rehearsalGain($m, BandCalc::gearOf($m, $gear)) * $room['skill'] * $f;
        }
        $credited = (array) $r['credited'];
        foreach ($gains as $id => $g) {
            $credited[$id] = ($credited[$id] ?? 0.0) + $g;
        }
        $levelUps = self::applySkill($repo, $gains);
        Mood::bond($repo, $members, 'rehearsal', null, $f);
        Mood::react($repo, $members, 'rehearsal', $f);
        Mood::roomMood($repo, $repo->members($bandId), $room['mood'], $f);
        $r['done'] += $dt;
        $r['credited'] = $credited;
        $r['levelUps'] = array_merge($r['levelUps'], $levelUps);
        $data['reward']['rehearsal'] = $r;
        $repo->db->update('activities', ['data' => json_encode($data, JSON_UNESCAPED_UNICODE)], ['id' => (int) $row['id']]);
    }

    /** Laufende Proben bis jetzt gutschreiben (vor jedem Spielstand, damit man dem Lernen zusehen kann). */
    public static function advanceRunning(Db $db, int $labelId): void
    {
        $now = Clock::now();
        $rows = $db->all("SELECT * FROM activities WHERE label_id = ? AND type = 'rehearsal' AND settled = 0 AND ends_at > ?", [$labelId, $now]);
        foreach ($rows as $row) {
            $db->transaction(static function () use ($db, $row, $now): void {
                self::advanceRehearsal(new Repo($db, (int) $row['label_id']), $row, $now);
            });
        }
    }

    /** Ergebnis einer Bandprobe aus dem, was sie gebracht hat. */
    private static function rehearsalResult(Repo $repo, int $activityId, string $reason): array
    {
        $row = $repo->db->one('SELECT * FROM activities WHERE id = ?', [$activityId]);
        $r = (json_decode($row['data'], true)['reward']['rehearsal'] ?? []) + ['credited' => [], 'levelUps' => [], 'done' => 0, 'limit' => ['id' => 0, 'why' => 'lust']];
        $name = $r['limit']['id'] ? ($repo->db->value('SELECT first_name FROM musicians WHERE id = ?', [(int) $r['limit']['id']]) ?? '') : '';
        if ($row['band_id'] !== null) {
            Traits::afterRehearsal($repo, $repo->members((int) $row['band_id']), $activityId, $r['limit'], (int) $r['done']);
        }
        return [
            'money' => 0,
            'fame' => 0.0,
            'meter' => null,
            'drops' => [],
            'venueUp' => null,
            'levelUps' => $r['levelUps'],
            'skill' => array_values(array_map(static fn ($g) => round((float) $g, 2), (array) $r['credited'])),
            'skillBy' => array_map(static fn ($g) => round((float) $g, 3), (array) $r['credited']),
            'seconds' => (int) $r['done'],
            'ended' => $reason === 'stopped' ? 'stopped' : $r['limit']['why'],
            'endedBy' => $name,
        ];
    }

    /** Eine laufende Bandprobe beenden: Was bis jetzt gelernt wurde, bleibt. */
    public function stopRehearsal(int $activityId): void
    {
        $db = $this->repo->db;
        $db->transaction(function () use ($db, $activityId): void {
            $row = $db->one("SELECT * FROM activities WHERE id = ? AND label_id = ? AND settled = 0 AND type = 'rehearsal'", [$activityId, $this->repo->labelId]);
            if ($row === null) {
                throw new ApiError('Die Probe läuft nicht mehr.');
            }
            $now = Clock::now();
            self::advanceRehearsal($this->repo, $row, $now);
            $endsAt = (int) $row['ends_at'];
            $db->update('activities', ['settled' => 1, 'ends_at' => min($now, $endsAt)], ['id' => $activityId]);
            $db->exec('UPDATE bands SET busy_until = 0 WHERE id = ? AND busy_until = ?', [(int) $row['band_id'], $endsAt]);
            $db->update('activities', ['result' => json_encode(self::rehearsalResult($this->repo, $activityId, 'stopped'), JSON_UNESCAPED_UNICODE)], ['id' => $activityId]);
        });
    }

    private static function applyFame(Repo $repo, array $gains): float
    {
        $total = 0.0;
        foreach ($gains as $musicianId => $gain) {
            $total += $repo->addMusicianFame((int) $musicianId, (float) $gain);
        }
        return $total;
    }

    /** Können-Zuwachs auf das Instrument, das der Musiker in der Band spielt; danach Stufen prüfen. */
    private static function applySkill(Repo $repo, array $gains): array
    {
        $levelUps = [];
        foreach ($gains as $musicianId => $gain) {
            $row = $repo->db->one('SELECT * FROM musicians WHERE id = ? AND label_id = ?', [(int) $musicianId, $repo->labelId]);
            if ($row === null) {
                continue;
            }
            $m = Repo::hydrateMusician($row);
            $column = $m['band_slot'] === 2 && $m['instr2'] !== null ? 'skill2' : 'skill1';
            $repo->db->update('musicians', [$column => min((float) Balance::MAX_STAT, BandCalc::skill($m) + (float) $gain)], ['id' => $m['id']]);
            $levelUp = Levels::check($repo, $m['id']);
            if ($levelUp !== null) {
                $levelUps[] = $levelUp;
            }
        }
        return $levelUps;
    }
}
