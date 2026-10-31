<?php
declare(strict_types=1);

namespace Signed\Game;

use DateTimeImmutable;
use DateTimeZone;
use Signed\ApiError;
use Signed\Clock;
use Signed\Db;
use Signed\Rng;

/**
 * Festival: jeden Mittwoch für alle Labels (Balance::FESTIVAL). Jedes Festival sucht ein Genre; bewerben können sich alle Bands mit
 * diesem Genre (auch hybrid), jedes Label mit so vielen, wie es will. Zum Anmeldeschluss entscheidet „der Veranstalter“: Sind es mehr
 * als Plätze, entscheidet das Los. Wer auf welche Bühne kommt, entscheidet die Bekanntheit, die die Band in der letzten Woche gesammelt
 * hat (eine Momentaufnahme); die meiste spielt als Headliner zuletzt auf der Hauptbühne. Das Line-up hängt bis zum Beginn aus.
 * Beim Beginn wird jeder Auftritt wie ein Gig berechnet und zu seiner Zeit gezeigt; die Band ist vom Beginn bis zu ihrem Auftritt dort.
 */
final class Festival
{
    public const KIND = 'festival';
    /** Von dieser Woche an (Wochen seit 1970) wechselt das Genre von Woche zu Woche. */
    private const ANCHOR = 2900;

    public static function next(Db $db, int $now): array
    {
        return Events::next($db, self::KIND, Balance::FESTIVAL, $now);
    }

    /** Genre und Name des Festivals einer Woche: für alle gleich, aus der Woche berechnet, nie zweimal hintereinander dasselbe Genre. */
    public static function theme(string $week): array
    {
        [$year, $number] = array_map('intval', explode('-W', $week));
        $monday = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->setISODate($year, $number, 1)->setTime(12, 0)->getTimestamp();
        $index = intdiv($monday, 7 * 86400);
        $genres = array_keys(Catalog::GENRES);
        $count = count($genres);
        $position = 0;
        for ($w = self::ANCHOR; $w <= $index; $w++) {
            $position = ($position + 1 + abs(crc32("signed-festival-$w")) % ($count - 1)) % $count;
        }
        $genre = $genres[$position];
        $names = Catalog::FESTIVALS[$genre];
        return ['genre' => $genre, 'name' => $names[$index % count($names)]];
    }

    /** Genres einer Band (Haupt-, zweites und drittes Genre), so wie sie gerade besetzt ist. */
    public static function genresOf(array $band, array $members): array
    {
        $identity = BandCalc::identity($members, $band['genre'], $band['genre2']);
        return array_values(array_filter([$identity['primary'], $identity['second'], $identity['third']]));
    }

    /** Plätze auf allen Bühnen zusammen. */
    public static function slots(): int
    {
        return array_sum(array_column(Balance::FESTIVAL['stages'], 'slots'));
    }

    /** Bekanntheit, die eine Band in den letzten Tagen gesammelt hat (alles, auch vom Festival). */
    public static function weekGain(Db $db, int $bandId, int $now): float
    {
        $from = Courses::day($now - (Balance::FESTIVAL['days'] - 1) * 86400);
        return round((float) $db->value('SELECT COALESCE(SUM(gain), 0) FROM fame_days WHERE band_id = ? AND day >= ?', [$bandId, $from]), 2);
    }

    // ---------- Bewerben ----------

    /** Was gegen eine Bewerbung spricht, sonst null. */
    public static function blocker(array $band, array $members, array $event, ?Db $db = null): ?string
    {
        $theme = self::theme($event['week']);
        if (count($members) < Balance::MIN_MEMBERS) {
            return sprintf('Die Band braucht mindestens %d Musiker.', Balance::MIN_MEMBERS);
        }
        if (!in_array($theme['genre'], self::genresOf($band, $members), true)) {
            $name = Catalog::GENRES[$theme['genre']]['name'];
            return sprintf('„%s“ sucht %s-Bands (auch gemischt mit %s). Diese Band spielt kein %s.', $theme['name'], $name, $name, $name);
        }
        if ($band['busy_until'] > (int) $event['starts_at']) {
            return sprintf('%s ist dann noch unterwegs.', Bands::who($members));
        }
        foreach ($members as $m) {
            if ($m['busy_until'] > (int) $event['starts_at']) {
                return sprintf('%s ist dann noch unterwegs.', $m['first_name']);
            }
        }
        if ($db !== null && Tours::active($db, $band['id']) !== null) {
            return sprintf('%s ist auf Tournee.', Bands::who($members));
        }
        $refuser = Mood::refuser($members, self::KIND);
        if ($refuser !== null) {
            return sprintf('%s hat auf ein Festival gerade keine Lust.', $refuser['first_name']);
        }
        return null;
    }

    public static function enter(Repo $repo, int $bandId): void
    {
        $db = $repo->db;
        $db->transaction(static function () use ($repo, $db, $bandId): void {
            $now = Clock::now();
            $event = self::next($db, $now);
            $band = $repo->band($bandId);
            if ($band['retired']) {
                throw new ApiError('Diese Band gibt es nicht mehr.');
            }
            if ($db->value("SELECT id FROM event_entries WHERE event_id = ? AND band_id = ? AND status = 'in'", [(int) $event['id'], $bandId]) !== null) {
                throw new ApiError('Die Band hat sich schon beworben.');
            }
            $blocker = self::blocker($band, $repo->members($bandId), $event, $db);
            if ($blocker !== null) {
                throw new ApiError($blocker);
            }
            $db->insert('event_entries', [
                'event_id' => (int) $event['id'], 'label_id' => $repo->labelId, 'band_id' => $bandId, 'status' => 'in', 'created_at' => $now,
            ]);
        });
    }

    public static function withdraw(Repo $repo, int $bandId): void
    {
        $event = self::next($repo->db, Clock::now());
        $gone = $repo->db->exec(
            "DELETE FROM event_entries WHERE event_id = ? AND band_id = ? AND label_id = ? AND status = 'in'",
            [(int) $event['id'], $bandId, $repo->labelId]
        );
        if ($gone === 0) {
            throw new ApiError('Die Band hat sich nicht beworben.');
        }
    }

    // ---------- Ablauf ----------

    /** Was fällig ist, der Reihe nach: Line-up festlegen (Anmeldeschluss), beginnen, abschließen. */
    public static function advance(Db $db, int $eventId, int $now): void
    {
        $db->transaction(static function () use ($db, $eventId, $now): void {
            $event = $db->one('SELECT * FROM events WHERE id = ?', [$eventId]);
            if ($event !== null && $event['state'] === 'open' && Events::closesAt($event) <= $now
                && $db->exec("UPDATE events SET state = 'lineup' WHERE id = ? AND state = 'open'", [$eventId]) === 1) {
                self::decide($db, $event, $now);
                $event = $db->one('SELECT * FROM events WHERE id = ?', [$eventId]);
            }
            if ($event !== null && $event['state'] === 'lineup' && (int) $event['starts_at'] <= $now
                && $db->exec("UPDATE events SET state = 'running' WHERE id = ? AND state = 'lineup'", [$eventId]) === 1) {
                self::start($db, $event);
                $event = $db->one('SELECT * FROM events WHERE id = ?', [$eventId]);
            }
            if ($event !== null && $event['state'] === 'running' && (int) $event['ends_at'] <= $now) {
                $db->exec("UPDATE events SET state = 'done' WHERE id = ? AND state = 'running'", [$eventId]);
            }
        });
    }

    /** Anmeldeschluss: Wer passt, wer per Los dabei ist, wer auf welche Bühne kommt und wann. */
    private static function decide(Db $db, array $event, int $now): void
    {
        $c = Balance::FESTIVAL;
        $eventId = (int) $event['id'];
        $theme = self::theme($event['week']);
        $repos = [];
        $repo = static function (int $labelId) use ($db, &$repos): Repo {
            return $repos[$labelId] ??= new Repo($db, $labelId);
        };
        $reject = static function (array $entry, string $text) use ($db, $repo): void {
            $db->update('event_entries', ['status' => 'rejected'], ['id' => (int) $entry['id']]);
            $repo((int) $entry['label_id'])->log('event', $text, (int) $entry['band_id']);
        };
        $valid = [];
        foreach ($db->all("SELECT * FROM event_entries WHERE event_id = ? AND status = 'in' ORDER BY created_at, id", [$eventId]) as $entry) {
            $r = $repo((int) $entry['label_id']);
            $row = $db->one('SELECT * FROM bands WHERE id = ? AND label_id = ? AND retired_at IS NULL', [(int) $entry['band_id'], $r->labelId]);
            $members = $row === null ? [] : $r->members((int) $row['id']);
            if ($row === null || count($members) < Balance::MIN_MEMBERS) {
                $reject($entry, sprintf('Die Bewerbung für „%s“ ist verfallen: Die Band gibt es so nicht mehr.', $theme['name']));
                continue;
            }
            $band = Repo::hydrateBand($row);
            if (!in_array($theme['genre'], self::genresOf($band, $members), true)) {
                $reject($entry, sprintf('%s passt nicht mehr zu „%s“ (gesucht: %s) und wurde nicht genommen.', $band['name'], $theme['name'], Catalog::GENRES[$theme['genre']]['name']));
                continue;
            }
            // Bühnen nach Bekanntheit: im neuen Kern nach Fans, sonst nach dem Ruhm der letzten Woche
            $gain = BandCore::$live ? BandCore::fans($db, $band['id']) : self::weekGain($db, $band['id'], $now);
            $valid[] = ['entry' => $entry, 'band' => $band, 'members' => $members, 'gain' => $gain, 'label' => $r->label()['name']];
        }
        if (!$valid) {
            $db->update('events', ['state' => 'cancelled', 'ends_at' => (int) $event['starts_at'], 'data' => json_encode($theme + ['acts' => []], JSON_UNESCAPED_UNICODE)], ['id' => $eventId]);
            return;
        }
        // Mehr Bewerbungen als Plätze: Das Los entscheidet (offiziell der Veranstalter).
        $valid = Rng::sample($valid, count($valid));
        foreach (array_slice($valid, self::slots()) as $v) {
            $reject($v['entry'], sprintf('Der Veranstalter von „%s“ hat sich für andere Bands entschieden. %s ist diesmal nicht dabei.', $theme['name'], $v['band']['name']));
        }
        $taken = array_slice($valid, 0, self::slots());
        // Bühnen nach der Bekanntheit der letzten Woche (bei Gleichstand bleibt die ausgeloste Reihenfolge).
        usort($taken, static fn ($x, $y) => $y['gain'] <=> $x['gain']);
        // Plätze nach Rang: auf jeder Bühne der letzte zuerst (Headliner), dann die davor; alle Bühnen enden zur selben Zeit.
        $last = max(array_column($c['stages'], 'slots'));
        $positions = [];
        foreach ($c['stages'] as $key => $stage) {
            for ($k = $stage['slots'] - 1; $k >= 0; $k--) {
                $positions[] = [$key, $last - $stage['slots'] + $k];
            }
        }
        $acts = [];
        foreach ($taken as $i => $v) {
            [$key, $slot] = $positions[$i];
            $acts[] = [
                'bandId' => $v['band']['id'], 'labelId' => (int) $v['entry']['label_id'], 'name' => $v['band']['name'], 'label' => $v['label'],
                'genres' => self::genresOf($v['band'], $v['members']), 'stage' => $key, 'slot' => $slot, 'gain' => $v['gain'], 'headliner' => $i === 0,
            ];
        }
        // Wenige Bands: Es geht gleich zu Beginn los.
        $shift = min(array_column($acts, 'slot'));
        $start = (int) $event['starts_at'];
        foreach ($acts as $i => $act) {
            $acts[$i]['at'] = $start + ($act['slot'] - $shift) * $c['gap'];
            $acts[$i]['endsAt'] = $acts[$i]['at'] + $c['stages'][$act['stage']]['songs'] * Balance::SONG_SECONDS;
            $db->update('event_entries', ['status' => 'accepted', 'place' => $i + 1], ['event_id' => $eventId, 'band_id' => $act['bandId']]);
            $repo($act['labelId'])->log('event', sprintf(
                '%s spielt beim Festival „%s“: %s, %s.',
                $act['name'], $theme['name'], $c['stages'][$act['stage']]['name'], Events::when($acts[$i]['at'])
            ) . ($i === 0 ? ' Als Headliner!' : ''), $act['bandId']);
        }
        $db->update('events', [
            'ends_at' => max(array_column($acts, 'endsAt')),
            'data' => json_encode($theme + ['acts' => $acts], JSON_UNESCAPED_UNICODE),
        ], ['id' => $eventId]);
        $text = sprintf('Das Line-up für „%s“ (%s) steht: Headliner ist %s (%s).', $theme['name'], Events::when($start), $acts[0]['name'], $acts[0]['label']);
        foreach ($db->all('SELECT id FROM labels') as $label) {
            $repo((int) $label['id'])->log('event', $text);
        }
    }

    /** Beginn: Jeder Auftritt wird wie ein Gig berechnet; die Band ist ab jetzt bis zum Ende ihres Auftritts auf dem Festival. */
    private static function start(Db $db, array $event): void
    {
        $c = Balance::FESTIVAL;
        $data = json_decode((string) $event['data'], true) ?: [];
        foreach ($data['acts'] ?? [] as $i => $act) {
            $repo = new Repo($db, $act['labelId']);
            $row = $db->one('SELECT * FROM bands WHERE id = ? AND label_id = ? AND retired_at IS NULL', [$act['bandId'], $repo->labelId]);
            $members = $row === null ? [] : $repo->members($act['bandId']);
            if ($row === null || count($members) < Balance::MIN_MEMBERS) {
                $data['acts'][$i]['missing'] = true;
                continue;
            }
            $band = Repo::hydrateBand($row);
            $stage = $c['stages'][$act['stage']];
            $gear = $repo->gearByMusician();
            $sound = BandCalc::bandSound($band, $members, $gear);
            $expect = BandCore::$live ? Balance::CORE_SOUND_EXPECT[BandCore::stage($db, $band['id'])] : (float) $stage['expect'];
            $sim = Performance::simulate(array_map(static fn ($m) => Activities::player($m, $gear), $members), $sound, $expect, $stage['songs']);
            // Ruhm wie bei einem Gig: Größe der Bühne × Sound × Publikum, etwas Zufall, PR-Profi
            $fame = 0.0;
            for ($s = 0; $s < $stage['songs']; $s++) {
                $fame += $stage['fame'] * BandCalc::soundFactor($sound) * $sim['perf'] * Rng::float(0.9, 1.1);
            }
            [$manager, $level] = $repo->bandManager($band);
            $fame *= BandCore::$live ? 0.0 : 1 + BandCalc::managerBonus($manager, $level, 'prprofi', Balance::PR_BONUS);
            $drops = [];
            if ($sim['meter'] >= Balance::FAN_MUSICIAN['minMeter'] && Rng::chance($c['fanChance']) && !FanMusician::onCooldown($repo)) {
                $drops[] = ['kind' => 'musician', 'venue' => $stage['fanVenue']];
            }
            $db->exec('UPDATE bands SET busy_until = ? WHERE id = ? AND busy_until < ?', [$act['endsAt'], $act['bandId'], $act['endsAt']]);
            (new Activities($repo))->insertBand($act['bandId'], self::KIND, $act['at'], $act['endsAt'], [
                'title' => sprintf('Festival „%s“: %s', $data['name'], $stage['name']),
                'songs' => $stage['songs'],
                'timeline' => $sim['timeline'],
                'reward' => [
                    'money' => $stage['money'],
                    'fameGains' => BandCalc::splitFame($members, $fame, $gear, true),
                    'chartBoost' => BandCore::$live ? 0 : round($stage['songs'] * Balance::CHART_GIG_BOOST, 3),
                    // Neuer Kern: Zuschauer der Bühne bringen neue Fans, dazu ein Hype-Schub für die neueste Platte
                    'coreEvent' => BandCore::$live ? Balance::CORE_EVENTS['festival'][$act['stage']] + ['live' => $sim['meter']] : null,
                    'venue' => $stage['venue'],
                    'meter' => $sim['meter'],
                    'drops' => $drops,
                    'festival' => ['event' => (int) $event['id'], 'name' => $data['name'], 'stage' => $stage['name'], 'headliner' => $act['headliner'], 'year' => self::year((int) $event['starts_at'])],
                ],
            ]);
            $data['acts'][$i]['meter'] = $sim['meter'];
        }
        $db->update('events', ['data' => json_encode($data, JSON_UNESCAPED_UNICODE)], ['id' => (int) $event['id']]);
    }

    /**
     * Beim Gutschreiben (Activities): Plakat für den Headliner, ein größeres Medium fragt nach einem Interview, Eintrag in die Chronik.
     * @return array die Angaben fürs Ergebnis, mit dem Medium der Anfrage
     */
    public static function settled(Repo $repo, int $bandId, array $info): array
    {
        $name = $repo->db->value('SELECT name FROM bands WHERE id = ?', [$bandId]) ?? '';
        if (!empty($info['headliner'])) {
            $repo->db->insert('awards', [
                'label_id' => $repo->labelId, 'band_id' => $bandId, 'kind' => self::KIND, 'place' => 1,
                'title' => sprintf('Headliner: %s %s', $info['name'], $info['year'] ?? ''), 'created_at' => Clock::now(),
            ]);
        }
        $members = $repo->members($bandId);
        if (BandCore::$live) {
            // Neuer Kern: eine Anfrage mit Bezug aufs Festival (nur mit Platte)
            $request = Inbox::eventRequest($repo, $bandId, sprintf('nach dem Festival „%s“', $info['name']));
            if ($request !== null) {
                $info['interview'] = $request;
            }
        } elseif ($members) {
            // Eine Stufe größer als sonst: Nach dem Festival kennt man die Band.
            $m = Rng::pick($members);
            $level = Levels::forFame((float) $m['fame']);
            $medium = Rng::pick(Catalog::INTERVIEW_MEDIA[min(3, $level + 1)]);
            (new Inbox($repo))->store('interview', ['musicianId' => $m['id'], 'level' => $level, 'medium' => $medium['name'], 'share' => $medium['share']], Clock::now(), Balance::POST['ttl']);
            $info['interview'] = ['medium' => $medium['name'], 'who' => $m['stage_name'] ?? $m['first_name']];
        }
        $repo->log('event', sprintf('%s hat beim Festival „%s“ gespielt: %s%s.', $name, $info['name'], $info['stage'], !empty($info['headliner']) ? ', als Headliner' : ''), $bandId);
        return $info;
    }

    private static function year(int $t): string
    {
        return (new DateTimeImmutable('@' . $t))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('Y');
    }

    // ---------- Anzeige ----------

    /** Das zuletzt festgelegte Festival (Line-up hängt aus, läuft, ist vorbei oder fiel aus). */
    private static function current(Db $db): ?array
    {
        return $db->one("SELECT * FROM events WHERE kind = ? AND state <> 'open' ORDER BY starts_at DESC LIMIT 1", [self::KIND]);
    }

    /**
     * Für den Spielstand: Bewerbung für das nächste Festival (mit den eigenen Bands) und das Line-up des aktuellen.
     * @param array<int, array> $bands eigene Bands, @param array<int, list<array>> $members Mitglieder je Band
     */
    public static function present(Repo $repo, array $bands, array $members, int $now): array
    {
        $db = $repo->db;
        $next = self::next($db, $now);
        $theme = self::theme($next['week']);
        $entries = Events::entries($db, (int) $next['id']);
        $entered = array_map('intval', array_column($entries, 'band_id'));
        $options = [];
        foreach ($bands as $band) {
            $in = in_array($band['id'], $entered, true);
            $options[] = [
                'bandId' => $band['id'], 'entered' => $in, 'blocked' => $in ? null : self::blocker($band, $members[$band['id']] ?? [], $next, $db),
                'gain' => self::weekGain($db, $band['id'], $now),
            ];
        }
        $event = self::current($db);
        $current = $event === null ? null : self::view($event, $repo->labelId, $now);
        return [
            'next' => $theme + [
                'startsAt' => (int) $next['starts_at'],
                'closesAt' => Events::closesAt($next),
                'when' => Events::when((int) $next['starts_at']),
                'closeWhen' => Events::when(Events::closesAt($next)),
                'slots' => self::slots(),
                'entries' => array_map(static fn ($e) => [
                    'bandId' => (int) $e['band_id'], 'name' => $e['band_name'], 'label' => $e['label_name'], 'mine' => (int) $e['label_id'] === $repo->labelId,
                ], $entries),
                'options' => $options,
            ],
            'current' => $current,
            'wakeAt' => min(array_filter([$current['nextAt'] ?? null, Events::closesAt($next)])),
            'rules' => [
                'days' => Balance::FESTIVAL['days'],
                'stages' => array_values(array_map(static fn ($k, $s) => ['key' => $k, 'name' => $s['name'], 'slots' => $s['slots'], 'songs' => $s['songs'], 'money' => $s['money'],
                    // Neuer Kern: Zuschauer der Bühne (daraus werden Fans)
                    'audience' => BandCore::$live ? Balance::CORE_EVENTS['festival'][$k]['audience'] : null],
                    array_keys(Balance::FESTIVAL['stages']), Balance::FESTIVAL['stages'])),
                'core' => BandCore::$live,
            ],
        ];
    }

    /** Ein festgelegtes Festival: Bühnen mit ihren Bands und Zeiten; wer gerade spielt, wer schon fertig ist. */
    private static function view(array $event, int $labelId, int $now): array
    {
        $data = json_decode((string) $event['data'], true) ?: [];
        $start = (int) $event['starts_at'];
        $base = ['id' => (int) $event['id'], 'name' => $data['name'] ?? '', 'genre' => $data['genre'] ?? null, 'startsAt' => $start, 'when' => Events::when($start)];
        if ($event['state'] === 'cancelled') {
            return $base + ['state' => 'cancelled', 'stages' => []];
        }
        $stages = [];
        foreach (Balance::FESTIVAL['stages'] as $key => $stage) {
            $stages[$key] = ['key' => $key, 'name' => $stage['name'], 'acts' => []];
        }
        $nextAt = $start > $now ? $start : null;
        foreach ($data['acts'] ?? [] as $act) {
            $done = $now >= $act['endsAt'];
            $stages[$act['stage']]['acts'][] = [
                'bandId' => $act['bandId'], 'name' => $act['name'], 'label' => $act['label'], 'mine' => $act['labelId'] === $labelId,
                'genres' => $act['genres'], 'at' => $act['at'], 'endsAt' => $act['endsAt'], 'headliner' => $act['headliner'],
                'live' => $now >= $act['at'] && !$done && empty($act['missing']), 'done' => $done, 'missing' => !empty($act['missing']) && $now >= $act['at'],
                'meter' => $done ? ($act['meter'] ?? null) : null,
            ];
            foreach ([$act['at'], $act['endsAt']] as $t) {
                if ($t > $now && ($nextAt === null || $t < $nextAt)) {
                    $nextAt = $t;
                }
            }
        }
        foreach ($stages as $key => $stage) {
            usort($stages[$key]['acts'], static fn ($x, $y) => $x['at'] <=> $y['at']);
        }
        return $base + [
            'state' => $now < $start ? 'lineup' : ($now >= (int) $event['ends_at'] ? 'done' : 'running'),
            'endsAt' => (int) $event['ends_at'],
            'stages' => array_values(array_filter($stages, static fn ($s) => $s['acts'])),
            'nextAt' => $nextAt,
        ];
    }
}
