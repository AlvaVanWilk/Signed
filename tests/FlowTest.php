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

test('Nachnamen wiederholen sich im eigenen Label nicht', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Namen');
    $db->exec('UPDATE labels SET money = 100000');
    $names = [];
    for ($i = 0; $i < 12; $i++) {
        foreach ($p->post('buyPack', ['pack' => 'studio'])['cards'] as $card) {
            if ($card['kind'] !== 'gear') {
                $names[] = $card[$card['kind']]['lastName'];
            }
        }
    }
    check(count($names) > 40, 'genug Karten gezogen');
    checkEquals(count($names), count(array_unique($names)));
});

test('Jam-Café: drei passende Musiker zur Auswahl, einer gründet mit dem Gastgeber eine Band', function () {
    $db = freshDb();
    $p = player($db);
    $p->post('register', ['username' => 'jammer', 'password' => 'geheim123', 'labelName' => 'Jam Records']);
    $offer = $p->post('scoutOpen', ['voucher' => true])['offer'];
    $m = $p->post('scoutPick', ['offerId' => $offer['id'], 'index' => 0])['musician'];
    $state = $p->get('state');
    check($state['musicians'][0]['favorite'], 'Der erste Musiker ist automatisch Favorit');

    $jam = function () use ($p, $m): array {
        $p->post('soloStart', ['musicianId' => $m['id'], 'type' => 'jam']);
        Clock::advance(Balance::JAM['songs'] * Balance::SONG_SECONDS);
        $state = $p->get('state');
        checkEquals(1, count($state['musicians']), 'Niemand unterschreibt von allein');
        checkEquals(1, count($state['jamOffers']));
        return $state['jamOffers'][0];
    };
    $offer = $jam();
    checkEquals(Balance::JAM['cards'], count($offer['cards']));
    $types = [];
    foreach ($offer['cards'] as $card) {
        checkEquals(0, $card['level'], 'Aus dem Jam-Café nur Stufe 0');
        check((bool) array_intersect($card['genres'], $m['genres']), 'Gemeinsames Genre');
        check(!in_array($card['instruments'][0]['type'], array_column($m['instruments'], 'type'), true), 'Anderes Instrument');
        foreach ($card['personality'] as $a) {
            foreach ($m['personality'] as $b) {
                check(!Signed\Game\BandCalc::clashes($a, $b), 'Keine Reibung');
            }
        }
        $types[] = $card['instruments'][0]['type'];
    }
    checkEquals(count($types), count(array_unique($types)));

    // Alle ablehnen und noch einmal – beliebig oft.
    $p->post('jamDecline', ['offerId' => $offer['id']]);
    checkEquals([], $p->get('state')['jamOffers']);
    $offer = $jam();

    $card = $offer['cards'][1];
    $genre = array_values(array_intersect($card['genres'], $m['genres']))[0];
    $res = $p->post('jamPick', ['offerId' => $offer['id'], 'index' => 1, 'name' => 'Die Jamsession', 'genre' => $genre]);
    $band = bandById($res['state'], $res['bandId']);
    checkEquals($genre, $band['genre']);
    checkEquals(2, count($res['state']['musicians']));
    checkEquals([], $res['state']['jamOffers']);
    checkApiError(fn () => $p->post('soloStart', ['musicianId' => $m['id'], 'type' => 'jam']), 'Band');
    checkApiError(fn () => $p->post('jamPick', ['offerId' => $offer['id'], 'index' => 0, 'name' => 'Nochmal', 'genre' => $genre]), 'nicht mehr');

    $p->post('musicianFavorite', ['musicianId' => $m['id'], 'favorite' => false]);
    check(!$p->get('state')['musicians'][0]['favorite']);
});

test('Bandprobe und Band-Promo', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Probe');
    $band = foundBand($p);
    $before = array_column($p->get('state')['musicians'], 'instruments', 'id');
    $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'rehearsal']);
    Clock::advance(Balance::REHEARSAL['songs'] * Balance::SONG_SECONDS);
    $state = $p->get('state');
    check(bandById($state, $band['id'])['cohesion'] > 0);
    foreach ($state['musicians'] as $m) {
        check($m['instruments'][0]['skill'] > $before[$m['id']][0]['skill'], 'Probe bringt Können');
    }
    $p->post('bandPromo', ['bandId' => $band['id']]);
    checkApiError(fn () => $p->post('promoStart', ['musicianId' => $band['memberIds'][0]]), 'beschäftigt');
    Clock::advance(Balance::BAND_PROMO['seconds']);
    check(bandById($p->get('state'), $band['id'])['fame'] > 0, 'Promo macht die Band bekannter');
});

test('Aufstieg: Bei Können 35 gibt es eine Fähigkeit, bei Stufe 3 wählt man aus drei', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Level');
    $m = $p->get('state')['musicians'][0];
    $db->exec('UPDATE musicians SET skill1 = 59.9, level = 1, abilities = ? WHERE id = ?', ['["talent"]', $m['id']]);
    $game = $p->post('minigameStart', ['kind' => 'practice', 'musicianId' => $m['id']])['game'];
    Clock::advance(Balance::SONG_SECONDS + 1);
    $result = $p->post('minigameFinish', ['sessionId' => $game['sessionId'], 'perfect' => $game['count'], 'good' => 0, 'miss' => 0])['result'];
    checkEquals(2, $result['levelUps'][0]['level']);
    $after = array_values(array_filter($p->get('state')['musicians'], static fn ($x) => $x['id'] === $m['id']))[0];
    checkEquals(2, count($after['abilities']));

    $db->exec('UPDATE musicians SET skill1 = 84.9 WHERE id = ?', [$m['id']]);
    $game = $p->post('minigameStart', ['kind' => 'practice', 'musicianId' => $m['id']])['game'];
    Clock::advance(Balance::SONG_SECONDS + 1);
    $result = $p->post('minigameFinish', ['sessionId' => $game['sessionId'], 'perfect' => $game['count'], 'good' => 0, 'miss' => 0])['result'];
    checkEquals(3, count($result['levelUps'][0]['choice']));
    checkApiError(fn () => $p->post('abilityChoose', ['musicianId' => $m['id'], 'ability' => 'gibtsnicht']), 'nicht zur Wahl');
    $state = $p->post('abilityChoose', ['musicianId' => $m['id'], 'ability' => $result['levelUps'][0]['choice'][1]])['state'];
    $after = array_values(array_filter($state['musicians'], static fn ($x) => $x['id'] === $m['id']))[0];
    checkEquals(3, count($after['abilities']));
    checkEquals(null, $after['abilityChoice']);
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

test('Straßenmusik bringt dem Label kein Geld, aber Präsenz; sperrt die Band bis zum Ende', function () {
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
    checkEquals(0, $result['money']);
    checkEquals($money, $state['label']['money']);
    check($result['presence'] > 0, 'Etwas Präsenz für die Musiker');
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

test('Wechselbörse: anbieten, bieten, überbieten, Zuschlag nach Ablauf', function () {
    $db = freshDb();
    $seller = registerPlayer($db, 'Verkauf');
    $a = registerPlayer($db, 'Bieter');
    $b = registerPlayer($db, 'Konkurrenz');
    $m = $seller->get('state')['musicians'][0];

    $seller->post('transferList', ['musicianId' => $m['id']]);
    $state = $seller->get('state');
    checkEquals(1, count($state['market']['mine']));
    $offer = $state['market']['mine'][0];
    check($offer['minPrice'] > 0);
    checkApiError(fn () => $seller->post('transferList', ['musicianId' => $m['id']]), 'beschäftigt');
    checkApiError(fn () => $seller->post('transferBid', ['transferId' => $offer['id'], 'amount' => $offer['minPrice']]), 'eigenen');
    // Wer an der Börse steht, kann keiner Band beitreten.
    $members = array_map(static fn ($x) => ['id' => $x['id'], 'slot' => 1], $state['musicians']);
    checkApiError(fn () => $seller->post('bandCreate', ['name' => 'Gesperrt', 'genre' => 'rock', 'members' => $members]), 'Wechselbörse');

    // Unter dem Mindestpreis geht nicht; das Geld wird zurückgelegt.
    checkApiError(fn () => $a->post('transferBid', ['transferId' => $offer['id'], 'amount' => $offer['minPrice'] - 10]), 'mindestens');
    $before = $a->get('state')['label']['money'];
    $a->post('transferBid', ['transferId' => $offer['id'], 'amount' => $offer['minPrice']]);
    checkEquals($before - $offer['minPrice'], $a->get('state')['label']['money']);
    checkApiError(fn () => $seller->post('transferWithdraw', ['transferId' => $offer['id']]), 'Gebot');

    // Überbieten: Das alte Gebot geht zurück.
    $market = $b->get('state')['market']['offers'][0];
    check($market['minBid'] > $offer['minPrice']);
    $b->post('transferBid', ['transferId' => $offer['id'], 'amount' => $market['minBid']]);
    checkEquals($before, $a->get('state')['label']['money']);
    check($b->get('state')['market']['offers'][0]['leading']);

    // Ablauf: Der Musiker wechselt, der Verkäufer bekommt das Geld.
    $sellerMoney = $seller->get('state')['label']['money'];
    Clock::advance(Balance::TRANSFER['seconds'] + 1);
    $state = $b->get('state');
    checkEquals(4, count($state['musicians']));
    check(in_array($m['id'], array_column($state['musicians'], 'id'), true));
    checkEquals($sellerMoney + $market['minBid'], $seller->get('state')['label']['money']);
    checkEquals(2, count($seller->get('state')['musicians']));
    checkEquals([], $b->get('state')['market']['offers']);
});

test('Wechselbörse: zurückziehen ohne Gebot, Zuschlag vorzeitig, späte Gebote verlängern', function () {
    $db = freshDb();
    $seller = registerPlayer($db, 'Anbieter');
    $buyer = registerPlayer($db, 'Kaeufer');
    [$m1, $m2] = $seller->get('state')['musicians'];

    $seller->post('transferList', ['musicianId' => $m1['id']]);
    $offer = $seller->get('state')['market']['mine'][0];
    $seller->post('transferWithdraw', ['transferId' => $offer['id']]);
    checkEquals([], $seller->get('state')['market']['mine']);
    checkEquals(0, $seller->get('state')['musicians'][0]['busyUntil'] > Clock::now() ? 1 : 0);

    $seller->post('transferList', ['musicianId' => $m2['id']]);
    $offer = $seller->get('state')['market']['mine'][0];
    Clock::advance(Balance::TRANSFER['seconds'] - 60);
    $buyer->post('transferBid', ['transferId' => $offer['id'], 'amount' => $offer['minPrice']]);
    $after = $seller->get('state')['market']['mine'][0];
    check($after['endsAt'] >= Clock::now() + Balance::TRANSFER['snipe'] - 1, 'Spätes Gebot verlängert die Frist');
    $seller->post('transferAccept', ['transferId' => $offer['id']]);
    checkEquals(4, count($buyer->get('state')['musicians']));
});

test('Wechselbörse: Marktwert steigt mit Können, Stufen und Ruhm', function () {
    $base = musician(['skill1' => 20.0, 'presence' => 20.0, 'fame' => 0.0, 'level' => 0, 'abilities' => []]);
    $better = musician(['skill1' => 50.0, 'presence' => 20.0, 'fame' => 150.0, 'level' => 1, 'abilities' => ['talent']]);
    check(Signed\Game\Transfers::value($better) > 3 * Signed\Game\Transfers::value($base));
});
