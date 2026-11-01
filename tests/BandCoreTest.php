<?php
declare(strict_types=1);

use Signed\Clock;
use Signed\Game\Balance;
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

test('Kern im Spiel: Charts jede Minute neu, eine neue Platte steht sofort drin; „Vorher“ und Nachrichten zur vollen Stunde', function () {
    $db = freshDb();
    $labels = [];
    foreach (['Alpha' => 5000, 'Beta' => 20000] as $name => $fans) {
        $p = registerPlayer($db, $name);
        $band = foundBand($p, "Band $name");
        BandCore::$live = true;
        $repo = new Repo($db, (int) $db->value('SELECT id FROM labels ORDER BY id DESC LIMIT 1'));
        $db->exec('UPDATE bands SET fans = ? WHERE id = ?', [$fans, $band['id']]);
        $labels[$name] = [$p, $repo, $repo->band($band['id'])];
    }
    [$pa, $ra, $ba] = $labels['Alpha'];
    coreRelease($ra, $ba, 80, 'Erste');
    Clock::advance(3 * 3600 + 3600 - Clock::now() % 3600 + 300);
    $album = static fn (Player $p, string $title) => array_values(array_filter($p->get('charts')['albums'], static fn ($a) => $a['title'] === $title))[0] ?? null;
    check($album($pa, 'Erste') !== null, 'Die erste Platte steht drin');
    // Beta veröffentlicht (wie aus dem Studio): sofort in den Charts, noch ohne Verkäufe hinter der ersten, neu
    [$pb, $rb, $bb] = $labels['Beta'];
    coreRelease($rb, $bb, 90, 'Zweite');
    Signed\Game\Charts::markDirty($db);
    $new = $album($pb, 'Zweite');
    check($new !== null, 'Neue Platte steht sofort in den Charts');
    checkEquals(2, $new['rank']);
    checkEquals('new', $new['trend']);
    // Zwanzig Minuten später (noch dieselbe Stunde): Die Verkäufe zählen schon mit, ohne auf die volle Stunde zu warten
    Clock::advance(20 * 60);
    $sold = $album($pb, 'Zweite')['sold'];
    check($sold > 0, 'Verkäufe zählen jede Minute');
    Clock::advance(10 * 60);
    $new = $album($pb, 'Zweite');
    check($new['sold'] > $sold, 'Zehn Minuten später mehr');
    checkEquals('new', $new['trend'], 'Neu bis zur vollen Stunde');
    checkEquals(0, (int) $db->value("SELECT COUNT(*) FROM history WHERE label_id = ? AND kind IN ('chart', 'chart1')", [$rb->labelId]), 'Nachrichten erst zur vollen Stunde');
    // Volle Stunde: „Vorher“ ist jetzt dieser Platz, die Nachricht vom Einstieg kommt
    Clock::advance(3600 - Clock::now() % 3600 + 60);
    $new = $album($pb, 'Zweite');
    checkEquals(2, $new['prev']);
    checkEquals('same', $new['trend']);
    checkEquals(1, (int) $db->value("SELECT COUNT(*) FROM history WHERE label_id = ? AND kind = 'chart'", [$rb->labelId]), 'Einstieg in der Chronik');
    // Mehr Fans und Hype: Nach gut einer Stunde zieht sie vorbei – zu sehen in der Minute, in der es passiert, mit Pfeil nach oben
    Clock::advance(30 * 60);
    $new = $album($pb, 'Zweite');
    checkEquals(1, $new['rank']);
    checkEquals('up', $new['trend']);
    Clock::advance(3600 - Clock::now() % 3600 + 60);
    $album($pb, 'Zweite');
    checkEquals(1, (int) $db->value("SELECT COUNT(*) FROM history WHERE label_id = ? AND kind = 'chart1'", [$rb->labelId]), 'Platz 1 in der Chronik zur vollen Stunde');
});

test('Kern im Spiel: Aufnehmen mit 10 bis 16 Songs und einer Produktionsstufe, danach beginnt der Hype', function () {
    [$db, $p, $repo, $band] = coreLabel();
    $opts = $p->post('recordOptions', ['bandId' => $band['id']]);
    check(str_contains((string) $opts['blocker'], 'mindestens 10 Songs'), 'Ohne 10 Songs geht es nicht: ' . $opts['blocker']);
    for ($i = 0; $i < 14; $i++) {
        $db->insert('songs', ['label_id' => $repo->labelId, 'band_id' => $band['id'], 'quality' => 50 + $i * 3, 'record_id' => null, 'created_at' => Clock::now()]);
    }
    $db->exec('UPDATE bands SET songs_written = 14.5 WHERE id = ?', [$band['id']]);
    $opts = $p->post('recordOptions', ['bandId' => $band['id']]);
    checkEquals(null, $opts['blocker']);
    checkEquals(12, count($opts['picked']), 'Vorauswahl: die besten 12');
    checkEquals([10, 16], [$opts['albumMin'], $opts['albumMax']]);
    checkEquals([300, 2000, 8000, 30000], array_column($opts['quality']['levels'], 'cost'));
    $best = array_slice(array_map(static fn ($i) => 50 + $i * 3, range(13, 0, -1)), 0, 12);
    coreNear(array_sum($best) / 12 / 100, $opts['quality']['songs'], 1e-6, 'Songs: Durchschnitt der besten 12');
    checkEquals(1.0, (float) $opts['quality']['levels'][0]['production'], 'Bei Ruhmstufe 0 ist die Garage passend');
    checkEquals([false, true, true, true], array_column($opts['quality']['levels'], 'locked'), 'Bessere Studios erst ab der passenden Ruhmstufe');
    checkEquals([0, 500, 5000, 50000], array_column($opts['quality']['levels'], 'from'));
    checkApiError(fn () => $p->post('recordStart', ['bandId' => $band['id'], 'title' => 'Zu früh', 'level' => 1, 'songIds' => $opts['picked'], 'cover' => $opts['covers'][0]]), 'erst ab 500 Fans');
    $money = (int) $db->value('SELECT money FROM labels WHERE id = ?', [$repo->labelId]);
    $ten = array_slice($opts['picked'], 0, 10);
    $all = array_column(Signed\Game\SongStock::stock($db, $band['id']), 'id');
    checkApiError(fn () => $p->post('recordStart', ['bandId' => $band['id'], 'title' => 'Zu kurz', 'level' => 0, 'songIds' => array_slice($ten, 0, 9), 'cover' => $opts['covers'][0]]), '10 bis 16');
    checkEquals(14, count($all));
    // Zehn Songs: Studio anteilig (300 ₲ für 12 → 250 ₲), zehnmal 5 Minuten
    $p->post('recordStart', ['bandId' => $band['id'], 'title' => 'Neuland', 'level' => 0, 'songIds' => $ten, 'cover' => $opts['covers'][0]]);
    checkEquals($money - 250 - Signed\Game\Balance::COVER_DESIGNERS['student']['fee'], (int) $db->value('SELECT money FROM labels WHERE id = ?', [$repo->labelId]), 'Studio je Lied');
    checkEquals(4, (int) $db->value('SELECT COUNT(*) FROM songs WHERE band_id = ? AND record_id IS NULL', [$band['id']]), 'Vier Songs bleiben im Vorrat');
    coreNear(4.5, (float) $db->value('SELECT songs_written FROM bands WHERE id = ?', [$band['id']]));
    Clock::advance(10 * Signed\Game\Balance::RECORD_SECONDS_PER_SONG + 2);
    $state = $p->get('state');
    $done = array_values(array_filter($state['results'], static fn ($r) => $r['type'] === 'record'))[0]['result'];
    check($done['core'] && $done['hype'] > 0, 'Die Platte erscheint mit Hype');
    $record = $db->one("SELECT * FROM records WHERE band_id = ? AND title = 'Neuland'", [$band['id']]);
    checkEquals('done', $record['status']);
    checkEquals(1, (int) $record['core_active']);
    $core = json_decode($record['core'], true);
    coreNear((float) $done['quality'] * 100, $core['quality'], 1e-6, 'Kern rechnet mit derselben Qualität');
    checkEquals(10, (int) $record['songs_total']);
    checkEquals(10, (int) $core['songs']);
    coreNear(min(100.0, $core['quality'] ** 2 / 100 * 10 / 12), Signed\Game\Core\Hype::release($core['quality'], 10), 1e-9);
    checkEquals(0, (int) $db->value('SELECT COUNT(*) FROM ledger WHERE label_id = ? AND text LIKE ?', [$repo->labelId, 'Erstverkauf%']), 'Kein Erstverkauf mehr');
    check(bandById($state, $band['id'])['core']['record'] !== null, 'Spielstand zeigt die neue Platte');
});

test('Kern im Spiel: Albumlänge – Hype, Preis und Studio anteilig (je Lied gleich viel wert), 12 Lieder wie bisher', function () {
    $c = Signed\Game\Balance::CORE_SALES;
    coreNear(10.0, Signed\Game\Core\Sales::price(12), 1e-9, '12 Lieder: 10 ₲');
    coreNear(10.0 * 10 / 12, Signed\Game\Core\Sales::price(10), 1e-9);
    coreNear(10.0 * 16 / 12, Signed\Game\Core\Sales::price(16), 1e-9);
    checkEquals(2000, Signed\Game\Studio::coreFee(1, 12));
    checkEquals(2667, Signed\Game\Studio::coreFee(1, 16));
    coreNear(64.0, Signed\Game\Core\Hype::release(80, 12), 1e-9, '12 Lieder wie bisher');
    coreNear(64.0 * 16 / 12, Signed\Game\Core\Hype::release(80, 16), 1e-9);
    checkEquals(100.0, Signed\Game\Core\Hype::release(95, 16), 'Höchstens 100');
    // Gleiche Band, gleiche Qualität: 16 Lieder bringen mehr Hype und mehr ₲ je Stück als 10
    $t0 = 1_800_000_000;
    $long = Career::settle(Career::release(Career::start(5000, 'genre', $t0), $t0, 80, 16), $t0 + 7 * 86400);
    $short = Career::settle(Career::release(Career::start(5000, 'genre', $t0), $t0, 80, 10), $t0 + 7 * 86400);
    check($long['sold'] > $short['sold'], 'Mehr Hype, mehr Käufer');
    coreNear($long['earned']['records'], $long['sold'] * $c['price'] * 16 / 12, 1e-6, 'Preis je Stück nach der Länge');
});

test('Kern im Spiel: Das Label lehnt Songs ab – Platz für neue, nur aus dem eigenen Vorrat', function () {
    [$db, $p, $repo, $band] = coreLabel();
    foreach ([40, 70, 55] as $q) {
        $db->insert('songs', ['label_id' => $repo->labelId, 'band_id' => $band['id'], 'quality' => $q, 'record_id' => null, 'created_at' => Clock::now()]);
    }
    $db->exec('UPDATE bands SET songs_written = 3.4 WHERE id = ?', [$band['id']]);
    $stock = bandById($p->get('state'), $band['id'])['core']['stock'];
    checkEquals([70.0, 55.0, 40.0], array_map('floatval', array_column($stock, 'quality')), 'Vorrat mit Qualität, beste zuerst');
    $state = $p->post('songReject', ['bandId' => $band['id'], 'songId' => $stock[2]['id']])['state'];
    checkEquals([70.0, 55.0], array_map('floatval', array_column(bandById($state, $band['id'])['core']['stock'], 'quality')), 'Der schwächste ist weg');
    coreNear(2.4, (float) $db->value('SELECT songs_written FROM bands WHERE id = ?', [$band['id']]), 1e-6, 'Platz für einen neuen');
    checkEquals(2, bandById($state, $band['id'])['mood']['songs'], 'Zähler auf der Bandseite');
    checkApiError(fn () => $p->post('songReject', ['bandId' => $band['id'], 'songId' => $stock[2]['id']]), 'nicht mehr');
    $other = registerPlayer($db, 'Fremd');
    checkApiError(fn () => $other->post('songReject', ['bandId' => $band['id'], 'songId' => $stock[0]['id']]), '');
    checkEquals(2, (int) $db->value('SELECT COUNT(*) FROM songs WHERE band_id = ? AND record_id IS NULL', [$band['id']]), 'Fremde können nichts ablehnen');
});

test('Kern im Spiel: Charts nach den Verkäufen der letzten 24 Stunden', function () {
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
    check($charts['nextAt'] > Clock::now() && $charts['nextAt'] - Clock::now() <= 60, 'Nächste Wertung in der nächsten Minute');
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
    // Die Bewerbung zeigt die Fans der Band (vorher den alten Wochenruhm, der im neuen Kern immer 0 war)
    $opt = array_values(array_filter($b->get('state')['festival']['next']['options'], static fn ($o) => $o['bandId'] === $big))[0];
    checkEquals(3000.0, (float) $opt['gain'], 'Bewerbung zeigt 3.000 Fans');
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

test('Kern im Spiel: Viele Kassetten von Anfang an, mit den Fans mehr Gute (500 / 5.000 / 50.000 Fans wie Label-Ruhm 100 / 1.000 / 10.000)', function () {
    [$db, $p, $repo, $band] = coreLabel();
    $s = new Signed\Game\Submissions($repo);
    checkEquals(0.0, $s->demoSuccess(), 'Ohne Fans');
    coreNear(96.0, Signed\Game\Submissions::perDay($s->demoSuccess()), 1e-9, 'Neues Label: vier je Stunde');
    $shares = static fn (float $erfolg) => Signed\Game\Submissions::levelShares($erfolg);
    checkEquals([0 => 94.0, 1 => 5.5, 2 => 0.5, 3 => 0.0], $shares(0.0), 'Zu Beginn fast nur Anfänger');
    coreNear(0.06, Signed\Game\Submissions::goodShare(0.0), 1e-9, 'Gut jede siebzehnte über Anfänger-Niveau');
    coreNear(0.15, Signed\Game\Submissions::goodShare(100.0), 1e-9, '500 Fans');
    coreNear(0.25, Signed\Game\Submissions::goodShare(1000.0), 1e-9, '5.000 Fans');
    coreNear(0.35, Signed\Game\Submissions::goodShare(10000.0), 1e-9, '50.000 Fans');
    coreNear(0.35, Signed\Game\Submissions::goodShare(1e6), 1e-9, 'Darüber bleibt es');
    coreNear((2.7 + 6.0) / 2, $shares(100.0 * sqrt(10))[2], 1e-9, 'Dazwischen gleitend: Profis bei gut 1.580 Fans');
    coreNear((5.5 + 12.0) / 2, $shares(50.0)[1], 1e-9, 'Bis 500 Fans gleichmäßig: 250 Fans liegen in der Mitte');
    $db->exec('UPDATE bands SET fans = 5000 WHERE id = ?', [$band['id']]);
    coreNear(1000.0, $s->demoSuccess(), 0.1, '5.000 Fans zählen wie Label-Ruhm 1.000');
    coreNear(96 + sqrt(1000), Signed\Game\Submissions::perDay($s->demoSuccess()), 0.1, 'Gut 127 Kassetten am Tag');
    $db->exec('UPDATE bands SET fans = 50000 WHERE id = ?', [$band['id']]);
    check($s->demoSuccess() > 10 * $s->success(), 'Der Ruhm-Gegenwert der Musiker wäre viel kleiner');
    $db->exec('UPDATE bands SET fans = 0 WHERE id = ?', [$band['id']]);
    // Posteingang ohne Grenze (Sandra, 1.11.): Ungehörtes fliegt nicht raus, Kassetten verfallen nur nach ihrer Liegezeit
    $p->get('state');
    $first = (int) $db->value('SELECT id FROM submissions WHERE label_id = ? ORDER BY id LIMIT 1', [$repo->labelId]);
    $db->exec('UPDATE submissions SET saved = 1 WHERE id = ?', [$first]);
    Clock::advance(2 * 86400);
    $p->get('state');
    $unheard = (int) $db->value('SELECT COUNT(*) FROM submissions WHERE label_id = ? AND opened_at IS NULL AND saved = 0', [$repo->labelId]);
    check($unheard > 100, "Keine Grenze bei 40: $unheard ungehörte");
    checkEquals(1, (int) $db->value('SELECT COUNT(*) FROM submissions WHERE id = ?', [$first]), 'Die gemerkte bleibt');
    $levels = array_map(static fn ($r) => json_decode($r['card'], true), $db->all('SELECT card FROM submissions WHERE label_id = ? AND id <> ?', [$repo->labelId, $first]));
    $singles = array_filter($levels, static fn ($c) => ($c['kind'] ?? '') !== 'band');
    $goodOnes = count(array_filter($singles, static fn ($c) => $c['level'] >= 1));
    check($goodOnes <= 0.15 * count($singles), sprintf('Wenig Gutes dabei (%d von %d)', $goodOnes, count($singles)));
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
    checkEquals(0, (int) $db->value("SELECT COUNT(*) FROM mail WHERE kind = 'interview' AND unpacked_at IS NULL"), 'Anfragen nach Ereignissen kommen ohne Umschlag');
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

test('Kern im Spiel: Die Künstlerleiste zeigt das Songtempo, mit dem wirklich geschrieben wird', function () {
    [$db, $p, $repo, $band] = coreLabel();
    $db->exec('UPDATE musicians SET wellbeing = 80 WHERE band_id = ?', [$band['id']]);
    $members = array_values($repo->members($band['id']));
    $expected = Signed\Game\Core\Songs::perDay(Signed\Game\Core\Songs::writers($members), $band['write_mode'] ?? 'normal', 'green', 'idle', BandCore::kind($repo->band($band['id']), $members)) / 24;
    coreNear(round($expected, 3), (float) bandById($p->get('state'), $band['id'])['mood']['songRate'], 1e-9, 'Songs je Stunde wie im neuen Kern');
    check($expected * 24 < 5, 'Ein paar Songs am Tag, nicht zehn');
});

test('Kern im Spiel: Kurse geben Können im neuen Maß je Schwierigkeit, angezeigt wie gutgeschrieben', function () {
    [$db, $p] = coreLabel();
    $course = Signed\Game\Courses::today();
    $gains = Balance::CORE_COURSE['gain'][$course['kind']];
    $ids = array_column($p->get('state')['musicians'], 'id');
    // Anfänger (alt 20 = neu 57,1) und Fortgeschrittener (alt 40 = neu 160) spielen das Instrument des Tages
    $db->exec('UPDATE musicians SET instr1 = ?, instr2 = NULL, skill2 = NULL, skill1 = 20 WHERE id = ?', [$course['instrument'], $ids[0]]);
    $db->exec('UPDATE musicians SET instr1 = ?, instr2 = NULL, skill2 = NULL, skill1 = 40 WHERE id = ?', [$course['instrument'], $ids[1]]);
    $db->exec('UPDATE labels SET money = 100000');
    $state = $p->get('state');
    checkEquals($gains, array_column($state['courses'][0]['levels'], 'gain'), 'Zuwachs je Schwierigkeit');
    $shown = static fn (int $id) => Signed\Game\Levels::shown((float) $db->value('SELECT skill1 FROM musicians WHERE id = ?', [$id]));
    $before = [$shown($ids[0]), $shown($ids[1])];
    $a = $p->post('courseAttend', ['musicianId' => $ids[0], 'course' => 0])['activity'];
    $b = $p->post('courseAttend', ['musicianId' => $ids[1], 'course' => 0])['activity'];
    Clock::advance($course['seconds'] + 5);
    $p->get('state');
    coreNear($before[0] + $gains[0], $shown($ids[0]), 1e-6, 'Anfänger: + Zuwachs der ersten Schwierigkeit');
    coreNear($before[1] + $gains[1], $shown($ids[1]), 1e-6, 'Fortgeschrittener: + Zuwachs der zweiten Schwierigkeit');
    $result = json_decode((string) $db->value('SELECT result FROM activities WHERE id = ?', [$b['id']]), true);
    coreNear($gains[1], (float) $result['skill'], 1e-6, 'Ergebnis nennt den Zuwachs im neuen Maß');
    coreNear(floor(($before[1] + $gains[1]) * 10) / 10, (float) $result['skillAfter'], 0.11, 'und das Können danach im neuen Maß');
    check($a['id'] !== $b['id']);
});

test('Kern im Spiel: Privatlehrer geben Können im neuen Maß und unterrichten bis zu ihrer neuen Grenze', function () {
    [$db, $p] = coreLabel();
    $db->exec('UPDATE labels SET money = 100000');
    $state = $p->get('state');
    $guitar = array_values(array_filter($state['musicians'], static fn ($m) => $m['instruments'][0]['type'] === 'guitar'))[0];
    $drums = array_values(array_filter($state['musicians'], static fn ($m) => $m['instruments'][0]['type'] === 'drums'))[0];
    $db->exec("UPDATE musicians SET skill1 = 20 WHERE id = ?", [$guitar['id']]);
    $db->exec("UPDATE musicians SET instr1 = 'guitar', skill1 = 61 WHERE id = ?", [$drums['id']]);
    $state = $p->post('mailTest')['state'];
    $job = array_values(array_filter($state['mail'], static fn ($m) => $m['kind'] === 'job' && $m['role'] === 'teacher' && $m['tier'] === 1))[0];
    $data = json_decode((string) $db->value('SELECT data FROM mail WHERE id = ?', [$job['mailId']]), true);
    $data['instrument'] = 'guitar';
    $data['genre'] = null;
    $db->update('mail', ['data' => json_encode($data)], ['id' => $job['mailId']]);
    coreNear(Balance::CORE_TEACHER[1]['gain'], (float) $job['gain'], 1e-9, 'Brief: Zuwachs im neuen Maß');
    coreNear(Balance::CORE_TEACHER[1]['max'], (float) $job['maxSkill'], 1e-9, 'Brief: Grenze im neuen Maß');
    // Alt 61 ist neu 448: über der Grenze 400 der ersten Stufe
    checkApiError(fn () => $p->post('mailHire', ['mailId' => $job['mailId'], 'target' => $drums['id']]), 'kann schon mehr');
    $before = Signed\Game\Levels::shown(20.0);
    $state = $p->post('mailHire', ['mailId' => $job['mailId'], 'target' => $guitar['id']])['state'];
    coreNear(Balance::CORE_TEACHER[1]['gain'], (float) $state['tutors'][0]['gain'], 1e-9);
    Clock::advance(Balance::TEACHER['tiers'][1]['seconds'] / 2);
    $half = $p->get('state')['tutors'][0]['done'];
    coreNear(Balance::CORE_TEACHER[1]['gain'] / 2, (float) $half, 0.05, 'Nach der halben Zeit die Hälfte');
    Clock::advance(Balance::TEACHER['tiers'][1]['seconds']);
    $p->get('state');
    $after = Signed\Game\Levels::shown((float) $db->value('SELECT skill1 FROM musicians WHERE id = ?', [$guitar['id']]));
    coreNear($before + Balance::CORE_TEACHER[1]['gain'], $after, 0.01, 'Am Ende genau der Zuwachs (auch über die Stufengrenze 100 hinweg)');
});

test('Kern im Spiel: Scout und Lehrer nennen ihre Können-Grenze im neuen Maß (sonst passt im Browser kein Lehrer)', function () {
    [, $p] = coreLabel();
    $mail = $p->post('mailTest')['state']['mail'];
    $job = static fn (string $role, int $tier) => array_values(array_filter($mail, static fn ($m) => $m['kind'] === 'job' && $m['role'] === $role && $m['tier'] === $tier))[0];
    $scout = $job('scout', 1);
    coreNear(round(Signed\Game\Levels::shown(34.0), 1), (float) $scout['maxSkill'], 1e-9, 'Scout Stufe 1: bis 34 alt, also bis 97,1 neu');
    coreNear(0.5, (float) $scout['multiGenre'], 1e-9, 'Scout Stufe 1: jedes zweite Talent mit mehreren Genres');
    coreNear(0.85, (float) $job('scout', 3)['multiGenre'], 1e-9);
    $teacher = $job('teacher', 1);
    coreNear(Balance::CORE_TEACHER[1]['max'], (float) $teacher['maxSkill'], 1e-9, 'Lehrer Stufe 1: bis 400 (neues Maß)');
    // Der Browser vergleicht das gezeigte Können der Musiker mit dieser Grenze: Ein Musiker mit altem Können 40 (neu 160) kann noch lernen
    check(Signed\Game\Levels::shown(40.0) < (float) $teacher['maxSkill'], 'Musiker unter der Grenze passt');
    $staff = $p->post('mailHire', ['mailId' => $scout['mailId']])['state']['scouts'][0];
    coreNear((float) $scout['maxSkill'], (float) $staff['maxSkill'], 1e-9, 'Auch der eingestellte Scout zeigt das neue Maß');
});

test('Kern im Spiel: Die laufende Probe zählt im neuen Maß gleichmäßig hoch, so wie der Browser weiterrechnet', function () {
    [$db, $p, $repo, $band] = coreLabel();
    equipBand($p, $db, $band);
    $id = (int) $db->value('SELECT id FROM musicians WHERE band_id = ? ORDER BY id LIMIT 1', [$band['id']]);
    $db->exec('UPDATE musicians SET skill1 = 20 WHERE band_id = ?', [$band['id']]);
    $shown = static fn (): float => Signed\Game\Levels::shown(Signed\Game\BandCalc::skill($repo->musician($id)));
    $start = $shown();
    $act = $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'rehearsal'])['activity'];
    // So rechnet der Browser zwischen zwei Spielständen weiter: Zuwachs je Probe-Einheit aus dem Spielstand
    $state = $p->get('state');
    $rate = (float) array_values(array_filter($state['musicians'], static fn ($m) => $m['id'] === $id))[0]['gains']['rehearsal'];
    check($rate > 0, 'Die Probe bringt etwas');
    $credited = static function (array $state) use ($act, $id): float {
        $run = array_values(array_filter($state['running'], static fn ($a) => $a['id'] === $act['id']))[0];
        return (float) ((array) $run['rehearsal']['credited'])[$id];
    };
    Clock::advance(300);
    $first = $credited($p->get('state'));
    coreNear($rate * 300 / Signed\Game\Balance::REHEARSAL['seconds'], $first, 0.005, 'Nach 5 Minuten so viel, wie der Browser zeigt');
    coreNear($shown() - $start, $first, 1e-6, 'Gutgeschrieben wird im neuen Maß (gespeichert auf der alten Skala)');
    Clock::advance(300);
    $second = $credited($p->get('state'));
    coreNear(2 * $first, $second, 0.005, 'Gleichmäßig weiter, kein Zurückspringen');
    $res = $p->post('rehearsalStop', ['activityId' => $act['id']]);
    $result = $db->value('SELECT result FROM activities WHERE id = ?', [$act['id']]);
    coreNear($shown() - $start, (float) json_decode($result, true)['skillBy'][$id], 0.001, 'Ergebnis im selben Maß');
    checkEquals([], $res['state']['running']);
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

/** Ein Instrument-Modell dieser Art in dieser Güteklasse (0 grau … 3 gold). */
function coreGearModel(string $type, int $tier): string
{
    foreach (Signed\Game\Catalog::GEAR as $key => $g) {
        if ($g['type'] === $type && Signed\Game\Gear::modelTier($key) === $tier) {
            return $key;
        }
    }
    throw new RuntimeException("Kein Modell für $type in Stufe $tier");
}

test('Kern im Spiel: Instrumentenwunsch – höchstens eine Stufe unter dem Können, Gold verlangt niemand, erst zwei Tage nach dem Aufstieg', function () {
    [$db, $p, $repo, $band] = coreLabel();
    giveManager($db, $band['id']);
    $m = array_values($repo->members($band['id']))[0];
    $type = Signed\Game\BandCalc::instrument($m);
    $slot = $type === $m['instr1'] ? 'skill1' : 'skill2';
    // Können-Stufe 0 und 1: grau reicht; 2 (Profi): grün; 3 (Virtuose): blau
    foreach ([[20.0, 0], [40.0, 0], [70.0, 1], [90.0, 2], [100.0, 2]] as [$skill, $tier]) {
        checkEquals($tier, Signed\Game\Gear::wantedTier([$slot => $skill] + $m, $type), "Wunsch bei Können $skill");
    }
    $gear = static fn (int $tier) => [$m['id'] => [$type => ['key' => coreGearModel($type, $tier)]]];
    check(!Signed\Game\Mood::gearNeed([$slot => 70.0] + $m, $gear(1)), 'Profi mit Grün ist zufrieden');
    check(Signed\Game\Mood::gearNeed([$slot => 70.0] + $m, $gear(0)), 'Profi mit Grau wartet auf Grün');
    check(!Signed\Game\Mood::gearNeed([$slot => 95.0] + $m, $gear(2)), 'Virtuose mit Blau ist zufrieden, Gold verlangt niemand');
    check(Signed\Game\Mood::gearNeed([$slot => 95.0] + $m, $gear(1)), 'Virtuose mit Grün wartet auf Blau');
    // Im Spiel: Profi mit grauem Instrument. Seine Uhr beginnt bei −48 Stunden, erst ab 0 äußert er den Wunsch.
    $db->exec('UPDATE gear SET musician_id = NULL WHERE musician_id = ?', [$m['id']]);
    $db->exec('UPDATE gear SET musician_id = ? WHERE id = ?', [$m['id'], $repo->insertGear(coreGearModel($type, 0))]);
    $db->exec("UPDATE musicians SET $slot = 70, gear_wait = NULL WHERE id = ?", [$m['id']]);
    $db->exec('UPDATE musicians SET wellbeing = 80 WHERE band_id = ?', [$band['id']]);
    $p->get('state');
    Clock::advance(3600);
    $state = $p->get('state');
    coreNear(-47.0, (float) $db->value('SELECT gear_wait FROM musicians WHERE id = ?', [$m['id']]), 0.01, 'Uhr beginnt unter null');
    $texts = static fn (array $state) => array_column(bandById($state, $band['id'])['mood']['wishes'], 'text');
    $why = static fn (array $state) => array_column(bandById($state, $band['id'])['mood']['why'][$m['id']]['items'] ?? [], 1, 0);
    check(!in_array('Ich will ein besseres Instrument.', $texts($state), true), 'Gleich nach dem Aufstieg noch kein Wunsch');
    check(isset($why($state)['Mag sein Instrument']), 'Er mag sein Instrument noch');
    // Zwei Tage nach dem Aufstieg: Er äußert den Wunsch, das Instrument drückt; die Wartezeit kostet erst nach weiteren 24 Stunden
    $db->exec('UPDATE musicians SET gear_wait = 0.5 WHERE id = ?', [$m['id']]);
    $state = $p->get('state');
    check(in_array('Ich will ein besseres Instrument.', $texts($state), true), 'Nach zwei Tagen der Wunsch');
    $items = $why($state);
    check(isset($items['Spielt besser, als das Instrument hergibt']), 'Jetzt merkt er es');
    check(!array_filter(array_keys($items), static fn ($t) => str_starts_with($t, 'Wartet schon')), 'Warten kostet noch nichts');
    $db->exec('UPDATE musicians SET gear_wait = 25 WHERE id = ?', [$m['id']]);
    $items = $why($p->get('state'));
    $waiting = array_values(array_filter($items, static fn ($t) => str_starts_with($t, 'Wartet schon'), ARRAY_FILTER_USE_KEY));
    coreNear(-Signed\Game\Core\Needs::penalty(25, Signed\Game\Core\Needs::perHour('gear', $m)), (float) ($waiting[0] ?? 0), 0.05, 'Nach einem Tag Warten drückt es');
    // Grün gekauft: Er ist zufrieden, die Uhr ist weg
    $db->exec('UPDATE gear SET musician_id = NULL WHERE musician_id = ?', [$m['id']]);
    $db->exec('UPDATE gear SET musician_id = ? WHERE id = ?', [$m['id'], $repo->insertGear(coreGearModel($type, 1))]);
    Clock::advance(60);
    $state = $p->get('state');
    checkEquals(null, $db->value('SELECT gear_wait FROM musicians WHERE id = ?', [$m['id']]), 'Kein Warten mehr');
    check(!in_array('Ich will ein besseres Instrument.', $texts($state), true), 'Kein Wunsch mehr');
});

test('Kern im Spiel: Eine Platte wünscht sich die Band erst mit genug Songs und eine Woche nach der letzten', function () {
    $t0 = 1_800_000_000;
    $wish = static fn (array $done, ?int $ready, float $songs) => Signed\Game\Mood::recordWishHours(['recordsDone' => $done, 'songsReady' => $ready], $songs, $t0);
    checkEquals(null, $wish([], null, 9.9), 'Ohne 10 Songs kein Wunsch');
    coreNear(5.0, $wish([], $t0 - 5 * 3600, 10.2), 1e-9, 'Vor der ersten Platte: sobald genug Songs da sind');
    checkEquals(null, $wish([$t0 - 6 * 86400], $t0 - 3 * 86400, 12), 'Die letzte Platte ist erst 6 Tage her');
    coreNear(24.0, $wish([$t0 - 8 * 86400], $t0 - 3 * 86400, 12), 1e-9, 'Ab dem siebten Tag nach der letzten Platte');
    coreNear(2.0, $wish([$t0 - 10 * 86400], $t0 - 2 * 3600, 10), 1e-9, 'Die Songs kamen später');

    [$db, $p, $repo, $band] = coreLabel();
    giveManager($db, $band['id']);
    [$a, $b] = array_values($repo->members($band['id']));
    $db->exec('UPDATE musicians SET personality = ?, known_traits = NULL, wellbeing = 45 WHERE id = ?', [json_encode(['kreativ']), $a['id']]);
    $db->exec('UPDATE musicians SET personality = ?, known_traits = NULL, wellbeing = 70 WHERE id = ?', [json_encode(['ehrgeizig']), $b['id']]);
    $kreativ = Signed\Game\Catalog::WISHES['kreativ'][0];
    $songs = static function (int $count, int $at) use ($db, $repo, $band): void {
        $db->exec('DELETE FROM songs WHERE band_id = ? AND record_id IS NULL', [$band['id']]);
        for ($i = 0; $i < $count; $i++) {
            // Der jüngste kam um $at: Ab dann hat der Vorrat so viele Songs
            $db->insert('songs', ['label_id' => $repo->labelId, 'band_id' => $band['id'], 'quality' => 60, 'record_id' => null, 'created_at' => $at - $i]);
        }
        $db->exec('UPDATE bands SET songs_written = ? WHERE id = ?', [$count + 0.2, $band['id']]);
    };
    $wishes = static fn (array $state) => array_column(bandById($state, $band['id'])['mood']['wishes'], 'text');
    $stagnant = static function (array $state) use ($band, $b): float {
        $items = array_column(bandById($state, $band['id'])['mood']['why'][$b['id']]['items'] ?? [], 1, 0);
        return (float) ($items['Will endlich eine neue Platte machen'] ?? 0.0);
    };
    // Neun Songs: Die Kreative ist unzufrieden, wünscht sich aber keine Platte; den Ehrgeizigen drängt nichts
    $songs(9, Clock::now() - 5 * 86400);
    $state = $p->get('state');
    check(!in_array($kreativ, $wishes($state), true), 'Mit 9 Songs kein Plattenwunsch');
    checkEquals(0.0, $stagnant($state), 'Mit 9 Songs keine Unruhe ohne Platte');
    // Zehn Songs (seit drei Tagen), noch keine Platte: Jetzt wünscht sie sich eine, und es drängt seit drei Tagen
    $songs(10, Clock::now() - 3 * 86400);
    $state = $p->get('state');
    check(in_array($kreativ, $wishes($state), true), 'Mit 10 Songs der Plattenwunsch');
    coreNear(-Signed\Game\Core\Needs::penalty(72, Signed\Game\Core\Needs::perHour('stagnant', ['personality' => ['ehrgeizig']])), $stagnant($state), 0.05, 'Unruhe seit drei Tagen');
    // Die letzte Platte ist zwei Tage alt: kein Wunsch, keine Unruhe, auch mit genug Songs
    $db->insert('records', ['label_id' => $repo->labelId, 'band_id' => $band['id'], 'title' => 'Frisch', 'songs_total' => 12, 'songs_done' => 12,
        'song_results' => '[]', 'status' => 'done', 'created_at' => Clock::now() - 2 * 86400, 'finished_at' => Clock::now() - 2 * 86400]);
    $state = $p->get('state');
    check(!in_array($kreativ, $wishes($state), true), 'Zwei Tage nach der Platte kein Plattenwunsch');
    checkEquals(0.0, $stagnant($state), 'Zwei Tage nach der Platte keine Unruhe');
    // Fünf Tage später (eine Woche nach der Platte) kommt der Wunsch zurück; die Unruhe drückt erst nach einem weiteren Tag
    Clock::advance(5 * 86400);
    $db->exec('UPDATE musicians SET wellbeing = 45 WHERE id = ?', [$a['id']]);
    $state = $p->get('state');
    check(in_array($kreativ, $wishes($state), true), 'Eine Woche nach der Platte der Wunsch');
    checkEquals(0.0, $stagnant($state), 'Am ersten Tag kostet die Unruhe noch nichts');
});

test('Kern im Spiel: Die Wunsch-Uhr der Kreativen läuft erst, wenn eine Platte möglich und fällig ist', function () {
    [$db, $p, $repo, $band] = coreLabel();
    $a = array_values($repo->members($band['id']))[0];
    $db->exec('UPDATE musicians SET personality = ?, wellbeing = 45, wish_wait = NULL WHERE id = ?', [json_encode(['kreativ']), $a['id']]);
    $db->exec('UPDATE bands SET songs_written = 2.0, write_mode = ? WHERE id = ?', ['normal', $band['id']]);
    $p->get('state');
    Clock::advance(6 * 3600);
    $p->get('state');
    checkEquals(null, $db->value('SELECT wish_wait FROM musicians WHERE id = ?', [$a['id']]), 'Mit 2 Songs wartet niemand auf eine Platte');
});

test('Kern im Spiel: Eine neue Platte frühestens 3 Tage nach der letzten – der Knopf sagt, warum und wie lange noch', function () {
    [$db, $p, $repo, $band] = coreLabel();
    for ($i = 0; $i < 14; $i++) {
        $db->insert('songs', ['label_id' => $repo->labelId, 'band_id' => $band['id'], 'quality' => 60, 'record_id' => null, 'created_at' => Clock::now()]);
    }
    $db->exec('UPDATE bands SET songs_written = 14.5 WHERE id = ?', [$band['id']]);
    checkEquals(0, bandById($p->get('state'), $band['id'])['core']['recordAt'], 'Vor der ersten Platte geht es sofort');
    coreRelease($repo, $band, 80);
    $released = Clock::now();
    checkEquals($released + 3 * 86400, bandById($p->get('state'), $band['id'])['core']['recordAt']);
    $opts = $p->post('recordOptions', ['bandId' => $band['id']]);
    check(str_contains((string) $opts['blocker'], 'Die letzte Platte ist noch ganz frisch'), 'Grund: ' . $opts['blocker']);
    check(str_contains((string) $opts['blocker'], 'Wieder möglich in 3 Tagen.'), 'Wartezeit: ' . $opts['blocker']);
    $start = fn () => $p->post('recordStart', ['bandId' => $band['id'], 'title' => 'Zu früh', 'level' => 0, 'songIds' => array_slice($opts['picked'], 0, 10), 'cover' => $opts['covers'][0]]);
    checkApiError($start, 'noch ganz frisch');
    Clock::advance(2 * 86400 + 3600);
    $opts = $p->post('recordOptions', ['bandId' => $band['id']]);
    check(str_contains((string) $opts['blocker'], 'Wieder möglich in 23 Stunden.'), 'Wartezeit: ' . $opts['blocker']);
    Clock::advance(22 * 3600 + 30 * 60);
    $opts = $p->post('recordOptions', ['bandId' => $band['id']]);
    check(str_contains((string) $opts['blocker'], 'Wieder möglich in einer Stunde.'), 'Wartezeit: ' . $opts['blocker']);
    Clock::advance(30 * 60);
    $opts = $p->post('recordOptions', ['bandId' => $band['id']]);
    checkEquals(null, $opts['blocker'], 'Nach drei Tagen geht es wieder');
    $p->post('recordStart', ['bandId' => $band['id'], 'title' => 'Pünktlich', 'level' => 0, 'songIds' => array_slice($opts['picked'], 0, 10), 'cover' => $opts['covers'][0]]);
    checkEquals('open', $db->value("SELECT status FROM records WHERE band_id = ? AND title = 'Pünktlich'", [$band['id']]));
});

test('Kern im Spiel: Kreative mögen Aufnehmen und Festivals, Proben nicht', function () {
    check(Signed\Game\Mood::liking(['kreativ'], 'record') > 0, 'Aufnehmen');
    check(Signed\Game\Mood::liking(['kreativ'], 'festival') > 0, 'Festival');
    check(Signed\Game\Mood::liking(['kreativ'], 'rehearsal') < 0, 'Proben nicht');
    checkEquals(0, Signed\Game\Mood::liking(['kreativ'], 'gig'), 'Auftreten ist ihnen egal');
});

test('Kern im Spiel: Eine Platte erscheint, wenn die Studiozeit um ist, auch wenn gerade niemand da ist', function () {
    [$db, $p, $repo, $band] = coreLabel();
    for ($i = 0; $i < 12; $i++) {
        $db->insert('songs', ['label_id' => $repo->labelId, 'band_id' => $band['id'], 'quality' => 60, 'record_id' => null, 'created_at' => Clock::now()]);
    }
    $db->exec('UPDATE bands SET songs_written = 12.3 WHERE id = ?', [$band['id']]);
    $opts = $p->post('recordOptions', ['bandId' => $band['id']]);
    $p->post('recordStart', ['bandId' => $band['id'], 'title' => 'Über Nacht', 'level' => 0, 'songIds' => array_slice($opts['picked'], 0, 10), 'cover' => $opts['covers'][0]]);
    $ends = (int) $db->value("SELECT ends_at FROM activities WHERE band_id = ? AND type = 'record'", [$band['id']]);
    checkEquals(Clock::now() + 10 * Balance::RECORD_SECONDS_PER_SONG, $ends);
    // Zwei Tage niemand da: Beim nächsten Besuch ist sie seit zwei Tagen draußen, der Abstand zählt ab dann
    Clock::advance(2 * 86400);
    $state = $p->get('state');
    $record = $db->one("SELECT finished_at, core FROM records WHERE band_id = ? AND title = 'Über Nacht'", [$band['id']]);
    checkEquals($ends, (int) $record['finished_at'], 'Erschienen, als die Studiozeit um war');
    checkEquals($ends, (int) json_decode($record['core'], true)['releasedAt'], 'Der Hype läuft seit dem Erscheinen');
    checkEquals($ends + 3 * 86400, bandById($state, $band['id'])['core']['recordAt'], 'Die nächste Platte geht drei Tage nach dem Erscheinen');
});

/** Neuer Musiker mit festem Charakter und Können, im Kern mit seinen Songs ($songs: feste Qualitäten, sonst gewürfelt wie auf einer Kassette). */
function coreNewcomer(Repo $repo, array $traits, float $skill, ?array $songs = null, string $instrument = 'bass'): int
{
    $card = Signed\Game\Generator::musician('common', ['instrument' => $instrument, 'genre' => 'rock', 'single' => true]);
    $card['personality'] = $traits;
    $card['skill1'] = $skill;
    $card['songs'] = $songs ?? Signed\Game\SongStock::roll($card);
    return $repo->insertMusician($card, 'test');
}

test('Kern im Spiel: Neue Musiker bringen eigene Songs mit – wie viele und wie gute nach Können und Charakter', function () {
    [$db, $p, $repo] = coreLabel();
    $roll = static fn (array $traits, float $skill) => Signed\Game\SongStock::roll(['skill1' => $skill, 'skill2' => null, 'personality' => $traits]);
    for ($i = 0; $i < 20; $i++) {
        checkEquals([], $roll(['technisch'], 90), 'Technische bringen keine Songs');
    }
    $counts = static function (array $traits, float $skill) use ($roll): array {
        $n = [];
        for ($i = 0; $i < 300; $i++) {
            $n[] = count($roll($traits, $skill));
        }
        return [array_sum($n) / count($n), min($n), max($n)];
    };
    [$avg, $min, $max] = $counts(['kreativ'], 70);
    check($avg > 9 && $avg < 12 && $min >= 5 && $max <= 16, "Kreative Profis bringen viele: im Schnitt $avg ($min bis $max)");
    [$avg] = $counts(['gelassen'], 20);
    check($avg > 2.4 && $avg < 3.6, "Anfänger bringen wenige: im Schnitt $avg");
    // Perfektionisten bringen weniger, aber öfter gute (ab 70)
    $good = static function (array $traits) use ($roll): float {
        $all = [];
        for ($i = 0; $i < 400; $i++) {
            $all = array_merge($all, $roll($traits, 40));
        }
        return count(array_filter($all, static fn ($q) => $q >= 70)) / max(1, count($all));
    };
    check($good(['perfektionist']) > $good(['gelassen']) + 0.08, 'Perfektionisten treffen öfter');
    check($good(['perfektionist']) < 0.5, 'Auch sie bringen mehr schwache als gute');
    // Unterschreiben: Die Songs liegen erst in der Tasche, dann im Vorrat der Solo-Band; die Karte sagt, wie viele
    $id = coreNewcomer($repo, ['kreativ'], 70, [82.0, 64.5, 31.0]);
    checkEquals(3, (int) $db->value('SELECT COUNT(*) FROM songs WHERE musician_id = ? AND band_id = 0', [$id]), 'In der Tasche');
    $state = $p->get('state');
    $solo = (int) $db->value('SELECT band_id FROM musicians WHERE id = ?', [$id]);
    checkEquals([82.0, 64.5, 31.0], array_map('floatval', array_column(bandById($state, $solo)['core']['stock'], 'quality')), 'Im Vorrat der Solo-Band');
    coreNear(3.0, (float) $db->value('SELECT songs_written FROM bands WHERE id = ?', [$solo]), 1e-9, 'Zähler passt zum Vorrat');
    checkEquals(0, (int) $db->value('SELECT COUNT(*) FROM songs WHERE band_id = 0'), 'Tasche leer');
    checkEquals(3, Signed\Game\Submissions::presentCard(['songs' => [1, 2, 3], 'ability_choice' => null] + Signed\Game\Generator::musician('common'), -1)['songs'], 'Karte zeigt die Zahl');
});

test('Kern im Spiel: Songs ziehen mit – einsteigen, zusammentun, aussteigen, auflösen; die Band schreibt, wer schreibt', function () {
    [$db, $p, $repo, $band] = coreLabel();
    $stock = static fn (int $bandId) => array_map('floatval', array_column(Signed\Game\SongStock::stock($db, $bandId), 'quality'));
    $a = coreNewcomer($repo, ['kreativ'], 40, [80.0, 60.0, 30.0], 'bass');
    $b = coreNewcomer($repo, ['gelassen'], 40, [75.0, 40.0], 'keys');
    $p->get('state');
    $soloA = (int) $db->value('SELECT band_id FROM musicians WHERE id = ?', [$a]);
    $soloB = (int) $db->value('SELECT band_id FROM musicians WHERE id = ?', [$b]);
    // Einsteigen: Die Solo-Band verschwindet, ihre Songs kommen mit
    $p->post('bandAddMember', ['bandId' => $band['id'], 'musicianId' => $a]);
    checkEquals([80.0, 60.0, 30.0], $stock($band['id']), 'Songs der Neuen im Vorrat der Band');
    checkEquals(0, (int) $db->value('SELECT COUNT(*) FROM songs WHERE band_id = ?', [$soloA]), 'Nichts bleibt bei der alten Solo-Band');
    coreNear(3.0, floor((float) $db->value('SELECT songs_written FROM bands WHERE id = ?', [$band['id']])), 1e-9);
    // Zusammentun: Der Vorrat der anderen kommt mit
    $p->post('bandMerge', ['bandId' => $band['id'], 'otherId' => $soloB, 'keepName' => 'a']);
    checkEquals([80.0, 75.0, 60.0, 40.0, 30.0], $stock($band['id']), 'Beide Vorräte zusammen');
    // Aussteigen: Wer geht, nimmt seine eigenen Songs mit in die Solo-Band
    $p->post('bandRemoveMember', ['bandId' => $band['id'], 'musicianId' => $a]);
    checkEquals([75.0, 40.0], $stock($band['id']), 'Die Band behält die anderen');
    checkEquals([80.0, 60.0, 30.0], $stock((int) $db->value('SELECT band_id FROM musicians WHERE id = ?', [$a])), 'Seine Songs gehen mit');
    coreNear(2.0, floor((float) $db->value('SELECT songs_written FROM bands WHERE id = ?', [$band['id']])), 1e-9);
    // Neue Songs: Jeden schreibt einer, der schreibt (Technische nie)
    $members = array_values($repo->members($band['id']));
    $db->exec('UPDATE musicians SET personality = ? WHERE id = ?', [json_encode(['technisch']), $members[0]['id']]);
    $db->exec('UPDATE musicians SET wellbeing = 90 WHERE band_id = ?', [$band['id']]);
    $p->get('state');
    Clock::advance(24 * 3600);
    $p->get('state');
    $writers = array_map('intval', array_column($db->all('SELECT musician_id FROM songs WHERE band_id = ? AND record_id IS NULL AND musician_id IS NOT NULL', [$band['id']]), 'musician_id'));
    check(count($writers) >= 5, 'Die Band hat geschrieben: ' . count($writers));
    check(!in_array((int) $members[0]['id'], $writers, true), 'Technische schreiben keine Songs');
    // Auflösen: Jeder nimmt seine Songs mit
    $before = array_count_values($writers);
    $p->post('bandDisband', ['bandId' => $band['id']]);
    $state = $p->get('state');
    foreach ($before as $musicianId => $count) {
        $solo = (int) $db->value('SELECT band_id FROM musicians WHERE id = ?', [$musicianId]);
        check(count($stock($solo)) >= $count, 'Songs gehen mit in die Solo-Band');
    }
    check(is_array($state['bands']), 'Spielstand geht');
});

test('Kern im Spiel: Der Songvorrat hat keine Grenze, kein Songstau; „Wir wollen aufnehmen!“ erst mit dem Plattenwunsch', function () {
    [$db, $p, $repo, $band] = coreLabel();
    giveManager($db, $band['id']);
    $start = 60;
    for ($i = 0; $i < $start; $i++) {
        $db->insert('songs', ['label_id' => $repo->labelId, 'band_id' => $band['id'], 'quality' => 20 + $i, 'record_id' => null, 'created_at' => Clock::now()]);
    }
    $db->exec('UPDATE bands SET songs_written = ? WHERE id = ?', [$start + 0.5, $band['id']]);
    $db->exec('UPDATE musicians SET wellbeing = 90 WHERE band_id = ?', [$band['id']]);
    coreRelease($repo, $band, 80);
    $p->get('state');
    Clock::advance(18 * 3600);
    $state = $p->get('state');
    $left = array_map('floatval', array_column(Signed\Game\SongStock::stock($db, $band['id']), 'quality'));
    check(count($left) > $start, sprintf('Der Vorrat wächst über %d hinaus (%d Songs)', $start, count($left)));
    check(in_array(20.0, $left, true), 'Auch der schwächste bleibt');
    checkEquals(count($left), (int) floor((float) $db->value('SELECT songs_written FROM bands WHERE id = ?', [$band['id']])), 'Zähler passt zum Vorrat');
    // Kein Songstau im neuen Kern; die Band will erst aufnehmen, wenn die letzte Platte eine Woche her ist
    foreach (bandById($state, $band['id'])['mood']['why'] as $why) {
        check(!isset(array_column($why['items'] ?? [], 1, 0)['Zu viele Songs warten auf eine Aufnahme']), 'Kein Songstau');
    }
    $texts = static fn (array $s) => array_column(bandById($s, $band['id'])['mood']['wishes'], 'text');
    check(!in_array('Wir wollen aufnehmen!', $texts($state), true), 'Volle Kiste, aber die Platte ist frisch');
    Clock::advance(7 * 86400);
    check(in_array('Wir wollen aufnehmen!', $texts($p->get('state')), true), 'Eine Woche später wollen sie aufnehmen');
    $hit = bandById($state, $band['id'])['mood']['hitRate'];
    check($hit > 0.05 && $hit < 0.5, "Trefferquote der Band auf der Bandseite: $hit");
});

test('Kern im Spiel: Kassetten und Scout-Talente bringen Songs nach ihrem echten Können mit; ausgepackte Kassetten bleiben liegen', function () {
    [$db, $p, $repo] = coreLabel();
    // Mitgebrachte Songs passen zur Können-Stufe der Karte (vorher wurden sie gewürfelt, bevor das Können feststand: immer wie Anfänger)
    $avg = static function (callable $make): float {
        $n = 0;
        for ($i = 0; $i < 150; $i++) {
            $n += count($make()['songs']);
        }
        return $n / 150;
    };
    $beginners = $avg(static fn () => Signed\Game\Submissions::make(0));
    $pros = $avg(static fn () => Signed\Game\Submissions::make(2));
    check($pros > 1.8 * $beginners, "Profis bringen mehr Songs mit als Anfänger ($pros gegen $beginners)");
    $talents = 0;
    for ($i = 0; $i < 40; $i++) {
        $talents += array_sum(array_map(static fn ($c) => count($c['songs']), Signed\Game\Scouting::find(3, null, null)));
    }
    check($talents / 120 > $beginners * 1.5, 'Scout-Talente der Stufe 3 bringen mehr als Anfänger');
    // Keine Grenze (Sandra, 1.11.): Auch was eine Assistenz schon ausgepackt hat, bleibt liegen, bis es verfällt
    $p->get('state');
    $db->exec("UPDATE submissions SET worker = 'a1', unpacked_at = created_at WHERE label_id = ?", [$repo->labelId]);
    $ids = array_map('intval', array_column($db->all('SELECT id FROM submissions WHERE label_id = ?', [$repo->labelId]), 'id'));
    check(count($ids) > 0, 'Es liegen Kassetten da');
    Clock::advance(86400);
    $p->get('state');
    $kept = (int) $db->value('SELECT COUNT(*) FROM submissions WHERE label_id = ? AND id IN (' . implode(',', $ids) . ')', [$repo->labelId]);
    checkEquals(count($ids), $kept, 'Nichts fliegt raus');
    $unheard = (int) $db->value('SELECT COUNT(*) FROM submissions WHERE label_id = ? AND opened_at IS NULL AND saved = 0', [$repo->labelId]);
    check($unheard > 40, "Mehr als 40 ungehörte ($unheard)");
});

test('Kern im Spiel: Herausgenommenes lehnt der Server ab; Entlassen prüft erst, ob der Musiker frei ist', function () {
    [$db, $p, $repo, $band] = coreLabel();
    $m = array_values($repo->members($band['id']))[0];
    foreach (['soloStart' => ['musicianId' => $m['id'], 'type' => 'practice'], 'practiceAuto' => ['musicianId' => $m['id'], 'slot' => 1],
        'buyPack' => ['pack' => 'starter'], 'scoutOpen' => ['voucher' => true], 'transferList' => ['musicianId' => $m['id']]] as $action => $in) {
        checkApiError(fn () => $p->post($action, $in), 'nicht mehr');
    }
    // Ein beschäftigter Solokünstler: Entlassen geht nicht, und seine Solo-Band bleibt
    $solo = coreNewcomer($repo, ['gelassen'], 20, []);
    $p->get('state');
    $soloBand = (int) $db->value('SELECT band_id FROM musicians WHERE id = ?', [$solo]);
    $db->exec('UPDATE musicians SET busy_until = ? WHERE id = ?', [Clock::now() + 600, $solo]);
    checkApiError(fn () => $p->post('musicianRelease', ['musicianId' => $solo]), 'unterwegs');
    checkEquals($soloBand, (int) $db->value('SELECT band_id FROM musicians WHERE id = ?', [$solo]), 'Die Solo-Band bleibt');
    checkEquals(1, (int) $db->value('SELECT COUNT(*) FROM bands WHERE id = ?', [$soloBand]));
});
