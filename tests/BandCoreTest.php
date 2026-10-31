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
