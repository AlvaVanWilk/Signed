<?php
declare(strict_types=1);

use Signed\Clock;
use Signed\Game\Balance;

/** Spielt den Kernkreislauf einmal komplett über die API durch. */

/** Registriert ein Label und gibt ihm drei gewöhnliche Rock-Musiker, damit die Tests gleich eine Band haben. */
function registerPlayer(Signed\Db $db, string $name): Player
{
    $p = player($db);
    $res = $p->post('register', ['username' => strtolower($name), 'password' => 'geheim123', 'labelName' => "$name Records"]);
    $repo = new Signed\Game\Repo($db, $res['label']['id']);
    foreach (['vocals', 'guitar', 'drums'] as $instrument) {
        $repo->insertMusician(Signed\Game\Generator::musician('common', ['instrument' => $instrument, 'genre' => 'rock', 'single' => true]), 'test');
    }
    return $p;
}

/** Gründet aus den drei Test-Musikern eine Band. */
function foundBand(Player $p, string $name = 'Die Ersten'): array
{
    $state = $p->get('state');
    $members = array_map(static fn ($m) => ['id' => $m['id'], 'slot' => 1], $state['musicians']);
    $genre = $state['musicians'][0]['genres'][0];
    $res = $p->post('bandCreate', ['name' => $name, 'genre' => $genre, 'members' => $members]);
    return array_values(array_filter($res['state']['bands'], static fn ($b) => $b['id'] === $res['bandId']))[0];
}

function playSong(Player $p, array $start, float $hitRate = 1.0): array
{
    $game = $p->post('minigameStart', $start)['game'];
    Clock::advance(Balance::SONG_SECONDS + 2);
    $count = $game['count'];
    $perfect = (int) floor($count * $hitRate);
    return $p->post('minigameFinish', ['sessionId' => $game['sessionId'], 'perfect' => $perfect, 'good' => 0, 'miss' => $count - $perfect]);
}

function bandById(array $state, int $id): array
{
    foreach ($state['bands'] as $band) {
        if ($band['id'] === $id) {
            return $band;
        }
    }
    throw new RuntimeException("Band $id fehlt");
}

test('Registrieren, anmelden, abmelden', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Alva');
    $session = $p->get('session');
    checkEquals('Alva Records', $session['label']['name']);
    checkEquals(Balance::START_MONEY, $session['label']['money']);
    check(isset($session['catalog']['genres']['rock']));

    $p->post('logout');
    checkEquals(null, $p->get('session')['label']);
    checkApiError(fn () => $p->get('state'), 'melde dich an');
    checkApiError(fn () => $p->post('login', ['username' => 'alva', 'password' => 'falsch123']), 'stimmt nicht');
    $p->post('login', ['username' => 'ALVA', 'password' => 'geheim123']);
    checkEquals('Alva Records', $p->get('state')['label']['name']);

    checkApiError(fn () => registerPlayer($db, 'Alva'), 'vergeben');
    checkApiError(fn () => player($db)->post('register', ['username' => 'neu', 'password' => 'geheim123', 'labelName' => 'alva records']), 'Namen');
    checkApiError(fn () => player($db)->post('register', ['username' => 'x', 'password' => 'geheim123', 'labelName' => 'X']), 'Benutzername');
    checkApiError(fn () => $p->get('buyPack'), 'Anfragemethode');
});

test('Neues Label startet ohne Musiker, aber mit Scout-Gutschein', function () {
    $db = freshDb();
    $p = player($db);
    $p->post('register', ['username' => 'neu', 'password' => 'geheim123', 'labelName' => 'Neu Records']);
    $state = $p->get('state');
    checkEquals(0, count($state['musicians']));
    checkEquals(Balance::SCOUT_START_VOUCHERS, $state['scout']['vouchers']);
    checkEquals(null, $state['scout']['offer']);
});

test('Talent-Scout: fünf gute Karten, eine unterschreibt, Kauf nur einmal pro Woche', function () {
    $db = freshDb();
    $p = player($db);
    $p->post('register', ['username' => 'scout', 'password' => 'geheim123', 'labelName' => 'Scout Records']);
    $offer = $p->post('scoutOpen', ['voucher' => true])['offer'];
    checkEquals(Balance::SCOUT_CARDS, count($offer['cards']));
    foreach ($offer['cards'] as $card) {
        check($card['rarity'] !== 'common', 'Nur gute Karten');
    }
    checkEquals($offer['id'], $p->post('scoutOpen', ['voucher' => true])['offer']['id'], 'Offene Auswahl wird wieder gezeigt, kein zweiter Gutschein');
    $res = $p->post('scoutPick', ['offerId' => $offer['id'], 'index' => 2]);
    checkEquals($offer['cards'][2]['firstName'], $res['musician']['firstName']);
    checkEquals(1, count($res['state']['musicians']));
    checkEquals(0, $res['state']['scout']['vouchers']);
    checkApiError(fn () => $p->post('scoutPick', ['offerId' => $offer['id'], 'index' => 1]), 'nicht mehr');
    checkApiError(fn () => $p->post('scoutOpen', ['voucher' => true]), 'keinen Scout-Gutschein');

    $db->exec('UPDATE labels SET money = 10000');
    $offer = $p->post('scoutOpen', [])['offer'];
    checkEquals(10000 - Balance::SCOUT_PRICE, $p->get('state')['label']['money']);
    $p->post('scoutPick', ['offerId' => $offer['id'], 'index' => 0]);
    checkApiError(fn () => $p->post('scoutOpen', []), 'wieder für dich unterwegs');
    Clock::advance(Balance::SCOUT_COOLDOWN);
    $p->post('scoutOpen', []);
});

test('Bandnamen sind im ganzen Spiel eindeutig, der Würfel schlägt freie Namen vor', function () {
    $db = freshDb();
    $a = registerPlayer($db, 'Erste');
    $b = registerPlayer($db, 'Zweite');
    foundBand($a, 'Die Kellerkinder');
    checkApiError(fn () => foundBand($b, 'die kellerkinder'), 'gibt es schon');
    $name = $b->post('nameSuggest', ['kind' => 'band'])['name'];
    check($name !== '' && strtolower($name) !== 'die kellerkinder');
    check($b->post('nameSuggest', ['kind' => 'record'])['name'] !== '');
});

test('Test-Musiker bilden eine gültige Band', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Sandra');
    $band = foundBand($p);
    checkEquals(3, count($band['memberIds']));
    check($band['sound'] > 0);
    checkEquals(0.0, $band['fame']);
    check(!$band['options']['gig']['available'], 'Ohne Platte keine Gigs');
    checkEquals(null, $band['record']['blocker']);
});

test('Packs kosten Geld und landen im Katalog', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Kim');
    $res = $p->post('buyPack', ['pack' => 'studio']);
    checkEquals(5, count($res['cards']));
    checkEquals(Balance::START_MONEY - Balance::PACKS['studio']['price'], $res['state']['label']['money']);
    $musicians = count(array_filter($res['cards'], static fn ($c) => $c['kind'] === 'musician'));
    checkEquals(3 + $musicians, count($res['state']['musicians']));

    $p->post('buyPack', ['pack' => 'gold']);
    checkApiError(fn () => $p->post('buyPack', ['pack' => 'gold']), 'Geld');
    checkApiError(fn () => $p->post('buyPack', ['pack' => 'gibtsnicht']), 'Pack');
});

test('Gold-Pack garantiert eine seltene Karte', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Rich');
    $db->exec('UPDATE labels SET money = 100000');
    for ($i = 0; $i < 20; $i++) {
        $cards = $p->post('buyPack', ['pack' => 'gold'])['cards'];
        $rarities = array_map(static fn ($c) => ($c['musician'] ?? $c['manager'] ?? null)['rarity'] ?? Signed\Game\Catalog::GEAR[$c['gear']['model']]['rarity'], $cards);
        check(array_diff($rarities, ['common']) !== [], 'Mindestens eine nicht-gewöhnliche Karte');
    }
});

test('Bandregeln: ein Genre pro Label, niemand in zwei Bands', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Lou');
    $band = foundBand($p);
    $state = $p->get('state');
    $m = $state['musicians'][0];
    checkApiError(fn () => $p->post('bandCreate', ['name' => 'Zweite', 'genre' => $band['genre'], 'members' => []]), 'Pro Genre');
    $other = $band['genre'] === 'pop' ? 'rock' : 'pop';
    checkApiError(fn () => $p->post('bandCreate', ['name' => 'Zweite', 'genre' => $other, 'members' => [['id' => $m['id']]]]), 'schon in einer Band');
    checkApiError(fn () => $p->post('bandCreate', ['name' => '   ', 'genre' => $other, 'members' => []]), 'Bandname');
});

test('Straßenmusik bringt Geld, sperrt die Band und wird nach Ablauf gutgeschrieben', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Busker');
    $band = foundBand($p);
    $money = $p->get('state')['label']['money'];

    $res = $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'busking']);
    $activity = $res['activity'];
    checkEquals(Balance::BUSKING['songs'] * Balance::SONG_SECONDS, $activity['endsAt'] - $activity['startedAt']);
    check(count($activity['timeline']) >= Balance::BUSKING['songs'] * 2, 'Live-Ticker hat Ereignisse');
    checkApiError(fn () => $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'openstage']), 'beschäftigt');
    checkApiError(fn () => $p->post('bandRemoveMember', ['bandId' => $band['id'], 'musicianId' => $band['memberIds'][0]]), 'beschäftigt');
    checkEquals($money, $p->get('state')['label']['money'], 'Geld erst nach dem Auftritt');

    Clock::advance(Balance::BUSKING['songs'] * Balance::SONG_SECONDS);
    $state = $p->get('state');
    checkEquals(1, count($state['results']));
    $result = $state['results'][0]['result'];
    check($result['money'] > 0);
    checkEquals($money + $result['money'], $state['label']['money']);
    check(bandById($state, $band['id'])['cohesion'] > 0, 'Auftritte steigern die Eingespieltheit');
    check(bandById($state, $band['id'])['released'], 'Band ist jetzt freigegeben');

    $p->post('activityAck', ['activityId' => $state['results'][0]['id']]);
    checkEquals(0, count($p->get('state')['results']));
});

test('Gig ohne Platte ist gesperrt', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Nope');
    $band = foundBand($p);
    checkApiError(fn () => $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'gig', 'songs' => 1]), 'Platte');
});

test('Solo-Probe verbessert das Können, Schummeln wird abgelehnt', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Practice');
    $m = $p->get('state')['musicians'][0];
    $game = $p->post('minigameStart', ['kind' => 'practice', 'musicianId' => $m['id'], 'slot' => 1])['game'];
    check(count($game['notes']) > 10);
    $count = $game['count'];

    checkApiError(fn () => $p->post('minigameFinish', ['sessionId' => $game['sessionId'], 'perfect' => $count, 'good' => 0, 'miss' => 0]), 'noch nicht vorbei');
    Clock::advance(Balance::SONG_SECONDS);
    checkApiError(fn () => $p->post('minigameFinish', ['sessionId' => $game['sessionId'], 'perfect' => $count + 5, 'good' => 0, 'miss' => 0]), 'Ungültig');
    checkApiError(fn () => $p->post('promoStart', ['musicianId' => $m['id']]), 'beschäftigt');

    $result = $p->post('minigameFinish', ['sessionId' => $game['sessionId'], 'perfect' => $count, 'good' => 0, 'miss' => 0])['result'];
    check($result['gain'] > 1, 'Perfekte Probe bringt spürbar Können');
    checkEquals(round($m['instruments'][0]['skill'] + $result['gain'], 1), $result['skillAfter']);
    checkApiError(fn () => $p->post('minigameFinish', ['sessionId' => $game['sessionId'], 'perfect' => $count, 'good' => 0, 'miss' => 0]), 'nicht mehr aktiv');

    // Abbrechen gibt den Musiker frei.
    $game = $p->post('minigameStart', ['kind' => 'practice', 'musicianId' => $m['id']])['game'];
    $p->post('minigameAbort', ['sessionId' => $game['sessionId']]);
    $p->post('promoStart', ['musicianId' => $m['id']]);
});

test('Promo-Mission steigert die Präsenz nach Ablauf', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Promo');
    $m = $p->get('state')['musicians'][0];
    $p->post('promoStart', ['musicianId' => $m['id']]);
    checkApiError(fn () => $p->post('minigameStart', ['kind' => 'practice', 'musicianId' => $m['id']]), 'beschäftigt');
    Clock::advance(Balance::PROMO_SECONDS);
    $state = $p->get('state');
    $after = array_values(array_filter($state['musicians'], static fn ($x) => $x['id'] === $m['id']))[0];
    check($after['presence'] > $m['presence']);
    checkEquals(round($after['presence'] - $m['presence'], 1), $state['results'][0]['result']['presence']);
});

test('Platte produzieren, dann Gigs spielen, dann die nächste Platte', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Studio');
    $band = foundBand($p);
    $money = $p->get('state')['label']['money'];

    checkApiError(fn () => $p->post('recordStart', ['bandId' => $band['id'], 'title' => 'Debüt', 'songs' => 2]), 'Lieder');
    $state = $p->post('recordStart', ['bandId' => $band['id'], 'title' => 'Debüt', 'songs' => 3])['state'];
    checkEquals($money - Balance::RECORD_COST, $state['label']['money']);
    checkEquals('Debüt', bandById($state, $band['id'])['record']['open']['title']);
    checkApiError(fn () => $p->post('recordStart', ['bandId' => $band['id'], 'title' => 'Noch eine', 'songs' => 3]), 'Produktion');

    $first = playSong($p, ['kind' => 'record', 'bandId' => $band['id']]);
    checkEquals(1, $first['result']['songsDone']);
    checkEquals(null, $first['result']['completed']);
    playSong($p, ['kind' => 'record', 'bandId' => $band['id']]);
    $last = playSong($p, ['kind' => 'record', 'bandId' => $band['id']]);
    $completed = $last['result']['completed'];
    check($completed !== null, 'Platte nach drei Liedern fertig');
    check($completed['perfect'], 'Keine Fehler = perfekte Platte');
    check($completed['money'] > 0 && $completed['fame'] > 0);

    $band = bandById($last['state'], $band['id']);
    checkEquals(1, $band['records']);
    checkEquals(3, $band['songsRecorded']);
    checkEquals(1, $band['perfectRecords']);
    check($band['fame'] > 0, 'Die Platte bringt Ruhm');
    check($band['options']['gig']['available']);
    checkEquals(3, $band['options']['gig']['maxSongs']);
    check($band['record']['blocker'] !== null, 'Nächste Platte erst nach Gigs');

    checkApiError(fn () => $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'gig', 'songs' => 4]), '1 bis 3');
    for ($i = 0; $i < Balance::GIGS_BETWEEN_RECORDS; $i++) {
        $gig = $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'gig', 'songs' => 2])['activity'];
        check(str_starts_with($gig['title'], 'Gig: '));
        Clock::advance(2 * Balance::SONG_SECONDS);
    }
    $state = $p->get('state');
    $band = bandById($state, $band['id']);
    checkEquals(Balance::GIGS_BETWEEN_RECORDS, $band['gigsPlayed']);
    checkEquals(null, $band['record']['blocker'], 'Nach genug Gigs darf die nächste Platte kommen');
    check(array_sum(array_map(static fn ($r) => $r['result']['money'], $state['results'])) > 0, 'Gigs bringen Gage');
});

test('Umbesetzung nach der Freigabe kostet Eingespieltheit, Bekanntheit geht mit', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Lineup');
    $band = foundBand($p);
    $db->exec('UPDATE bands SET cohesion = 60, released = 1');
    $db->exec('UPDATE musicians SET fame = 10');
    $leaving = $band['memberIds'][0];
    $state = $p->post('bandRemoveMember', ['bandId' => $band['id'], 'musicianId' => $leaving])['state'];
    $after = bandById($state, $band['id']);
    checkEquals(60 - Balance::COHESION_REMOVE_PENALTY, $after['cohesion']);
    checkEquals(20.0, $after['fame'], 'Bandruhm sinkt genau um die Bekanntheit des Ausgetretenen');
    checkApiError(fn () => $p->post('bandRemoveMember', ['bandId' => $band['id'], 'musicianId' => $after['memberIds'][0]]), 'mindestens 2');

    $state = $p->post('bandAddMember', ['bandId' => $band['id'], 'musicianId' => $leaving, 'slot' => 1])['state'];
    checkEquals(30.0, bandById($state, $band['id'])['fame']);
    checkEquals(60 - Balance::COHESION_REMOVE_PENALTY - Balance::COHESION_ADD_PENALTY, bandById($state, $band['id'])['cohesion']);
});

test('Instrumente zuweisen: nur passende Art, eins pro Art', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Gear');
    $state = $p->get('state');
    $singer = array_values(array_filter($state['musicians'], static fn ($m) => $m['instruments'][0]['type'] === 'vocals'))[0];
    $repo = new Signed\Game\Repo($db, $state['label']['id']);
    $micA = $repo->insertGear('mic_basic');
    $micB = $repo->insertGear('mic_gold');
    $guitar = $repo->insertGear('gtr_school');

    checkApiError(fn () => $p->post('gearAssign', ['gearId' => $guitar, 'musicianId' => $singer['id']]), 'spielt kein');
    $p->post('gearAssign', ['gearId' => $micA, 'musicianId' => $singer['id']]);
    $state = $p->post('gearAssign', ['gearId' => $micB, 'musicianId' => $singer['id']])['state'];
    $assigned = array_values(array_filter($state['gear'], static fn ($g) => $g['musicianId'] === $singer['id']));
    checkEquals(1, count($assigned));
    checkEquals($micB, $assigned[0]['id']);
});

test('Manager: höchstens drei Bands, Fan-Karte bringt Ruhm', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Boss');
    $band = foundBand($p);
    $labelId = $p->get('state')['label']['id'];
    $repo = new Signed\Game\Repo($db, $labelId);
    $managerId = $repo->insertManager(['first_name' => 'Mo', 'last_name' => 'Ney', 'rarity' => 'rare', 'level' => 2, 'skills' => ['verhandler'], 'look' => []]);
    $state = $p->post('bandSetManager', ['bandId' => $band['id'], 'managerId' => $managerId])['state'];
    checkEquals(2.0, bandById($state, $band['id'])['managerLevel']);
    check(bandById($state, $band['id'])['options']['gig']['songCap'] > Balance::GIG_SONG_CAP);

    $db->insert('items', ['label_id' => $labelId, 'kind' => 'fan', 'value' => 9.0, 'created_at' => Clock::now()]);
    $itemId = (int) $db->value('SELECT id FROM items');
    $state = $p->post('itemUse', ['itemId' => $itemId, 'bandId' => $band['id']])['state'];
    checkEquals(9.0, bandById($state, $band['id'])['fame']);
    checkEquals(0, count($state['items']));
});

test('Album-Charts: Platten aller Labels, Platz 1 gibt einen Gutschein, Tantiemen fließen täglich', function () {
    $db = freshDb();
    $a = registerPlayer($db, 'Alpha');
    $b = registerPlayer($db, 'Beta');
    $bandA = foundBand($a, 'Alpha Band');
    $bandB = foundBand($b, 'Beta Band');
    $a->post('recordStart', ['bandId' => $bandA['id'], 'title' => 'Alpha EP', 'songs' => 3]);
    for ($i = 0; $i < 3; $i++) {
        $done = playSong($a, ['kind' => 'record', 'bandId' => $bandA['id']]);
    }
    checkEquals(Balance::SCOUT_START_VOUCHERS + 2, $done['state']['scout']['vouchers'], 'Gutschein für die erste und für die perfekte Platte');
    $b->post('recordStart', ['bandId' => $bandB['id'], 'title' => 'Beta EP', 'songs' => 3]);
    for ($i = 0; $i < 3; $i++) {
        playSong($b, ['kind' => 'record', 'bandId' => $bandB['id']], 0.5);
    }

    $charts = $b->get('charts');
    checkEquals(2, count($charts['albums']));
    checkEquals('Alpha EP', $charts['albums'][0]['title'], 'Die bessere Platte steht vorn');
    checkEquals('EP', $charts['albums'][0]['format']);
    check(!$charts['albums'][0]['own'] && $charts['albums'][1]['own']);
    checkEquals(2, count($charts['labels']));
    checkEquals(Balance::SCOUT_START_VOUCHERS + 3, $a->get('state')['scout']['vouchers'], 'Platz 1 bringt einen weiteren Gutschein');
    $b->get('charts');
    checkEquals(Balance::SCOUT_START_VOUCHERS + 3, $a->get('state')['scout']['vouchers'], 'Nur einmal pro Platte');

    $money = $a->get('state')['label']['money'];
    Clock::advance(2 * 86400 + 60);
    $state = $a->get('state');
    check($state['label']['money'] > $money, 'Tantiemen nach zwei Tagen');
    check(str_starts_with($state['history'][0]['text'], 'Tantiemen'));
    $points = bandById($state, $bandA['id'])['discography'][0]['points'];
    $before = $charts['albums'][0]['points'];
    check(abs($points - $before * Balance::CHART_DECAY ** 2) < 0.5, 'Punkte schrumpfen täglich');
});

test('Auflösen gibt die Musiker frei und behält ihre Bekanntheit', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Split');
    $band = foundBand($p);
    $db->exec('UPDATE musicians SET fame = 4');
    $state = $p->post('bandDisband', ['bandId' => $band['id']])['state'];
    checkEquals(0, count($state['bands']));
    foreach ($state['musicians'] as $m) {
        checkEquals(null, $m['bandId']);
        checkEquals(4.0, $m['fame']);
    }
});
