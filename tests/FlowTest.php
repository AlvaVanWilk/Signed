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
    $res = $p->post('bandCreate', ['name' => $name, 'members' => $members]);
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
    $res = $p->post('buyPack', ['pack' => 'aushang']);
    checkEquals(4, count($res['offer']['cards']));
    checkEquals(Balance::START_MONEY - Balance::PACKS['aushang']['price'], $res['state']['label']['money']);
    checkEquals(3, count($res['state']['musicians']), 'Erst wählen, dann gehört die Karte dir');
    checkApiError(fn () => $p->post('buyPack', ['pack' => 'troedel']), 'erst eine Karte');
    $res = $p->post('packPick', ['offerId' => $res['offer']['id'], 'index' => 2]);
    checkEquals('musician', $res['card']['kind']);
    checkEquals(4, count($res['state']['musicians']));
    checkEquals(null, $res['state']['packOffer']);
    checkApiError(fn () => $p->post('packPick', ['offerId' => 1, 'index' => 0]), 'nicht mehr');

    checkApiError(fn () => $p->post('buyPack', ['pack' => 'werkstatt']), 'Instrument');
    checkApiError(fn () => $p->post('buyPack', ['pack' => 'werkstatt', 'type' => 'drums']), 'Geld');
    checkApiError(fn () => $p->post('buyPack', ['pack' => 'gibtsnicht']), 'Pack');
    $ledger = $p->get('state')['finance']['ledger'];
    checkEquals(-Balance::PACKS['aushang']['price'], $ledger[0]['amount']);
});

test('Nachnamen wiederholen sich im eigenen Label nicht', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Namen');
    $db->exec('UPDATE labels SET money = 100000');
    $names = [];
    // Mitgenommen werden Musiker und Manager; innerhalb einer Auswahl und gegenüber dem Label keine Doppelten.
    for ($i = 0; $i < 30; $i++) {
        $offer = $p->post('buyPack', ['pack' => 'backstage'])['offer'];
        $inOffer = [];
        foreach ($offer['cards'] as $card) {
            if ($card['kind'] !== 'gear') {
                $inOffer[] = $card[$card['kind']]['lastName'];
            }
        }
        checkEquals(count($inOffer), count(array_unique($inOffer)));
        check(!array_intersect($inOffer, $names), 'Kein Nachname, den das Label schon hat');
        $picked = $p->post('packPick', ['offerId' => $offer['id'], 'index' => $i % 3])['card'];
        $names[] = $picked[$picked['kind']]['lastName'];
    }
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
    check(count($offer['cards']) >= 2 && count($offer['cards']) <= Balance::JAM['cards']);
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

    $res = $p->post('jamPick', ['offerId' => $offer['id'], 'index' => 1, 'name' => 'Die Jamsession']);
    $band = bandById($res['state'], $res['bandId']);
    checkEquals(2, count($band['memberIds']));
    checkEquals(2, count($res['state']['musicians']));
    checkEquals([], $res['state']['jamOffers']);
    checkApiError(fn () => $p->post('soloStart', ['musicianId' => $m['id'], 'type' => 'jam']), 'Band');
    checkApiError(fn () => $p->post('jamPick', ['offerId' => $offer['id'], 'index' => 0, 'name' => 'Nochmal']), 'nicht mehr');

    // Ohne freien Band-Platz geht niemand mehr ins Jam-Café.
    $shop = $p->post('buyPack', ['pack' => 'aushang'])['offer'];
    $free = $p->post('packPick', ['offerId' => $shop['id'], 'index' => 0])['card']['musician'];
    checkApiError(fn () => $p->post('soloStart', ['musicianId' => $free['id'], 'type' => 'jam']), 'Label-Ruhm');

    $p->post('musicianFavorite', ['musicianId' => $m['id'], 'favorite' => false]);
    check(!$p->get('state')['musicians'][0]['favorite']);
});

test('Bandprobe und Band-Promo', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Probe');
    $band = foundBand($p);
    $before = array_column($db->all('SELECT id, skill1 FROM musicians'), 'skill1', 'id');
    $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'rehearsal']);
    Clock::advance(Balance::REHEARSAL['songs'] * Balance::SONG_SECONDS);
    $state = $p->get('state');
    check(bandById($state, $band['id'])['cohesion'] > 0);
    foreach ($db->all('SELECT id, skill1 FROM musicians') as $m) {
        check((float) $m['skill1'] > (float) $before[$m['id']], 'Probe bringt Können');
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
    $db->exec('UPDATE musicians SET skill1 = 59.995, level = 1, abilities = ? WHERE id = ?', ['["talent"]', $m['id']]);
    $game = $p->post('minigameStart', ['kind' => 'practice', 'musicianId' => $m['id']])['game'];
    Clock::advance(Balance::SONG_SECONDS + 1);
    $result = $p->post('minigameFinish', ['sessionId' => $game['sessionId'], 'perfect' => $game['count'], 'good' => 0, 'miss' => 0])['result'];
    checkEquals(2, $result['levelUps'][0]['level']);
    $after = array_values(array_filter($p->get('state')['musicians'], static fn ($x) => $x['id'] === $m['id']))[0];
    checkEquals(2, count($after['abilities']));

    $db->exec('UPDATE musicians SET skill1 = 84.9999 WHERE id = ?', [$m['id']]);
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

test('Packs: Backstage ohne gewöhnliche Karten, Werkstatt nach Wunsch, Tipp im Music Store passt', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Rich');
    $db->exec('UPDATE labels SET money = 1000000');
    $rarity = static fn ($c) => ($c['musician'] ?? $c['manager'] ?? null)['rarity'] ?? Signed\Game\Catalog::GEAR[$c['gear']['model']]['rarity'];
    $tips = 0;
    $own = array_unique(array_merge(...array_map(static fn ($m) => array_column($m['instruments'], 'type'), $p->get('state')['musicians'])));
    for ($i = 0; $i < 15; $i++) {
        $offer = $p->post('buyPack', ['pack' => 'backstage'])['offer'];
        check(!in_array('common', array_map($rarity, $offer['cards']), true));
        checkEquals(['musician', 'musician', 'manager', 'gear'], array_column($offer['cards'], 'kind'));
        $p->post('packPick', ['offerId' => $offer['id'], 'index' => 3]);

        $offer = $p->post('buyPack', ['pack' => 'werkstatt', 'type' => 'drums'])['offer'];
        foreach ($offer['cards'] as $card) {
            checkEquals('drums', Signed\Game\Catalog::GEAR[$card['gear']['model']]['type']);
            check(in_array($rarity($card), ['epic', 'legendary'], true));
        }
        $p->post('packPick', ['offerId' => $offer['id'], 'index' => 0]);

        $offer = $p->post('buyPack', ['pack' => 'musicstore'])['offer'];
        foreach ($offer['cards'] as $card) {
            if ($card['tip'] !== null) {
                $tips++;
                check(in_array(Signed\Game\Catalog::GEAR[$card['gear']['model']]['type'], $own, true));
            }
        }
        // Nicht mitnehmen, damit der Musiker ohne Instrument bleibt.
        $p->post('packPick', ['offerId' => $offer['id'], 'index' => array_search(null, array_column($offer['cards'], 'tip'), true)]);
    }
    check($tips >= 1 && $tips <= 10, "Tipps $tips von 15");
});

test('Bandregeln: weitere Bands erst mit Label-Ruhm, niemand in zwei Bands, Genre aus der Besetzung', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Lou');
    $band = foundBand($p);
    checkEquals('rock', $band['genres'][0] ?? null, 'Gesang, Gitarre, Schlagzeug, alle Rock: eine Rockband');
    checkEquals('Rockband', $band['title']);
    $state = $p->get('state');
    $m = $state['musicians'][0];
    checkEquals(1, $state['label']['bandSlots']['open']);
    checkApiError(fn () => $p->post('bandCreate', ['name' => 'Zweite', 'members' => []]), '50 Label-Ruhm');
    $db->exec('UPDATE bands SET fame_own = 80');
    checkEquals(2, $p->get('state')['label']['bandSlots']['open']);
    checkApiError(fn () => $p->post('bandCreate', ['name' => 'Zweite', 'members' => [['id' => $m['id']]]]), 'schon in einer Band');
    checkApiError(fn () => $p->post('bandCreate', ['name' => '   ', 'members' => []]), 'Bandname');

    // Ohne Schlagzeug ist es keine Rockband mehr.
    $drummer = array_values(array_filter($state['musicians'], static fn ($x) => $x['instruments'][0]['type'] === 'drums'))[0];
    $state = $p->post('bandRemoveMember', ['bandId' => $band['id'], 'musicianId' => $drummer['id']])['state'];
    checkEquals([], bandById($state, $band['id'])['genres']);
    check(str_contains($state['history'][0]['text'], 'keine gemeinsame Genre-Linie'));
});

test('Notgroschen: Reicht das Geld nicht für eine Platte, bringt Straßenmusik Geld in den Hut', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Pleite');
    $band = foundBand($p);
    $db->exec('UPDATE labels SET money = 10');
    $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'busking']);
    Clock::advance(Balance::BUSKING['songs'] * Balance::SONG_SECONDS);
    $state = $p->get('state');
    $hat = $state['results'][0]['result']['money'];
    check($hat >= Balance::BUSKING_HAT[0] && $hat <= Balance::BUSKING_HAT[1], "Hut: $hat");
    checkEquals(10 + $hat, $state['label']['money']);

    // Solo ohne Band genauso.
    $solo = $band['memberIds'][0];
    $p->post('bandRemoveMember', ['bandId' => $band['id'], 'musicianId' => $solo]);
    $p->post('soloStart', ['musicianId' => $solo, 'type' => 'busking']);
    Clock::advance(Balance::BUSKING['songs'] * Balance::SONG_SECONDS);
    check($p->get('state')['label']['money'] > 10 + $hat, 'Solo-Straßenmusik füllt auch den Hut');
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
    check($result['fame'] > 0, 'Etwas Bekanntheit für die Musiker');
    check(bandById($state, $band['id'])['cohesion'] > 0, 'Auftritte steigern das Zusammenspiel');
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
    check($result['gain'] > 0.2, 'Perfekte Probe bringt spürbar Können');
    check(abs($result['skillBefore'] + $result['gain'] - $result['skillAfter']) <= 0.11, 'Vorher + Zuwachs = nachher');
    check($result['skillAfter'] <= (float) $db->value('SELECT skill1 FROM musicians WHERE id = ?', [$m['id']]), 'Nie aufgerundet');
    checkApiError(fn () => $p->post('minigameFinish', ['sessionId' => $game['sessionId'], 'perfect' => $count, 'good' => 0, 'miss' => 0]), 'nicht mehr aktiv');

    // Abbrechen gibt den Musiker frei.
    $game = $p->post('minigameStart', ['kind' => 'practice', 'musicianId' => $m['id']])['game'];
    $p->post('minigameAbort', ['sessionId' => $game['sessionId']]);
    $p->post('promoStart', ['musicianId' => $m['id']]);
});

test('Promo-Mission steigert die Bekanntheit nach Ablauf, bei Bekannten weniger', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Promo');
    $m = $p->get('state')['musicians'][0];
    $p->post('promoStart', ['musicianId' => $m['id']]);
    checkApiError(fn () => $p->post('minigameStart', ['kind' => 'practice', 'musicianId' => $m['id']]), 'beschäftigt');
    Clock::advance(Balance::PROMO_SECONDS);
    $state = $p->get('state');
    $after = array_values(array_filter($state['musicians'], static fn ($x) => $x['id'] === $m['id']))[0];
    check($after['fame'] > $m['fame']);
    check(abs(($after['fame'] - $m['fame']) - $state['results'][0]['result']['fame']) <= 0.1, 'Anzeige passt zum Zuwachs');

    $db->exec('UPDATE musicians SET fame = 400 WHERE id = ?', [$m['id']]);
    $p->post('promoStart', ['musicianId' => $m['id']]);
    Clock::advance(Balance::PROMO_SECONDS);
    check($p->get('state')['results'][1]['result']['fame'] < Balance::PROMO_FAME[1] / 5, 'Wer schon bekannt ist, gewinnt wenig');
});

test('Platte produzieren, dann Gigs spielen, dann die nächste Platte', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Studio');
    $band = foundBand($p);
    $money = $p->get('state')['label']['money'];

    checkApiError(fn () => $p->post('recordStart', ['bandId' => $band['id'], 'title' => 'Debüt', 'songs' => 2]), 'Lieder');
    $state = $p->post('recordStart', ['bandId' => $band['id'], 'title' => 'Debüt', 'songs' => 3])['state'];
    checkEquals($money - Signed\Game\Studio::cost(3), $state['label']['money']);
    checkEquals('Debüt', bandById($state, $band['id'])['record']['open']['title']);
    checkApiError(fn () => $p->post('recordStart', ['bandId' => $band['id'], 'title' => 'Noch eine', 'songs' => 3]), 'Produktion');

    $first = playSong($p, ['kind' => 'record', 'bandId' => $band['id']]);
    checkEquals(1, $first['result']['songsDone']);
    checkEquals(null, $first['result']['completed']);
    $second = playSong($p, ['kind' => 'record', 'bandId' => $band['id']]);
    checkEquals('master', bandById($second['state'], $band['id'])['record']['open']['next']['type'], 'Zum Schluss der Master-Mix');
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

    checkApiError(fn () => $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'gig', 'songs' => 4]), '3 Lieder');
    checkApiError(fn () => $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'gig', 'songs' => 2]), '3 Lieder');
    // EP mit 3 Liedern: 6 Lieder live, also 2 Gigs à 3 Lieder.
    $gigs = 3 * Balance::LIVE_SONGS_PER_RECORD_SONG / 3;
    for ($i = 0; $i < $gigs; $i++) {
        check(str_contains((string) bandById($p->get('state'), $band['id'])['record']['blocker'], 'live'));
        $gig = $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'gig', 'songs' => 3])['activity'];
        check(str_starts_with($gig['title'], 'Gig: '));
        Clock::advance(3 * Balance::SONG_SECONDS);
    }
    $state = $p->get('state');
    $band = bandById($state, $band['id']);
    checkEquals($gigs, $band['gigsPlayed']);
    checkEquals(null, $band['record']['blocker'], 'Nach genug Gigs darf die nächste Platte kommen');
    check(array_sum(array_map(static fn ($r) => $r['result']['money'], $state['results'])) > 0, 'Gigs bringen Gage');
});

test('Umbesetzung nach der Freigabe kostet Zusammenspiel, Bekanntheit geht mit', function () {
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

test('Album-Charts: Platten aller Labels, Platz 1 gibt einen Gutschein, Tantiemen laufen, Promo schiebt an', function () {
    $db = freshDb();
    $a = registerPlayer($db, 'Alpha');
    $b = registerPlayer($db, 'Beta');
    $bandA = foundBand($a, 'Alpha Band');
    $bandB = foundBand($b, 'Beta Band');
    $a->post('recordStart', ['bandId' => $bandA['id'], 'title' => 'Alpha EP', 'songs' => 3]);
    for ($i = 0; $i < 3; $i++) {
        $done = playSong($a, ['kind' => 'record', 'bandId' => $bandA['id']]);
    }
    checkEquals(Balance::SCOUT_START_VOUCHERS + 1, $done['state']['scout']['vouchers'], 'Gutschein nur für die erste Platte, nicht fürs Perfekte');
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
    checkEquals(Balance::SCOUT_START_VOUCHERS + 1, $a->get('state')['scout']['vouchers'], 'Ohne genug Konkurrenz kein Gutschein für Platz 1');

    $c = registerPlayer($db, 'Gamma');
    $bandC = foundBand($c, 'Gamma Band');
    $c->post('recordStart', ['bandId' => $bandC['id'], 'title' => 'Gamma EP', 'songs' => 3]);
    for ($i = 0; $i < 3; $i++) {
        playSong($c, ['kind' => 'record', 'bandId' => $bandC['id']], 0.5);
    }
    $c->get('charts');
    checkEquals(Balance::SCOUT_START_VOUCHERS + 2, $a->get('state')['scout']['vouchers'], 'Platz 1 bringt einen weiteren Gutschein');
    $b->get('charts');
    checkEquals(Balance::SCOUT_START_VOUCHERS + 2, $a->get('state')['scout']['vouchers'], 'Nur einmal pro Platte');

    $first = array_column($b->get('charts')['albums'], null, 'title');
    checkEquals('new', $first['Beta EP']['trend']);

    // Tantiemen laufen ständig: in kleinen Schritten so viel, wie die Punkte über die Zeit ergeben.
    $money = $a->get('state')['label']['money'];
    $row = $db->one("SELECT chart_points, finished_at, boost, boost_at FROM records WHERE title = 'Alpha EP'");
    $from = Clock::now();
    for ($i = 0; $i < 8; $i++) {
        Clock::advance(900);
        $a->get('state');
    }
    $expected = 0.0;
    for ($t = $from; $t < Clock::now(); $t += 10) {
        $expected += Signed\Game\Charts::pointsAt($row, $t) * 10 / 3600 * Balance::ROYALTY_MONEY;
    }
    // Platz 1: doppelte Tantiemen.
    checkEquals(1, (int) $db->value("SELECT rank_last FROM records WHERE title = 'Alpha EP'"));
    $expected *= Signed\Game\Charts::rankFactor(1, Balance::CHART_RANK_BONUS);
    $state = $a->get('state');
    check(abs($state['label']['money'] - $money - $expected) <= 1.5, sprintf('Tantiemen nach zwei Stunden: %d statt %.1f', $state['label']['money'] - $money, $expected));
    check(str_starts_with($state['finance']['ledger'][0]['text'], 'Tantiemen'));
    check(count(array_filter($state['finance']['ledger'], static fn ($l) => str_starts_with($l['text'], 'Tantiemen'))) <= 3, 'Eine Zeile pro Tag');
    check($state['finance']['moneyPerHour'] > 0);

    // Band-Promo schiebt die neueste Platte an; in der neuen Chartstunde gibt es einen Platz „vorher“.
    $before = (float) $db->value("SELECT boost FROM records WHERE title = 'Beta EP'");
    $b->post('bandPromo', ['bandId' => $bandB['id']]);
    Clock::advance(Balance::BAND_PROMO['seconds']);
    $b->get('state');
    check((float) $db->value("SELECT boost FROM records WHERE title = 'Beta EP'") > $before, 'Promo gibt Schub');
    Clock::advance(3600);
    $beta = array_column($b->get('charts')['albums'], null, 'title')['Beta EP'];
    check($beta['prev'] !== null && in_array($beta['trend'], ['up', 'down', 'same'], true));

    Clock::advance(3 * 86400);
    checkEquals([], $b->get('charts')['albums'], 'Nach ein paar Tagen sind alle draußen');
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
    checkApiError(fn () => $seller->post('bandCreate', ['name' => 'Gesperrt', 'members' => $members]), 'Wechselbörse');

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
    $base = musician(['skill1' => 20.0, 'fame' => 0.0, 'level' => 0, 'abilities' => []]);
    $better = musician(['skill1' => 50.0, 'fame' => 150.0, 'level' => 1, 'abilities' => ['talent']]);
    check(Signed\Game\Transfers::value($better) > 3 * Signed\Game\Transfers::value($base));
});

test('Jam-Café: Je besser die Session, desto besser die Musiker, die sich melden', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Qualitaet');
    $repo = new Signed\Game\Repo($db, $p->get('state')['label']['id']);
    $host = $p->get('state')['musicians'][0];
    $average = function (float $meter) use ($repo, $host): float {
        $sum = 0.0;
        $count = 0;
        for ($i = 0; $i < 60; $i++) {
            foreach (Signed\Game\Jam::createOffer($repo, $host['id'], $meter)['cards'] as $card) {
                $sum += $card['instruments'][0]['skill'];
                $count++;
                checkEquals(0, $card['level']);
            }
        }
        return $sum / $count;
    };
    $poor = $average(10);
    $great = $average(95);
    check($great > $poor + 5, "begeistert $great, schlecht $poor");
    $offer = $p->get('state')['jamOffers'][0];
    checkEquals($host['id'], $offer['host']['id']);
    check(isset($offer['cards'][0]['match']['chemistry']));
});

test('Ein Teil des Ruhms bleibt am Bandnamen, auch wenn Mitglieder gehen', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Name');
    $band = foundBand($p);
    $labelId = $p->get('state')['label']['id'];
    $db->insert('items', ['label_id' => $labelId, 'kind' => 'fan', 'value' => 20.0, 'created_at' => Clock::now()]);
    $state = $p->post('itemUse', ['itemId' => (int) $db->value('SELECT id FROM items'), 'bandId' => $band['id']])['state'];
    $after = bandById($state, $band['id']);
    checkEquals(20.0, $after['fame']);
    checkEquals(20.0 * Balance::BAND_FAME_SHARE, $after['fameOwn']);
    $state = $p->post('bandRemoveMember', ['bandId' => $band['id'], 'musicianId' => $after['memberIds'][0]])['state'];
    $left = bandById($state, $band['id']);
    check($left['fame'] < 20.0 && $left['fame'] > 20.0 * Balance::BAND_FAME_SHARE, 'Wer geht, nimmt seine Bekanntheit mit, der Name behält seinen Teil');
});

test('Namensänderung: vor dem ersten Auftritt frei, danach mit Gebühr und ohne den Ruhm des alten Namens', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Umbenenner');
    $band = foundBand($p);
    $money = $p->get('state')['label']['money'];
    $p->post('bandRename', ['bandId' => $band['id'], 'name' => 'Erster Versuch']);
    checkEquals($money, $p->get('state')['label']['money'], 'Vor dem ersten Auftritt kostenlos');

    $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'busking']);
    Clock::advance(Balance::BUSKING['songs'] * Balance::SONG_SECONDS);
    $db->exec('UPDATE bands SET fame_own = 12 WHERE id = ?', [$band['id']]);
    $money = $p->get('state')['label']['money'];
    $state = $p->post('bandRename', ['bandId' => $band['id'], 'name' => 'Zweiter Versuch'])['state'];
    checkEquals($money - Balance::RENAME_COST, $state['label']['money']);
    checkEquals(0.0, (float) bandById($state, $band['id'])['fameOwn']);
    checkEquals('Zweiter Versuch', bandById($state, $band['id'])['name']);
});

test('Instrumente: Eigenschaften ab episch, Verkauf an den Second-Hand-Laden', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Verkaeufer');
    $db->exec('UPDATE labels SET money = 100000');
    $offer = $p->post('buyPack', ['pack' => 'werkstatt', 'type' => 'guitar'])['offer'];
    foreach ($offer['cards'] as $card) {
        $rarity = Signed\Game\Catalog::GEAR[$card['gear']['model']]['rarity'];
        checkEquals(Balance::GEAR_PERKS[$rarity], count($card['gear']['perks']));
    }
    $state = $p->post('packPick', ['offerId' => $offer['id'], 'index' => 0])['state'];
    $gear = end($state['gear']);
    check(count($gear['perks']) >= 1, 'Gespeichert');
    $money = $state['label']['money'];
    $res = $p->post('gearSell', ['gearId' => $gear['id']]);
    checkEquals($gear['sellPrice'], $res['price']);
    checkEquals($money + $res['price'], $res['state']['label']['money']);
    check(!in_array($gear['id'], array_column($res['state']['gear'], 'id'), true));
    checkApiError(fn () => $p->post('gearSell', ['gearId' => $gear['id']]), 'gibt es nicht');
});

test('Charts: Plätze bleiben eine Chartwoche stehen, Nachrichten bei Einstieg und Platz 1', function () {
    $db = freshDb();
    $a = registerPlayer($db, 'Erster');
    $b = registerPlayer($db, 'Zweiter');
    $bandA = foundBand($a, 'Erste Band');
    $bandB = foundBand($b, 'Zweite Band');
    foreach ([[$a, $bandA, 'Vorn'], [$b, $bandB, 'Hinten']] as [$p, $band, $title]) {
        $p->post('recordStart', ['bandId' => $band['id'], 'title' => $title, 'songs' => 3]);
        for ($i = 0; $i < 3; $i++) {
            playSong($p, ['kind' => 'record', 'bandId' => $band['id']], $title === 'Vorn' ? 1.0 : 0.5);
        }
    }
    $charts = $a->get('charts')['albums'];
    checkEquals(['Vorn', 'Hinten'], array_column($charts, 'title'));
    $news = array_column($a->get('state')['history'], 'kind');
    check(in_array('chart1', $news, true), 'Platz 1 ist eine Nachricht');
    check(in_array('chart', array_column($b->get('state')['history'], 'kind'), true), 'Einstieg ist eine Nachricht');

    // „Hinten“ hat plötzlich viel mehr Punkte – ohne Einstieg oder Schub bleiben die Plätze bis zur nächsten Chartwoche.
    $db->exec("UPDATE records SET chart_points = chart_points * 50 WHERE title = 'Hinten'");
    Clock::advance(60);
    checkEquals(['Vorn', 'Hinten'], array_column($a->get('charts')['albums'], 'title'));
    Clock::advance(Balance::CHART_INTERVAL);
    $charts = $a->get('charts')['albums'];
    checkEquals(['Hinten', 'Vorn'], array_column($charts, 'title'));
    checkEquals('up', $charts[0]['trend']);
    checkEquals(2, $charts[0]['prev']);
    check(in_array('chart1', array_column($b->get('state')['history'], 'kind'), true));

    // Dauer-Promo hält eine Platte nicht ewig: Der Schub ist gedeckelt, darunter fällt die Platte weiter.
    for ($i = 0; $i < 30; $i++) {
        $b->post('bandPromo', ['bandId' => $bandB['id']]);
        Clock::advance(Balance::BAND_PROMO['seconds']);
        $b->get('state');
    }
    check((float) $db->value("SELECT boost FROM records WHERE title = 'Hinten'") <= Balance::CHART_BOOST_MAX + 1e-9, 'Schub gedeckelt');
    $row = $db->one("SELECT chart_points, finished_at, boost, boost_at FROM records WHERE title = 'Hinten'");
    $row['boost'] = Balance::CHART_BOOST_MAX;
    $row['boost_at'] = Clock::now();
    check(Signed\Game\Charts::pointsAt($row, (int) $row['finished_at'] + 12 * Balance::CHART_PEAK) < 0.05 * (float) $row['chart_points'], 'Auch mit vollem Schub fällt sie heraus');
});

test('Rap: Hip-Hop-Gesang übt im Rhythmus-Spiel mit der Aufnahme', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Rapper');
    $m = $p->get('state')['musicians'][0];
    $db->exec("UPDATE musicians SET instr1 = 'vocals', instr2 = NULL, genres = ? WHERE id = ?", ['["hiphop"]', $m['id']]);
    $game = $p->post('minigameStart', ['kind' => 'practice', 'musicianId' => $m['id']])['game'];
    checkEquals('rhythm', $game['mode']);
    check(str_contains($game['music']['own'], 'hiphop-0/gesang-'), 'Eigene Hip-Hop-Aufnahme');
    check($game['count'] > 20, 'Silben aus der MIDI-Spur');
    $db->exec("UPDATE musicians SET genres = ? WHERE id = ?", ['["pop"]', $m['id']]);
    checkEquals('pitch', $p->post('minigameStart', ['kind' => 'practice', 'musicianId' => $m['id']])['game']['mode'], 'Pop singt nach Tonhöhe');
});

test('Promo lässt sich abbrechen: sofort wieder frei, ohne Belohnung', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Promo');
    $band = foundBand($p);
    $m = $band['memberIds'][0];
    $activity = $p->post('promoStart', ['musicianId' => $m])['activity'];
    checkApiError(fn () => $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'busking']), 'unterwegs');
    $state = $p->post('promoCancel', ['activityId' => $activity['id']])['state'];
    checkEquals(0, count($state['running']));
    checkEquals(0, count($state['results']));
    $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'busking']);

    // Band-Promo genauso; Auftritte lassen sich nicht abbrechen.
    $gig = $p->get('state')['running'][0];
    checkApiError(fn () => $p->post('promoCancel', ['activityId' => $gig['id']]), 'Promo');
    Clock::advance(Balance::BUSKING['songs'] * Balance::SONG_SECONDS);
    $activity = $p->post('bandPromo', ['bandId' => $band['id']])['activity'];
    $fame = $db->value('SELECT SUM(fame) FROM musicians');
    $p->post('promoCancel', ['activityId' => $activity['id']]);
    Clock::advance(Balance::BAND_PROMO['seconds']);
    $p->get('state');
    checkEquals($fame, $db->value('SELECT SUM(fame) FROM musicians'), 'Keine Bekanntheit nach Abbruch');
    $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'rehearsal']);
});

test('Band anderer Labels ansehen: Mitglieder, Genre, Platten und Spuren zum Anhören', function () {
    $db = freshDb();
    $a = registerPlayer($db, 'Anna');
    $band = foundBand($a, 'Die Sichtbaren');
    $b = registerPlayer($db, 'Bert');
    $view = $b->post('bandView', ['bandId' => $band['id']]);
    checkEquals('Die Sichtbaren', $view['name']);
    check(!$view['own'], 'Fremde Band');
    checkEquals(count($band['memberIds']), count($view['members']));
    check(isset($view['members'][0]['stem']['type']), 'Spur zum Anhören');
    check(!isset($view['members'][0]['value']), 'Kein Marktwert');
    check($a->post('bandView', ['bandId' => $band['id']])['own']);
    checkApiError(fn () => $b->post('bandView', ['bandId' => 99999]), 'gibt es nicht');
});
