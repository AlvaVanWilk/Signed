<?php
declare(strict_types=1);

use Signed\Clock;
use Signed\Game\BandCore;
use Signed\Game\Core\Career;
use Signed\Game\Repo;

// Neuer Kern im Spiel (Schalter BandCore::$live an): Speichern, Weiterrechnen, Geld, Chart-Stunden, Auszeichnungen.

/** Label mit Band, Schalter an. @return array{0: Signed\Db, 1: Player, 2: Repo, 3: array} */
function coreLabel(string $name = 'Kern'): array
{
    $db = freshDb();
    $p = registerPlayer($db, $name);
    $band = foundBand($p);
    BandCore::$live = true;
    $repo = new Repo($db, (int) $db->value('SELECT id FROM labels ORDER BY id DESC LIMIT 1'));
    return [$db, $p, $repo, $repo->band($band['id'])];
}

/** Fertige Platte in der Datenbank anlegen (wie nach dem Studio) und erscheinen lassen. */
function coreRelease(Repo $repo, array $band, float $quality, string $title = 'Erste'): int
{
    $id = $repo->db->insert('records', [
        'label_id' => $repo->labelId, 'band_id' => $band['id'], 'title' => $title, 'songs_total' => 12, 'songs_done' => 12,
        'song_results' => '[]', 'status' => 'done', 'created_at' => Clock::now(), 'finished_at' => Clock::now(),
    ]);
    BandCore::release($repo, $band, $id, $quality);
    return $id;
}

test('Kern im Spiel: Eine Platte verkauft Stunde für Stunde, Geld und Fans kommen an wie im Rechenwerk', function () {
    [$db, , $repo, $band] = coreLabel();
    $t0 = Clock::now();
    $moneyBefore = (int) $db->value('SELECT money FROM labels WHERE id = ?', [$repo->labelId]);
    $recordId = coreRelease($repo, $band, 80);
    Clock::advance(36 * 3600);
    BandCore::settleLabel($repo);
    // Dasselbe im Rechenwerk ohne Datenbank
    $career = Career::settle(Career::release(Career::start(0, BandCore::kind($band, $repo->members($band['id'])), $t0), $t0, 80), $t0 + 36 * 3600);
    coreNear($career['fans'], BandCore::fans($db, $band['id']), 1e-6, 'Fans');
    $sold = (float) $db->value('SELECT sold FROM records WHERE id = ?', [$recordId]);
    coreNear($career['sold'] + $career['catalogSold'], $sold, 1e-6, 'Verkauft');
    $money = (int) $db->value('SELECT money FROM labels WHERE id = ?', [$repo->labelId]) - $moneyBefore;
    check(abs($money - $sold * 10) < 1, "Geld $money für $sold Platten");
    // Je Stunde mitgeschrieben, zusammen so viel wie durch den Hype verkauft
    $hours = $db->all('SELECT hour, sold FROM chart_hours WHERE record_id = ? ORDER BY hour', [$recordId]);
    checkEquals(36, count($hours));
    coreNear($career['sold'], array_sum(array_map('floatval', array_column($hours, 'sold'))), 1e-6, 'Chart-Stunden');
    coreNear(48.0 / 24, (float) $hours[0]['sold'], 1e-6, 'Erste Stunde: ein 24stel des ersten Tages');
});

test('Kern im Spiel: Jeder Spielaufruf rechnet weiter, ohne Schalter bleibt alles beim Alten', function () {
    [$db, $p, $repo, $band] = coreLabel();
    coreRelease($repo, $band, 90);
    $before = (int) $db->value('SELECT money FROM labels WHERE id = ?', [$repo->labelId]);
    Clock::advance(5 * 3600);
    $p->get('state');
    check((int) $db->value('SELECT money FROM labels WHERE id = ?', [$repo->labelId]) > $before, 'Plattenverkäufe kommen beim Aufruf an');
    checkEquals(Clock::now(), (int) $db->value('SELECT core_at FROM bands WHERE id = ?', [$band['id']]));
    BandCore::$live = false;
    Clock::advance(5 * 3600);
    $p->get('state');
    checkEquals(Clock::now() - 5 * 3600, (int) $db->value('SELECT core_at FROM bands WHERE id = ?', [$band['id']]), 'Schalter aus: der neue Kern ruht');
});

test('Kern im Spiel: Auftritt verbraucht Frische, neue Fans kommen nach dem Auftritt', function () {
    [$db, , $repo, $band] = coreLabel();
    $db->exec('UPDATE bands SET fans = 500 WHERE id = ?', [$band['id']]);
    coreRelease($repo, $band, 80);
    $preview = BandCore::preview($repo, $band);
    $gig = BandCore::startGig($repo, $band, 70.0);
    coreNear($preview['tickets'], $gig['tickets'], 1e-9, 'Vorschau wie Auftritt');
    coreNear(0.5, (float) $db->value('SELECT fresh FROM bands WHERE id = ?', [$band['id']]));
    checkEquals(Clock::now(), (int) $db->value('SELECT last_gig_at FROM bands WHERE id = ?', [$band['id']]));
    $fans = BandCore::fans($db, $band['id']);
    Clock::advance(300);
    BandCore::finishGig($repo, $band, $gig['newFans']);
    check(BandCore::fans($db, $band['id']) >= $fans + $gig['newFans'] - 1e-6, 'Neue Fans sind da');
    $again = BandCore::preview($repo, $band);
    check($again['tickets'] < $gig['tickets'], 'Gleich noch einmal: weniger Zuschauer');
});

test('Kern im Spiel: Gold, Platin und Diamant nach den neuen Grenzen', function () {
    [$db, , $repo, $band] = coreLabel();
    $db->exec('UPDATE bands SET fans = 10000 WHERE id = ?', [$band['id']]);
    $recordId = coreRelease($repo, $band, 95);
    Clock::advance(40 * 86400);
    BandCore::settleLabel($repo);
    check((float) $db->value('SELECT sold FROM records WHERE id = ?', [$recordId]) > 5000, 'Mehr als 5.000 verkauft');
    checkEquals(1, (int) $db->value("SELECT COUNT(*) FROM awards WHERE record_id = ? AND kind = 'gold'", [$recordId]));
    checkEquals(0, (int) $db->value("SELECT COUNT(*) FROM awards WHERE record_id = ? AND kind = 'platinum'", [$recordId]));
});

test('Kern im Spiel: Ältere Platten laufen aus und verkaufen nur noch über den Katalog', function () {
    [$db, , $repo, $band] = coreLabel();
    $db->exec('UPDATE bands SET fans = 2000 WHERE id = ?', [$band['id']]);
    $first = coreRelease($repo, $band, 80, 'Erste');
    Clock::advance(7 * 86400);
    coreRelease($repo, $band, 85, 'Zweite');
    Clock::advance(14 * 86400);
    BandCore::settleLabel($repo);
    $core = json_decode((string) $db->value('SELECT core FROM records WHERE id = ?', [$first]), true);
    check($core['ended'] ?? false, 'Erste Platte ausgelaufen');
    checkEquals(1, (int) $db->value('SELECT core_active FROM records WHERE id = ?', [$first]), 'Katalog läuft noch');
    Clock::advance(30 * 7 * 86400);
    BandCore::settleLabel($repo);
    checkEquals(0, (int) $db->value('SELECT core_active FROM records WHERE id = ?', [$first]), 'Nach Monaten ganz fertig');
});

test('Kern im Spiel: Auftreten über die Schnittstelle – ein Knopf, kein Ort, keine Kosten, ohne Platte geht es auch', function () {
    [$db, $p, $repo, $band] = coreLabel();
    $state = $p->get('state');
    check($state['core'] === true, 'Spielstand meldet den neuen Kern');
    $view = bandById($state, $band['id'])['core'];
    checkEquals(0.0, (float) $view['fans']);
    checkEquals('kneipe', $view['gig']['venue']);
    checkEquals(25, $view['gig']['tickets'], 'Neue Band ohne Platte: 25 Neugierige');
    checkEquals(125, $view['gig']['earnings']);
    $money = (int) $db->value('SELECT money FROM labels WHERE id = ?', [$repo->labelId]);
    $res = $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'gig']);
    checkEquals($money, (int) $db->value('SELECT money FROM labels WHERE id = ?', [$repo->labelId]), 'Keine Kosten vorab');
    $activity = $db->one("SELECT * FROM activities WHERE band_id = ? AND type = 'gig' ORDER BY id DESC LIMIT 1", [$band['id']]);
    $data = json_decode($activity['data'], true);
    checkEquals(125, $data['reward']['money']);
    check($data['reward']['core']['newFans'] > 0, 'Aus Neugierigen werden Fans');
    Clock::advance((int) $activity['ends_at'] - Clock::now() + 1);
    $state = $p->get('state');
    checkEquals($money + 125, (int) $db->value('SELECT money FROM labels WHERE id = ?', [$repo->labelId]), 'Verdienst nach dem Auftritt');
    $result = array_values(array_filter($state['results'], static fn ($r) => $r['type'] === 'gig'))[0]['result'];
    checkEquals(25, $result['tickets']);
    check($result['fans'] > 0 && BandCore::fans($db, $band['id']) > 0, 'Neue Fans gutgeschrieben');
    coreNear(0.5 + 1 / 24 * (Clock::now() - (int) $activity['started_at']) / 3600, bandById($state, $band['id'])['core']['fresh'], 1e-3, 'Frische erholt sich');
});

test('Kern im Spiel: Songs entstehen einzeln mit eigener Qualität, im Tempo der neuen Formel', function () {
    [$db, $p, $repo, $band] = coreLabel();
    $p->get('state');
    $db->exec('UPDATE musicians SET wellbeing = 90 WHERE band_id = ?', [$band['id']]);
    Clock::advance(24 * 3600);
    $p->get('state');
    $written = (float) $db->value('SELECT songs_written FROM bands WHERE id = ?', [$band['id']]);
    $members = $repo->members($band['id']);
    $expected = Signed\Game\Core\Songs::perDay(Signed\Game\Core\Songs::writers($members), 'normal', 'green', 'idle', BandCore::kind($band, $members));
    check($written > 0.5 * $expected && $written <= $expected + 1e-6, "An einem Tag $written Songs (höchstens $expected)");
    $rows = $db->all('SELECT quality FROM songs WHERE band_id = ?', [$band['id']]);
    checkEquals((int) floor($written), count($rows), 'Jeder fertige Song liegt einzeln im Vorrat');
    foreach ($rows as $r) {
        check((float) $r['quality'] >= 0 && (float) $r['quality'] <= 100, 'Qualität 0–100');
    }
});

test('Kern im Spiel: Aufnehmen mit 12 Songs und einer Produktionsstufe, danach beginnt der Hype', function () {
    [$db, $p, $repo, $band] = coreLabel();
    $opts = $p->post('recordOptions', ['bandId' => $band['id']]);
    check(str_contains((string) $opts['blocker'], '12 Songs'), 'Ohne 12 Songs geht es nicht: ' . $opts['blocker']);
    for ($i = 0; $i < 14; $i++) {
        $db->insert('songs', ['label_id' => $repo->labelId, 'band_id' => $band['id'], 'quality' => 50 + $i * 3, 'record_id' => null, 'created_at' => Clock::now()]);
    }
    $db->exec('UPDATE bands SET songs_written = 14.5 WHERE id = ?', [$band['id']]);
    $opts = $p->post('recordOptions', ['bandId' => $band['id']]);
    checkEquals(null, $opts['blocker']);
    checkEquals(12, count($opts['picked']));
    checkEquals([300, 2000, 8000, 30000], array_column($opts['quality']['levels'], 'cost'));
    $best = array_slice(array_map(static fn ($i) => 50 + $i * 3, range(13, 0, -1)), 0, 12);
    coreNear(array_sum($best) / 12 / 100, $opts['quality']['songs'], 1e-6, 'Songs: Durchschnitt der besten 12');
    checkEquals(1.0, (float) $opts['quality']['levels'][0]['production'], 'Bei Ruhmstufe 0 ist die Garage passend');
    $money = (int) $db->value('SELECT money FROM labels WHERE id = ?', [$repo->labelId]);
    $p->post('recordStart', ['bandId' => $band['id'], 'title' => 'Neuland', 'level' => 0, 'songIds' => $opts['picked'], 'cover' => $opts['covers'][0]]);
    checkEquals($money - 300 - Signed\Game\Balance::COVER_DESIGNERS['student']['fee'], (int) $db->value('SELECT money FROM labels WHERE id = ?', [$repo->labelId]));
    checkEquals(2, (int) $db->value('SELECT COUNT(*) FROM songs WHERE band_id = ? AND record_id IS NULL', [$band['id']]), 'Zwei Songs bleiben im Vorrat');
    coreNear(2.5, (float) $db->value('SELECT songs_written FROM bands WHERE id = ?', [$band['id']]));
    Clock::advance(12 * Signed\Game\Balance::RECORD_SECONDS_PER_SONG + 2);
    $state = $p->get('state');
    $done = array_values(array_filter($state['results'], static fn ($r) => $r['type'] === 'record'))[0]['result'];
    check($done['core'] && $done['hype'] > 0, 'Die Platte erscheint mit Hype');
    $record = $db->one("SELECT * FROM records WHERE band_id = ? AND title = 'Neuland'", [$band['id']]);
    checkEquals('done', $record['status']);
    checkEquals(1, (int) $record['core_active']);
    coreNear((float) $done['quality'] * 100, json_decode($record['core'], true)['quality'], 1e-6, 'Kern rechnet mit derselben Qualität');
    checkEquals(0, (int) $db->value('SELECT COUNT(*) FROM ledger WHERE label_id = ? AND text LIKE ?', [$repo->labelId, 'Erstverkauf%']), 'Kein Erstverkauf mehr');
    check(bandById($state, $band['id'])['core']['record'] !== null, 'Spielstand zeigt die neue Platte');
});

test('Kern im Spiel: Charts nach den Verkäufen der letzten 24 Stunden, jede volle Stunde neu', function () {
    $db = freshDb();
    $repos = [];
    foreach (['Alpha' => [5000, 90], 'Beta' => [500, 80], 'Gamma' => [20000, 70]] as $name => [$fans, $quality]) {
        $p = registerPlayer($db, $name);
        $band = foundBand($p, "Band $name");
        BandCore::$live = true;
        $repo = new Repo($db, (int) $db->value('SELECT id FROM labels ORDER BY id DESC LIMIT 1'));
        $db->exec('UPDATE bands SET fans = ? WHERE id = ?', [$fans, $band['id']]);
        coreRelease($repo, $repo->band($band['id']), $quality, "Platte $name");
        $repos[$name] = [$p, $repo];
    }
    Clock::advance(26 * 3600);
    [$p] = $repos['Beta'];
    $charts = $p->get('charts');
    check($charts['core'], 'Neue Charts');
    $order = array_column($charts['albums'], 'title');
    checkEquals(['Platte Gamma', 'Platte Alpha', 'Platte Beta'], $order, 'Mehr Fans und Hype verkaufen mehr');
    checkEquals(1, $charts['albums'][0]['rank']);
    check($charts['albums'][0]['sold'] > $charts['albums'][1]['sold'], 'Verkäufe der letzten 24 Stunden absteigend');
    checkEquals(1, (int) $db->value("SELECT COUNT(*) FROM awards WHERE kind = 'number1'"), 'Platz 1 bei drei Labels');
    checkEquals(['Gamma Records', 'Alpha Records', 'Beta Records'], array_column($charts['labels'], 'label'), 'Label-Bestenliste nach Fans');
    checkEquals('Band Gamma', $charts['bands'][0]['band'], 'Fan-Lieblinge');
    check(isset($charts['sound']), 'Sound-Monster');
    check($charts['nextAt'] > Clock::now() && $charts['nextAt'] - Clock::now() <= 3600, 'Countdown bis zur vollen Stunde');
    $finance = $p->get('state')['finance'];
    check($finance['core'] && $finance['moneyPerHour'] > 0, 'Geld je Stunde aus den Platten');
});

test('Kern im Spiel: Battle zahlt die neuen Preise, Zuschauer bringen Fans, der Platz einen Hype-Schub', function () {
    $db = freshDb();
    $a = registerPlayer($db, 'Battler');
    $bandA = foundBand($a, 'Laute Lieder');
    $b = registerPlayer($db, 'Gegner');
    $bandB = foundBand($b, 'Leise Lieder');
    BandCore::$live = true;
    $repoA = new Repo($db, (int) $a->get('state')['label']['id']);
    $repoB = new Repo($db, (int) $b->get('state')['label']['id']);
    $next = $a->get('state')['battle']['next'];
    $a->post('battleEnter', ['bandId' => $bandA['id']]);
    $b->post('battleEnter', ['bandId' => $bandB['id']]);
    Clock::set($next['startsAt'] - 3600);
    coreRelease($repoA, $repoA->band($bandA['id']), 80, 'A');
    coreRelease($repoB, $repoB->band($bandB['id']), 80, 'B');
    Clock::set($next['startsAt'] + 5);
    $cur = $a->get('state')['battle']['current'];
    $moneyA = (int) $db->value('SELECT money FROM labels WHERE id = ?', [$repoA->labelId]);
    $moneyB = (int) $db->value('SELECT money FROM labels WHERE id = ?', [$repoB->labelId]);
    $hypeBefore = BandCore::view($db, $repoA->band($bandA['id']), $repoA->members($bandA['id']), (int) $cur['endsAt'] + 1)['hype'];
    Clock::set($cur['endsAt'] + 2);
    $resA = array_values(array_filter($a->get('state')['results'], static fn ($r) => $r['type'] === 'battle'))[0]['result'];
    $resB = array_values(array_filter($b->get('state')['results'], static fn ($r) => $r['type'] === 'battle'))[0]['result'];
    $won = $resA['battle']['place'] === 1 ? [$resA, $moneyA, $repoA] : [$resB, $moneyB, $repoB];
    $lost = $resA['battle']['place'] === 1 ? [$resB, $moneyB, $repoB] : [$resA, $moneyA, $repoA];
    checkEquals($won[1] + 12000, (int) $db->value('SELECT money FROM labels WHERE id = ?', [$won[2]->labelId]), 'Sieger: 12.000 ₲');
    checkEquals($lost[1] + 6000, (int) $db->value('SELECT money FROM labels WHERE id = ?', [$lost[2]->labelId]), 'Platz 2: 6.000 ₲');
    check($resA['fans'] > 0 && $resB['fans'] > 0, 'Zuschauer bringen neue Fans');
    checkEquals(0.0, (float) $resA['fame'], 'Kein Ruhm mehr');
    $hypeAfter = BandCore::view($db, $repoA->band($bandA['id']), $repoA->members($bandA['id']))['hype'];
    check($hypeAfter > $hypeBefore, "Schub nach Platz: $hypeBefore → $hypeAfter");
});

test('Kern im Spiel: Festival-Bühnen nach Fans, Zuschauer bringen Fans, die Bühne einen Hype-Schub', function () {
    $db = freshDb();
    $a = registerPlayer($db, 'Festa');
    $b = registerPlayer($db, 'Festb');
    BandCore::$live = true;
    $next = $a->get('state')['festival']['next'];
    $small = genreBand($db, $a, $next['genre'], 'Kleine Fische');
    $big = genreBand($db, $b, $next['genre'], 'Große Nummer');
    $db->exec('UPDATE bands SET fans = 3000 WHERE id = ?', [$big]);
    $db->exec('UPDATE bands SET fans = 100 WHERE id = ?', [$small]);
    $a->post('festivalEnter', ['bandId' => $small]);
    $b->post('festivalEnter', ['bandId' => $big]);
    $repoB = new Repo($db, (int) $b->get('state')['label']['id']);
    Clock::set($next['closesAt'] + 5);
    $cur = $a->get('state')['festival']['current'];
    checkEquals([$small, $big], array_column($cur['stages'][0]['acts'], 'bandId'), 'Die Band mit mehr Fans ist Headliner');
    coreRelease($repoB, $repoB->band($big), 80, 'Festivalplatte');
    $fans = BandCore::fans($db, $big);
    Clock::set((int) $cur['endsAt'] + 2);
    $res = array_values(array_filter($b->get('state')['results'], static fn ($r) => $r['type'] === 'festival'))[0]['result'];
    check($res['fans'] > 0 && BandCore::fans($db, $big) > $fans, 'Neue Fans vom Festival');
    checkEquals(0.0, (float) $res['fame']);
});
