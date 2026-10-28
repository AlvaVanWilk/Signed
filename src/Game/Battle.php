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
 * Battle of the Bands: jeden Samstag für alle Labels (Balance::BATTLE). Jedes Label meldet bis zu zwei Bands an.
 * Zu Beginn wird ausgelost und das ganze Turnier ausgespielt wie bei einer WM: erst Gruppen (jeder gegen jeden), dann
 * K.-o.-Runden bis zum Finale. Gezeigt wird es nach der Uhr, Runde für Runde. Wer ein Spiel gewinnt, entscheiden Sound,
 * Laune und Tagesform (Zufall); Ruhm zählt nicht. Bands eines Labels kommen möglichst in verschiedene Gruppen und spielen
 * in der ersten K.-o.-Runde möglichst nicht gegeneinander.
 * Alle Bands bleiben bis zum Schluss dort (so verrät die Uhr nicht, wie weit eine Band kommt); danach gibt es die Belohnung.
 */
final class Battle
{
    public const KIND = 'battle';
    private const GROUP_NAMES = 'ABCDEFGH';
    private const KO = [8 => ['r16', 'Achtelfinale'], 4 => ['qf', 'Viertelfinale'], 2 => ['sf', 'Halbfinale'], 1 => ['final', 'Finale']];

    /** Das nächste Battle, für das man sich anmelden kann. */
    public static function next(Db $db, int $now): array
    {
        return Events::next($db, self::KIND, Balance::BATTLE, $now);
    }

    /** Wie lange ein Spiel dauert: beide Bands spielen, dann berät die Jury. */
    public static function matchSeconds(): int
    {
        $c = Balance::BATTLE;
        return 2 * $c['songs'] * Balance::SONG_SECONDS + $c['jury'];
    }

    /** Laune als Faktor: bei mood.at genau 1, darüber besser, darunter schlechter. */
    public static function moodFactor(float $mood): float
    {
        $c = Balance::BATTLE['mood'];
        return 1 + ($mood - $c['at']) * $c['per'];
    }

    // ---------- Anmelden ----------

    /** Was gegen eine Anmeldung spricht, sonst null. */
    public static function blocker(Repo $repo, array $band, array $members, array $event, array $entries): ?string
    {
        $c = Balance::BATTLE;
        $start = (int) $event['starts_at'];
        if (count($members) < $c['members']) {
            return 'Beim Battle of the Bands treten nur Bands an, keine Solokünstler.';
        }
        if ($band['busy_until'] > $start) {
            return sprintf('%s ist dann noch unterwegs.', Bands::who($members));
        }
        foreach ($members as $m) {
            if ($m['busy_until'] > $start) {
                return sprintf('%s ist dann noch unterwegs.', $m['first_name']);
            }
        }
        if (Tours::active($repo->db, $band['id']) !== null) {
            return sprintf('%s ist auf Tournee.', Bands::who($members));
        }
        $refuser = Mood::refuser($members, self::KIND);
        if ($refuser !== null) {
            return sprintf('%s hat auf ein Battle gerade keine Lust.', $refuser['first_name']);
        }
        if (count(array_filter($entries, static fn ($e) => (int) $e['label_id'] === $repo->labelId)) >= $c['perLabel']) {
            return sprintf('Jedes Label darf höchstens %d Bands anmelden.', $c['perLabel']);
        }
        if (count($entries) >= $c['max']) {
            return 'Das Battle ist schon voll.';
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
            $entries = Events::entries($db, (int) $event['id']);
            if (in_array($bandId, array_map('intval', array_column($entries, 'band_id')), true)) {
                throw new ApiError('Die Band ist schon angemeldet.');
            }
            $blocker = self::blocker($repo, $band, $repo->members($bandId), $event, $entries);
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
            throw new ApiError('Die Band ist nicht angemeldet.');
        }
    }

    // ---------- Ablauf ----------

    /** Beginnen (auslosen und ausspielen) oder abschließen, je nachdem, was fällig ist. */
    public static function advance(Db $db, int $eventId, int $now): void
    {
        $db->transaction(static function () use ($db, $eventId, $now): void {
            $event = $db->one('SELECT * FROM events WHERE id = ?', [$eventId]);
            if ($event === null) {
                return;
            }
            if ($event['state'] === 'open' && (int) $event['starts_at'] <= $now) {
                if ($db->exec("UPDATE events SET state = 'running' WHERE id = ? AND state = 'open'", [$eventId]) === 1) {
                    self::draw($db, $event, $now);
                }
            } elseif ($event['state'] === 'running' && (int) $event['ends_at'] <= $now) {
                if ($db->exec("UPDATE events SET state = 'done' WHERE id = ? AND state = 'running'", [$eventId]) === 1) {
                    self::announce($db, $event);
                }
            }
        });
    }

    /** Wer antritt, so wie die Band zu Beginn ist; null, wenn sie nicht spielen kann. */
    private static function team(Repo $repo, int $bandId, int $start): ?array
    {
        $row = $repo->db->one('SELECT * FROM bands WHERE id = ? AND label_id = ? AND retired_at IS NULL', [$bandId, $repo->labelId]);
        if ($row === null) {
            return null;
        }
        $band = Repo::hydrateBand($row);
        $members = $repo->members($bandId);
        if (count($members) < Balance::BATTLE['members'] || $band['busy_until'] > $start) {
            return null;
        }
        foreach ($members as $m) {
            if ($m['busy_until'] > $start) {
                return null;
            }
        }
        $sound = BandCalc::bandSound($band, $members, $repo->gearByMusician());
        $mood = Mood::bandMood(array_column($members, 'wellbeing'))['value'];
        $identity = BandCalc::identity($members, $band['genre'], $band['genre2']);
        return [
            'id' => $bandId,
            'labelId' => $repo->labelId,
            'label' => $repo->label()['name'],
            'name' => $band['name'],
            'genres' => array_values(array_filter([$identity['primary'], $identity['second'], $identity['third']])),
            'sound' => $sound,
            'mood' => round($mood, 1),
            'strength' => round($sound * self::moodFactor($mood), 2),
            // Für die Ansage: wer spielt was
            'members' => array_map(static fn ($m) => [$m['stage_name'] ?? $m['first_name'], BandCalc::instrument($m)], $members),
        ];
    }

    private static function draw(Db $db, array $event, int $now): void
    {
        $c = Balance::BATTLE;
        $eventId = (int) $event['id'];
        $start = (int) $event['starts_at'];
        $teams = [];
        $repos = [];
        foreach ($db->all("SELECT * FROM event_entries WHERE event_id = ? AND status = 'in' ORDER BY created_at, id", [$eventId]) as $entry) {
            $labelId = (int) $entry['label_id'];
            if (!isset($repos[$labelId])) {
                // Die Laune so, wie sie jetzt wirklich ist (auch wenn das Label länger nicht da war).
                $repos[$labelId] = new Repo($db, $labelId);
                Mood::settle($repos[$labelId], $now);
            }
            $team = self::team($repos[$labelId], (int) $entry['band_id'], $start);
            if ($team === null) {
                $db->update('event_entries', ['status' => 'dropped'], ['id' => (int) $entry['id']]);
                $name = $db->value('SELECT name FROM bands WHERE id = ?', [(int) $entry['band_id']]) ?? 'Eine Band';
                $repos[$labelId]->log('event', sprintf('%s konnte beim Battle of the Bands nicht antreten.', $name), (int) $entry['band_id']);
                continue;
            }
            $teams[$team['id']] = $team;
        }
        if (count($teams) < $c['min']) {
            $db->update('events', ['state' => 'cancelled', 'ends_at' => $start, 'data' => json_encode(['teams' => (object) $teams], JSON_UNESCAPED_UNICODE)], ['id' => $eventId]);
            foreach ($teams as $t) {
                $repos[$t['labelId']]->log('event', 'Das Battle of the Bands fällt diesmal aus: Es haben sich zu wenige Bands angemeldet.', $t['id']);
            }
            return;
        }
        $plan = self::play($teams);
        $endsAt = $start + (count($plan['rounds']) - 1) * $c['round'] + self::matchSeconds();
        $db->update('events', ['ends_at' => $endsAt, 'data' => json_encode(['teams' => $teams] + $plan, JSON_UNESCAPED_UNICODE)], ['id' => $eventId]);

        $title = 'Battle of the Bands';
        foreach ($teams as $id => $t) {
            $repo = $repos[$t['labelId']];
            $r = $plan['results'][$id];
            $db->update('event_entries', ['place' => $r['place']], ['event_id' => $eventId, 'band_id' => $id]);
            $db->exec('UPDATE bands SET busy_until = ? WHERE id = ? AND busy_until < ?', [$endsAt, $id, $endsAt]);
            $members = $repo->members($id);
            $fame = $c['fame']['match'] * $r['played'] + $c['fame']['win'] * $r['wins'] + ($c['fame']['place'][$r['place']] ?? 0);
            $cheer = $c['cheer'][$r['place']] ?? 0;
            (new Activities($repo))->insertBand($id, self::KIND, $start, $endsAt, [
                'title' => $title,
                'songs' => 0,
                'reward' => [
                    'money' => $c['money'][$r['place']] ?? 0,
                    'fameGains' => BandCalc::splitFame($members, $fame, $repo->gearByMusician(), true),
                    'wellbeing' => $cheer > 0 ? array_fill_keys(array_column($members, 'id'), $cheer) : [],
                    'chartBoost' => round($r['played'] * $c['songs'] * Balance::CHART_GIG_BOOST, 3),
                    // Applaus wie in einer großen Halle
                    'venue' => 'halle',
                    'battle' => $r + ['event' => $eventId, 'of' => count($teams), 'day' => self::day($start)],
                ],
            ]);
        }
    }

    /** Am Ende: Alle Labels erfahren, wer gewonnen hat. */
    private static function announce(Db $db, array $event): void
    {
        $data = json_decode((string) $event['data'], true) ?: [];
        $byPlace = [];
        foreach ($data['results'] ?? [] as $id => $r) {
            if ($r['place'] !== null && $r['place'] <= 3) {
                $t = $data['teams'][$id];
                $byPlace[$r['place']] = sprintf('%s (%s)', $t['name'], $t['label']);
            }
        }
        if (!isset($byPlace[1])) {
            return;
        }
        $text = sprintf('Battle of the Bands: %s gewinnt!', $byPlace[1])
            . (isset($byPlace[2]) ? sprintf(' Zweiter: %s.', $byPlace[2]) : '')
            . (isset($byPlace[3]) ? sprintf(' Dritter: %s.', $byPlace[3]) : '');
        foreach ($db->all('SELECT id FROM labels') as $label) {
            (new Repo($db, (int) $label['id']))->log('event', $text);
        }
    }

    /** Beim Gutschreiben (Activities): Pokal für die ersten drei, Eintrag in die Chronik. */
    public static function settled(Repo $repo, int $bandId, array $info): void
    {
        $name = $repo->db->value('SELECT name FROM bands WHERE id = ?', [$bandId]) ?? '';
        $place = $info['place'] ?? null;
        if ($place !== null && $place <= 3) {
            $repo->db->insert('awards', [
                'label_id' => $repo->labelId, 'band_id' => $bandId, 'kind' => self::KIND, 'place' => $place,
                'title' => sprintf('Battle of the Bands %s', $info['day'] ?? ''), 'created_at' => Clock::now(),
            ]);
        }
        $repo->log('event', sprintf('%s beim Battle of the Bands: %s.', $name, self::placeText($info)), $bandId);
    }

    /** „gewonnen“, „Platz 3“, „im Viertelfinale ausgeschieden“ … */
    public static function placeText(array $info): string
    {
        $place = $info['place'] ?? null;
        if ($place === 1) {
            return 'gewonnen';
        }
        if ($place !== null) {
            return sprintf('Platz %d', $place);
        }
        return match ($info['reached'] ?? 'group') {
            'r16' => 'im Achtelfinale ausgeschieden',
            'qf' => 'im Viertelfinale ausgeschieden',
            'sf' => 'im Halbfinale ausgeschieden',
            default => 'in der Gruppe ausgeschieden',
        };
    }

    private static function day(int $t): string
    {
        return (new DateTimeImmutable('@' . $t))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('j.n.Y');
    }

    // ---------- Turnier (reine Regeln, ohne Datenbank) ----------

    /** Wie viele Gruppen: bis 2 Bands gleich das Finale, bis 5 eine Gruppe, dann 2, 4 oder 8 Gruppen. */
    public static function groupCount(int $n): int
    {
        return match (true) {
            $n <= 2 => 0,
            $n <= 5 => 1,
            $n <= 11 => 2,
            $n <= 23 => 4,
            default => 8,
        };
    }

    /**
     * Lost aus und spielt das ganze Turnier.
     * @param array<int, array{id: int, labelId: int, strength: float}> $teams
     * @return array{groups: list<list<int>>, rounds: list<array{k: string, name: string}>, matches: list<array>, results: array<int, array>}
     */
    public static function play(array $teams): array
    {
        $ids = Rng::sample(array_keys($teams), count($teams));
        $groupCount = self::groupCount(count($ids));
        $groups = $groupCount > 0 ? self::groups($teams, $ids, $groupCount) : [];
        $rounds = [];
        $matches = [];
        $results = array_fill_keys($ids, ['place' => null, 'reached' => 'group', 'played' => 0, 'wins' => 0]);
        $play = static function (int $r, string $kind, int $a, int $b, ?int $group) use ($teams, &$matches, &$results): array {
            $m = self::match($teams, $r, $kind, $a, $b, $group);
            $matches[] = $m;
            foreach ([$a, $b] as $id) {
                $results[$id]['played']++;
                $results[$id]['wins'] += $m['w'] === $id ? 1 : 0;
            }
            return $m;
        };

        // Gruppenspiele: jeder gegen jeden, alle Gruppen gleichzeitig
        $days = array_map([self::class, 'roundRobin'], $groups);
        $dayCount = $days ? max(array_map('count', $days)) : 0;
        for ($d = 0; $d < $dayCount; $d++) {
            $rounds[] = ['k' => 'group', 'name' => sprintf('Gruppenspiele, %d. Runde', $d + 1)];
            foreach ($days as $g => $list) {
                foreach ($list[$d] ?? [] as [$a, $b]) {
                    $play(count($rounds) - 1, 'group', $a, $b, $g);
                }
            }
        }
        $tables = array_map(static fn ($list) => self::table($list, $matches), $groups);

        // Wer weiterkommt
        $third = null;
        if ($groupCount === 0) {
            $pairs = [[$ids[0], $ids[1]]];
        } elseif ($groupCount === 1) {
            $t = array_column($tables[0], 'id');
            $pairs = [[$t[0], $t[1]]];
            if (count($t) >= 4) {
                $third = [$t[2], $t[3]];
            }
            // Wer es nicht ins Finale oder ins Spiel um Platz 3 schafft, behält seinen Platz in der Tabelle.
            foreach ($t as $i => $id) {
                if ($i >= ($third === null ? 2 : 4)) {
                    $results[$id]['place'] = $i + 1;
                }
            }
        } else {
            // Wie bei einer WM: Erster gegen Zweiten der Nachbargruppe, die Gruppensieger getrennt in beiden Hälften.
            $pairs = [];
            for ($g = 0; $g < $groupCount; $g += 2) {
                $pairs[] = [$tables[$g][0]['id'], $tables[$g + 1][1]['id']];
            }
            for ($g = 0; $g < $groupCount; $g += 2) {
                $pairs[] = [$tables[$g + 1][0]['id'], $tables[$g][1]['id']];
            }
            $pairs = self::separate($teams, $pairs);
        }

        // K.-o.-Runden bis zum Finale; die Verlierer des Halbfinales spielen gleichzeitig mit dem Finale um Platz 3.
        while (true) {
            [$key, $name] = self::KO[count($pairs)];
            $rounds[] = ['k' => $key, 'name' => $name];
            $r = count($rounds) - 1;
            $winners = [];
            $losers = [];
            foreach ($pairs as [$a, $b]) {
                $results[$a]['reached'] = $results[$b]['reached'] = $key;
                $m = $play($r, $key, $a, $b, null);
                $winners[] = $m['w'];
                $losers[] = $m['w'] === $a ? $b : $a;
            }
            if (count($pairs) === 1) {
                $results[$winners[0]]['place'] = 1;
                $results[$losers[0]]['place'] = 2;
                if ($third !== null) {
                    $m = $play($r, 'third', $third[0], $third[1], null);
                    $results[$m['w']]['place'] = 3;
                    $results[$m['w'] === $third[0] ? $third[1] : $third[0]]['place'] = 4;
                }
                break;
            }
            if (count($pairs) === 2) {
                $third = $losers;
            }
            $pairs = array_chunk($winners, 2);
        }
        return ['groups' => $groups, 'rounds' => $rounds, 'matches' => $matches, 'results' => $results];
    }

    /** Ein Spiel: Stärke (Sound × Laune) × Tagesform; die Jury vergibt die gerundete Punktzahl. */
    private static function match(array $teams, int $round, string $kind, int $a, int $b, ?int $group): array
    {
        $luck = Balance::BATTLE['luck'];
        $fa = Rng::float(1 - $luck, 1 + $luck);
        $fb = Rng::float(1 - $luck, 1 + $luck);
        $sa = $teams[$a]['strength'] * $fa;
        $sb = $teams[$b]['strength'] * $fb;
        $w = $sa >= $sb ? $a : $b;
        $pa = (int) round($sa);
        $pb = (int) round($sb);
        if ($pa === $pb) {
            // Gleich viele Punkte: Die Jury gibt dem Besseren einen dazu.
            $w === $a ? $pa++ : $pb++;
        }
        return ['r' => $round, 'k' => $kind, 'g' => $group, 'a' => $a, 'b' => $b, 'pa' => $pa, 'pb' => $pb, 'w' => $w, 'fa' => round($fa, 3), 'fb' => round($fb, 3)];
    }

    /** Gruppen auslosen: gleich groß (±1), die Bands eines Labels in verschiedenen Gruppen (so viele es Gruppen gibt). */
    private static function groups(array $teams, array $ids, int $count): array
    {
        $byLabel = [];
        foreach ($ids as $id) {
            $byLabel[$teams[$id]['labelId']][] = $id;
        }
        // Labels mit zwei Bands zuerst (usort ist stabil, sonst bleibt die ausgeloste Reihenfolge); jedes Label auf einmal
        // in die kleinsten Gruppen, je Band eine andere.
        usort($byLabel, static fn ($x, $y) => count($y) <=> count($x));
        $groups = array_fill(0, $count, []);
        foreach ($byLabel as $list) {
            $order = Rng::sample(range(0, $count - 1), $count);
            $sizes = array_map('count', $groups);
            usort($order, static fn ($x, $y) => $sizes[$x] <=> $sizes[$y]);
            foreach ($list as $k => $id) {
                $groups[$order[$k % $count]][] = $id;
            }
        }
        return $groups;
    }

    /**
     * Jeder gegen jeden (Kreis-Verfahren): Liste der Spieltage mit ihren Paarungen. Bei ungerader Zahl hat je Spieltag eine Band frei.
     * @return list<list<array{0: int, 1: int}>>
     */
    public static function roundRobin(array $ids): array
    {
        if (count($ids) % 2 === 1) {
            $ids[] = null;
        }
        $n = count($ids);
        $days = [];
        for ($d = 0; $d < $n - 1; $d++) {
            $pairs = [];
            for ($i = 0; $i < $n / 2; $i++) {
                [$a, $b] = [$ids[$i], $ids[$n - 1 - $i]];
                if ($a !== null && $b !== null) {
                    $pairs[] = $d % 2 === 0 ? [$a, $b] : [$b, $a];
                }
            }
            $days[] = $pairs;
            // Alle außer dem ersten rücken einen Platz weiter.
            $first = array_shift($ids);
            $last = array_pop($ids);
            array_unshift($ids, $first, $last);
        }
        return $days;
    }

    /** Erste K.-o.-Runde: Treffen zwei Bands eines Labels aufeinander, wird mit einem anderen Spiel getauscht, wenn das geht. */
    private static function separate(array $teams, array $pairs): array
    {
        $label = static fn (int $id): int => $teams[$id]['labelId'];
        foreach ($pairs as $i => [$a, $b]) {
            if ($label($a) !== $label($b)) {
                continue;
            }
            foreach ($pairs as $j => [$c, $d]) {
                if ($j !== $i && $label($a) !== $label($d) && $label($c) !== $label($b)) {
                    $pairs[$i][1] = $d;
                    $pairs[$j][1] = $b;
                    break;
                }
            }
        }
        return $pairs;
    }

    /**
     * Gruppentabelle aus den Spielen (nur die Gruppenspiele dieser Bands zählen).
     * Reihenfolge: Siege, dann Punkte-Unterschied, dann Punkte.
     * @return list<array{id: int, played: int, wins: int, points: int, against: int}>
     */
    public static function table(array $ids, array $matches): array
    {
        $rows = [];
        foreach ($ids as $id) {
            $rows[$id] = ['id' => $id, 'played' => 0, 'wins' => 0, 'points' => 0, 'against' => 0];
        }
        foreach ($matches as $m) {
            if ($m['k'] !== 'group' || !isset($rows[$m['a']], $rows[$m['b']])) {
                continue;
            }
            foreach ([[$m['a'], $m['pa'], $m['pb']], [$m['b'], $m['pb'], $m['pa']]] as [$id, $own, $other]) {
                $rows[$id]['played']++;
                $rows[$id]['points'] += $own;
                $rows[$id]['against'] += $other;
                $rows[$id]['wins'] += $m['w'] === $id ? 1 : 0;
            }
        }
        $rows = array_values($rows);
        usort($rows, static fn ($x, $y) => [$y['wins'], $y['points'] - $y['against'], $y['points'], $x['id']]
            <=> [$x['wins'], $x['points'] - $x['against'], $x['points'], $y['id']]);
        return $rows;
    }

    // ---------- Anzeige ----------

    /**
     * Für den Spielstand: Anmeldung zum nächsten Battle (mit den eigenen Bands) und das laufende oder letzte Battle.
     * @param array<int, array> $bands eigene Bands, @param array<int, list<array>> $members Mitglieder je Band
     */
    public static function present(Repo $repo, array $bands, array $members, int $now): array
    {
        $db = $repo->db;
        $next = self::next($db, $now);
        $entries = Events::entries($db, (int) $next['id']);
        $entered = array_map('intval', array_column($entries, 'band_id'));
        $options = [];
        foreach ($bands as $band) {
            $in = in_array($band['id'], $entered, true);
            $list = $members[$band['id']] ?? [];
            // Solokünstler tauchen hier gar nicht erst auf.
            if (!$in && count($list) < Balance::BATTLE['members']) {
                continue;
            }
            $options[] = [
                'bandId' => $band['id'], 'entered' => $in, 'blocked' => $in ? null : self::blocker($repo, $band, $list, $next, $entries),
                // So viel macht die Laune gerade aus (+0,05 = 5 % mehr Punkte)
                'moodBonus' => round(self::moodFactor(Mood::bandMood(array_column($list, 'wellbeing'))['value']) - 1, 3),
            ];
        }
        $latest = Events::latest($db, self::KIND, $now);
        $current = $latest === null ? null : self::view($latest, $repo->labelId, $now);
        return [
            'next' => [
                'startsAt' => (int) $next['starts_at'],
                'when' => Events::when((int) $next['starts_at']),
                'max' => Balance::BATTLE['max'],
                'perLabel' => Balance::BATTLE['perLabel'],
                'entries' => array_map(static fn ($e) => [
                    'bandId' => (int) $e['band_id'], 'name' => $e['band_name'], 'label' => $e['label_name'], 'mine' => (int) $e['label_id'] === $repo->labelId,
                ], $entries),
                'options' => $options,
            ],
            'current' => $current,
            // Dann soll der Browser neu fragen (nächste Runde, nächstes Ergebnis, Beginn).
            'wakeAt' => min(array_filter([$current['nextAt'] ?? null, (int) $next['starts_at']])),
            'rules' => [
                'round' => Balance::BATTLE['round'], 'match' => self::matchSeconds(), 'songs' => Balance::BATTLE['songs'],
                'luck' => Balance::BATTLE['luck'], 'moodMax' => round(self::moodFactor(100.0) - 1, 3), 'prizes' => Balance::BATTLE['money'],
            ],
        ];
    }

    /** Ein begonnenes Battle so weit, wie es nach der Uhr schon gespielt ist. */
    private static function view(array $event, int $labelId, int $now): array
    {
        $data = json_decode((string) $event['data'], true) ?: [];
        $start = (int) $event['starts_at'];
        $teams = [];
        foreach ($data['teams'] ?? [] as $id => $t) {
            $teams[(string) $id] = [
                'id' => (int) $id, 'name' => $t['name'], 'label' => $t['label'], 'mine' => (int) $t['labelId'] === $labelId,
                'genres' => $t['genres'], 'sound' => $t['sound'],
            ];
        }
        $base = ['id' => (int) $event['id'], 'startsAt' => $start, 'when' => Events::when($start), 'teams' => (object) $teams];
        if ($event['state'] === 'cancelled') {
            return $base + ['state' => 'cancelled'];
        }
        $round = Balance::BATTLE['round'];
        $len = self::matchSeconds();
        $rounds = [];
        $revealed = [];
        $live = [];
        $nextAt = null;
        foreach ($data['rounds'] as $r => $info) {
            $at = $start + $r * $round;
            $reveal = $at + $len;
            // K.-o.-Spiele stehen erst fest, wenn die Runde davor entschieden ist.
            $known = $r === 0 ? $start : $start + ($r - 1) * $round + $len;
            $list = [];
            foreach ($data['matches'] as $i => $m) {
                if ($m['r'] !== $r) {
                    continue;
                }
                $show = $m['k'] === 'group' || $now >= $known;
                $out = ['i' => $i, 'kind' => $m['k'], 'group' => $m['g'], 'a' => $show ? $m['a'] : null, 'b' => $show ? $m['b'] : null,
                    'live' => $now >= $at && $now < $reveal];
                if ($now >= $reveal) {
                    $out += ['pa' => $m['pa'], 'pb' => $m['pb'], 'w' => $m['w']];
                    $revealed[] = $m;
                } elseif ($out['live']) {
                    $live[] = ['i' => $i, 'a' => $m['a'], 'b' => $m['b'], 'kind' => $m['k'], 'group' => $m['g'], 'revealAt' => $reveal,
                        'lines' => self::lines($data['teams'], $m, (int) $event['id'], $i, $at)];
                }
                $list[] = $out;
            }
            $rounds[] = ['name' => $info['name'], 'kind' => $info['k'], 'at' => $at, 'revealAt' => $reveal, 'matches' => $list];
            foreach ([$at, $reveal] as $t) {
                if ($t > $now && ($nextAt === null || $t < $nextAt)) {
                    $nextAt = $t;
                }
            }
        }
        $groups = [];
        foreach ($data['groups'] as $g => $ids) {
            $groups[] = ['name' => 'Gruppe ' . self::GROUP_NAMES[$g], 'table' => self::table($ids, $revealed)];
        }
        $done = $now >= (int) $event['ends_at'];
        $places = [];
        if ($done) {
            foreach ($data['results'] as $id => $r) {
                $places[] = ['id' => (int) $id, 'place' => $r['place'], 'text' => self::placeText($r)];
            }
            usort($places, static fn ($x, $y) => [$x['place'] ?? 99, $x['id']] <=> [$y['place'] ?? 99, $y['id']]);
        }
        return $base + [
            'state' => $done ? 'done' : 'running',
            'endsAt' => (int) $event['ends_at'],
            'groups' => $groups,
            'rounds' => $rounds,
            'live' => $live,
            'places' => $places,
            'nextAt' => $nextAt,
        ];
    }

    /**
     * Was man während eines Spiels liest: die Lieder, je Lied ein Moment (gute Tagesform bringt Glanzmomente, schlechte Patzer),
     * das Publikum und die Jury. Für alle gleich (aus der Spielnummer abgeleitet), mit Uhrzeit.
     * @return list<array{at: int, side: string, kind: string, text: string}>
     */
    private static function lines(array $teams, array $m, int $eventId, int $i, int $at): array
    {
        $pick = static function (array $list, string $key) use ($eventId, $i) {
            return $list[abs(crc32("battle-$eventId-$i-$key")) % count($list)];
        };
        $song = Balance::SONG_SECONDS;
        $songs = Balance::BATTLE['songs'];
        $out = [];
        $t = $at;
        foreach (['a' => $m['fa'], 'b' => $m['fb']] as $side => $form) {
            $team = $teams[$m[$side]];
            for ($s = 0; $s < $songs; $s++) {
                $title = $pick(Catalog::SONG_WORDS[0], "$side$s-0") . $pick(Catalog::SONG_WORDS[1], "$side$s-1");
                $out[] = ['at' => $t + 1, 'side' => $side, 'kind' => 'song', 'text' => sprintf('%s spielt „%s“.', $team['name'], $title)];
                [$name, $instrument] = $pick($team['members'], "$side$s-p");
                // Erstes Lied: ab mittlerer Form glänzt jemand; zweites Lied: nur bei guter Form, bei schlechter patzt jemand.
                $moment = $s === 0 ? ($form >= 0.97 ? 'hl' : 'miss') : ($form >= 1.08 ? 'hl' : ($form < 0.92 ? 'miss' : null));
                if ($moment !== null) {
                    $texts = $moment === 'hl'
                        ? Catalog::HIGHLIGHTS[$instrument] ?? Catalog::HIGHLIGHTS['vocals']
                        : array_merge(Catalog::MISTAKES['any'], Catalog::MISTAKES[$instrument] ?? []);
                    $out[] = ['at' => $t + 12, 'side' => $side, 'kind' => $moment, 'text' => sprintf($pick($texts, "$side$s-t"), $name)];
                }
                $t += $song;
            }
            $crowd = $form >= 1.07 ? 'high' : ($form <= 0.93 ? 'low' : 'mid');
            $out[] = ['at' => $t - 3, 'side' => $side, 'kind' => 'crowd', 'text' => $pick(Catalog::CROWD[$crowd], "$side-c")];
        }
        $out[] = ['at' => $t + 1, 'side' => '', 'kind' => 'jury', 'text' => 'Die Jury berät …'];
        return $out;
    }
}
