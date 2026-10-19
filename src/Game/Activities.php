<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;
use Signed\Db;
use Signed\Rng;

/**
 * Automatische und passive Aktionen: Straßenmusik, Open Stage, Gigs und Promo-Missionen.
 * Das Ergebnis wird beim Start berechnet und gutgeschrieben, sobald die Zeit abgelaufen ist.
 */
final class Activities
{
    public const PERFORMANCES = ['busking', 'openstage', 'gig'];

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
        if (!in_array($type, self::PERFORMANCES, true)) {
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
            $gear = $this->repo->gearByMusician();
            $sound = BandCalc::sound($members, $band['genre'], $band['cohesion'], $gear);
            $fame = BandCalc::fame($members);
            [$manager, $level] = $this->repo->bandManager($band);
            $venueKey = null;

            if ($type === 'busking') {
                $cfg = Balance::BUSKING;
                $songs = $cfg['songs'];
                $falloff = BandCalc::falloff($fame, $cfg['falloff'], $cfg['minFactor']);
                $gage = (int) round($cfg['gage'] * $songs * $falloff);
                $fameBase = $cfg['fame'] * $falloff;
                $expect = $cfg['expect'];
                $title = 'Straßenmusik: ' . Rng::pick(Catalog::STREETS);
            } elseif ($type === 'openstage') {
                $cfg = Balance::OPENSTAGE;
                $songs = $cfg['songs'];
                $gage = 0;
                $fameBase = $cfg['fame'] * BandCalc::falloff($fame, $cfg['falloff']);
                $expect = $cfg['expect'];
                $title = 'Open Stage: ' . Rng::pick(Catalog::OPEN_STAGES);
            } else {
                if ($band['records'] < 1) {
                    throw new ApiError('Richtige Gigs gibt es erst, wenn die Band eine Platte produziert hat.');
                }
                $max = BandCalc::maxGigSongs($band['songs_recorded'], $level);
                if ($songs < 1 || $songs > $max) {
                    throw new ApiError(sprintf('Ein Gig dieser Band kann 1 bis %d Lieder lang sein.', $max));
                }
                $venue = BandCalc::venue($fame);
                $negotiator = BandCalc::managerBonus($manager, $level, 'verhandler', Balance::NEGOTIATOR_BONUS);
                $gage = BandCalc::gagePerSong($venue, $negotiator) * $songs;
                $fameBase = $venue['fame'];
                $expect = $venue['expect'];
                $venueKey = $venue['key'];
                $title = sprintf('Gig: %s (%s)', Rng::pick(Catalog::VENUE_NAMES[$venue['key']]), $venue['name']);
            }

            $players = array_map(static fn ($m) => [
                'name' => $m['first_name'],
                'instrument' => BandCalc::instrument($m),
                'skill' => BandCalc::skill($m),
                'presence' => $m['presence'],
                'traits' => $m['traits'],
                'techniques' => $m['techniques'],
            ], $members);
            $sim = Performance::simulate($players, $sound, (float) $expect, $songs);

            // Ruhm: Größe des Auftritts × Sound × Publikum, plus etwas Zufall.
            $fameTotal = 0.0;
            for ($i = 0; $i < $songs; $i++) {
                $fameTotal += $fameBase * BandCalc::soundFactor($sound) * $sim['perf'] * Rng::float(0.9, 1.1);
            }
            $fameTotal *= 1 + BandCalc::managerBonus($manager, $level, 'prprofi', Balance::PR_BONUS);
            $chemistry = BandCalc::chemistry($members);

            $drops = [];
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

            $id = $db->insert('activities', [
                'label_id' => $this->repo->labelId,
                'band_id' => $bandId,
                'musician_id' => null,
                'type' => $type,
                'started_at' => $now,
                'ends_at' => $endsAt,
                'data' => json_encode([
                    'title' => $title,
                    'songs' => $songs,
                    'timeline' => $sim['timeline'],
                    'reward' => [
                        'money' => $gage,
                        'fameGains' => BandCalc::splitFame($members, $fameTotal, $gear),
                        'cohesion' => round(Balance::COHESION_PER_SONG * $songs * $chemistry, 2),
                        'maxCohesion' => BandCalc::maxCohesion($chemistry),
                        'meter' => $sim['meter'],
                        'drops' => $drops,
                        'venue' => $venueKey,
                    ],
                ], JSON_UNESCAPED_UNICODE),
            ]);
            return Present::activity($db->one('SELECT * FROM activities WHERE id = ?', [$id]));
        });
    }

    /** Promo-Mission: ein Musiker ist eine Weile unterwegs und gewinnt Präsenz. Läuft offline. */
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
            if ($m['presence'] >= Balance::MAX_STAT) {
                throw new ApiError(sprintf('%s hat schon die volle Präsenz.', $m['first_name']));
            }
            $factor = in_array('attraktiv', $m['traits'], true) ? Balance::ATTRACTIVE_FACTOR : 1.0;
            $gain = Rng::float(...Balance::PROMO_GAIN) * $factor * max(0.1, 1 - $m['presence'] / 110);
            $endsAt = $now + Balance::PROMO_SECONDS;

            $claimed = $db->exec('UPDATE musicians SET busy_until = ? WHERE id = ? AND busy_until <= ?', [$endsAt, $musicianId, $now]);
            if ($claimed === 0) {
                throw new ApiError(sprintf('%s ist gerade beschäftigt.', $m['first_name']));
            }
            $id = $db->insert('activities', [
                'label_id' => $this->repo->labelId,
                'band_id' => null,
                'musician_id' => $musicianId,
                'type' => 'promo',
                'started_at' => $now,
                'ends_at' => $endsAt,
                'data' => json_encode([
                    'title' => 'Promo-Mission: ' . Bands::fullName($m),
                    'songs' => 0,
                    'reward' => ['presence' => round($gain, 2)],
                ], JSON_UNESCAPED_UNICODE),
            ]);
            return Present::activity($db->one('SELECT * FROM activities WHERE id = ?', [$id]));
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
                $result = $row['type'] === 'promo'
                    ? self::applyPromo($repo, $row, $data)
                    : self::applyPerformance($repo, $row, $data);
                $db->update('activities', ['result' => json_encode($result, JSON_UNESCAPED_UNICODE)], ['id' => (int) $row['id']]);
            });
        }
    }

    private static function applyPerformance(Repo $repo, array $row, array $data): array
    {
        $db = $repo->db;
        $reward = $data['reward'];
        $bandId = (int) $row['band_id'];
        $repo->earn((int) $reward['money']);

        $fameGain = 0.0;
        foreach ($reward['fameGains'] as $musicianId => $gain) {
            $db->exec('UPDATE musicians SET fame = fame + ? WHERE id = ? AND label_id = ?', [(float) $gain, (int) $musicianId, $repo->labelId]);
            $fameGain += (float) $gain;
        }
        $result = [
            'money' => (int) $reward['money'],
            'fame' => round($fameGain, 1),
            'meter' => $reward['meter'],
            'cohesion' => 0.0,
            'drops' => [],
            'venueUp' => null,
        ];

        $bandRow = $db->one('SELECT * FROM bands WHERE id = ?', [$bandId]);
        if ($bandRow !== null) {
            $band = Repo::hydrateBand($bandRow);
            $cohesion = $band['cohesion'] >= $reward['maxCohesion']
                ? $band['cohesion']
                : min((float) $reward['maxCohesion'], $band['cohesion'] + (float) $reward['cohesion']);
            $updates = ['cohesion' => $cohesion];
            if ($row['type'] === 'gig') {
                $updates['gigs_played'] = $band['gigs_played'] + 1;
                $updates['gigs_since_record'] = $band['gigs_since_record'] + 1;
                if ($band['gigs_played'] === 0) {
                    $repo->log('gig', sprintf('%s spielt den ersten richtigen Gig.', $band['name']), $bandId);
                }
            }
            $db->update('bands', $updates, ['id' => $bandId]);
            $result['cohesion'] = round($cohesion - $band['cohesion'], 1);

            $fameNow = (float) $db->value('SELECT COALESCE(SUM(fame), 0) FROM musicians WHERE band_id = ?', [$bandId]);
            $before = BandCalc::venue($fameNow - $fameGain);
            $after = BandCalc::venue($fameNow);
            if ($after['key'] !== $before['key']) {
                $result['venueUp'] = $after['name'];
                $repo->log('fame', sprintf('%s ist bekannt genug für Gigs der Stufe %s.', $band['name'], $after['name']), $bandId);
            }
        }

        foreach ($reward['drops'] as $drop) {
            if ($drop['kind'] === 'manager') {
                $manager = $repo->manager($repo->insertManager(Generator::manager('common')));
                $result['drops'][] = ['kind' => 'manager', 'name' => $manager['first_name'] . ' ' . $manager['last_name'], 'level' => $manager['level'], 'skills' => $manager['skills']];
                $repo->log('card', sprintf('Nach der Open Stage bietet sich %s %s als Manager an.', $manager['first_name'], $manager['last_name']), $bandId);
            } elseif ($drop['kind'] === 'fan') {
                $db->insert('items', ['label_id' => $repo->labelId, 'kind' => 'fan', 'value' => (float) $drop['value'], 'created_at' => Clock::now()]);
                $result['drops'][] = ['kind' => 'fan', 'value' => $drop['value']];
            }
        }
        return $result;
    }

    private static function applyPromo(Repo $repo, array $row, array $data): array
    {
        $db = $repo->db;
        $musician = $db->one('SELECT presence FROM musicians WHERE id = ? AND label_id = ?', [(int) $row['musician_id'], $repo->labelId]);
        if ($musician === null) {
            return ['presence' => 0, 'total' => 0];
        }
        $before = (float) $musician['presence'];
        $after = min((float) Balance::MAX_STAT, $before + (float) $data['reward']['presence']);
        $db->update('musicians', ['presence' => $after], ['id' => (int) $row['musician_id']]);
        return ['presence' => round($after - $before, 1), 'total' => round($after, 1)];
    }
}
