<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;
use Signed\Db;
use Signed\Rng;

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
            return 'Die Band ist gerade beschäftigt.';
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

    public function start(int $bandId, string $type, int $songs = 0): array
    {
        if (!in_array($type, self::BAND_TYPES, true)) {
            throw new ApiError('Unbekannte Aktivität.');
        }
        $db = $this->repo->db;
        return $db->transaction(function () use ($db, $bandId, $type, $songs): array {
            $now = Clock::now();
            $band = $this->repo->band($bandId);
            $members = $this->repo->members($bandId);
            $blocker = self::blocker($band, $members, $now);
            if ($blocker !== null) {
                throw new ApiError($blocker);
            }
            $refuser = Mood::refuser($members, $type);
            if ($refuser !== null) {
                throw new ApiError($refuser['first_name'] . ' hat darauf gerade keine Lust. Eine Anordnung, die der Band gefällt, hebt zuerst die Stimmung.');
            }
            $gear = $this->repo->gearByMusician();
            $sound = BandCalc::bandSound($band, $members, $gear);
            $fame = BandCalc::bandFame($band, $members);
            [$manager, $level] = $this->repo->bandManager($band);
            $chemistry = BandCalc::chemistry($members);
            $cohesionRule = BandCalc::cohesionRule(BandCalc::identity($members, $band['genre'], $band['genre2']));
            $venueKey = null;
            $gage = 0;
            $skill = [];
            $cohesionPerSong = Balance::COHESION_PER_SONG;

            if ($type === 'rehearsal') {
                $songs = Balance::REHEARSAL['songs'];
                $cohesionPerSong = Balance::REHEARSAL['cohesion'];
                foreach ($members as $m) {
                    $skill[$m['id']] = round(BandCalc::rehearsalGain($m, BandCalc::gearOf($m, $gear)), 2);
                }
                $title = 'Bandprobe';
                $sim = ['timeline' => self::rehearsalTimeline($members, $songs), 'meter' => 0.0, 'perf' => 0.0];
                $fameTotal = 0.0;
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
                $sim = Performance::simulate(array_map([self::class, 'player'], $members), $sound, (float) $expect, $songs);
                if ($type === 'busking') {
                    $gage = self::hat($this->repo, $sim['perf']);
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

            $endsAt = $now + $songs * Balance::SONG_SECONDS;
            $claimed = $db->exec('UPDATE bands SET busy_until = ?, released = 1 WHERE id = ? AND busy_until <= ?', [$endsAt, $bandId, $now]);
            if ($claimed === 0) {
                throw new ApiError('Die Band ist gerade beschäftigt.');
            }
            return $this->insert($bandId, null, $type, $now, $endsAt, [
                'title' => $title,
                'songs' => $songs,
                'timeline' => $sim['timeline'],
                'reward' => [
                    'money' => $gage,
                    'fameGains' => BandCalc::splitFame($members, $fameTotal, $gear),
                    'skill' => $skill,
                    'cohesion' => round($cohesionPerSong * $songs * $chemistry * $cohesionRule['growth'], 2),
                    'maxCohesion' => $cohesionRule['max'],
                    'meter' => $sim['meter'],
                    'drops' => $drops,
                    'venue' => $venueKey,
                    // Jedes gespielte Lied schiebt die neueste Platte in den Charts an – egal wo.
                    'chartBoost' => $type === 'gig' ? round($songs * Balance::CHART_GIG_BOOST, 3) : 0,
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
            $sim = Performance::simulate([self::player($m)], $sound, (float) $expect, $songs);
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
                    'fameGains' => BandCalc::splitFame([$m], $fameTotal, $gear),
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
            $cfg = Balance::BAND_PROMO;
            $endsAt = $now + $cfg['seconds'];
            if ($db->exec('UPDATE bands SET busy_until = ? WHERE id = ? AND busy_until <= ?', [$endsAt, $bandId, $now]) === 0) {
                throw new ApiError('Die Band ist gerade beschäftigt.');
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
                "SELECT * FROM activities WHERE id = ? AND label_id = ? AND settled = 0 AND type IN ('promo', 'bandpromo', 'practice', 'course')",
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
                    $row['type'] === 'practice', $row['type'] === 'course' => self::applyPractice($repo, $row, $data),
                    $row['band_id'] === null => self::applySolo($repo, $row, $data),
                    default => self::applyBand($repo, $row, $data),
                };
                $db->update('activities', ['result' => json_encode($result, JSON_UNESCAPED_UNICODE)], ['id' => (int) $row['id']]);
            });
        }
    }

    // ---------- Gutschrift ----------

    private static function applyBand(Repo $repo, array $row, array $data): array
    {
        $db = $repo->db;
        $reward = $data['reward'];
        $bandId = (int) $row['band_id'];
        $repo->earn((int) ($reward['money'] ?? 0), sprintf('Gage: %s', $data['title']));
        $fameGain = $repo->addBandFame($bandId, $reward['fameGains'] ?? []);
        if ($row['type'] === 'bandpromo') {
            // Album-Zyklus: Band-Promo zählt (abgebrochene kommen hier nicht an).
            $db->exec('UPDATE bands SET band_promos = band_promos + 1 WHERE id = ?', [$bandId]);
        }
        Charts::boost($repo, $bandId, (float) ($reward['chartBoost'] ?? 0));
        // Gemeinsam erlebt: Die Beziehungen der Mitglieder wachsen (je nach Passung).
        Mood::bond($repo, $repo->members($bandId), $row['type']);
        Mood::react($repo, $repo->members($bandId), $row['type']);
        $result = [
            'money' => (int) ($reward['money'] ?? 0),
            'fame' => round($fameGain, 2),
            'meter' => $reward['meter'] ?? null,
            'cohesion' => 0.0,
            'drops' => [],
            'venueUp' => null,
            'levelUps' => self::applySkill($repo, $reward['skill'] ?? []),
            // Können je Mitglied (Bandprobe), damit das Ergebnis zeigt, was sie gebracht hat.
            'skill' => array_values(array_map(static fn ($g) => (float) $g, $reward['skill'] ?? [])),
            'cohesionFull' => false,
        ];

        $bandRow = $db->one('SELECT * FROM bands WHERE id = ?', [$bandId]);
        if ($bandRow !== null && isset($reward['cohesion'])) {
            $band = Repo::hydrateBand($bandRow);
            $cohesion = $band['cohesion'] >= $reward['maxCohesion']
                ? $band['cohesion']
                : min((float) $reward['maxCohesion'], $band['cohesion'] + (float) $reward['cohesion']);
            $updates = ['cohesion' => $cohesion];
            if ($row['type'] === 'gig') {
                $updates['gigs_played'] = $band['gigs_played'] + 1;
                $updates['gigs_since_record'] = $band['gigs_since_record'] + 1;
                // Album-Zyklus: jedes Lied, das die Band vor Publikum spielt, zählt.
                $updates['live_songs'] = $band['live_songs'] + (int) ($data['songs'] ?? 0);
                if ($band['gigs_played'] === 0) {
                    $repo->log('gig', sprintf('%s spielt den ersten richtigen Gig.', $band['name']), $bandId);
                }
            }
            $db->update('bands', $updates, ['id' => $bandId]);
            $result['cohesion'] = round($cohesion - $band['cohesion'], 1);
            $result['cohesionFull'] = $cohesion >= (float) $reward['maxCohesion'];

            $fameNow = (float) $db->value('SELECT COALESCE(SUM(fame), 0) FROM musicians WHERE band_id = ?', [$bandId]);
            $before = BandCalc::venue($fameNow - $fameGain);
            $after = BandCalc::venue($fameNow);
            if ($after['key'] !== $before['key']) {
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

    private static function player(array $m): array
    {
        return [
            'name' => $m['first_name'],
            'instrument' => BandCalc::instrument($m),
            'skill' => BandCalc::skill($m),
            'abilities' => $m['abilities'],
        ];
    }

    /** Promo bringt Bekanntheit; je bekannter jemand schon ist, desto weniger. Ausstrahlung verstärkt sie. */
    private static function promoFame(array $m, array $range): float
    {
        $factor = Levels::has($m, 'ausstrahlung') ? Balance::ATTRACTIVE_FACTOR : 1.0;
        return Rng::float(...$range) * $factor / (1 + $m['fame'] / Balance::PROMO_FAME_FALLOFF);
    }

    private static function rehearsalTimeline(array $members, int $songs): array
    {
        $timeline = [];
        for ($s = 0; $s < $songs; $s++) {
            $start = $s * Balance::SONG_SECONDS;
            $timeline[] = ['t' => $start + 0.5, 'kind' => 'song', 'text' => sprintf('Lied %d: „%s“', $s + 1, Generator::songTitle()), 'meter' => 50];
            foreach ([8, 19] as $offset) {
                $text = sprintf(Rng::pick(Catalog::REHEARSAL_LINES), Rng::pick($members)['first_name']);
                $timeline[] = ['t' => $start + $offset, 'kind' => 'highlight', 'text' => $text, 'meter' => 50];
            }
        }
        return $timeline;
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
