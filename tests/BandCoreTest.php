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

test('Kern im Spiel: Regler fürs Songschreiben – sorgfältig schreibt weniger, aber bessere Songs', function () {
    [$db, $p, $repo, $band] = coreLabel();
    $p->get('state');
    checkEquals('normal', bandById($p->get('state'), $band['id'])['core']['writeMode']);
    checkApiError(fn () => $p->post('bandWriteMode', ['bandId' => $band['id'], 'mode' => 'turbo']), 'nicht schreiben');
    $state = $p->post('bandWriteMode', ['bandId' => $band['id'], 'mode' => 'careful'])['state'];
    checkEquals('careful', bandById($state, $band['id'])['core']['writeMode']);
    $db->exec('UPDATE musicians SET wellbeing = 90 WHERE band_id = ?', [$band['id']]);
    Clock::advance(24 * 3600);
    $p->get('state');
    $written = (float) $db->value('SELECT songs_written FROM bands WHERE id = ?', [$band['id']]);
    $members = $repo->members($band['id']);
    $expected = Signed\Game\Core\Songs::perDay(Signed\Game\Core\Songs::writers($members), 'careful', 'green', 'idle', BandCore::kind($band, $members));
    check($written <= $expected + 1e-6, "Sorgfältig: $written Songs an einem Tag (höchstens $expected)");
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
    // Preisgeld als eigene Buchung (daneben laufen die Plattenverkäufe weiter)
    $prize = static fn (Repo $repo) => (int) $db->value("SELECT COALESCE(SUM(amount), 0) FROM ledger WHERE label_id = ? AND text LIKE '%Battle of the Bands%'", [$repo->labelId]);
    checkEquals(6000, $prize($lost[2]), 'Platz 2: 6.000 ₲');
    // Der Sieger wählt: Preisgeld oder der Wochenpreis, der schon auf dem Plakat stand
    checkEquals(0, $prize($won[2]), 'Sieger: erst die Wahl');
    checkEquals(Signed\Game\Prizes::weekly((int) $next['startsAt']), $won[0]['prize']['weekly']);
    checkEquals($next['weekly'], $won[0]['prize']['weekly'], 'Wochenpreis wie auf dem Plakat');
    $winner = $won[2]->labelId === $repoA->labelId ? $a : $b;
    $prizes = $winner->get('state')['prizes'];
    checkEquals('choice', $prizes[0]['kind']);
    checkApiError(fn () => $winner->post('prizeChoose', ['prizeId' => $prizes[0]['id'], 'pick' => 'beides']), 'gibt es nicht');
    $winner->post('prizeChoose', ['prizeId' => $prizes[0]['id'], 'pick' => 'money']);
    checkEquals(12000, $prize($won[2]), 'Sieger: 12.000 ₲');
    checkEquals([], $winner->get('state')['prizes'], 'Gewählt ist gewählt');
    check($resA['fans'] > 0 && $resB['fans'] > 0, 'Zuschauer bringen neue Fans');
    checkEquals(0.0, (float) $resA['fame'], 'Kein Ruhm mehr');
    $hypeAfter = BandCore::view($db, $repoA->band($bandA['id']), $repoA->members($bandA['id']))['hype'];
    check($hypeAfter > $hypeBefore, "Schub nach Platz: $hypeBefore → $hypeAfter");
});

test('Kern im Spiel: Wochenpreise vom Battle – blaues Instrument als Gutschein, Aufnahme umsonst, besserer Proberaum, Song-Coach', function () {
    [$db, $p, $repo, $band] = coreLabel();
    $offer = static fn (string $prize) => Signed\Game\Prizes::offer($repo, $band['id'], $prize);
    // Jede Woche ein Wochenpreis, für alle gleich
    $keys = [];
    for ($w = 0; $w < 12; $w++) {
        $keys[Signed\Game\Prizes::weekly(Clock::now() + $w * 7 * 86400)] = true;
    }
    check(count($keys) >= 3, 'Der Wochenpreis wechselt');
    // Proberaum
    $room = (int) $db->value('SELECT room FROM bands WHERE id = ?', [$band['id']]);
    $p->post('prizeChoose', ['prizeId' => $offer('room_upgrade'), 'pick' => 'weekly']);
    checkEquals($room + 1, (int) $db->value('SELECT room FROM bands WHERE id = ?', [$band['id']]), 'Proberaum eine Stufe besser');
    // Song-Coach für drei Tage
    $p->post('prizeChoose', ['prizeId' => $offer('coach_days'), 'pick' => 'weekly']);
    $coach = json_decode((string) $db->value("SELECT data FROM staff WHERE role = 'coach' AND label_id = ?", [$repo->labelId]), true);
    checkEquals($band['id'], (int) $coach['bandId']);
    checkEquals(3 * 86400, (int) $coach['until'] - (int) $coach['from'], 'drei Tage');
    // Blaues Instrument: Gutschein, dann aussuchen
    $p->post('prizeChoose', ['prizeId' => $offer('blue_instrument'), 'pick' => 'weekly']);
    $voucher = array_values(array_filter($p->get('state')['prizes'], static fn ($x) => $x['prize'] === 'blue_instrument'))[0];
    checkEquals('voucher', $voucher['kind']);
    $gear = (int) $db->value('SELECT COUNT(*) FROM gear WHERE label_id = ?', [$repo->labelId]);
    checkApiError(fn () => $p->post('prizeRedeem', ['prizeId' => $voucher['id'], 'type' => 'kazoo']), 'gibt es nicht');
    $p->post('prizeRedeem', ['prizeId' => $voucher['id'], 'type' => 'guitar']);
    checkEquals($gear + 1, (int) $db->value('SELECT COUNT(*) FROM gear WHERE label_id = ?', [$repo->labelId]));
    $model = (string) $db->value('SELECT model FROM gear WHERE label_id = ? ORDER BY id DESC LIMIT 1', [$repo->labelId]);
    checkEquals(2, Signed\Game\Gear::modelTier($model), 'blau');
    checkEquals('guitar', Signed\Game\Catalog::GEAR[$model]['type']);
    // Aufnahme umsonst: Gutschein der Band, gilt für die Produktion ihrer Ruhmstufe
    $p->post('prizeChoose', ['prizeId' => $offer('free_production'), 'pick' => 'weekly']);
    checkEquals(true, $p->post('recordOptions', ['bandId' => $band['id']])['voucher'] ?? null);
    for ($i = 0; $i < 12; $i++) {
        $db->insert('songs', ['label_id' => $repo->labelId, 'band_id' => $band['id'], 'quality' => 70, 'record_id' => null, 'created_at' => Clock::now()]);
    }
    $db->exec('UPDATE bands SET songs_written = 12 WHERE id = ?', [$band['id']]);
    Clock::advance(3 * 86400 + 60);
    $opts = $p->post('recordOptions', ['bandId' => $band['id']]);
    $money = (int) $db->value('SELECT money FROM labels WHERE id = ?', [$repo->labelId]);
    $p->post('recordStart', ['bandId' => $band['id'], 'title' => 'Gewonnen', 'level' => 0, 'songIds' => $opts['picked'], 'cover' => $opts['covers'][0]]);
    checkEquals($money - Signed\Game\Balance::COVER_DESIGNERS['student']['fee'], (int) $db->value('SELECT money FROM labels WHERE id = ?', [$repo->labelId]), 'Studio umsonst, nur das Cover kostet');
    checkEquals(null, Signed\Game\Prizes::production($repo, $band['id']), 'Gutschein eingelöst');
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

test('Kern im Spiel: Manager – Verhandler bringt mehr je Zuschauer, PR-Profi mehr neue Fans, Netzwerker mehr Hype aus Anfragen', function () {
    [$db, $p, $repo, $band] = coreLabel();
    $id = $repo->insertManager(['first_name' => 'Mo', 'last_name' => 'Ney', 'rarity' => 'epic', 'level' => 3, 'skills' => ['verhandler', 'prprofi', 'netzwerker'], 'look' => []]);
    $db->exec('UPDATE bands SET manager_id = ?, manager_since = ? WHERE id = ?', [$id, Clock::now(), $band['id']]);
    [, $level] = $repo->bandManager($repo->band($band['id']));
    check($level > 0, 'Manager wirkt');
    $earn = (int) round(125 * (1 + $level * Signed\Game\Balance::NEGOTIATOR_BONUS));
    checkEquals($earn, bandById($p->get('state'), $band['id'])['core']['gig']['earnings'], 'Vorschau mit Verhandler');
    $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'gig']);
    $data = json_decode((string) $db->value("SELECT data FROM activities WHERE band_id = ? AND type = 'gig' ORDER BY id DESC LIMIT 1", [$band['id']]), true);
    checkEquals($earn, $data['reward']['money'], 'Verdienst mit Verhandler');
    // Neue Band ohne Platte: 25 Neugierige, davon werden u × Live-Qualität Fans
    $base = 25 * Signed\Game\Balance::CORE_CONCERT['u'] * $data['reward']['meter'] / 100;
    coreNear($base * (1 + $level * Signed\Game\Balance::PR_BONUS), (float) $data['reward']['core']['newFans'], 0.01, 'Neue Fans mit PR-Profi');
    Clock::advance(3600);
    $mail = $db->insert('mail', ['label_id' => $repo->labelId, 'kind' => 'interview', 'created_at' => Clock::now(), 'expires_at' => Clock::now() + 86400, 'unpacked_at' => Clock::now(),
        'data' => json_encode(['musicianId' => $repo->members($band['id'])[0]['id'], 'level' => 1, 'medium' => 'Stadtmagazin', 'share' => 0.1, 'request' => 'interview'])]);
    $p->post('mailAccept', ['mailId' => $mail]);
    $data = json_decode((string) $db->value("SELECT data FROM activities WHERE type = 'interview' ORDER BY id DESC LIMIT 1"), true);
    coreNear(Signed\Game\Balance::CORE_EVENTS['requests']['interview']['boost'] * (1 + $level * Signed\Game\Balance::MANAGER_STAFF['network']), (float) $data['reward']['boost'], 0.01, 'Schub mit Netzwerker');
});

test('Kern im Spiel: Anfragen statt Interviews – erst mit Platte, Hype-Schub für die neueste Platte, Werbung bringt Geld', function () {
    [$db, $p, $repo, $band] = coreLabel();
    $db->exec('UPDATE labels SET interview_at = ? WHERE id = ?', [Clock::now() - 86400, $repo->labelId]);
    $p->get('state');
    checkEquals(0, (int) $db->value("SELECT COUNT(*) FROM mail WHERE kind = 'interview'"), 'Ohne Platte keine Anfragen');
    $db->exec('UPDATE bands SET fans = 4000 WHERE id = ?', [$band['id']]);
    coreRelease($repo, $band, 80);
    $db->exec('UPDATE labels SET interview_at = ? WHERE id = ?', [Clock::now() - 3 * 86400, $repo->labelId]);
    $p->get('state');
    unpackAll($db);
    $requests = array_values(array_filter($p->get('state')['mail'], static fn ($m) => ($m['request'] ?? null) !== null));
    check($requests !== [], 'Anfragen kommen');
    foreach (array_keys(Signed\Game\Balance::CORE_EVENTS['requests']) as $kind) {
        $db->insert('mail', ['label_id' => $repo->labelId, 'kind' => 'interview', 'created_at' => Clock::now(), 'expires_at' => Clock::now() + 86400, 'unpacked_at' => Clock::now(),
            'data' => json_encode(['musicianId' => $repo->members($band['id'])[0]['id'], 'level' => 1, 'medium' => 'Stadtmagazin', 'share' => 0.1, 'request' => $kind])]);
    }
    $ad = array_values(array_filter($p->get('state')['mail'], static fn ($m) => ($m['request'] ?? null) === 'ad'))[0];
    checkEquals(1200, $ad['seconds']);
    $hype = BandCore::view($db, $band, $repo->members($band['id']))['hype'];
    $money = (int) $db->value('SELECT money FROM labels WHERE id = ?', [$repo->labelId]);
    $p->post('mailAccept', ['mailId' => $ad['mailId']]);
    Clock::advance(1201);
    $p->get('state');
    $fans = BandCore::fans($db, $band['id']);
    check((int) $db->value('SELECT money FROM labels WHERE id = ?', [$repo->labelId]) >= $money + (int) floor($fans) - 1, 'Werbung: so viele ₲, wie die Band Fans hat');
    check(BandCore::view($db, $band, $repo->members($band['id']))['hype'] > $hype - 0.5, 'Schub für die neueste Platte');
});

test('Kern im Spiel: Ein altes Interview gilt als Interview-Anfrage, die Test-Briefe sind Anfragen', function () {
    [$db, $p, $repo, $band] = coreLabel();
    $mail = $db->insert('mail', ['label_id' => $repo->labelId, 'kind' => 'interview', 'created_at' => Clock::now(), 'expires_at' => Clock::now() + 86400, 'unpacked_at' => Clock::now(),
        'data' => json_encode(['musicianId' => $repo->members($band['id'])[0]['id'], 'level' => 1, 'medium' => 'Stadtmagazin', 'share' => 0.1])]);
    $old = array_values(array_filter($p->get('state')['mail'], static fn ($m) => $m['mailId'] === $mail))[0];
    checkEquals('interview', $old['request'], 'Altes Interview zeigt sich als Anfrage');
    checkEquals(0, $old['fame'], 'Keine Bekanntheit mehr im Brief');
    checkEquals(Signed\Game\Balance::CORE_EVENTS['requests']['interview']['seconds'], $old['seconds'], 'Dauer wie beim Zusagen');
    $p->post('mailTest');
    unpackAll($db);
    $letters = array_values(array_filter($p->get('state')['mail'], static fn ($m) => $m['kind'] === 'interview'));
    check($letters !== [], 'Test-Briefe sind da');
    foreach ($letters as $m) {
        check(isset(Signed\Game\Balance::CORE_EVENTS['requests'][$m['request'] ?? '']), 'Test-Brief ist eine Anfrage');
    }
});

test('Kern im Spiel: Kassetten wachsen mit den Fans (500 / 5.000 / 50.000 Fans wie Label-Ruhm 100 / 1.000 / 10.000)', function () {
    [$db, $p, $repo, $band] = coreLabel();
    $s = new Signed\Game\Submissions($repo);
    checkEquals(0.0, $s->demoSuccess(), 'Ohne Fans');
    coreNear(24.0, Signed\Game\Submissions::perDay($s->demoSuccess()), 1e-9, 'Neues Label: eine je Stunde');
    $db->exec('UPDATE bands SET fans = 5000 WHERE id = ?', [$band['id']]);
    coreNear(1000.0, $s->demoSuccess(), 0.1, '5.000 Fans zählen wie Label-Ruhm 1.000');
    coreNear(24 + 1.8 * sqrt(1000), Signed\Game\Submissions::perDay($s->demoSuccess()), 0.1, 'Gut 80 Kassetten am Tag');
    $db->exec('UPDATE bands SET fans = 50000 WHERE id = ?', [$band['id']]);
    coreNear(0.25, Signed\Game\Submissions::goodShare($s->demoSuccess()), 1e-9, 'Ganz oben ist jede vierte gut');
    check($s->demoSuccess() > 10 * $s->success(), 'Der Ruhm-Gegenwert der Musiker wäre viel kleiner');
});

test('Kern im Spiel: Die Auszeit gibt es nur, wenn jemand in der Band rot ist', function () {
    [$db, $p, $repo, $band] = coreLabel();
    checkApiError(fn () => $p->post('bandRetreat', ['bandId' => $band['id'], 'option' => 'ausflug']), 'unzufrieden');
    $first = array_values($repo->members($band['id']))[0];
    $db->exec('UPDATE musicians SET wellbeing = ? WHERE id = ?', [Signed\Game\Balance::MOOD['yellow'] - 5, $first['id']]);
    $res = $p->post('bandRetreat', ['bandId' => $band['id'], 'option' => 'ausflug']);
    check(bandById($res['state'], $band['id'])['busyUntil'] > Clock::now(), 'Mit einem Roten geht die Band in die Auszeit');
});

test('Kern im Spiel: Tournee ab 5.000 Fans mit dem Bus; Auftritte wie zu Hause, nur mit Halle; Welttournee geparkt', function () {
    [$db, $p, $repo, $band] = coreLabel();
    $db->exec('UPDATE labels SET money = 400000');
    $db->exec('UPDATE bands SET fans = 4000, records = 1 WHERE id = ?', [$band['id']]);
    coreRelease($repo, $repo->band($band['id']), 90);
    $start = static fn () => $p->post('tourStart', ['bandId' => $band['id'], 'region' => 'europa', 'stations' => 3]);
    checkApiError($start, 'Fans');
    checkApiError(fn () => $p->post('vehicleBuy', ['bandId' => $band['id'], 'kind' => 'bus']), 'lohnt sich erst');
    $db->exec('UPDATE bands SET fans = 20000 WHERE id = ?', [$band['id']]);
    checkApiError($start, 'Bus');
    $p->post('vehicleBuy', ['bandId' => $band['id'], 'kind' => 'bus']);
    checkApiError(fn () => $p->post('vehicleBuy', ['bandId' => $band['id'], 'kind' => 'plane']), 'lohnt sich erst');
    $options = array_column(bandById($p->get('state'), $band['id'])['tours'], null, 'key');
    check($options['europa']['reached'] && $options['europa']['blocked'] === null, 'Europa geht ab 5.000 Fans');
    checkEquals(5000, $options['europa']['fans']);
    check(str_contains((string) $options['nordamerika']['blocked'], '50.000 Fans'), 'Übersee erst als Superstar');
    check(str_contains((string) $options['welt']['blocked'], 'später'), 'Welttournee geparkt');
    $res = $start();
    Clock::set($res['activity']['endsAt'] + 1);
    $p->get('state');
    $gig = $p->post('tourShow', ['bandId' => $band['id']])['activity'];
    $data = json_decode((string) $db->value('SELECT data FROM activities WHERE id = ?', [$gig['id']]), true);
    $core = $data['reward']['core'];
    checkEquals('halle', $data['reward']['size'], 'Mehr Leute, als in den größten Club passen: Halle');
    check($core['tickets'] > 1500, 'Mehr als 1.500 Zuschauer');
    $factor = $data['reward']['tour']['match'] ? Signed\Game\Balance::TOUR['match']['money'] : 1.0;
    coreNear($core['tickets'] * Signed\Game\Balance::CORE_EARNINGS[2] * $factor, (float) $data['reward']['money'], 1.0, 'Verdienst je Zuschauer wie zu Hause');
    $fans = BandCore::fans($db, $band['id']);
    Clock::set($gig['endsAt'] + 1);
    $state = $p->get('state');
    check(BandCore::fans($db, $band['id']) >= $fans, 'Neue Fans nach dem Auftritt (bis zum Dach)');
    $played = array_values(array_filter($state['results'], static fn ($r) => $r['type'] === 'tourgig'))[0]['result'];
    check($played['tickets'] > 1500 && $played['money'] > 0, 'Ergebnis mit Zuschauern und Geld');
    $tour = bandById($state, $band['id'])['tour'];
    checkEquals(1, count($tour['visits']));
    checkEquals((int) round($core['tickets']), $tour['visits'][0]['tickets']);
    if ($played['tour']['next'] !== null) {
        $next = $p->post('tourShow', ['bandId' => $band['id']])['activity'];
        $again = json_decode((string) $db->value('SELECT data FROM activities WHERE id = ?', [$next['id']]), true)['reward']['core'];
        check($again['demand'] < 0.6 * $core['demand'], 'Frische gilt auch auf Tournee: gleich danach kommen weniger');
    }
    // Ruhm gibt es nicht mehr; die Bekanntheit der Musiker folgt den Fans der Band
    checkEquals(round(3 * BandCore::fameEquivalent(BandCore::fans($db, $band['id'])), 1), round((float) $db->value('SELECT SUM(fame) FROM musicians WHERE band_id = ?', [$band['id']]), 1));
});

test('Kern im Spiel: Anfragen nach Ereignissen – Goldene Schallplatte, Platz 1, Battle-Platz, mit Bezug im Brief; ohne Platte keine', function () {
    [$db, $p, $repo, $band] = coreLabel();
    $requests = static fn () => array_values(array_filter($p->get('state')['mail'], static fn ($m) => ($m['request'] ?? null) !== null));
    unpackAll($db);
    checkEquals(null, Signed\Game\Inbox::eventRequest($repo, $band['id'], 'nach dem Test'), 'Ohne Platte keine Anfrage');
    $id = coreRelease($repo, $repo->band($band['id']), 80);
    $db->exec('UPDATE bands SET records = 1 WHERE id = ?', [$band['id']]);
    Signed\Game\Awards::sales($repo, $band['id'], $id, 'Erste', 4990.0, 5010.0);
    Signed\Game\Battle::settled($repo, $band['id'], ['place' => 2, 'day' => '1.1.2027']);
    Signed\Game\Battle::settled($repo, $band['id'], ['place' => 5, 'day' => '1.1.2027']);
    unpackAll($db);
    $reasons = array_column($requests(), 'reason');
    check(in_array('nach der Goldenen Schallplatte für „Erste“', $reasons, true), 'Gold: ' . implode(' | ', $reasons));
    check(in_array('nach Platz 2 beim Battle of the Bands', $reasons, true), 'Battle-Platz');
    checkEquals(2, count($reasons), 'Platz 5 bringt keine Anfrage');
});

test('Kern im Spiel: Nach Konzerten kommt je Band höchstens eine Anfrage am Tag, auch bei vielen Auftritten', function () {
    [$db, $p, $repo, $band] = coreLabel();
    Signed\Rng::seed(7);
    $db->exec('UPDATE bands SET fans = 480, records = 1 WHERE id = ?', [$band['id']]);
    $db->exec('UPDATE musicians SET wellbeing = 90 WHERE band_id = ?', [$band['id']]);
    coreRelease($repo, $repo->band($band['id']), 95);
    $start = Clock::now();
    for ($i = 0; $i < 12; $i++) {
        $gig = $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'gig'])['activity'];
        Clock::set($gig['endsAt'] + 60);
        $p->get('state');
        $db->exec('UPDATE musicians SET wellbeing = 90 WHERE band_id = ?', [$band['id']]);
    }
    check(Clock::now() - $start < 86400, 'alles an einem Tag');
    $after = 0;
    foreach ($db->all("SELECT data FROM mail WHERE label_id = ? AND kind = 'interview'", [$repo->labelId]) as $m) {
        $reason = json_decode($m['data'], true)['reason'] ?? '';
        $after += in_array($reason, ['nach dem ausverkauften Konzert', 'nach dem tollen Auftritt'], true) ? 1 : 0;
    }
    check($after <= 1, "Höchstens eine Anfrage nach Konzerten am Tag (waren $after)");
});

test('Kern im Spiel: Austritt, Zusammentun und Umbenennen nach den Fan-Regeln', function () {
    [$db, $p, $repo, $band] = coreLabel();
    $db->exec('UPDATE bands SET fans = 9000 WHERE id = ?', [$band['id']]);
    $members = array_values($repo->members($band['id']));
    // Eine Viererband, damit nach dem Austritt noch genug übrig sind
    $fourth = $repo->insertMusician(Signed\Game\Generator::musician('common', ['instrument' => 'bass', 'genre' => 'rock', 'single' => true]), 'test');
    $db->exec('UPDATE musicians SET band_id = ? WHERE id = ?', [$band['id'], $fourth]);
    $members = array_values($repo->members($band['id']));
    [$takes, $keeps] = Signed\Game\Core\Fans::leave(9000, array_map([Signed\Game\Core\Fans::class, 'weight'], $members), 0);
    $p->post('bandRemoveMember', ['bandId' => $band['id'], 'musicianId' => $members[0]['id']]);
    coreNear($keeps, BandCore::fans($db, $band['id']), 1e-6, 'Die Band behält den Rest');
    $solo = (int) $db->value('SELECT band_id FROM musicians WHERE id = ?', [$members[0]['id']]);
    coreNear($takes, BandCore::fans($db, $solo), 1e-6, 'Er nimmt die Hälfte seiner Fans mit');
    check($takes + $keeps < 9000, 'Zusammen weniger als vorher');
    // Umbenennen: ein Zehntel geht, die Platten bleiben
    $recordId = coreRelease($repo, $repo->band($band['id']), 80);
    $before = BandCore::fans($db, $band['id']);
    $p->post('bandRename', ['bandId' => $band['id'], 'name' => 'Neuer Name']);
    coreNear($before * 0.9, BandCore::fans($db, $band['id']), 1e-6);
    checkEquals($band['id'], (int) $db->value('SELECT band_id FROM records WHERE id = ?', [$recordId]), 'Platte bleibt bei der Band');
    // Zusammentun: die größere plus die Hälfte der kleineren
    $big = BandCore::fans($db, $band['id']);
    $small = BandCore::fans($db, $solo);
    $p->post('bandMerge', ['bandId' => $band['id'], 'otherId' => $solo, 'keepName' => 'a']);
    coreNear(max($big, $small) + 0.5 * min($big, $small), BandCore::fans($db, $band['id']), 1e-3, 'Zusammentun');
});

test('Kern im Spiel: Bedürfnisse kosten erst nach 24 Stunden etwas und wachsen nur alle 24 Stunden', function () {
    [$db, $p, $repo, $band] = coreLabel();
    giveManager($db, $band['id']);
    $m = array_values($repo->members($band['id']))[0];
    $wait = static function (float $hours) use ($db, $p, $band, $m): float {
        $db->exec('UPDATE musicians SET wish_wait = ?, wellbeing = 30 WHERE id = ?', [$hours, $m['id']]);
        $items = bandById($p->get('state'), $band['id'])['mood']['why'][$m['id']]['items'] ?? [];
        $hit = array_values(array_filter($items, static fn ($i) => str_starts_with($i[0], 'Wartet schon')));
        return $hit ? (float) $hit[0][1] : 0.0;
    };
    checkEquals(0.0, $wait(23), 'Auftauchen kostet nichts');
    coreNear(-9.6, $wait(25));
    coreNear(-9.6, $wait(47));
    coreNear(-19.2, $wait(49));
});

test('Kern im Spiel: Können wird im neuen Maß gezeigt, Proben bringt 50 je Stunde, nach oben offen', function () {
    [$db, $p, $repo, $band] = coreLabel();
    $m = array_values($repo->members($band['id']))[0];
    $db->exec('UPDATE musicians SET skill1 = 60 WHERE id = ?', [$m['id']]);
    $state = $p->get('state');
    checkEquals([100, 400, 1600], Signed\Game\Present::catalog()['rules']['skillLevels'], 'Stufen im neuen Maß');
    $card = array_values(array_filter($state['musicians'], static fn ($x) => $x['id'] === $m['id']))[0];
    coreNear(400.0, (float) $card['instruments'][0]['skill'], 1e-9, 'Altes 60 ist neues 400');
    // Eine Stunde Proben: 50 dazu (ohne besondere Züge und Instrument-Boni), auch über der alten Grenze 100
    $db->exec('UPDATE musicians SET skill1 = 100, personality = ? WHERE id = ?', [json_encode(['gelassen']), $m['id']]);
    $before = Signed\Game\Levels::shown(100.0);
    $gain = Signed\Game\BandCalc::rehearsalGain($repo->musician($m['id']), ['key' => 'x', 'bonuses' => []]);
    coreNear(12.5, $gain, 1e-9, 'Je Probe-Viertelstunde 12,5');
    check(Signed\Game\Levels::cap(130.0) === 130.0, 'Keine Obergrenze mehr');
    coreNear($before + 50, Signed\Game\Core\Skill::fromLegacy(Signed\Game\Core\Skill::toLegacy($before + 50)), 1e-9, 'Umrechnung hin und zurück');
});

test('Kern im Spiel: Umschalten mit einem alten Spielstand (Platten in den Charts, Lager, Ruhm) – danach läuft alles, zweimal umschalten ändert nichts', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Altlabel');
    $band = foundBand($p, 'Alte Hasen');
    $db->exec('UPDATE labels SET money = 50000');
    // Altes Spiel: Platte in den Charts, Auftritt mit Gage und Ruhm, Instrument im Lager
    makeRecord($db, $p, $band['id'], 'Damals', 4);
    $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'gig', 'songs' => 3]);
    Clock::advance(3600);
    $state = $p->get('state');
    check((float) $db->value('SELECT SUM(fame) FROM musicians WHERE band_id = ?', [$band['id']]) > 0, 'Altes Spiel: Ruhm');
    $labelId = (int) $state['label']['id'];
    $db->exec('UPDATE musicians SET fame = fame + 10 WHERE band_id = ?', [$band['id']]);
    (new Repo($db, $labelId))->insertGear(array_key_first(Signed\Game\Catalog::GEAR));
    // Umschalten wie auf dem Server: Schalter an, dann der Schritt
    BandCore::$live = true;
    Signed\Game\CoreTransition::run($db);
    $money = (int) $db->value('SELECT money FROM labels WHERE id = ?', [$labelId]);
    checkEquals(5000, $money);
    Signed\Game\CoreTransition::run($db);
    checkEquals(5000, (int) $db->value('SELECT money FROM labels WHERE id = ?', [$labelId]), 'Zweimal umschalten ändert nichts');
    checkEquals(1, (int) $db->value("SELECT COUNT(*) FROM history WHERE band_id = ? AND text LIKE 'Vor dem Umbau: % Ruhm.'", [$band['id']]), 'Erinnerung nur einmal');
    $state = $p->get('state');
    check($state['core'] === true && $state['transition']['core'] !== null, 'Was ist neu?');
    checkEquals(0.0, (float) bandById($state, $band['id'])['core']['fans']);
    $p->post('transitionDone', ['keep' => []]);
    checkEquals(null, $p->get('state')['transition']);
    // Danach: auftreten, Charts, Aufnahme-Fenster
    $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'gig']);
    Clock::advance(3600);
    $state = $p->get('state');
    check(BandCore::fans($db, $band['id']) > 0, 'Erste Fans nach dem Umschalten');
    $charts = $p->get('charts');
    check($charts['core'] === true, 'Charts im neuen Kern');
    checkEquals([], array_values(array_filter($charts['albums'], static fn ($a) => $a['title'] === 'Damals')), 'Alte Platten sind aus den Charts');
    $p->post('recordOptions', ['bandId' => $band['id']]);
});

test('Kern im Spiel: Übergang – 0 Fans, neues Startgeld, eigenes Instrument in passender Farbe, Lager verfällt, alter Ruhm als Erinnerung', function () {
    [$db, $p, $repo, $band] = coreLabel();
    $members = array_values($repo->members($band['id']));
    [$a, $b, $c] = $members;
    $db->exec('UPDATE labels SET money = 123456 WHERE id = ?', [$repo->labelId]);
    $db->exec('UPDATE bands SET fame_own = 40, songs_written = 7.6, fans = 0 WHERE id = ?', [$band['id']]);
    $db->exec('UPDATE musicians SET fame = 30 WHERE id = ?', [$a['id']]);
    $db->exec('DELETE FROM gear WHERE label_id = ?', [$repo->labelId]);
    // a: Profi (altes Können 62) mit goldenem Instrument → blau; b: kein Instrument, Fortgeschritten → grün; c: Anfänger ohne → grau
    $db->exec('UPDATE musicians SET skill1 = 62 WHERE id = ?', [$a['id']]);
    $db->exec('UPDATE musicians SET skill1 = 40 WHERE id = ?', [$b['id']]);
    $db->exec('UPDATE musicians SET skill1 = 20 WHERE id = ?', [$c['id']]);
    $gold = array_key_first(array_filter(Signed\Game\Catalog::GEAR, static fn ($g) => $g['type'] === $a['instr1'] && $g['rarity'] === 'legendary'));
    $goldId = $repo->insertGear($gold);
    $db->exec('UPDATE gear SET musician_id = ? WHERE id = ?', [$a['id'], $goldId]);
    $repo->insertGear(array_key_first(Signed\Game\Catalog::GEAR));
    $db->insert('items', ['label_id' => $repo->labelId, 'kind' => 'fan', 'value' => 8.0, 'created_at' => Clock::now()]);
    $tourId = $db->insert('tours', ['label_id' => $repo->labelId, 'band_id' => $band['id'], 'state' => 'arrived', 'stations' => 3, 'country' => 'fr', 'deadline' => 0, 'started_at' => Clock::now(), 'data' => '{}']);
    Signed\Game\CoreTransition::run($db);
    checkEquals(0, (int) $db->value('SELECT COUNT(*) FROM items WHERE label_id = ?', [$repo->labelId]), 'Fan-Karten verfallen');
    checkEquals('ended', $db->value('SELECT state FROM tours WHERE id = ?', [$tourId]), 'Laufende Tourneen enden');
    checkEquals(5000, (int) $db->value('SELECT money FROM labels WHERE id = ?', [$repo->labelId]));
    checkEquals(0.0, BandCore::fans($db, $band['id']));
    checkEquals(2, Signed\Game\Gear::modelTier((string) $db->value('SELECT model FROM gear WHERE id = ?', [$goldId])), 'Gold wird Blau');
    $tiers = [];
    foreach ($db->all('SELECT model, musician_id FROM gear WHERE label_id = ?', [$repo->labelId]) as $g) {
        $tiers[(int) $g['musician_id']] = Signed\Game\Gear::modelTier($g['model']);
    }
    checkEquals(3, count($tiers), 'Lager verfällt, jeder hat genau eins');
    checkEquals([2, 1, 0], [$tiers[$a['id']], $tiers[$b['id']], $tiers[$c['id']]]);
    checkEquals(7, (int) $db->value('SELECT COUNT(*) FROM songs WHERE band_id = ? AND record_id IS NULL', [$band['id']]), 'Songs im Vorrat mit Qualität');
    check((int) $db->value("SELECT COUNT(*) FROM history WHERE band_id = ? AND text LIKE 'Vor dem Umbau: % Ruhm.'", [$band['id']]) === 1, 'Erinnerung an den alten Ruhm');
    checkEquals(0.0, (float) $db->value('SELECT fame FROM musicians WHERE id = ?', [$a['id']]));
    $summary = json_decode((string) $db->value('SELECT transition FROM labels WHERE id = ?', [$repo->labelId]), true)['core'];
    checkEquals(123456, $summary['money']);
    checkEquals(['kept' => 0, 'swapped' => 1, 'expired' => 1, 'given' => 2], $summary['gear']);
    checkEquals(1, $summary['cards']);
});
