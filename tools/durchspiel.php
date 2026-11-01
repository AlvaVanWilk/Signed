<?php
declare(strict_types=1);

/**
 * Durchspiel-Prüfung (docs/kern.md, Tempo): Ein Bot spielt ein neues Label im neuen Kern über die echte Spiel-Schnittstelle, mit einer
 * frischen Test-Datenbank; die Uhr wird vorgespult. Er hört Kassetten an, unterschreibt, gründet eine Band, kauft Instrumente, tritt auf,
 * nimmt eine Platte auf, sobald es geht (billigstes passendes Studio), nimmt Anfragen an und meldet sich für Battle und Festival.
 * Kurse, Lehrer, Scouts, Proberaum und Tourneen nutzt er nicht.
 *
 * Aufruf: php tools/durchspiel.php <taeglich|alle2tage|woechentlich> [Wochen] [Seed] [--quiet] [--why]
 *   taeglich: 8 und 19 Uhr (je bis zu 2 Auftritte), alle2tage: 19 Uhr, woechentlich: sonntags 15 Uhr (bis zu 3 Auftritte).
 *   --quiet: nur die Zeile KENNZAHLEN und die Zusammenfassung; --why: je Besuch, wer am schlechtesten drauf ist und warum.
 * Gibt je Besuch eine Zeile aus (Geld, Fans, Stufe, Hype, Platten, Können, Stimmung, Vorrat, Songs je Tag, was passiert ist) und am Ende,
 * wann welche Stufe erreicht wurde. SIGNED_ROOT=<Pfad> spielt mit einer anderen Kopie des Spiels (zum Vergleichen von Regeln).
 */

$root = getenv('SIGNED_ROOT') ?: dirname(__DIR__);
require $root . '/src/bootstrap.php';
require $root . '/tests/support.php';

use Signed\ApiError;
use Signed\Clock;
use Signed\Game\Balance;
use Signed\Game\BandCore;
use Signed\Game\Catalog;

$profile = $argv[1] ?? 'taeglich';
$weeks = (int) ($argv[2] ?? 8);
$seed = (int) ($argv[3] ?? 1);
$quiet = in_array('--quiet', $argv, true);
mt_srand($seed);
BandCore::$live = true;
$tz = new DateTimeZone('Europe/Berlin');
$start = (new DateTimeImmutable('2026-11-02 09:00', $tz))->getTimestamp(); // Montag
Clock::set($start);

final class Bot
{
    public array $s = [];
    public array $log = [];
    public array $reached = [];
    public array $events = [];
    public int $calls = 0;
    public array $errors = [];
    public string $genre = '';

    public function __construct(private Player $p)
    {
    }

    public function call(string $action, array $in = []): ?array
    {
        $this->calls++;
        try {
            $res = $this->p->post($action, $in);
        } catch (ApiError $e) {
            $this->errors[$action . ': ' . $e->getMessage()] = ($this->errors[$action . ': ' . $e->getMessage()] ?? 0) + 1;
            return null;
        }
        if (isset($res['state'])) {
            $this->s = $res['state'];
        }
        return $res;
    }

    public function refresh(): void
    {
        $this->calls++;
        $this->s = $this->p->get('state');
    }

    public function money(): int
    {
        return (int) $this->s['label']['money'];
    }

    private function musician(int $id): ?array
    {
        foreach ($this->s['musicians'] as $m) {
            if ($m['id'] === $id) {
                return $m;
            }
        }
        return null;
    }

    /** Hauptband: die Band mit den meisten Mitgliedern (mindestens 2). */
    public function main(): ?array
    {
        $best = null;
        foreach ($this->s['bands'] as $b) {
            if (count($b['memberIds']) >= 2 && ($best === null || count($b['memberIds']) > count($best['memberIds']))) {
                $best = $b;
            }
        }
        return $best;
    }

    private function busy(array $b): bool
    {
        if ($b['busyUntil'] > Clock::now()) {
            return true;
        }
        foreach ($b['memberIds'] as $id) {
            if (($this->musician($id)['busyUntil'] ?? 0) > Clock::now()) {
                return true;
            }
        }
        return false;
    }

    private function played(array $m): array
    {
        return $m['bandSlot'] === 2 && isset($m['instruments'][1]) ? $m['instruments'][1] : $m['instruments'][0];
    }

    // ---------- Post: auspacken, anhören, Anfragen annehmen, Manager einstellen ----------

    public function post(): void
    {
        foreach ($this->s['submissions'] as $x) {
            if ($x['state'] === 'envelope') {
                $this->call('postUnpack', ['table' => 's', 'id' => $x['submissionId']]);
                Clock::advance(Balance::POST['unpack'] + 1);
            }
        }
        foreach ($this->s['mail'] as $x) {
            if (($x['kind'] ?? '') === 'envelope') {
                $this->call('postUnpack', ['table' => 'm', 'id' => $x['mailId']]);
                Clock::advance(Balance::POST['unpack'] + 1);
            }
        }
        $this->refresh();
        foreach ($this->s['submissions'] as $x) {
            if ($x['state'] === 'closed' && $this->wanted($x, true)) {
                $this->call('submissionOpen', ['submissionId' => $x['submissionId']]);
                Clock::advance(Balance::SUBMISSIONS['listen'] + 1);
            }
        }
        $this->refresh();
        // Manager für die Hauptband, wenn genug Geld da ist
        $main = $this->main();
        if ($main !== null && $main['managerId'] === null && count($main['memberIds']) >= 3) {
            foreach ($this->s['mail'] as $m) {
                if (($m['kind'] ?? '') === 'job' && ($m['role'] ?? '') === 'manager' && $this->money() - (int) $m['price'] > 3000) {
                    if ($this->call('mailHire', ['mailId' => $m['mailId'], 'target' => $main['id']]) !== null) {
                        // Der neue Manager kümmert sich um die Hauptband
                        $free = array_values(array_filter($this->s['managers'] ?? [], static fn ($x) => empty($x['bandIds'] ?? $x['bands'] ?? [])));
                        $id = $free[0]['id'] ?? ($this->s['managers'][0]['id'] ?? null);
                        if ($id !== null) {
                            $this->call('bandSetManager', ['bandId' => $main['id'], 'managerId' => $id]);
                        }
                        $this->events[] = 'Manager';
                        break;
                    }
                }
            }
        }
    }

    /** Lohnt sich die Kassette? Vor dem Anhören nur nach dem Hinweis (Genre-Farben), danach nach Instrument, Genre und Können. */
    private function wanted(array $x, bool $beforeListening = false): bool
    {
        if ($beforeListening) {
            // Anhören kostet nur Zeit: alles, was zum Genre passen könnte
            $genres = $x['hint']['genres'] ?? [];
            return $this->genre === '' || !$genres || in_array($this->genre, $genres, true);
        }
        return false;
    }

    // ---------- Unterschreiben und Band bauen ----------

    /** Welche Instrumente der Hauptband noch fehlen (Grundbesetzung Gesang, Gitarre, Bass, Schlagzeug). */
    private function missing(): array
    {
        $want = ['vocals', 'guitar', 'bass', 'drums'];
        $main = $this->main();
        $have = [];
        foreach ($main['memberIds'] ?? [] as $id) {
            $m = $this->musician($id);
            if ($m) {
                $have[] = $this->played($m)['type'];
            }
        }
        return array_values(array_diff($want, $have));
    }

    public function sign(): void
    {
        $opened = array_values(array_filter($this->s['submissions'], static fn ($x) => $x['state'] === 'open'));
        if ($this->genre === '') {
            // Genre des Labels: das, in dem die besten gesichteten Musiker zusammen am meisten können
            $score = [];
            foreach ($opened as $x) {
                foreach ($x['kind'] === 'band' ? $x['members'] : [$x] as $m) {
                    foreach ($m['genres'] as $g) {
                        $score[$g] = ($score[$g] ?? 0) + $m['instruments'][0]['skill'];
                    }
                }
            }
            arsort($score);
            $this->genre = (string) (array_key_first($score) ?? 'rock');
        }
        // Am besten zuerst
        usort($opened, static fn ($a, $b) => ($b['kind'] === 'band' ? 999 : $b['instruments'][0]['skill']) <=> ($a['kind'] === 'band' ? 999 : $a['instruments'][0]['skill']));
        foreach ($opened as $x) {
            $missing = $this->missing();
            if (!$missing) {
                break;
            }
            $reserve = 500;
            if ($x['kind'] === 'band') {
                // Eine ganze Band nur am Anfang, wenn sie im Genre spielt
                if ($this->main() === null && in_array($this->genre, $x['members'][0]['genres'], true) && $this->money() - $x['price'] > $reserve) {
                    if ($this->call('submissionSign', ['submissionId' => $x['submissionId']]) !== null) {
                        $this->events[] = 'Band unterschrieben (' . count($x['members']) . ')';
                    }
                }
                continue;
            }
            $type = $x['instruments'][0]['type'];
            if (in_array($type, $missing, true) && in_array($this->genre, $x['genres'], true) && $this->money() - $x['price'] > $reserve) {
                if ($this->call('submissionSign', ['submissionId' => $x['submissionId']]) !== null) {
                    $this->events[] = 'unterschrieben: ' . $type;
                    $this->form();
                }
            }
        }
    }

    /** Solokünstler im Genre in die Hauptband holen (oder die erste Band aus zwei Solokünstlern gründen). */
    public function form(): void
    {
        $this->refresh();
        $solos = array_values(array_filter($this->s['bands'], fn ($b) => count($b['memberIds']) === 1 && in_array($this->genre, $this->musician($b['memberIds'][0])['genres'] ?? [], true)));
        $main = $this->main();
        foreach ($solos as $solo) {
            $m = $this->musician($solo['memberIds'][0]);
            if ($main === null) {
                $other = array_values(array_filter($solos, static fn ($x) => $x['id'] !== $solo['id']))[0] ?? null;
                if ($other === null) {
                    return;
                }
                if ($this->call('bandMerge', ['bandId' => $solo['id'], 'otherId' => $other['id'], 'keepName' => 'new', 'newName' => 'Die Durchspieler', 'slot' => 1]) !== null) {
                    $this->events[] = 'Band gegründet';
                    $this->form();
                }
                return;
            }
            if ($solo['id'] === $main['id'] || $this->busy($main) || $this->busy($solo)) {
                continue;
            }
            if (in_array($this->played($m)['type'], $this->missing(), true)) {
                if ($this->call('bandMerge', ['bandId' => $main['id'], 'otherId' => $solo['id'], 'keepName' => 'a', 'slot' => 1]) !== null) {
                    $this->events[] = 'dazu: ' . $this->played($m)['type'];
                    $main = $this->main();
                }
            }
        }
    }

    // ---------- Instrumente ----------

    public function gear(): void
    {
        $main = $this->main();
        if ($main === null) {
            return;
        }
        $owned = [];
        foreach ($this->s['gear'] as $g) {
            if ($g['musicianId'] !== null) {
                $owned[$g['musicianId']][] = $g;
            }
        }
        foreach ($main['memberIds'] as $id) {
            $m = $this->musician($id);
            $type = $this->played($m)['type'];
            $mine = array_values(array_filter($owned[$id] ?? [], static fn ($g) => (Catalog::GEAR[$g['model']]['type'] ?? '') === $type));
            if (!$mine) {
                // Ohne Instrument lernt niemand: das günstige graue aus dem Grundsortiment
                foreach ($this->s['store']['basics'] as $basic) {
                    if ($basic['type'] === $type && $this->money() > $basic['price']) {
                        $res = $this->call('storeBuyBasic', ['model' => $basic['model']]);
                        if ($res !== null) {
                            $this->call('gearAssign', ['gearId' => $res['gear']['id'], 'musicianId' => $id]);
                            $this->events[] = 'Instrument grau: ' . $type;
                        }
                        break;
                    }
                }
                continue;
            }
            // Besser werden: ein Instrument in der Farbe der Können-Stufe aus dem Regal, wenn genug Geld übrig bleibt
            $tier = max(array_map(static fn ($g) => (int) ($g['tier'] ?? 0), $mine));
            $stage = (int) $m['level'];
            if ($tier < $stage) {
                foreach ($this->s['store']['items'] as $item) {
                    if (!$item['bought'] && $item['type'] === $type && $item['tier'] > $tier && $item['tier'] <= $stage && $this->money() - $item['price'] > 500) {
                        $res = $this->call('storeBuy', ['slot' => $item['slot'], 'idx' => $item['idx']]);
                        if ($res !== null) {
                            $this->call('gearAssign', ['gearId' => $res['gear']['id'], 'musicianId' => $id]);
                            $this->events[] = 'Instrument Stufe ' . $item['tier'] . ': ' . $type;
                        }
                        break;
                    }
                }
            }
        }
    }

    // ---------- Band: Auszeit, Platte, Auftritt, Proben ----------

    /** Eine Runde für die Hauptband; gibt die Sekunden zurück, die das gedauert hat (0 = nichts getan). */
    public function act(bool $last): int
    {
        $main = $this->main();
        if ($main === null || $this->busy($main)) {
            return 0;
        }
        $b = $main;
        $red = [];
        foreach ($b['memberIds'] as $id) {
            $m = $this->musician($id);
            if (($m['wellbeing']['level'] ?? 'green') === 'red') {
                $red[] = $m;
            }
        }
        $likes = static fn (string $type) => (bool) array_filter($red, static fn ($m) => \Signed\Game\Mood::liking($m['personality'], $type) > 0);
        $canRecord = count($b['core']['stock'] ?? []) >= 10 && count($b['memberIds']) >= 3 && (int) ($b['core']['recordAt'] ?? 0) <= Clock::now();
        if ($red && !($likes('record') && $canRecord)) {
            // Erst, was die Roten mögen (hebt ihre Laune, erfüllt ihren Wunsch); sonst eine Auszeit
            foreach (['gig', 'rehearsal'] as $type) {
                if ($likes($type)) {
                    $res = $this->call('activityStart', ['bandId' => $b['id'], 'type' => $type]);
                    if ($res !== null) {
                        $this->events[] = ($type === 'gig' ? 'Auftritt' : 'Probe') . ' (für Rote)';
                        return $type === 'gig' ? max(60, (int) $res['activity']['endsAt'] - Clock::now()) : 0;
                    }
                }
            }
            if ($this->call('bandRetreat', ['bandId' => $b['id'], 'option' => 'ausflug']) !== null) {
                $this->events[] = 'Auszeit';
                return 0;
            }
        }
        // Platte, sobald 12 Songs da sind (Studio der eigenen Ruhmstufe, wenn bezahlbar)
        if ($canRecord) {
            $opts = $this->call('recordOptions', ['bandId' => $b['id']]);
            if ($opts !== null && $opts['blocker'] === null) {
                $stage = (int) $b['core']['stage'];
                for ($level = $stage; $level >= 0; $level--) {
                    $fee = (int) round(Balance::CORE_MONEY['production'][$level] * count($opts['picked']) / Balance::CORE_ALBUM_SONGS);
                    if ($this->money() >= $fee + 300) {
                        if ($this->call('recordStart', ['bandId' => $b['id'], 'title' => 'Platte ' . (count($this->events) + 1), 'level' => $level, 'songIds' => $opts['picked'], 'cover' => $opts['covers'][0]]) !== null) {
                            $this->events[] = sprintf('Platte (Studio %d, Q %.1f, Sound %.2f, Songs %.2f)', $level, $opts['quality']['levels'][$level]['quality'] ?? 0, $opts['quality']['sound'] ?? 0, $opts['quality']['songs'] ?? 0);
                            return 0;
                        }
                        break;
                    }
                }
            }
        }
        // Auftritt, wenn die Band frisch ist
        if (($b['core']['fresh'] ?? 1) >= 0.6) {
            $res = $this->call('activityStart', ['bandId' => $b['id'], 'type' => 'gig']);
            if ($res !== null) {
                $this->events[] = 'Auftritt';
                return max(60, (int) $res['activity']['endsAt'] - Clock::now());
            }
        }
        // Zum Schluss proben (läuft weiter, wenn man weg ist)
        if ($last) {
            if ($this->call('activityStart', ['bandId' => $b['id'], 'type' => 'rehearsal']) !== null) {
                $this->events[] = 'Probe';
            }
        }
        return 0;
    }

    /** Anfragen annehmen (am Ende der Sitzung): Sie schicken einen Musiker weg und heben den Hype der neuesten Platte. */
    public function requests(): bool
    {
        $main = $this->main();
        if ($main === null || ($main['core']['hype'] ?? 0) < 5 || $this->busy($main)) {
            return false;
        }
        foreach ($this->s['mail'] as $m) {
            if (($m['kind'] ?? '') === 'interview') {
                $mus = $this->musician((int) ($m['musicianId'] ?? 0));
                if ($mus !== null && $mus['busyUntil'] <= Clock::now() && $this->call('mailAccept', ['mailId' => $m['mailId']]) !== null) {
                    $this->events[] = 'Anfrage angenommen';
                    return true;
                }
            }
        }
        return false;
    }

    /** Festival und Battle: anmelden, wenn es geht. */
    public function enter(): void
    {
        foreach (['festival' => 'festivalEnter', 'battle' => 'battleEnter'] as $key => $action) {
            foreach ($this->s[$key]['next']['options'] ?? [] as $o) {
                if (!$o['entered'] && $o['blocked'] === null && ($this->main()['id'] ?? 0) === $o['bandId']) {
                    if ($this->call($action, ['bandId' => $o['bandId']]) !== null) {
                        $this->events[] = 'angemeldet: ' . $key;
                    }
                }
            }
        }
    }

    /** Eine Sitzung: Post, Unterschreiben, Instrumente, Anmelden, bis zu $gigs Auftritte, zum Schluss Probe. */
    public function session(int $gigs): void
    {
        $this->refresh();
        $this->post();
        $this->sign();
        $this->form();
        $this->gear();
        $this->enter();
        for ($i = 0; $i < $gigs; $i++) {
            $this->refresh();
            $took = $this->act(false);
            if ($took === 0) {
                break;
            }
            Clock::advance($took + 30);
        }
        // Zum Schluss: eine Anfrage, solange die neueste Platte noch Hype hat, sonst proben
        $this->refresh();
        if (!$this->requests()) {
            $this->act(true);
        }
    }

    public function snapshot(int $day): array
    {
        $this->refresh();
        $main = $this->main();
        $skills = [];
        $moods = ['green' => 0, 'yellow' => 0, 'red' => 0];
        foreach ($main['memberIds'] ?? [] as $id) {
            $m = $this->musician($id);
            $skills[] = $this->played($m)['skill'];
            $moods[$m['wellbeing']['level']]++;
        }
        $records = $main['records'] ?? 0;
        // Wer am schlechtesten drauf ist, und warum (sichtbar mit Manager)
        $low = null;
        foreach ($main['memberIds'] ?? [] as $id) {
            $m = $this->musician($id);
            if ($low === null || $m['wellbeing']['value'] < $low['wellbeing']['value']) {
                $low = $m;
            }
        }
        $why = '';
        if ($low !== null) {
            $why0 = json_decode(json_encode($main['mood']['why'] ?? []), true); $items = $why0[$low['id']]['items'] ?? [];
            usort($items, static fn ($a, $b) => $a[1] <=> $b[1]);
            $why = sprintf('%s %s (%.0f): ', $low['firstName'], implode('/', $low['personality']), $low['wellbeing']['value'])
                . implode('; ', array_map(static fn ($i) => $i[0] . ' ' . round($i[1]), array_slice(array_filter($items, static fn ($i) => $i[1] < 0), 0, 3)));
        }
        return [
            'day' => $day,
            'money' => $this->money(),
            'fans' => (int) ($main['core']['fans'] ?? 0),
            'stage' => (int) ($main['core']['stage'] ?? 0),
            'hype' => round((float) ($main['core']['hype'] ?? 0), 1),
            'records' => is_array($records) ? count($records) : (int) $records,
            'members' => count($main['memberIds'] ?? []),
            'skill' => $skills ? round(array_sum($skills) / count($skills)) : 0,
            'mood' => sprintf('%d/%d/%d', $moods['green'], $moods['yellow'], $moods['red']),
            'stock' => count($main['core']['stock'] ?? []),
            'rate' => round((float) ($main['mood']['songRate'] ?? 0) * 24, 2),
            'bandMood' => $main['mood']['level'] ?? '-',
            'kind' => $main['core']['kind'] ?? '-',
            'why' => $why,
        ];
    }
}

$db = freshDb();
$bot = new Bot(player($db));
$bot->call('register', ['username' => 'bot', 'password' => 'geheim123', 'labelName' => 'Bot Records']);

// Sitzungen: täglich 8 und 19 Uhr (je bis zu 2 Auftritte), alle zwei Tage 19 Uhr (2), wöchentlich Sonntag 15 Uhr (3)
$sessions = [];
for ($d = 0; $d < $weeks * 7; $d++) {
    $day = (new DateTimeImmutable('@' . $start))->setTimezone($tz)->setTime(0, 0)->modify("+$d day");
    if ($profile === 'taeglich') {
        $sessions[] = [$day->setTime(8, 0)->getTimestamp(), 2, $d];
        $sessions[] = [$day->setTime(19, 0)->getTimestamp(), 2, $d];
    } elseif ($profile === 'alle2tage' && $d % 2 === 0) {
        $sessions[] = [$day->setTime(19, 0)->getTimestamp(), 2, $d];
    } elseif ($profile === 'woechentlich' && $day->format('N') === '7') {
        $sessions[] = [$day->setTime(15, 0)->getTimestamp(), 3, $d];
    }
}
$lastDay = -1;
$rows = [];
foreach ($sessions as [$at, $gigs, $d]) {
    if ($at > Clock::now()) {
        Clock::set($at);
    }
    $bot->events = [];
    $bot->session($gigs);
    $snap = $bot->snapshot($d);
    foreach ([1 => 500, 2 => 5000, 3 => 50000] as $stage => $fans) {
        if (!isset($bot->reached[$stage]) && $snap['fans'] >= $fans) {
            $bot->reached[$stage] = $d;
        }
    }
    $rows[$d] = $snap + ['events' => implode(', ', array_unique($bot->events))];
    if (in_array('--why', $argv, true)) {
        echo date('d.m. H:i', Clock::now()), ' ', $snap['why'], "\n";
    }
}
// Ende: bis zum letzten Tag vorspulen
Clock::set((new DateTimeImmutable('@' . $start))->setTimezone($tz)->setTime(23, 0)->modify('+' . ($weeks * 7 - 1) . ' day')->getTimestamp());
$end = $bot->snapshot($weeks * 7 - 1);

if (!$quiet) {
    printf("%-4s %8s %7s %2s %6s %3s %4s %6s %6s %4s %5s %-6s %-8s %s\n", 'Tag', 'Geld', 'Fans', 'St', 'Hype', 'Pl', 'Mitg', 'Können', 'Laune', 'Vorr', 'S/Tag', 'Band', 'Art', 'Was');
    foreach ($rows as $r) {
        printf("%-4d %8s %7s %2d %6.1f %3d %4d %6d %6s %4d %5.2f %-6s %-8s %s\n", $r['day'] + 1, number_format($r['money'], 0, ',', '.'), number_format($r['fans'], 0, ',', '.'), $r['stage'], $r['hype'], $r['records'], $r['members'], $r['skill'], $r['mood'], $r['stock'], $r['rate'], $r['bandMood'], $r['kind'], mb_substr($r['events'], 0, 80));
    }
}
// Kennzahlen: Sitzungen mit roter Band, längste rote Strecke (Sitzungen am Stück), Tag der ersten Platte
$redSessions = 0; $streak = 0; $maxStreak = 0; $firstRecord = null;
foreach ($rows as $r) {
    $isRed = $r['bandMood'] === 'red';
    $redSessions += $isRed ? 1 : 0;
    $streak = $isRed ? $streak + 1 : 0;
    $maxStreak = max($maxStreak, $streak);
    if ($firstRecord === null && $r['records'] > 0) { $firstRecord = $r['day'] + 1; }
}
printf("KENNZAHLEN %s seed=%d fans=%d stage=%d records=%d money=%d red=%d/%d streak=%d first=%s s500=%s s5000=%s\n", $profile, $seed, $end['fans'], $end['stage'], $end['records'], $end['money'], $redSessions, count($rows), $maxStreak, $firstRecord ?? '-', isset($bot->reached[1]) ? $bot->reached[1] + 1 : '-', isset($bot->reached[2]) ? $bot->reached[2] + 1 : '-');
printf("\n%s, %d Wochen, Seed %d: Ende Tag %d – Geld %s ₲, Fans %s (Stufe %d), Platten %d, Können %d, Laune %s\n", $profile, $weeks, $seed, $end['day'] + 1,
    number_format($end['money'], 0, ',', '.'), number_format($end['fans'], 0, ',', '.'), $end['stage'], $end['records'], $end['skill'], $end['mood']);
foreach ([1 => 500, 2 => 5000, 3 => 50000] as $stage => $fans) {
    printf("  %s Fans: %s\n", number_format($fans, 0, ',', '.'), isset($bot->reached[$stage]) ? 'Tag ' . ($bot->reached[$stage] + 1) : 'nicht erreicht');
}
arsort($bot->errors);
printf("  Aufrufe: %d, abgelehnt: %d (%s)\n", $bot->calls, array_sum($bot->errors), implode(' | ', array_map(static fn ($k, $v) => sprintf('%d× %s', $v, $k), array_slice(array_keys($bot->errors), 0, 6), array_slice($bot->errors, 0, 6))));
