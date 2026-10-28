<?php
declare(strict_types=1);

use Signed\Clock;
use Signed\Game\Balance;
use Signed\Game\Catalog;
use Signed\Game\Store;
use Signed\Game\Assistant;
use Signed\Game\Inbox;

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

/** Packt alle Umschläge im Posteingang aus (Kassetten und Briefe), als hätte man es selbst getan. */
function unpackAll(Signed\Db $db): void
{
    $db->exec('UPDATE submissions SET unpacked_at = created_at WHERE unpacked_at IS NULL');
    $db->exec('UPDATE mail SET unpacked_at = created_at WHERE unpacked_at IS NULL');
}

/** Gründet aus den drei Test-Musikern eine Band. */
function foundBand(Player $p, string $name = 'Die Ersten'): array
{
    $state = $p->get('state');
    $members = array_map(static fn ($m) => ['id' => $m['id'], 'slot' => 1], $state['musicians']);
    $res = $p->post('bandCreate', ['name' => $name, 'members' => $members]);
    return array_values(array_filter($res['state']['bands'], static fn ($b) => $b['id'] === $res['bandId']))[0];
}

/**
 * Eine Platte aufnehmen und fertig werden lassen: Songs in den Vorrat legen, Studio und Produzent wählen, das erste Cover nehmen,
 * die Studiozeit abwarten. Liefert den Spielstand danach und das Ergebnis der Platte.
 */
function makeRecord(Signed\Db $db, Player $p, int $bandId, string $title, int $songs = 3, int $studio = 0, string $producer = 'none'): array
{
    $db->exec('UPDATE bands SET songs_written = songs_written + ? WHERE id = ?', [$songs, $bandId]);
    $opts = $p->post('recordOptions', ['bandId' => $bandId, 'songs' => $songs]);
    $p->post('recordStart', ['bandId' => $bandId, 'title' => $title, 'songs' => $songs, 'studio' => $studio, 'producer' => $producer, 'cover' => $opts['covers'][0]]);
    Clock::advance($songs * Balance::RECORD_SECONDS_PER_SONG + 2);
    $state = $p->get('state');
    $done = array_values(array_filter($state['results'], static fn ($r) => $r['type'] === 'record'));
    return ['state' => $state, 'completed' => $done ? end($done)['result'] : null];
}

/** Ein einfacher Manager fürs Label (ohne Bewerbung), auf Wunsch gleich bei einer Band. */
function giveManager(\Signed\Db $db, ?int $bandId = null): int
{
    $labelId = (int) $db->value('SELECT id FROM labels ORDER BY id DESC LIMIT 1');
    $id = (new Signed\Game\Repo($db, $labelId))->insertManager(['first_name' => 'Mira', 'last_name' => 'Plan', 'rarity' => 'common', 'level' => 1, 'skills' => [], 'look' => []]);
    if ($bandId !== null) {
        $db->exec('UPDATE bands SET manager_id = ?, manager_since = ? WHERE id = ?', [$id, Clock::now(), $bandId]);
    }
    return $id;
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
    Signed\Game\Bands::$autoSolo = false; // altes Feature: setzt Musiker ohne Band voraus (fällt im Aufräum-Schritt weg)
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

    $p->post('musicianFavorite', ['musicianId' => $m['id'], 'favorite' => false]);
    check(!$p->get('state')['musicians'][0]['favorite']);
});

/** Gibt jedem Musiker der Band ein einfaches Instrument seiner Art (ohne Instrument lernt man bei der Probe nichts). */
function equipBand(Player $p, Signed\Db $db, array $band): void
{
    $repo = new Signed\Game\Repo($db, (int) $db->value('SELECT id FROM labels ORDER BY id DESC LIMIT 1'));
    $models = ['vocals' => 'mic_basic', 'guitar' => 'gtr_school', 'drums' => 'drm_rehearsal', 'bass' => 'bass_entry', 'keys' => 'key_home'];
    foreach ($db->all('SELECT id, instr1 FROM musicians WHERE band_id = ?', [$band['id']]) as $m) {
        $p->post('gearAssign', ['gearId' => $repo->insertGear($models[$m['instr1']]), 'musicianId' => $m['id']]);
    }
}

test('Bandprobe und Band-Promo', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Probe');
    $band = foundBand($p);
    equipBand($p, $db, $band);
    $before = array_column($db->all('SELECT id, skill1 FROM musicians'), 'skill1', 'id');
    $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'rehearsal']);
    Clock::advance(2 * Balance::REHEARSAL['lust']);
    $state = $p->get('state');
    check(bandById($state, $band['id'])['cohesion'] > 0);
    foreach ($db->all('SELECT id, skill1 FROM musicians') as $m) {
        check((float) $m['skill1'] > (float) $before[$m['id']], 'Probe bringt Können');
    }
    $p->post('bandPromo', ['bandId' => $band['id']]);
    checkApiError(fn () => $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'rehearsal']), 'beschäftigt');
    Clock::advance(Balance::BAND_PROMO['seconds']);
    check(bandById($p->get('state'), $band['id'])['fame'] > 0, 'Promo macht die Band bekannter');
});

test('Aufstieg: Stufe steigt mit dem Können, die Spieltechnik bleibt (keine Fähigkeiten mehr)', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Level');
    $m = $p->get('state')['musicians'][0];
    $db->exec('UPDATE musicians SET skill1 = 59.995, level = 1, abilities = ? WHERE id = ?', ['["talent", "tapping"]', $m['id']]);
    $game = $p->post('minigameStart', ['kind' => 'practice', 'musicianId' => $m['id']])['game'];
    Clock::advance(Balance::SONG_SECONDS + 1);
    $result = $p->post('minigameFinish', ['sessionId' => $game['sessionId'], 'perfect' => $game['count'], 'good' => 0, 'miss' => 0])['result'];
    checkEquals(2, $result['levelUps'][0]['level']);
    checkEquals(null, $result['levelUps'][0]['choice']);
    $after = array_values(array_filter($p->get('state')['musicians'], static fn ($x) => $x['id'] === $m['id']))[0];
    checkEquals(['tapping'], $after['abilities'], 'nur die Technik, alte Fähigkeiten fallen weg');
    checkApiError(fn () => $p->post('abilityChoose', ['musicianId' => $m['id'], 'ability' => 'tapping']), '');
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
    checkEquals(9999, $state['label']['bandSlots']['open'], 'keine Bandplätze mehr');
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
    Signed\Game\Bands::$autoSolo = false; // altes Feature: setzt Musiker ohne Band voraus (fällt im Aufräum-Schritt weg)
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
    checkApiError(fn () => $p->post('practiceAuto', ['musicianId' => $m['id'], 'slot' => 1]), 'beschäftigt');

    $result = $p->post('minigameFinish', ['sessionId' => $game['sessionId'], 'perfect' => $count, 'good' => 0, 'miss' => 0])['result'];
    check($result['gain'] > 0.2, 'Perfekte Probe bringt spürbar Können');
    check(abs($result['skillBefore'] + $result['gain'] - $result['skillAfter']) <= 0.11, 'Vorher + Zuwachs = nachher');
    check($result['skillAfter'] <= (float) $db->value('SELECT skill1 FROM musicians WHERE id = ?', [$m['id']]), 'Nie aufgerundet');
    checkApiError(fn () => $p->post('minigameFinish', ['sessionId' => $game['sessionId'], 'perfect' => $count, 'good' => 0, 'miss' => 0]), 'nicht mehr aktiv');

    // Abbrechen gibt den Musiker frei.
    $game = $p->post('minigameStart', ['kind' => 'practice', 'musicianId' => $m['id']])['game'];
    $p->post('minigameAbort', ['sessionId' => $game['sessionId']]);
    $p->post('practiceAuto', ['musicianId' => $m['id'], 'slot' => 1]);
});

test('Platte produzieren, dann Gigs spielen, dann die nächste Platte', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Studio');
    $band = foundBand($p);
    $money = $p->get('state')['label']['money'];

    $db->exec('UPDATE labels SET money = 100000');
    $money = 100000;
    $opts = $p->post('recordOptions', ['bandId' => $band['id'], 'songs' => 3]);
    checkEquals(3, count($opts['covers']), 'drei Cover-Entwürfe');
    $start = ['bandId' => $band['id'], 'title' => 'Debüt', 'songs' => 3, 'studio' => 3, 'producer' => 'diamond', 'cover' => $opts['covers'][0]];
    checkApiError(fn () => $p->post('recordStart', ['songs' => 2] + $start), 'Lieder');
    checkApiError(fn () => $p->post('recordStart', $start), 'Songs');
    $db->exec('UPDATE bands SET songs_written = 4 WHERE id = ?', [$band['id']]);
    checkApiError(fn () => $p->post('recordStart', ['producer' => 'roxy'] + $start), 'Produzenten');
    $state = $p->post('recordStart', $start)['state'];
    checkEquals($money - Signed\Game\Studio::cost(3, 3, 'diamond') - Balance::COVER_DESIGNERS['student']['fee'], $state['label']['money'], 'Studio, Produzent und Cover');
    checkEquals('Debüt', bandById($state, $band['id'])['record']['open']['title']);
    checkEquals(1, (int) floor((float) $db->value('SELECT songs_written FROM bands WHERE id = ?', [$band['id']])), 'Die Songs kommen aus dem Vorrat');
    checkApiError(fn () => $p->post('recordStart', ['title' => 'Noch eine'] + $start), 'nimmt gerade auf');
    Clock::advance(3 * Balance::RECORD_SECONDS_PER_SONG + 2);
    $last = ['state' => $p->get('state')];
    $completed = array_values(array_filter($last['state']['results'], static fn ($r) => $r['type'] === 'record'))[0]['result'];
    check($completed['perfect'], 'Bestes Studio mit Diamond Dee: perfekt');
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
    // Album-Zyklus: die Platte (3 Lieder) dreimal komplett live spielen und dreimal Band-Promo.
    $gigs = Balance::RECORD_CYCLE['plays'];
    for ($i = 0; $i < $gigs; $i++) {
        check(str_contains((string) bandById($p->get('state'), $band['id'])['record']['blocker'], 'live'));
        $gig = $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'gig', 'songs' => 3])['activity'];
        check(str_starts_with($gig['title'], 'Gig: '));
        Clock::advance(3 * Balance::SONG_SECONDS);
    }
    $state = $p->get('state');
    $band = bandById($state, $band['id']);
    checkEquals($gigs, $band['gigsPlayed']);
    checkEquals(['have' => 9, 'need' => 9], $band['record']['cycle']['songs']);
    check($band['record']['blocker'] !== null, 'Ohne Promo noch keine neue Platte');
    for ($i = 0; $i < Balance::RECORD_CYCLE['promos']; $i++) {
        $p->post('bandPromo', ['bandId' => $band['id']]);
        Clock::advance(Balance::BAND_PROMO['seconds']);
        $state = $p->get('state');
    }
    $band = bandById($state, $band['id']);
    checkEquals(Balance::RECORD_CYCLE['promos'], $band['record']['cycle']['promos']['have']);
    checkEquals(null, $band['record']['blocker'], 'Nach Gigs und Promo darf die nächste Platte kommen');
    check(array_sum(array_map(static fn ($r) => $r['result']['money'], $state['results'])) > 0, 'Gigs bringen Gage');
});

test('Umbesetzung: Bekanntheit geht mit, der Zusammenhalt folgt den Beziehungen', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Lineup');
    $band = foundBand($p);
    $db->exec('UPDATE bands SET released = 1');
    $db->exec('UPDATE musicians SET fame = 10');
    $leaving = $band['memberIds'][0];
    $state = $p->post('bandRemoveMember', ['bandId' => $band['id'], 'musicianId' => $leaving])['state'];
    $after = bandById($state, $band['id']);
    checkEquals(20.0, $after['fame'], 'Bandruhm sinkt genau um die Bekanntheit des Ausgetretenen');
    checkEquals(50.0, $after['cohesion'], 'Fremde: mittlerer Zusammenhalt');

    // Die beiden anderen werden Freunde: voller Zusammenhalt.
    [$x, $y] = $after['memberIds'];
    $db->insert('relations', ['label_id' => (int) $db->value('SELECT id FROM labels'), 'a_id' => min($x, $y), 'b_id' => max($x, $y), 'value' => 10.0, 'at' => Clock::now()]);
    $state = $p->get('state');
    checkEquals(100.0, bandById($state, $band['id'])['cohesion']);
    checkEquals(null, bandById($state, $band['id'])['mood']['togetherness'], 'ohne Manager unsichtbar');
    checkEquals([], (array) bandById($state, $band['id'])['mood']['relations'], 'Freundschaft sieht man ohne Manager nicht');
    giveManager($db, $band['id']);
    $state = $p->get('state');
    checkEquals(10.0, bandById($state, $band['id'])['mood']['togetherness']['value']);
    // Großer Streit ist laut, auch ohne Manager.
    $db->exec('UPDATE bands SET manager_id = NULL');
    $db->exec('UPDATE relations SET value = -9');
    checkEquals(1, count((array) bandById($p->get('state'), $band['id'])['mood']['relations']), 'Streit sieht man');

    $state = $p->post('bandAddMember', ['bandId' => $band['id'], 'musicianId' => $leaving, 'slot' => 1])['state'];
    checkEquals(30.0, bandById($state, $band['id'])['fame']);
    check(bandById($state, $band['id'])['cohesion'] < 100.0, 'Ein Neuer, den noch keiner kennt, drückt den Zusammenhalt');
});

test('Instrumente zuweisen: nur passende Art, eins pro Art, bessere Klassen erst mit genug Können', function () {
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
    // Legendär erst als Virtuose (Können 85)
    $db->exec('UPDATE musicians SET skill1 = 40 WHERE id = ?', [$singer['id']]);
    checkApiError(fn () => $p->post('gearAssign', ['gearId' => $micB, 'musicianId' => $singer['id']]), 'braucht noch 45 Können');
    $db->exec('UPDATE musicians SET skill1 = 86 WHERE id = ?', [$singer['id']]);
    $state = $p->post('gearAssign', ['gearId' => $micB, 'musicianId' => $singer['id']])['state'];
    $assigned = array_values(array_filter($state['gear'], static fn ($g) => $g['musicianId'] === $singer['id']));
    checkEquals(1, count($assigned));
    checkEquals($micB, $assigned[0]['id']);
});

test('Manager: Stufe wirkt bei Gigs, Fan-Karte bringt Ruhm', function () {
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

test('Album-Charts: Platten aller Labels, Platz 1 wird vermerkt, Tantiemen laufen, Promo schiebt an', function () {
    $db = freshDb();
    $a = registerPlayer($db, 'Alpha');
    $b = registerPlayer($db, 'Beta');
    $bandA = foundBand($a, 'Alpha Band');
    $bandB = foundBand($b, 'Beta Band');
    $db->exec('UPDATE labels SET money = 100000');
    $done = makeRecord($db, $a, $bandA['id'], 'Alpha EP', 3, 2, 'roxy');
    makeRecord($db, $b, $bandB['id'], 'Beta EP');

    $charts = $b->get('charts');
    checkEquals(2, count($charts['albums']));
    checkEquals('Alpha EP', $charts['albums'][0]['title'], 'Die bessere Platte steht vorn');
    checkEquals('EP', $charts['albums'][0]['format']);
    check(!$charts['albums'][0]['own'] && $charts['albums'][1]['own']);
    checkEquals(2, count($charts['labels']));
    checkEquals(0, (int) $db->value("SELECT COUNT(*) FROM records WHERE topped = 1"), 'Ohne genug Konkurrenz zählt Platz 1 nicht');

    $c = registerPlayer($db, 'Gamma');
    $bandC = foundBand($c, 'Gamma Band');
    makeRecord($db, $c, $bandC['id'], 'Gamma EP');
    $c->get('charts');
    checkEquals(1, (int) $db->value("SELECT COUNT(*) FROM records WHERE topped = 1 AND title = 'Alpha EP'"), 'Platz 1 vermerkt');
    $b->get('charts');
    checkEquals(1, (int) $db->value("SELECT COUNT(*) FROM records WHERE topped = 1"), 'Nur einmal');

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
    // Platz 1 – aber Tantiemen nur nach Punkten, kein Platz-Bonus.
    checkEquals(1, (int) $db->value("SELECT rank_last FROM records WHERE title = 'Alpha EP'"));
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

    Clock::advance(12 * 86400);
    checkEquals([], $b->get('charts')['albums'], 'Nach ein paar Tagen sind alle draußen');
});

test('Auflösen gibt die Musiker frei und behält ihre Bekanntheit', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Split');
    $band = foundBand($p);
    $db->exec('UPDATE musicians SET fame = 4');
    $state = $p->post('bandDisband', ['bandId' => $band['id']])['state'];
    // Jeder ist danach Solokünstler (eine Band mit einem Mitglied).
    checkEquals(3, count($state['bands']));
    foreach ($state['bands'] as $b) {
        checkEquals(1, count($b['memberIds']));
    }
    foreach ($state['musicians'] as $m) {
        check($m['bandId'] !== $band['id']);
        checkEquals(4.0, $m['fame']);
    }
});

test('Wechselbörse: anbieten, bieten, überbieten, Zuschlag nach Ablauf', function () {
    $db = freshDb();
    Signed\Game\Bands::$autoSolo = false; // altes Feature: setzt Musiker ohne Band voraus (fällt im Aufräum-Schritt weg)
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
    Signed\Game\Bands::$autoSolo = false; // altes Feature: setzt Musiker ohne Band voraus (fällt im Aufräum-Schritt weg)
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
    Signed\Game\Bands::$autoSolo = false; // altes Feature: setzt Musiker ohne Band voraus (fällt im Aufräum-Schritt weg)
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

test('Namensänderung: immer kostenlos; mit Laufbahn geht der Ruhm am Namen verloren und die Platten bleiben beim alten Namen', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Umbenenner');
    $band = foundBand($p);
    $money = $p->get('state')['label']['money'];
    $p->post('bandRename', ['bandId' => $band['id'], 'name' => 'Erster Versuch']);
    checkEquals($money, $p->get('state')['label']['money'], 'Vor dem ersten Auftritt kostenlos');
    checkEquals(0, count(array_filter(wikiOwn($p)['bands'], static fn ($b) => $b['status'] === 'former')), 'ohne Laufbahn bleibt nichts zurück');

    $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'busking']);
    Clock::advance(Balance::BUSKING['songs'] * Balance::SONG_SECONDS);
    $db->exec('UPDATE bands SET fame_own = 12, records = 1 WHERE id = ?', [$band['id']]);
    $db->exec("INSERT INTO records (label_id, band_id, title, songs_total, songs_done, song_results, status, created_at, finished_at) VALUES (?, ?, 'Erste', 3, 3, '[]', 'done', ?, ?)", [$band['labelId'] ?? 1, $band['id'], Clock::now(), Clock::now()]);
    $money = $p->get('state')['label']['money'];
    $state = $p->post('bandRename', ['bandId' => $band['id'], 'name' => 'Zweiter Versuch'])['state'];
    checkEquals($money, $state['label']['money'], 'auch danach kostenlos');
    $now = bandById($state, $band['id']);
    checkEquals(0.0, (float) $now['fameOwn']);
    checkEquals('Zweiter Versuch', $now['name']);
    checkEquals(0, $now['records'], 'die Platten gehören nicht mehr zur neuen Band');
    $wiki = wikiOwn($p);
    $oldBands = array_values(array_filter($wiki['bands'], static fn ($b) => $b['status'] === 'former'));
    checkEquals(1, count($oldBands), 'die alte Band steht in der WikiSignia');
    $former = $p->post('wikiBand', ['id' => $oldBands[0]['id']]);
    checkEquals('Erster Versuch', $former['name']);
    checkEquals('renamed', $former['reason']);
    checkEquals(12.0, $former['fame']);
    checkEquals(1, count($former['records']), 'ihre Platte bleibt bei ihr');
    checkEquals('former', $former['status']);
});

test('Instrumente: farbige Boni je Klasse (grau keiner, grün 1, blau 2, gold 3), Verkauf an den Second-Hand-Laden', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Verkaeufer');
    $db->exec('UPDATE labels SET money = 100000');
    $offer = $p->post('buyPack', ['pack' => 'werkstatt', 'type' => 'guitar'])['offer'];
    foreach ($offer['cards'] as $card) {
        $rarity = Signed\Game\Catalog::GEAR[$card['gear']['model']]['rarity'];
        checkEquals(Signed\Game\Gear::tier($rarity), count($card['gear']['stats']['bonuses'] ?? $card['gear']['bonuses'] ?? []));
    }
    $state = $p->post('packPick', ['offerId' => $offer['id'], 'index' => 0])['state'];
    $gear = end($state['gear']);
    checkEquals($gear['tier'], count($gear['bonuses']), 'Gespeichert');
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
        $db->exec('UPDATE labels SET money = 100000');
        makeRecord($db, $p, $band['id'], $title, 3, $title === 'Vorn' ? 2 : 0, $title === 'Vorn' ? 'roxy' : 'none');
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
    check(Signed\Game\Charts::pointsAt($row, (int) $row['finished_at'] + 5 * 86400) < 0.05 * (float) $row['chart_points'], 'Auch mit vollem Schub fällt sie heraus');
});

test('Rap: Hip-Hop-Gesang übt im Rhythmus-Spiel (Etüde)', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Rapper');
    $m = $p->get('state')['musicians'][0];
    $db->exec("UPDATE musicians SET instr1 = 'vocals', instr2 = NULL, genres = ? WHERE id = ?", ['["hiphop"]', $m['id']]);
    $game = $p->post('minigameStart', ['kind' => 'practice', 'musicianId' => $m['id']])['game'];
    checkEquals('rhythm', $game['mode']);
    checkEquals('hiphop', $game['etude']['genre'], 'Hip-Hop-Etüde');
    check($game['count'] > 0, 'Silben im Rhythmus');
    $db->exec("UPDATE musicians SET genres = ? WHERE id = ?", ['["pop"]', $m['id']]);
    checkEquals('pitch', $p->post('minigameStart', ['kind' => 'practice', 'musicianId' => $m['id']])['game']['mode'], 'Pop singt nach Tonhöhe');
});

test('Promo lässt sich abbrechen: sofort wieder frei, ohne Belohnung', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Promo');
    $band = foundBand($p);
    $activity = $p->post('bandPromo', ['bandId' => $band['id']])['activity'];
    checkApiError(fn () => $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'busking']), 'beschäftigt');
    $state = $p->post('promoCancel', ['activityId' => $activity['id']])['state'];
    checkEquals(0, count($state['running']));
    checkEquals(0, count($state['results']));
    $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'busking']);

    // Band-Promo genauso; Auftritte lassen sich nicht abbrechen.
    $gig = $p->get('state')['running'][0];
    checkApiError(fn () => $p->post('promoCancel', ['activityId' => $gig['id']]), 'läuft nicht mehr');
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

test('Charts: Eine Band darf mehrere Platten in den Charts haben', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Flut');
    $band = foundBand($p);
    $labelId = (int) $db->value('SELECT label_id FROM bands WHERE id = ?', [$band['id']]);
    foreach (['Eins' => 80, 'Zwei' => 60, 'Drei' => 40, 'Vier' => 20] as $title => $points) {
        $db->insert('records', [
            'label_id' => $labelId, 'band_id' => $band['id'], 'title' => $title, 'songs_total' => 3, 'songs_done' => 3,
            'song_results' => '[]', 'status' => 'done', 'created_at' => Clock::now(), 'finished_at' => Clock::now(),
            'chart_points' => $points, 'royalties_until' => Clock::now(),
        ]);
    }
    Signed\Game\Charts::markDirty($db);
    $placed = array_column(array_filter($p->get('charts')['albums'], static fn ($a) => $a['placed']), 'title');
    checkEquals(['Eins', 'Zwei', 'Drei', 'Vier'], $placed);
});

test('Gigs: Die Band spielt immer ihre neueste Platte – Gigs schieben diese an', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Setlist');
    $band = foundBand($p);
    $labelId = (int) $db->value('SELECT label_id FROM bands WHERE id = ?', [$band['id']]);
    $ids = [];
    foreach (['Alt' => 80, 'Neu' => 40] as $title => $points) {
        $ids[$title] = $db->insert('records', [
            'label_id' => $labelId, 'band_id' => $band['id'], 'title' => $title, 'songs_total' => 3, 'songs_done' => 3,
            'song_results' => '[]', 'status' => 'done', 'created_at' => Clock::now(), 'finished_at' => Clock::now(),
            'chart_points' => $points, 'royalties_until' => Clock::now(),
        ]);
        Clock::advance(60);
    }
    $db->exec('UPDATE bands SET records = 2, songs_recorded = 6 WHERE id = ?', [$band['id']]);
    $promoted = static fn ($state) => array_column(array_filter(bandById($state, $band['id'])['discography'], static fn ($r) => $r['promoted']), 'title');
    checkEquals(['Neu'], $promoted($p->get('state')));
    $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'gig', 'songs' => 3]);
    Clock::advance(3 * Balance::SONG_SECONDS);
    $p->get('state');
    check((float) $db->value('SELECT boost FROM records WHERE id = ?', [$ids['Neu']]) > 0, 'Gig schiebt die neueste Platte');
    checkEquals(0.0, (float) $db->value('SELECT boost FROM records WHERE id = ?', [$ids['Alt']]));
});

test('Tantiemen: Platten ohne Chartplatz verkaufen sich weiter, ältere bringen dauerhaft Katalog-Geld', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Katalog');
    $band = foundBand($p);
    $labelId = (int) $db->value('SELECT label_id FROM bands WHERE id = ?', [$band['id']]);
    // Vier Platten, alle verkaufen sich (und stehen in den Charts).
    foreach (['Alt' => 50, 'Mitte' => 40, 'Neu' => 20, 'Klein' => 10] as $title => $points) {
        $db->insert('records', [
            'label_id' => $labelId, 'band_id' => $band['id'], 'title' => $title, 'songs_total' => 3, 'songs_done' => 3,
            'song_results' => '[]', 'status' => 'done', 'created_at' => Clock::now(), 'finished_at' => Clock::now(),
            'chart_points' => $points, 'royalties_until' => Clock::now(), 'catalog_until' => Clock::now(),
        ]);
        Clock::advance(60);
    }
    Signed\Game\Charts::markDirty($db);
    $finance = $p->get('state')['finance'];
    checkEquals(4, count($finance['records']), 'Alle verkaufen sich');
    checkEquals(4, count(array_filter($finance['records'], static fn ($r) => $r['placed'])));
    check($finance['catalogPerHour'] > 1.1 && $finance['catalogPerHour'] < 1.3, 'Katalog: 120 Punkte × 0,01');

    // Nach den Charts: Katalog läuft weiter.
    Clock::advance(Balance::CHART_MAX_AGE + 86400);
    $p->get('state');
    $money = (int) $db->value('SELECT money FROM labels WHERE id = ?', [$labelId]);
    Clock::advance(10 * 3600);
    $state = $p->get('state');
    $gain = $state['label']['money'] - $money;
    check($gain >= 11 && $gain <= 13, "Katalog nach 10 Stunden: $gain");
    checkEquals(0, count($state['finance']['records']), 'Keine Chart-Tantiemen mehr');
});

test('Charts: Alle Platten zählen – Top 10 mit Belohnung, darunter bis Platz 100', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Viele');
    $band = foundBand($p);
    $labelId = (int) $db->value('SELECT label_id FROM bands WHERE id = ?', [$band['id']]);
    for ($i = 1; $i <= 12; $i++) {
        $bandId = $db->insert('bands', ['label_id' => $labelId, 'name' => "Band $i", 'genre' => 'rock', 'created_at' => Clock::now()]);
        $db->insert('records', [
            'label_id' => $labelId, 'band_id' => $bandId, 'title' => "Platte $i", 'songs_total' => 3, 'songs_done' => 3,
            'song_results' => '[]', 'status' => 'done', 'created_at' => Clock::now(), 'finished_at' => Clock::now(),
            'chart_points' => 20 * $i, 'royalties_until' => Clock::now(),
        ]);
    }
    Signed\Game\Charts::markDirty($db);
    $albums = $p->get('charts')['albums'];
    checkEquals(12, count($albums), 'Kein Label-Deckel mehr');
    checkEquals(Balance::CHART_SIZE, count(array_filter($albums, static fn ($a) => $a['placed'])));
    checkEquals('Platte 12', $albums[0]['title']);
    checkEquals(12, end($albums)['rank']);
});

test('Allein üben lassen: läuft von selbst, bringt ein Viertel einer perfekten Solo-Probe', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Auto');
    $m = $p->get('state')['musicians'][0];
    $before = (float) $db->value('SELECT skill1 FROM musicians WHERE id = ?', [$m['id']]);
    $expected = $m['gains']['auto'][0];
    check($expected > 0 && abs($expected - $m['gains']['practice'][0] * Balance::AUTO_PRACTICE['share']) < 0.002);
    $p->post('practiceAuto', ['musicianId' => $m['id'], 'slot' => 1]);
    checkApiError(fn () => $p->post('minigameStart', ['kind' => 'practice', 'musicianId' => $m['id']]), 'beschäftigt');
    Clock::advance(Balance::AUTO_PRACTICE['seconds']);
    $state = $p->get('state');
    $after = (float) $db->value('SELECT skill1 FROM musicians WHERE id = ?', [$m['id']]);
    check(abs(($after - $before) - $expected) < 0.002, 'Zuwachs wie angezeigt');
    checkEquals('practice', $state['results'][0]['type']);
    // Zurückholen geht auch hier, dann gibt es nichts.
    $activity = $p->post('practiceAuto', ['musicianId' => $m['id'], 'slot' => 1])['activity'];
    $p->post('promoCancel', ['activityId' => $activity['id']]);
    Clock::advance(Balance::AUTO_PRACTICE['seconds']);
    $p->get('state');
    checkEquals($after, (float) $db->value('SELECT skill1 FROM musicians WHERE id = ?', [$m['id']]));
});

test('Tageskurs: für alle dieselbe Kursart, Schwierigkeit nach Stufe, gleicher Zuwachs, einmal pro Musiker', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Kurs');
    $course = Signed\Game\Courses::today();
    $m = $p->get('state')['musicians'][0];
    $db->exec('UPDATE musicians SET instr1 = ?, instr2 = NULL, skill2 = NULL, skill1 = 20 WHERE id = ?', [$course['instrument'], $m['id']]);
    $db->exec('UPDATE labels SET money = 10000');
    $state = $p->get('state');
    checkEquals($course['instrument'], $state['courses'][0]['instrument']);
    checkEquals($course['name'], $state['courses'][0]['name']);
    $candidate = array_values(array_filter($state['courses'][0]['candidates'], static fn ($c) => $c['musicianId'] === $m['id']))[0];
    checkEquals(0, $candidate['level'], 'Anfänger');
    $price = Signed\Game\Courses::price($course, 0);
    $p->post('courseAttend', ['musicianId' => $m['id'], 'course' => 0]);
    checkEquals(10000 - $price, $p->get('state')['label']['money']);
    checkApiError(fn () => $p->post('minigameStart', ['kind' => 'practice', 'musicianId' => $m['id']]), 'beschäftigt');
    Clock::advance($course['seconds']);
    $p->get('state');
    check(abs((float) $db->value('SELECT skill1 FROM musicians WHERE id = ?', [$m['id']]) - (20 + $course['gain'])) < 0.001, 'Gleicher Zuwachs für alle');
    checkApiError(fn () => $p->post('courseAttend', ['musicianId' => $m['id']]), 'schon im Kurs');

    // Profi: teurere Schwierigkeit, gleicher Zuwachs; Zurückholen gibt das Geld zurück.
    $other = $p->get('state')['musicians'][1] ?? null;
    if ($other !== null) {
        $db->exec('UPDATE musicians SET instr1 = ?, instr2 = NULL, skill2 = NULL, skill1 = 70 WHERE id = ?', [$course['instrument'], $other['id']]);
        $db->exec('UPDATE labels SET money = 10000');
        $activity = $p->post('courseAttend', ['musicianId' => $other['id'], 'course' => 0])['activity'];
        checkEquals(10000 - Signed\Game\Courses::price($course, 2), $p->get('state')['label']['money'], 'Profi zahlt mehr');
        $p->post('promoCancel', ['activityId' => $activity['id']]);
        checkEquals(10000, $p->get('state')['label']['money']);
    }
});

test('Tageskurs: nie zweimal hintereinander dasselbe Instrument', function () {
    $prev = null;
    $kinds = [];
    for ($d = 0; $d < 200; $d++) {
        $today = Signed\Game\Courses::today(1767225600 + $d * 86400);
        check($today['instrument'] !== $prev, "Tag $d: zweimal {$today['instrument']}");
        $prev = $today['instrument'];
        $kinds[$today['name']] = ($kinds[$today['name']] ?? 0) + 1;
    }
    check(($kinds['Kurs'] ?? 0) > ($kinds['Meisterklasse'] ?? 0) && ($kinds['Meisterklasse'] ?? 0) > 5, 'Kursarten gewichtet: ' . json_encode($kinds));
    // Mehrere Kurse am Tag, alle mit verschiedenen Instrumenten, für alle gleich.
    for ($d = 0; $d < 30; $d++) {
        $all = Signed\Game\Courses::all(1767225600 + $d * 86400);
        checkEquals(Balance::COURSE['perDay'], count($all));
        checkEquals(count($all), count(array_unique(array_column($all, 'instrument'))), 'verschiedene Instrumente');
        checkEquals(Signed\Game\Courses::today(1767225600 + $d * 86400)['instrument'], $all[0]['instrument']);
    }
});

test('Instrumentwechsel: Vorschau stimmt, Zurückwechseln geht jederzeit', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Wechsel');
    $band = foundBand($p);
    $db->exec('UPDATE bands SET released = 1');
    $drummer = (int) $db->value("SELECT id FROM musicians WHERE instr1 = 'drums'");
    $db->exec("UPDATE musicians SET instr2 = 'bass', skill2 = 30 WHERE id = ?", [$drummer]);
    $preview = bandById($p->get('state'), $band['id'])['slotPreview'][$drummer];
    $after = bandById($p->post('bandSetSlot', ['bandId' => $band['id'], 'musicianId' => $drummer, 'slot' => 2])['state'], $band['id']);
    checkEquals($preview, $after['sound']);

    $preview = $after['slotPreview'][$drummer];
    $back = bandById($p->post('bandSetSlot', ['bandId' => $band['id'], 'musicianId' => $drummer, 'slot' => 1])['state'], $band['id']);
    checkEquals($preview, $back['sound']);
    check($back['cohesion'] === $after['cohesion'], 'ein Rollenwechsel kostet keinen Zusammenhalt');
});

test('Fan will einsteigen: selten nach begeistertem Gig, passendes fehlendes Instrument, höchstens einmal pro Woche', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Fans');
    $band = foundBand($p);
    $repo = new Signed\Game\Repo($db, (int) $db->value('SELECT id FROM labels'));
    $row = Signed\Game\Repo::hydrateBand($db->one('SELECT * FROM bands WHERE id = ?', [$band['id']]));
    $fan = Signed\Game\FanMusician::join($repo, $row, 'arena');
    check($fan !== null, 'Fan kommt');
    $m = $repo->musician($fan['id']);
    check(!in_array($m['instr1'], ['vocals', 'guitar', 'drums'], true), 'spielt ein Instrument, das der Band fehlt');
    check($m['band_id'] === null && $fan['canJoin'], 'ist im Label und könnte in die Band');
    checkEquals(null, Signed\Game\FanMusician::join($repo, $row, 'arena'), 'zweiter Fan in derselben Woche');
    Clock::advance(Balance::FAN_MUSICIAN['cooldown'] + 1);
    check(Signed\Game\FanMusician::join($repo, $row, 'kneipe') !== null, 'nach einer Woche wieder möglich');
    $state = $p->post('bandAddMember', ['bandId' => $band['id'], 'musicianId' => $fan['id'], 'slot' => 1])['state'];
    checkEquals(4, count(bandById($state, $band['id'])['memberIds']));
});

test('Jam-Café: Wer darüber eine Band gegründet hat, kann nicht noch einmal hin – auch nach dem Auflösen', function () {
    $db = freshDb();
    Signed\Game\Bands::$autoSolo = false; // altes Feature: setzt Musiker ohne Band voraus (fällt im Aufräum-Schritt weg)
    $p = player($db);
    $p->post('register', ['username' => 'sammler', 'password' => 'geheim123', 'labelName' => 'Sammel Records']);
    $offer = $p->post('scoutOpen', ['voucher' => true])['offer'];
    $m = $p->post('scoutPick', ['offerId' => $offer['id'], 'index' => 0])['musician'];
    $p->post('soloStart', ['musicianId' => $m['id'], 'type' => 'jam']);
    Clock::advance(Balance::JAM['songs'] * Balance::SONG_SECONDS);
    $offer = $p->get('state')['jamOffers'][0];
    $res = $p->post('jamPick', ['offerId' => $offer['id'], 'index' => 0, 'name' => 'Eintagsfliege']);
    $state = $p->post('bandDisband', ['bandId' => $res['bandId']])['state'];
    checkEquals(2, count(array_filter($state['musicians'], static fn ($x) => $x['jammed'])), 'Gastgeber und neuer Musiker sind markiert');
    foreach ($state['musicians'] as $x) {
        checkApiError(fn () => $p->post('soloStart', ['musicianId' => $x['id'], 'type' => 'jam']), 'nicht noch einmal');
    }
});

test('Aufnehmen: drei Cover-Entwürfe, neue kosten ein Honorar, Studio und Produzent bestimmen Qualität, Anspruch und Laune', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Cover');
    $band = foundBand($p);
    $db->exec('UPDATE labels SET money = 100000');
    $opts = $p->post('recordOptions', ['bandId' => $band['id'], 'songs' => 4]);
    checkEquals(3, count(array_unique(array_column($opts['covers'], 'motif'))), 'drei verschiedene Motive');
    $redraw = $p->post('coverRedraw');
    checkEquals(100000 - Balance::COVER_REDRAW, $redraw['state']['label']['money']);
    checkEquals(3, count($redraw['covers']));
    // Ohne Produzent nur die Garage; jeder Produzent macht es besser, auch in der Garage.
    $by = [];
    foreach ($opts['options'] as $o) {
        $by[$o['producer'] . $o['studio']] = $o;
    }
    check($by['none0']['refusal'] === null && $by['none1']['refusal'] !== null, 'ohne Produzent nur Garage');
    check($by['mo1']['refusal'] === null && $by['mo2']['refusal'] !== null, 'Mo bringt ins Tonstudio');
    check($by['diamond3']['refusal'] === null);
    check($by['legend0']['refusal'] !== null, 'Die Legende nimmt nur Bands, die schon gut klingen');
    check($by['diamond0']['quality'] > $by['none0']['quality'], 'Produzent hilft auch in der Garage');
    check($by['diamond3']['royalties'][1] > $by['none0']['royalties'][1], 'Besser produziert chartet höher');
    check($by['diamond3']['cost'] > $by['none0']['cost']);
    // Eine berühmte Band ärgert sich über die Garage
    $db->exec('UPDATE musicians SET fame = 400, wellbeing = 70 WHERE band_id = ?', [$band['id']]);
    $opts = $p->post('recordOptions', ['bandId' => $band['id'], 'songs' => 3]);
    $garage = array_values(array_filter($opts['options'], fn ($o) => $o['producer'] === 'none' && $o['studio'] === 0))[0];
    check($garage['claim'] < 0, 'unter der Würde');
    $res = makeRecord($db, $p, $band['id'], 'Billig');
    $w = (float) $db->value('SELECT AVG(wellbeing) FROM musicians WHERE band_id = ?', [$band['id']]);
    check($w < 70, 'Garage ärgert eine berühmte Band: ' . $w);
    $c = $res['completed'];
    check($c !== null && $c['studio'] === 'garage' && $c['producer'] === 'none');
    checkEquals($c['cover'], bandById($res['state'], $band['id'])['discography'][0]['cover']);
    checkEquals(null, bandById($res['state'], $band['id'])['record']['open']);
    checkApiError(fn () => $p->post('recordStart', ['bandId' => $band['id'], 'title' => 'X', 'songs' => 3, 'studio' => 0, 'producer' => 'none', 'cover' => ['motif' => 'quatsch']]), 'Cover');
});

test('Hall of Fame: Bands aller Labels nach Ruhm', function () {
    $db = freshDb();
    $a = registerPlayer($db, 'Ruhmreich');
    $b = registerPlayer($db, 'Klein');
    $bandA = foundBand($a, 'Die Großen');
    foundBand($b, 'Die Kleinen');
    $db->exec('UPDATE bands SET fame_own = 50 WHERE id = ?', [$bandA['id']]);
    $list = $b->get('charts')['bands'];
    checkEquals(['Die Großen', 'Die Kleinen'], array_column($list, 'band'));
    checkEquals([1, 2], array_column($list, 'rank'));
    check(!$list[0]['own'] && $list[1]['own']);
});

test('Solo-Probe: Alle üben Etüden aus dem Synthesizer, Gesang im Tonhöhen-Spiel', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Etueden');
    $state = $p->get('state');
    $byInstrument = [];
    foreach ($state['musicians'] as $m) {
        $byInstrument[$m['instruments'][0]['type']] = $m;
    }
    $drums = $p->post('minigameStart', ['kind' => 'practice', 'musicianId' => $byInstrument['drums']['id']])['game'];
    check(!empty($drums['backing']) && isset($drums['etude']) && !isset($drums['music']), 'Schlagzeug: Etüde mit Begleitung');
    checkEquals($drums['etude']['bpm'], $drums['bpm'], 'Tempo der Etüde');
    Clock::advance(Balance::SONG_SECONDS + 2);
    $res = $p->post('minigameFinish', ['sessionId' => $drums['sessionId'], 'perfect' => $drums['count'], 'good' => 0, 'miss' => 0]);
    check($res['result']['gain'] > 0, 'Etüde bringt Können');
    $vocals = $p->post('minigameStart', ['kind' => 'practice', 'musicianId' => $byInstrument['vocals']['id']])['game'];
    check(isset($vocals['etude']) && $vocals['mode'] === 'pitch' && isset($vocals['tonality']), 'Gesang: Etüde im Tonhöhen-Spiel');
    foreach (array_slice($vocals['notes'], 0, -1) as $i => $n) {
        $next = $vocals['notes'][$i + 1];
        $jump = abs($next['p'] - $n['p']);
        if ($jump >= 3) {
            check($next['t'] - ($n['t'] + $n['d']) >= 0.07 * $jump - 0.002 || $n['d'] <= 0.15, 'Vor großen Sprüngen bleibt Zeit');
        }
    }
});

test('Gesang: Vor einem großen Sprung wird der Ton hinten gekürzt', function () {
    $chart = Signed\Game\Music::melodyChart(['bpm' => 120, 'notes' => [[0, 2, 60, 80], [2, 2, 72, 80], [4, 2, 74, 80]]], 24);
    // C4 → C5 (7 Stufen): Lücke 7 × 0,07 s; C5 → D5 (1 Stufe): kein Abstand nötig.
    checkEquals(round(1 - 7 * 0.07, 3), $chart['notes'][0]['d']);
    checkEquals(1.0, (float) $chart['notes'][1]['d']);
});

test('Eigene Reihenfolge der Band- und Favoritenknöpfe', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Reihe');
    checkEquals([], $p->get('state')['label']['navOrder']);
    $res = $p->post('navOrder', ['order' => ['m5', 'b2', 'b2', 'x1', 7, 'b9']]);
    checkEquals(['m5', 'b2', 'b9'], $res['state']['label']['navOrder']);
});

test('Musiker entlassen: nur ohne Band, Instrumente bleiben im Lager', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Entlass');
    $band = foundBand($p);
    $inBand = $band['memberIds'][0];
    checkApiError(fn () => $p->post('musicianRelease', ['musicianId' => $inBand]), 'aus der Band');
    $p->post('bandRemoveMember', ['bandId' => $band['id'], 'musicianId' => $inBand]);
    $db->exec('UPDATE musicians SET busy_until = 0');
    // Herausgenommen ist er Solokünstler; auch der darf gehen.
    $gear = (int) $db->value('SELECT COUNT(*) FROM gear');
    $before = count($p->get('state')['musicians']);
    $state = $p->post('musicianRelease', ['musicianId' => $inBand])['state'];
    checkEquals($before - 1, count($state['musicians']));
    checkEquals($gear, (int) $db->value('SELECT COUNT(*) FROM gear WHERE musician_id IS NULL OR musician_id <> ?', [$inBand]), 'Instrumente bleiben');
    checkApiError(fn () => $p->post('musicianRelease', ['musicianId' => $inBand]), 'nicht');
});

test('Bands leben offline weiter: Songs, Stimmung; rot friert ein; Beziehungen wachsen und driften', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Leben');
    $band = foundBand($p);
    $bandId = $band['id'];
    $state = $p->get('state');
    $start = $state['bands'][0]['mood'];
    checkEquals('green', $start['level']);
    checkEquals(0, $start['songs']);

    // Drei Stunden offline: im Tempo „grün“ entstehen rund drei Songs mal dem, was die Mitglieder beitragen.
    $crew = Signed\Game\Mood::crewFactor(array_map([Signed\Game\Repo::class, 'hydrateMusician'], $db->all('SELECT * FROM musicians WHERE band_id = ?', [$bandId])));
    Clock::advance(3 * 3600);
    $mood = $p->get('state')['bands'][0]['mood'];
    check(abs($mood['songs'] - 3 * $crew) <= 1.01, sprintf('etwa %.1f Songs nach drei Stunden: %d', 3 * $crew, $mood['songs']));
    check($mood['value'] > 40, 'noch gute Stimmung');

    // Rot: Jemand ist sehr unzufrieden – offline passiert nichts mehr, nicht einmal Songs.
    $ids = array_column($db->all('SELECT id FROM musicians WHERE band_id = ?', [$bandId]), 'id');
    $db->exec('UPDATE musicians SET wellbeing = 10 WHERE id = ?', [$ids[0]]);
    $db->exec("UPDATE labels SET online_at = ?", [Clock::now() - 3600]);
    $before = $p->get('state')['bands'][0]['mood'];
    checkEquals('red', $before['level']);
    Clock::advance(2 * 86400);
    $after = $p->get('state')['bands'][0]['mood'];
    checkEquals($before['songs'], $after['songs'], 'eine rote Band schreibt offline keine Songs');
    checkEquals($before['value'], $after['value'], 'und wird offline nicht schlechter');

    // Gemeinsame Erlebnisse lassen Beziehungen wachsen.
    $rel = $p->get('state')['bands'][0]['mood']['relations'];
    check($rel !== [], 'Beziehungen gibt es für jedes Paar');
    $a = $db->one('SELECT * FROM musicians WHERE id = ?', [$ids[0]]);
    $b = $db->one('SELECT * FROM musicians WHERE id = ?', [$ids[1]]);
    $members = array_map([Signed\Game\Repo::class, 'hydrateMusician'], [$a, $b]);
    $repo = new Signed\Game\Repo($db, (int) $a['label_id']);
    $key = min($ids[0], $ids[1]) . '-' . max($ids[0], $ids[1]);
    for ($i = 0; $i < 24; $i++) {
        Signed\Game\Mood::bond($repo, $members, 'rehearsal');
    }
    $grown = Signed\Game\Mood::relations($repo, $members)[$key];
    $target = Signed\Game\Mood::relationTarget(Signed\Game\Mood::fit($members[0], $members[1]));
    check(abs($grown - $target) < 0.1 * abs($target) + 0.4, "Beziehung nähert sich dem Ziel ($grown von $target)");

    // Wer nicht mehr zusammen ist, driftet auf 0 zurück.
    $db->exec('UPDATE musicians SET band_id = NULL WHERE id IN (?, ?)', [$ids[0], $ids[1]]);
    foreach ($members as &$m) {
        $m['band_id'] = null;
    }
    unset($m);
    Clock::advance(3600 * 10);
    $drifted = Signed\Game\Mood::relations($repo, $members)[$key];
    check(abs($drifted) < abs($grown) && abs($drifted) >= 0, 'Beziehung driftet Richtung 0');
});

test('Solokünstler: eine Band mit einem Mitglied, ohne Genre-Bonus; der Letzte kann nicht herausgenommen werden', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Solo');
    $one = $p->get('state')['musicians'][0];
    $res = $p->post('bandCreate', ['name' => 'Solo Eins', 'members' => [['id' => $one['id'], 'slot' => 1]]]);
    $band = bandById($res['state'], $res['bandId']);
    checkEquals(1, count($band['memberIds']));
    checkEquals(1.0, $band['genreBonus'], 'weder Genre-Band-Bonus noch Malus der gemischten Band');
    checkApiError(fn () => $p->post('bandRemoveMember', ['bandId' => $band['id'], 'musicianId' => $one['id']]), 'mindestens 1');
    check($p->get('state')['label']['bandSlots']['open'] > 8, 'keine Obergrenze für Bands');
});

test('Rote Band: Anordnungen, die ein Unzufriedener nicht mag, sind gesperrt; was er mag, hebt die Stimmung', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Sperre');
    $band = foundBand($p);
    $ids = array_column($db->all('SELECT id FROM musicians WHERE band_id = ?', [$band['id']]), 'id');
    // Einer wird zum Chaoten (mag Auftritte, mag keine Proben) und ist rot; die anderen haben keine Vorlieben.
    $db->exec("UPDATE musicians SET personality = '[]' WHERE band_id = ?", [$band['id']]);
    $db->exec("UPDATE musicians SET personality = ?, known_traits = NULL, wellbeing = 10 WHERE id = ?", [json_encode(['chaot']), $ids[0]]);
    $state = $p->get('state');
    $mood = bandById($state, $band['id'])['mood'];
    check($mood['reactions']['rehearsal']['locked'], 'Proben sind gesperrt');
    check(!$mood['reactions']['gig']['locked'], 'Auftritte sind nicht gesperrt');
    checkEquals([$ids[0]], $mood['reactions']['rehearsal']['dislike']);
    check(count($mood['wishes']) >= 1, 'der Unzufriedene äußert einen Wunsch');
    checkApiError(fn () => $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'rehearsal']), 'keine Lust');
    // Straßenmusik mag der Chaot: die Anordnung geht und hebt sein Wohlbefinden nach dem Auftritt.
    $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'busking']);
    Clock::advance(600);
    $p->get('state');
    $after = (float) $db->value('SELECT wellbeing FROM musicians WHERE id = ?', [$ids[0]]);
    check($after > 10, 'Wohlbefinden steigt nach einer Anordnung, die er mag: ' . $after);
});

test('Offene Probe: Können wächst laufend, Abbrechen behält den Fortschritt, am Ende hört die Band von selbst auf', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Offen');
    $band = foundBand($p);
    equipBand($p, $db, $band);
    $ids = array_column($db->all('SELECT id FROM musicians WHERE band_id = ? ORDER BY id', [$band['id']]), 'id');
    $skill = static fn (int $id) => (float) $db->value('SELECT skill1 FROM musicians WHERE id = ?', [$id]);
    $start = $skill($ids[0]);
    $act = $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'rehearsal'])['activity'];
    check($act['endsAt'] - $act['startedAt'] >= Balance::REHEARSAL['minLust'], 'die Probe hat eine natürliche Dauer');
    // Nach 5 Minuten: schon etwas gelernt, noch kein Ergebnis.
    Clock::advance(300);
    $running = $p->get('state');
    check($skill($ids[0]) > $start, 'Können wächst schon während der Probe');
    checkEquals(1, count($running['running']), 'die Probe läuft noch');
    $half = $skill($ids[0]) - $start;
    // Abbrechen: nichts geht verloren, die Band ist frei.
    $res = $p->post('rehearsalStop', ['activityId' => $act['id']]);
    checkEquals([], $res['state']['running']);
    check(abs(($skill($ids[0]) - $start) - $half) < 0.01, 'bis dahin Gelerntes bleibt');
    $b = bandById($res['state'], $band['id']);
    check($b['busyUntil'] <= Clock::now(), 'die Band ist wieder frei');
    checkApiError(fn () => $p->post('rehearsalStop', ['activityId' => $act['id']]), 'läuft nicht mehr');
    // Zweite Probe: läuft bis zum Ende, die Band hört von selbst auf.
    $act2 = $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'rehearsal'])['activity'];
    $full = $act2['endsAt'] - $act2['startedAt'];
    Clock::advance($full + 10);
    $state = $p->get('state');
    checkEquals([], $state['running']);
    check($skill($ids[0]) - $start > $half, 'bis zum Ende hat sich mehr getan');
});

test('Offene Probe: Wer sie nicht mag, wird zuerst rot – dann endet sie früher', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Genervt');
    $band = foundBand($p);
    equipBand($p, $db, $band);
    $ids = array_column($db->all('SELECT id FROM musicians WHERE band_id = ? ORDER BY id', [$band['id']]), 'id');
    $db->exec('UPDATE musicians SET personality = ?, wellbeing = 38 WHERE id = ?', [json_encode(['chaot']), $ids[0]]);
    $act = $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'rehearsal'])['activity'];
    check($act['endsAt'] - $act['startedAt'] < Balance::REHEARSAL['lust'], 'der Chaot nervt sich früh: ' . ($act['endsAt'] - $act['startedAt']));
    checkEquals('red', $act['rehearsal']['limit']['why']);
    Clock::advance($act['endsAt'] - $act['startedAt'] + 5);
    $p->get('state');
    $w = (float) $db->value('SELECT wellbeing FROM musicians WHERE id = ?', [$ids[0]]);
    check($w < 38, 'sein Wohlbefinden ist gesunken: ' . $w);
});

test('Einsendungen: Willkommens-Stapel, laufender Eingang, Unterschreiben kostet die Prämie, Testbewerber aller Stufen', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Post');
    $state = $p->get('state');
    checkEquals(5, count($state['submissions']), 'Willkommens-Stapel: fünf Einsendungen');
    checkEquals(['envelope'], array_values(array_unique(array_column($state['submissions'], 'state'))), 'alle im Umschlag');
    // Auspacken dauert ein paar Sekunden, einer nach dem anderen.
    $p->post('postUnpack', ['table' => 's', 'id' => $state['submissions'][0]['submissionId']]);
    checkApiError(fn () => $p->post('postUnpack', ['table' => 's', 'id' => $state['submissions'][1]['submissionId']]), 'schon einen');
    checkApiError(fn () => $p->post('submissionOpen', ['submissionId' => $state['submissions'][0]['submissionId']]), 'erst auspacken');
    Clock::advance(Balance::POST['unpack']);
    unpackAll($db);
    $state = $p->get('state');
    checkEquals(['closed'], array_values(array_unique(array_column($state['submissions'], 'state'))), 'ausgepackt: alles Kassetten, ungehört');
    check(!isset($state['submissions'][0]['firstName']), 'ungehört verrät nichts');
    checkApiError(fn () => $p->post('submissionSign', ['submissionId' => $state['submissions'][0]['submissionId']]), 'sichten');
    // Sichten dauert ein paar Sekunden.
    $ids = array_column($state['submissions'], 'submissionId');
    $p->post('submissionOpen', ['submissionId' => $ids[0]]);
    checkApiError(fn () => $p->post('submissionOpen', ['submissionId' => $ids[1]]), 'schon eine andere');
    $listening = $p->get('state')['submissions'];
    checkEquals(1, count(array_filter($listening, static fn ($s) => $s['state'] === 'listening')), 'nur eine gleichzeitig');
    check(!isset($listening[array_search($ids[0], array_column($listening, 'submissionId'), true)]['firstName']), 'beim Sichten noch nichts zu sehen');
    foreach (array_slice($ids, 1) as $id) {
        Clock::advance(Balance::SUBMISSIONS['listen'] + 1);
        $p->post('submissionOpen', ['submissionId' => $id]);
    }
    Clock::advance(Balance::SUBMISSIONS['listen'] + 1);
    $state = $p->get('state');
    checkEquals(['open'], array_values(array_unique(array_column($state['submissions'], 'state'))));
    check(count(array_filter($state['submissions'], static fn ($s) => $s['instruments'][0]['skill'] >= 15)) >= 3, 'drei brauchbare');
    // Zeit vergeht: es kommen neue, alte verfallen nicht vor dem Verfallsdatum.
    Clock::advance(86400);
    $later = $p->get('state')['submissions'];
    check(count($later) >= 5 + 3, 'nach einem Tag etwa acht neue: ' . count($later));
    // Unterschreiben (die fünf geöffneten vom Anfang).
    $first = array_values(array_filter($later, static fn ($s) => $s['state'] === 'open'))[0];
    $money = $p->get('state')['label']['money'];
    $res = $p->post('submissionSign', ['submissionId' => $first['submissionId']]);
    checkEquals($money - $first['price'], $res['state']['label']['money']);
    check(in_array($res['musicianId'], array_column($res['state']['musicians'], 'id'), true), 'der Musiker ist im Label');
    checkApiError(fn () => $p->post('submissionSign', ['submissionId' => $first['submissionId']]), 'nicht mehr');
    // Wegwerfen.
    $second = array_values(array_filter($res['state']['submissions'], static fn ($s) => $s['state'] === 'open'))[0];
    $res = $p->post('submissionDrop', ['submissionId' => $second['submissionId']]);
    check(!in_array($second['submissionId'], array_column($res['state']['submissions'], 'submissionId'), true));
    // Ablauf nach dem Verfallsdatum.
    Clock::advance(Balance::SUBMISSIONS['ttl'] + 10);
    $old = array_filter($p->get('state')['submissions'], static fn ($s) => $s['expiresAt'] <= Clock::now());
    checkEquals([], array_values($old));
    // Neue ungehörte Kassetten zum Ausprobieren.
    $before = count($p->get('state')['submissions']);
    $res = $p->post('submissionsTest', ['closed' => true]);
    checkEquals($before + 7, count($res['state']['submissions']), 'sechs Musiker und eine Band');
    // Testbewerber: alle vier Können-Stufen, darunter Bekanntheit.
    $res = $p->post('submissionsTest');
    $open = array_values(array_filter($res['state']['submissions'], static fn ($s) => $s['state'] === 'open'));
    $levels = array_unique(array_map(static fn ($s) => $s['level'], array_filter($open, static fn ($s) => $s['kind'] === 'solo')));
    foreach ([0, 1, 2, 3] as $l) {
        check(in_array($l, $levels, true), "Stufe $l kommt vor");
    }
    check(count(array_filter($open, static fn ($s) => ($s['fameLevel'] ?? 0) >= 1)) >= 1, 'bekannte Bewerber');
    foreach ($open as $s) {
        foreach ($s['members'] ?? [$s] as $card) {
            check(count($card['abilities'] ?? []) === 1, 'jeder hat genau eine Spieltechnik');
        }
    }
});

test('Einsendungen: Menge und Güte wachsen mit dem Erfolg des Labels', function () {
    // Fließend: 8 + 1,8 × √Erfolg, ohne Stufen und ohne Obergrenze.
    checkEquals(8.0, Signed\Game\Submissions::perDay(0));
    checkEquals(8.0 + 1.8 * 20, Signed\Game\Submissions::perDay(400));
    check(Signed\Game\Submissions::perDay(20000) > Signed\Game\Submissions::perDay(5000), 'wächst immer weiter');
    check(Signed\Game\Submissions::goodShare(5000) > Signed\Game\Submissions::goodShare(0) * 4);
    $cheap = Signed\Game\Submissions::price(['skill1' => 25.0, 'skill2' => null, 'fame' => 0.0]);
    $star = Signed\Game\Submissions::price(['skill1' => 80.0, 'skill2' => null, 'fame' => 100.0]);
    check($cheap >= 60 && $cheap <= 90, "Können 25 etwa 70 ₲: $cheap");
    check($star > 4 * $cheap, 'Bekannt und gut ist teurer');
});

test('Ohne Instrument lernt man bei der Probe nichts, steht aber dabei', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Leer');
    $band = foundBand($p);
    equipBand($p, $db, $band);
    $ids = array_column($db->all('SELECT id FROM musicians WHERE band_id = ? ORDER BY id', [$band['id']]), 'id');
    $db->exec('UPDATE musicians SET skill1 = 20 WHERE band_id = ?', [$band['id']]);
    // Dem ersten Musiker das Instrument wegnehmen.
    $gearId = (int) $db->value('SELECT id FROM gear WHERE musician_id = ?', [$ids[0]]);
    $p->post('gearAssign', ['gearId' => $gearId, 'musicianId' => null]);
    $state = $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'rehearsal'])['state'];
    $first = array_values(array_filter($state['musicians'], static fn ($m) => $m['id'] === $ids[0]))[0];
    checkEquals(0.0, (float) $first['gains']['rehearsal'], 'kein Zuwachs ohne Instrument');
    Clock::advance(600);
    $p->get('state');
    checkEquals(20.0, (float) $db->value('SELECT skill1 FROM musicians WHERE id = ?', [$ids[0]]), 'ohne Instrument bleibt das Können');
    check((float) $db->value('SELECT skill1 FROM musicians WHERE id = ?', [$ids[1]]) > 20.0, 'die anderen lernen');
});

test('Einsendungen: Bands bewerben sich, man nimmt alle oder einzelne, sie kennen sich schon', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Gruppe');
    $p->get('state');
    $res = $p->post('submissionsTest');
    $bands = array_values(array_filter($res['state']['submissions'], static fn ($s) => $s['state'] === 'open' && $s['kind'] === 'band'));
    check(count($bands) >= 2, 'gesichtete Bands im Briefkasten');
    $band = $bands[0];
    check(count($band['members']) >= 2 && count($band['members']) <= 4, 'zwei bis vier Mitglieder');
    checkEquals(array_sum(array_column($band['members'], 'price')), $band['price'], 'Preis = Summe');
    // Alle aufnehmen: Sie kennen sich (Beziehung von Anfang an) und bleiben gut gelaunt.
    $before = count($res['state']['musicians']);
    $res = $p->post('submissionSign', ['submissionId' => $band['submissionId']]);
    checkEquals($before + count($band['members']), count($res['state']['musicians']));
    $ids = array_slice(array_column($res['state']['musicians'], 'id'), -count($band['members']));
    $pair = $db->one('SELECT value FROM relations WHERE a_id = ? AND b_id = ?', [min($ids[0], $ids[1]), max($ids[0], $ids[1])]);
    check($pair !== null && $pair['value'] > 0, 'Beziehung startet nicht bei 0');
    check((float) $db->value('SELECT wellbeing FROM musicians WHERE id = ?', [$ids[0]]) >= Balance::MOOD['start'] - 0.01, 'alle genommen: niemand gekränkt');
    // Nur einen aus einer anderen Band: Die anderen ziehen weiter, der Aufgenommene ist gekränkt.
    $other = $bands[1];
    $res = $p->post('submissionSign', ['submissionId' => $other['submissionId'], 'members' => [0]]);
    check(!in_array($other['submissionId'], array_column($res['state']['submissions'], 'submissionId'), true), 'Rest zieht weiter');
    $solo = end($res['state']['musicians']);
    check((float) $db->value('SELECT wellbeing FROM musicians WHERE id = ?', [$solo['id']]) < Balance::MOOD['start'] - 5, 'gekränkt');
    checkApiError(fn () => $p->post('submissionSign', ['submissionId' => $band['submissionId']]), 'nicht mehr');
});

test('Einsendungen: eigene Reihenfolge merken', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Ordnung');
    $ids = array_column($p->get('state')['submissions'], 'submissionId');
    $p->post('submissionOrder', ['order' => array_reverse($ids)]);
    checkEquals(array_reverse($ids), $p->get('state')['label']['subOrder']);
});

test('Einsendungen: Verträglichkeit mit den eigenen Musikern, erst nach dem Sichten', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Passung');
    $closed = $p->get('state')['submissions'][0];
    checkApiError(fn () => $p->post('submissionCompat', ['submissionId' => $closed['submissionId']]), 'sichten');
    $res = $p->post('submissionsTest');
    $open = array_values(array_filter($res['state']['submissions'], static fn ($s) => $s['state'] === 'open' && $s['kind'] === 'solo'))[0];
    $compat = $p->post('submissionCompat', ['submissionId' => $open['submissionId']])['compat'];
    checkEquals(count($res['state']['musicians']), count($compat), 'ein Eintrag je Musiker des Labels');
    check($compat[0]['target'] >= end($compat)['target'], 'beste zuerst');
    check($compat[0]['target'] <= 10 && end($compat)['target'] >= -10, 'Beziehung liegt zwischen −10 und +10');
});

test('Solokünstler: Wer unterschreibt, steht sofort als Band mit einem Mitglied da; Bands unterschreiben als Band', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Sofort');
    $p->get('state');
    $res = $p->post('submissionsTest');
    $solo = array_values(array_filter($res['state']['submissions'], static fn ($s) => $s['state'] === 'open' && $s['kind'] === 'solo'))[0];
    $before = count($res['state']['bands']);
    $res = $p->post('submissionSign', ['submissionId' => $solo['submissionId']]);
    checkEquals($before + 1, count($res['state']['bands']), 'sofort eine Solo-Band');
    $new = array_values(array_filter($res['state']['bands'], static fn ($b) => $b['memberIds'] === [$res['musicianId']]))[0];
    check(str_contains($new['name'], $solo['lastName']), 'benannt nach dem Künstler');
    foreach ($res['state']['musicians'] as $m) {
        check($m['bandId'] !== null, 'niemand steht ohne Band da');
    }
    // Eine Band wird als Band gegründet.
    $band = array_values(array_filter($res['state']['submissions'], static fn ($s) => $s['state'] === 'open' && $s['kind'] === 'band'))[0];
    $count = count($res['state']['bands']);
    $res = $p->post('submissionSign', ['submissionId' => $band['submissionId']]);
    $founded = array_values(array_filter($res['state']['bands'], static fn ($b) => $b['name'] === $band['bandName']));
    checkEquals(1, count($founded), 'die Band ist gegründet');
    checkEquals(count($band['members']), count($founded[0]['memberIds']));
    checkEquals($count + 1, count($res['state']['bands']));
});

test('Solokünstler wechseln in eine Band: Die leere Solo-Band verschwindet; wer schon etwas aufgebaut hat, bleibt', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Wechsler');
    $band = foundBand($p, 'Hauptband');
    $res = $p->post('submissionsTest');
    $solo = array_values(array_filter($res['state']['submissions'], static fn ($s) => $s['state'] === 'open' && $s['kind'] === 'solo'))[0];
    $musicianId = $p->post('submissionSign', ['submissionId' => $solo['submissionId']])['musicianId'];
    $state = $p->post('bandAddMember', ['bandId' => $band['id'], 'musicianId' => $musicianId, 'slot' => 1])['state'];
    $mine = bandById($state, $band['id']);
    check(in_array($musicianId, $mine['memberIds'], true), 'in der Band');
    check(count(array_filter($state['bands'], static fn ($b) => $b['memberIds'] === [$musicianId])) === 0, 'Solo-Band ist weg');
    // Eine Solo-Band mit Geschichte bleibt bestehen.
    $res = $p->post('submissionsTest');
    $solo2 = array_values(array_filter($res['state']['submissions'], static fn ($s) => $s['state'] === 'open' && $s['kind'] === 'solo'))[0];
    $id2 = $p->post('submissionSign', ['submissionId' => $solo2['submissionId']])['musicianId'];
    $db->exec('UPDATE bands SET released = 1 WHERE id = (SELECT band_id FROM musicians WHERE id = ?)', [$id2]);
    checkApiError(fn () => $p->post('bandAddMember', ['bandId' => $band['id'], 'musicianId' => $id2, 'slot' => 1]), 'aufgebaut');
});

test('Zusammentun: Kandidaten nach Nutzen geordnet, Unmögliches zuletzt, Bands lassen sich verschmelzen', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Zusammen');
    $band = foundBand($p, 'Kern');
    $res = $p->post('submissionsTest');
    // Zwei Solokünstler dazuholen lassen.
    foreach (array_slice(array_values(array_filter($res['state']['submissions'], static fn ($s) => $s['state'] === 'open' && $s['kind'] === 'solo')), 0, 2) as $s) {
        $p->post('submissionSign', ['submissionId' => $s['submissionId']]);
    }
    $list = $p->post('bandJoinCandidates', ['bandId' => $band['id']])['candidates'];
    checkEquals(2, count($list));
    foreach ($list as $c) {
        check(isset($c['effects']) && array_key_exists('blocked', $c), 'Angaben vollständig');
    }
    // Geordnet nach Ausmaß der Veränderung: größter Gewinn zuerst.
    $scores = array_column($list, 'score');
    $sorted = $scores;
    rsort($sorted);
    check($scores === $sorted, 'nach Gewinn sortiert');
    foreach ($list as $c) {
        check(in_array($c['rating'], ['better', 'neutral', 'worse'], true), 'Bewertung');
    }
    // Zusammentun mit Namenswahl: Wer den Namen aufgibt, verliert den Ruhm daran, seine Platten bleiben bei der ehemaligen Band.
    $other = $list[0];
    $db->exec('UPDATE bands SET released = 1, records = 1, fame_own = 30 WHERE id = ?', [$other['bandId']]);
    $db->exec("INSERT INTO records (label_id, band_id, title, songs_total, songs_done, song_results, status, created_at, finished_at) VALUES (?, ?, 'Alte Platte', 3, 3, '[]', 'done', ?, ?)", [$p->get('state')['label']['id'] ?? 1, $other['bandId'], Clock::now(), Clock::now()]);
    // Ein Einzelmusiker hat keinen Namen, den man behalten könnte.
    checkApiError(fn () => $p->post('bandMerge', ['bandId' => $band['id'], 'otherId' => $other['bandId'], 'keepName' => 'b']), 'Namen');
    $res = $p->post('bandMerge', ['bandId' => $band['id'], 'otherId' => $other['bandId'], 'keepName' => 'new', 'newName' => 'Zusammen Neu']);
    checkEquals($band['id'], $res['bandId'], 'die Zeile dieser Band besteht weiter');
    $kept = bandById($res['state'], $band['id']);
    checkEquals('Zusammen Neu', $kept['name']);
    check(in_array($other['memberIds'][0], $kept['memberIds'], true), 'das neue Mitglied ist dabei');
    checkEquals(0.0, (float) $kept['fameOwn'], 'mit neuem Namen ist der Ruhm am Namen weg');
    check(count(array_filter($res['state']['bands'], static fn ($b) => $b['id'] === $other['bandId'])) === 0, 'die andere Band steht nicht mehr in der Leiste');
    $former = $p->post('wikiBand', ['id' => $other['bandId']]);
    checkEquals('merged', $former['reason']);
    checkEquals(30.0, $former['fame']);
    checkEquals(1, count($former['records']), 'ihre Platte bleibt bei ihr');
    $joined = $p->post('wikiSignie', ['id' => $other['memberIds'][0]]);
    checkEquals('current', $joined['status']);
    check(count($joined['events']) >= 1, 'der Wechsel steht in seiner Laufbahn');
    // Tantiemen: nur bis Platz 50 der Charts
    check(Balance::ROYALTY_RANKS === 50, 'Tantiemen enden nach Platz 50');
});

test('Zusammentun: Wer zwei Instrumente spielt, sagt, welches er in der neuen Band spielt', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Zweifach');
    $band = foundBand($p, 'Kern');
    $res = $p->post('submissionsTest');
    $s = array_values(array_filter($res['state']['submissions'], static fn ($s) => $s['state'] === 'open' && $s['kind'] === 'solo'))[0];
    $p->post('submissionSign', ['submissionId' => $s['submissionId']]);
    $solo = array_values(array_filter($p->get('state')['bands'], static fn ($b) => count($b['memberIds']) === 1))[0];
    $id = $solo['memberIds'][0];
    $db->exec("UPDATE musicians SET instr1 = 'keys', instr2 = 'bass', skill2 = 30 WHERE id = ?", [$id]);
    $res = $p->post('bandMerge', ['bandId' => $band['id'], 'otherId' => $solo['id'], 'keepName' => 'a', 'slot' => 2]);
    checkEquals(2, (int) $db->value('SELECT band_slot FROM musicians WHERE id = ?', [$id]), 'spielt das zweite Instrument');
});

test('Künstlername: Wunsch ab genug Bekanntheit (Diven früher), einer von drei Vorschlägen, macht froh, bleibt in der Band', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Kuenstler');
    $band = foundBand($p, 'Kern');
    $res = $p->post('submissionsTest');
    $s = array_values(array_filter($res['state']['submissions'], static fn ($s) => $s['state'] === 'open' && $s['kind'] === 'solo'))[0];
    $p->post('submissionSign', ['submissionId' => $s['submissionId']]);
    $solo = array_values(array_filter($p->get('state')['bands'], static fn ($b) => count($b['memberIds']) === 1))[0];
    $id = $solo['memberIds'][0];
    $wants = static fn ($st) => in_array('stageName', array_column(array_values(array_filter($st['bands'], static fn ($b) => in_array($id, $b['memberIds'], true)))[0]['mood']['wishes'] ?? [], 'action'), true);
    // Solo frei umbenennen geht nicht
    checkApiError(fn () => $p->post('bandRename', ['bandId' => $solo['id'], 'name' => 'Irgendwas']), 'Künstlernamen');
    // Unbekannt: kein Wunsch – und wer gelassen ist, will auch berühmt keinen
    $db->exec("UPDATE musicians SET fame = 1, personality = ? WHERE id = ?", ['["diva"]', $id]);
    check(!$wants($p->get('state')), 'unbekannt: kein Wunsch');
    $db->exec("UPDATE musicians SET fame = 500, personality = ? WHERE id = ?", ['["gelassen"]', $id]);
    check(!$wants($p->get('state')), 'gelassen: kein Wunsch');
    // Eine Diva wünscht sich früh einen (das erfährt man über den Manager)
    giveManager($db, $solo['id']);
    $db->exec("UPDATE musicians SET fame = ?, personality = ?, wellbeing = 50 WHERE id = ?", [Balance::STAGE_NAME['want']['diva'] + 1, '["diva"]', $id]);
    check($wants($p->get('state')), 'Diva: Wunsch nach Künstlernamen');
    $options = $p->post('stageNameOptions', ['musicianId' => $id])['options'];
    checkEquals(Balance::STAGE_NAME['choices'], count($options));
    checkEquals($options, $p->post('stageNameOptions', ['musicianId' => $id])['options'], 'dieselben Vorschläge');
    checkApiError(fn () => $p->post('stageNameGive', ['musicianId' => $id, 'name' => 'Selbst ausgedacht']), 'vorgeschlagen');
    $res = $p->post('stageNameGive', ['musicianId' => $id, 'name' => $options[0]]);
    $m = array_values(array_filter($res['state']['musicians'], static fn ($x) => $x['id'] === $id))[0];
    checkEquals($options[0], $m['stageName']);
    check((float) $db->value('SELECT wellbeing FROM musicians WHERE id = ?', [$id]) >= 50 + Balance::STAGE_NAME['mood'] - 0.01, 'freut sich');
    checkEquals($options[0], array_values(array_filter($res['state']['bands'], static fn ($b) => $b['memberIds'] === [$id]))[0]['name'], 'Solo-Band heißt so');
    check(!$wants($res['state']), 'Wunsch erfüllt');
    // In die Band und wieder allein: Der Name bleibt
    $res = $p->post('bandMerge', ['bandId' => $band['id'], 'otherId' => $solo['id'], 'keepName' => 'a', 'slot' => 1]);
    $res = $p->post('bandRemoveMember', ['bandId' => $band['id'], 'musicianId' => $id]);
    checkEquals($options[0], array_values(array_filter($res['state']['bands'], static fn ($b) => $b['memberIds'] === [$id]))[0]['name']);
});

test('WikiSignia: Wer entlassen wurde, behält seinen Artikel', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Wiki');
    $band = foundBand($p);
    $id = $band['memberIds'][0];
    $p->post('bandRemoveMember', ['bandId' => $band['id'], 'musicianId' => $id]);
    $p->post('musicianRelease', ['musicianId' => $id]);
    $index = wikiOwn($p);
    $entry = array_values(array_filter($index['signies'], static fn ($s) => $s['id'] === $id));
    checkEquals(1, count($entry));
    checkEquals('former', $entry[0]['status']);
    $article = $p->post('wikiSignie', ['id' => $id]);
    checkEquals('former', $article['status']);
    checkEquals('released', $article['reason']);
    check(count($article['bands']) >= 1, 'seine Band steht im Artikel');
    // Auch andere Labels können den Artikel lesen; er nennt das frühere Label
    $other = registerPlayer($db, 'Wiki2');
    $seen = $other->post('wikiSignie', ['id' => $id]);
    checkEquals('former', $seen['status']);
    checkEquals(1, count($seen['formerLabels']));
    checkApiError(fn () => $other->post('wikiSignie', ['id' => 999999]), 'gibt es nicht');
});

test('Zusammentun: ein verbundener Name behält die Hälfte des Ruhms beider Namen, Namen ehemaliger Bands sind nur mit Ruhm geschützt', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Verbinder');
    $band = foundBand($p, 'Kern');
    $res = $p->post('submissionsTest');
    $solo = array_values(array_filter($res['state']['submissions'], static fn ($s) => $s['state'] === 'open' && $s['kind'] === 'solo'))[0];
    $id = $p->post('submissionSign', ['submissionId' => $solo['submissionId']])['musicianId'];
    // Ein Instrument, das in der Band noch fehlt (sonst passt die Besetzung nicht).
    $db->exec("UPDATE musicians SET instr1 = 'keys', instr2 = NULL, skill2 = NULL WHERE id = ?", [$id]);
    $other = $p->post('bandJoinCandidates', ['bandId' => $band['id']])['candidates'][0];
    check(in_array('combo', $other['nameOptions'], true), 'Verbinden wird angeboten');
    $db->exec('UPDATE bands SET fame_own = 40 WHERE id = ?', [$band['id']]);
    $db->exec('UPDATE bands SET fame_own = 10 WHERE id = ?', [$other['bandId']]);
    $res = $p->post('bandMerge', ['bandId' => $band['id'], 'otherId' => $other['bandId'], 'keepName' => 'combo', 'newName' => 'Solo & Kern']);
    $kept = bandById($res['state'], $band['id']);
    checkEquals('Solo & Kern', $kept['name']);
    checkEquals(25.0, (float) $kept['fameOwn'], 'die Hälfte von 40 + 10');
    // Ehemalige Band: Name nur geschützt, wenn er etwas galt
    $db->exec("UPDATE bands SET retired_at = 1, fame_final = 5 WHERE id = ?", [$band['id']]);
    check(!Signed\Game\Names::bandTaken($db, 'Solo & Kern'), 'ohne Ruhm frei');
    $db->exec("UPDATE bands SET fame_final = 50 WHERE id = ?", [$band['id']]);
    check(Signed\Game\Names::bandTaken($db, 'Solo & Kern'), 'mit Ruhm geschützt');
});

/** WikiSignia-Verzeichnis des eigenen Labels. */
function wikiOwn($p): array
{
    $own = array_values(array_filter($p->post('wikiIndex')['labels'], static fn ($l) => $l['own']))[0];
    return $p->post('wikiLabel', ['id' => $own['id']]);
}

test('WikiSignia: Labels als oberste Ebene, fremde Artikel mit Label, ohne Geld-Einträge, Suche über alle', function () {
    $db = freshDb();
    $a = registerPlayer($db, 'WikiA');
    $b = registerPlayer($db, 'WikiB');
    $bandA = foundBand($a, 'Alpha Wiki');
    $labels = $b->post('wikiIndex')['labels'];
    check(count($labels) >= 2 && $labels[0]['own'], 'das eigene Label steht zuerst');
    // Eine fremde Band ist lesbar und nennt ihr Label
    $article = $b->post('wikiBand', ['id' => $bandA['id']]);
    checkEquals('Alpha Wiki', $article['name']);
    check($article['label']['own'] === false && $article['label']['name'] !== '', 'Label steht dabei');
    foreach ($article['events'] as $e) {
        check(in_array($e['kind'], ['lineup', 'band', 'record', 'level'], true), 'nur öffentliche Einträge');
    }
    // Ein fremder Künstler ebenfalls
    $musician = $article['members'][0];
    $sig = $b->post('wikiSignie', ['id' => $musician['id']]);
    check($sig['label']['own'] === false, 'Label des Künstlers');
    // Suche über alle Labels
    $found = $b->post('wikiSearch', ['q' => 'alpha wi']);
    check(count($found['bands']) >= 1 && $found['bands'][0]['label'] !== '', 'gefunden mit Label');
    $byName = $b->post('wikiSearch', ['q' => mb_strtolower($musician['firstName'])]);
    check(count($byName['signies']) >= 1, 'Künstler gefunden');
});

test('Music Store: Grundausstattung – jedes graue Modell immer da, beliebig oft; Angebot meist grün, selten blau, ganz selten gold', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Grund');
    $store = $p->get('state')['store'];
    $commons = array_keys(array_filter(Catalog::GEAR, fn ($g) => $g['rarity'] === 'common'));
    checkEquals($commons, array_column($store['basics'], 'model'), 'alle gewöhnlichen Modelle');
    $money = $p->get('state')['label']['money'];
    $p->post('storeBuyBasic', ['model' => $commons[0]]);
    $res = $p->post('storeBuyBasic', ['model' => $commons[0]]);
    checkEquals($money - 2 * Balance::STORE['price']['common'], $res['state']['label']['money'], 'zweimal dasselbe geht');
    checkEquals(0, $res['gear']['tier']);
    checkApiError(fn () => $p->post('storeBuyBasic', ['model' => 'gtr_master']), 'nicht');
    // Über ein Jahr: Gold etwa einmal die Woche, kein Grau im Angebot, je Art höchstens eins
    $gold = 0;
    $blue = 0;
    for ($day = 0; $day < 365; $day++) {
        $offer = Store::shelf(20000 + $day);
        $rar = array_count_values(array_column($offer, 'rarity'));
        check(!isset($rar['common']), 'kein Grau im Angebot');
        checkEquals(count($offer), count(array_unique(array_column($offer, 'type'))));
        $gold += $rar['legendary'] ?? 0;
        $blue += $rar['epic'] ?? 0;
    }
    check($gold >= 25 && $gold <= 75, 'Gold etwa einmal die Woche: ' . $gold);
    check($blue >= 90 && $blue <= 170, 'Blau in etwa jedem dritten Angebot: ' . $blue);
});

test('Music Store: Angebot für alle gleich, wechselt täglich, immer drei Fächer, Gekauftes hinterlässt ein leeres', function () {
    $db = freshDb();
    $a = registerPlayer($db, 'StoreA');
    $b = registerPlayer($db, 'StoreB');
    $db->exec('UPDATE labels SET money = 100000');
    $shelfA = $a->get('state')['store'];
    $shelfB = $b->get('state')['store'];
    checkEquals(array_column($shelfA['items'], 'model'), array_column($shelfB['items'], 'model'), 'für alle gleich');
    $count = count($shelfA['items']);
    checkEquals(Balance::STORE['offer']['shelf'], count(array_filter($shelfA['items'], fn ($i) => $i['tier'] < 3)), 'immer gleich viele Fächer');
    $types = array_column($shelfA['items'], 'type');
    checkEquals(count($types), count(array_unique($types)), 'je Art höchstens eins');
    // Kaufen: das Fach verschwindet, nochmal geht nicht
    $item = $shelfA['items'][0];
    $money = $a->get('state')['label']['money'];
    $res = $a->post('storeBuy', ['slot' => $item['slot'], 'idx' => $item['idx']]);
    checkEquals($money - $item['price'], $res['state']['label']['money']);
    checkEquals($item['model'], $res['gear']['model']);
    checkEquals($count, count($res['state']['store']['items']), 'das Fach bleibt');
    check($res['state']['store']['items'][0]['bought'], 'leer: gekauft');
    check(!$b->get('state')['store']['items'][0]['bought'], 'beim anderen Label noch da');
    checkApiError(fn () => $a->post('storeBuy', ['slot' => $item['slot'], 'idx' => $item['idx']]), 'schon gekauft');
    // Nach dem Wechsel ist das alte Angebot weg
    Clock::advance($shelfA['endsAt'] - Clock::now() + 60);
    $later = $a->get('state')['store'];
    check($later['slot'] > $shelfA['slot'], 'neues Angebot');
    checkApiError(fn () => $a->post('storeBuy', ['slot' => $shelfA['slot'], 'idx' => $shelfA['items'][1]['idx']]), 'nicht mehr im Angebot');
});

test('Proberaum: eigener Raum je Band und je Solokünstler, Ausbau wirkt aufs Proben', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Raum');
    $db->exec('UPDATE labels SET money = 100000');
    $band = foundBand($p, 'Kellerkinder');
    $state = $p->get('state');
    $b = bandById($state, $band['id']);
    checkEquals(0, $b['room']['level']);
    checkEquals('Bruchbude', $b['room']['name']);
    $gainBefore = mById($state, $b['memberIds'][0])['gains']['rehearsal'];
    $lustBefore = $b['options']['rehearsal']['seconds'];
    $money = $state['label']['money'];
    $state = $p->post('roomUpgrade', ['bandId' => $band['id']])['state'];
    $b = bandById($state, $band['id']);
    checkEquals(1, $b['room']['level']);
    checkEquals($money - Balance::ROOM['levels'][1]['cost'], $state['label']['money']);
    if ($gainBefore > 0) {
        check(mById($state, $b['memberIds'][0])['gains']['rehearsal'] > $gainBefore, 'mehr Können je Probe');
    }
    check($b['options']['rehearsal']['seconds'] >= $lustBefore, 'länger Lust');
    // Jeder Solokünstler hat seine eigene Übungsecke (voller Preis, gilt nur für ihn)
    $p->post('submissionsTest');
    for ($i = 0; $i < 2; $i++) {
        $res = $p->post('submissionsTest');
        $solo = array_values(array_filter($res['state']['submissions'], static fn ($s) => $s['state'] === 'open' && $s['kind'] === 'solo'))[0];
        $p->post('submissionSign', ['submissionId' => $solo['submissionId']]);
    }
    $state = $p->get('state');
    $solos = array_values(array_filter($state['bands'], static fn ($x) => count($x['memberIds']) === 1));
    check(count($solos) >= 2, 'zwei Solokünstler');
    check($solos[0]['room']['solo'], 'eigene Übungsecke');
    checkEquals(Balance::ROOM['levels'][1]['cost'], $solos[0]['room']['next']['cost']);
    $state = $p->post('roomUpgrade', ['bandId' => $solos[0]['id']])['state'];
    checkEquals(1, bandById($state, $solos[0]['id'])['room']['level']);
    checkEquals(0, bandById($state, $solos[1]['id'])['room']['level'], 'die anderen behalten ihre');
    // Ganz ausgebaut geht nicht weiter
    $p->post('roomUpgrade', ['bandId' => $band['id']]);
    $p->post('roomUpgrade', ['bandId' => $band['id']]);
    checkApiError(fn () => $p->post('roomUpgrade', ['bandId' => $band['id']]), 'voll ausgebaut');
});

function mById(array $state, int $id): array
{
    return array_values(array_filter($state['musicians'], static fn ($m) => $m['id'] === $id))[0];
}

test('Auftritt buchen: Orte mit Kosten und Gewinn-Schätzung, Gage liegt in der Spanne, Songs nötig, Ort unter Anspruch ärgert', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Booker');
    $band = foundBand($p, 'Bühnenreif');
    // Ohne Songs geht nichts
    $opts = $p->post('gigOptions', ['bandId' => $band['id']]);
    check($opts['options'][0]['blocked'] !== null, 'ohne Songs gesperrt');
    $db->exec('UPDATE bands SET songs_written = 6 WHERE id = ?', [$band['id']]);
    $opts = $p->post('gigOptions', ['bandId' => $band['id']]);
    $kneipe = $opts['options'][0];
    checkEquals('kneipe', $kneipe['key']);
    checkEquals(null, $kneipe['blocked']);
    check($kneipe['gage'][0] <= $kneipe['gage'][1], 'Spanne');
    checkEquals($kneipe['gage'][0] - $kneipe['cost'], $kneipe['profit'][0]);
    check(count($opts['options']) >= 2, 'auch Orte über dem Anspruch');
    // Eine unbekannte Band verdient am meisten im kleinsten Ort, nicht im großen Saal
    $best = array_reduce($opts['options'], static fn ($m, $o) => $m === null || $o['profit'][1] > $m['profit'][1] ? $o : $m);
    checkEquals('kneipe', $best['key'], 'Unbekannte: Kneipe lohnt am meisten');
    // Bezahlt wird nach dem Auftritt aus der Gage, vorher nur, was sie sicher nicht deckt: auch pleite kann man spielen.
    checkEquals(0, $kneipe['deposit'], 'Kneipe: keine Anzahlung');
    $db->exec('UPDATE labels SET money = 0');
    $money = 0;
    $res = $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'gig', 'venue' => 'kneipe']);
    checkEquals(0, $res['state']['label']['money'], 'nichts vorher');
    Clock::advance($kneipe['songs'] * Balance::SONG_SECONDS + 5);
    $after = $p->get('state');
    $gage = $after['label']['money'] - ($money - $kneipe['cost']);
    check($after['label']['money'] >= 0, 'nie im Minus');
    check((bool) $db->value("SELECT COUNT(*) FROM ledger WHERE text LIKE 'Rechnung:%'"), 'Rechnung im Kontoauszug');
    check($gage > 0, 'Gage kommt an');
    checkEquals(30, Signed\Game\Gigs::deposit(100, 100, 1.0), 'schlechtester Abend bringt 70 %');
    checkEquals(65, Signed\Game\Gigs::deposit(100, 100, 0.5), 'nur was der schlechteste Abend nicht deckt');
    check($gage >= $kneipe['gage'][0] * 0.6 && $gage <= $kneipe['gage'][1] * 1.4, 'Gage etwa in der Schätzung');
    // Ort unter dem Anspruch: Stimmung sinkt
    $db->exec('UPDATE bands SET fame_own = 200 WHERE id = ?', [$band['id']]);
    $before = array_sum(array_map(static fn ($m) => (float) $m['wellbeing'], $db->all('SELECT wellbeing FROM musicians WHERE band_id = ?', [$band['id']])));
    $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'gig', 'venue' => 'kneipe']);
    $afterMood = array_sum(array_map(static fn ($m) => (float) $m['wellbeing'], $db->all('SELECT wellbeing FROM musicians WHERE band_id = ?', [$band['id']])));
    check($afterMood < $before, 'unter der Würde ärgert');
});

test('Verborgene Charakterzüge und Bedürfnisse: ohne Manager nur die Stimmung, wer rot ist, sagt laut, was er will; der Manager kennt alles', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Verborgen');
    $band = foundBand($p);
    $ids = array_column($db->all('SELECT id FROM musicians WHERE band_id = ?', [$band['id']]), 'id');
    $db->exec("UPDATE musicians SET personality = '[\"gelassen\"]', known_traits = '[]', wellbeing = 70 WHERE band_id = ?", [$band['id']]);
    $db->exec('UPDATE musicians SET personality = ?, wellbeing = 45 WHERE id = ?', [json_encode(['rampensau', 'teamplayer']), $ids[0]]);
    $find = static fn ($state, $id) => array_values(array_filter($state['musicians'], static fn ($x) => $x['id'] === $id))[0];
    $state = $p->get('state');
    checkEquals([], $find($state, $ids[0])['personality'], 'noch unbekannt');
    checkEquals(2, $find($state, $ids[0])['hiddenTraits']);
    $b = bandById($state, $band['id']);
    checkEquals(false, $b['managed']);
    checkEquals([], $b['mood']['wishes'], 'gelb, aber ohne Manager kein Wunsch');
    checkEquals([], (array) $b['mood']['why'], 'ohne Manager kein Warum');
    checkEquals([], $b['managerNotes']);
    // Wirken allein verrät nichts mehr (Straßenmusik, Zeit vergeht).
    $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'busking']);
    Clock::advance(2 * 86400);
    // Ohne die Stimmung nachzurechnen (sonst würde sein offener Wunsch ihn mit der Zeit rot und laut machen, siehe Balance::NEEDS).
    $db->exec('UPDATE musicians SET wellbeing = 45 WHERE id = ?', [$ids[0]]);
    $db->exec('UPDATE bands SET mood_at = ? WHERE id = ?', [Clock::now() - 60, $band['id']]);
    checkEquals([], $find($p->get('state'), $ids[0])['personality'], 'auch nach zwei Tagen unbekannt');
    // Rot: sagt laut, was er will, und man erfährt den Zug dahinter.
    $db->exec('UPDATE musicians SET wellbeing = 10 WHERE id = ?', [$ids[0]]);
    Clock::advance(60);
    $state = $p->get('state');
    checkEquals(['rampensau'], $find($state, $ids[0])['personality'], 'rot: der Zug hinter dem Wunsch');
    $wishes = bandById($state, $band['id'])['mood']['wishes'];
    checkEquals([$ids[0]], array_values(array_unique(array_column($wishes, 'musicianId'))), 'nur der Rote sagt etwas');
    check((bool) $db->value("SELECT COUNT(*) FROM history WHERE kind = 'trait' AND text LIKE '%laut%'"), 'Nachricht fürs Label');
    // Mit Manager: Was wirkt, gibt er weiter (Straßenmusik gemocht: extrovertiert), dazu alle Wünsche, Warum und Notizen.
    giveManager($db, $band['id']);
    $db->exec('UPDATE musicians SET wellbeing = 70 WHERE id = ?', [$ids[0]]);
    $db->exec("UPDATE musicians SET personality = '[\"teamplayer\", \"rampensau\"]', known_traits = '[]' WHERE id = ?", [$ids[0]]);
    $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'busking']);
    Clock::advance(600);
    $state = $p->get('state');
    checkEquals(['rampensau'], $find($state, $ids[0])['personality'], 'mit Manager: zeigt sich beim Wirken');
    check((bool) $db->value("SELECT COUNT(*) FROM history WHERE kind = 'trait' AND text LIKE '%erzählt%'"), 'der Manager erzählt es');
    Clock::advance(Balance::TRAIT_REVEAL['after']);
    $db->exec('UPDATE musicians SET wellbeing = 45 WHERE id = ?', [$ids[0]]);
    $state = $p->get('state');
    foreach ($ids as $id) {
        checkEquals(0, $find($state, $id)['hiddenTraits'], 'nach einem Tag kennt er alle Züge');
    }
    $b = bandById($state, $band['id']);
    checkEquals(true, $b['managed']);
    check(isset(((array) $b['mood']['why'])[$ids[0]]), 'Warum mit Manager');
    check(in_array($ids[0], array_column($b['mood']['wishes'], 'musicianId'), true), 'gelber Wunsch mit Manager');
    check((bool) array_filter($b['managerNotes'], static fn ($n) => str_contains($n['text'], '„')), 'Notiz mit Wunsch');
    // Einsendungen zeigen keine Züge
    foreach ($state['submissions'] as $s) {
        if (($s['state'] ?? '') === 'open' && ($s['kind'] ?? '') === 'solo') {
            checkEquals([], $s['personality']);
        }
    }
});

test('Auszeit: kostet je Mitglied, die Band ist weg, danach geht es allen besser, auch aus dem Roten; Gelassene erholen sich mehr', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Ferien');
    $band = foundBand($p);
    $ids = $band['memberIds'];
    $db->exec('UPDATE labels SET money = 1000');
    $db->exec("UPDATE musicians SET wellbeing = 20, personality = '[\"diva\"]' WHERE band_id = ?", [$band['id']]);
    $db->exec("UPDATE musicians SET personality = '[\"gelassen\"]' WHERE id = ?", [$ids[0]]);
    checkApiError(fn () => $p->post('bandRetreat', ['bandId' => $band['id'], 'option' => 'kreuzfahrt']), 'gibt es nicht');
    $res = $p->post('bandRetreat', ['bandId' => $band['id'], 'option' => 'ausflug']);
    checkEquals(1000 - Balance::RETREAT['options']['ausflug']['perMember'] * count($ids), $res['state']['label']['money']);
    checkApiError(fn () => $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'rehearsal']), 'beschäftigt');
    $before = array_column($db->all('SELECT id, wellbeing FROM musicians WHERE band_id = ?', [$band['id']]), 'wellbeing', 'id');
    Clock::advance(Balance::RETREAT['options']['ausflug']['seconds'] + 5);
    $p->get('state');
    $after = array_column($db->all('SELECT id, wellbeing FROM musicians WHERE band_id = ?', [$band['id']]), 'wellbeing', 'id');
    $gain = static fn ($id) => (float) $after[$id] - (float) $before[$id];
    check($gain($ids[1]) >= 5, 'erholt: ' . $gain($ids[1]));
    check($gain($ids[0]) > $gain($ids[1]) + 2, 'Gelassene erholen sich mehr');
});

test('Manager-Notizen: besseres Instrument, Streit, lange ohne Bühne, bereit fürs Studio; eine Band oder bis zu vier Solokünstler', function () {
    $band = ['name' => 'Testband', 'songs_written' => 4.0, 'records' => 0];
    $members = [
        ['id' => 1, 'first_name' => 'Ana', 'stage_name' => null, 'band_slot' => 1, 'instr1' => 'guitar', 'instr2' => null, 'skill1' => 70.0, 'skill2' => null],
        ['id' => 2, 'first_name' => 'Ben', 'stage_name' => null, 'band_slot' => 1, 'instr1' => 'bass', 'instr2' => null, 'skill1' => 20.0, 'skill2' => null],
    ];
    $gear = [1 => ['guitar' => ['key' => 'gtr_school', 'name' => 'Einsteiger-E-Gitarre']]];
    $notes = array_column(Signed\Game\Managers::notes($band, $members, $gear, ['1-2' => -5.0], [], 30.0), 'text');
    check((bool) array_filter($notes, static fn ($t) => str_contains($t, 'Ana spielt inzwischen besser')), 'besseres Instrument');
    check((bool) array_filter($notes, static fn ($t) => str_contains($t, 'Ana und Ben verstehen sich')), 'Streit');
    check((bool) array_filter($notes, static fn ($t) => str_contains($t, 'nicht mehr auf der Bühne')), 'lange ohne Bühne');
    check((bool) array_filter($notes, static fn ($t) => str_contains($t, 'bereit fürs Studio')), 'Studio');
    checkEquals([], array_column(Signed\Game\Managers::notes(['songs_written' => 1.0] + $band, $members, [], ['1-2' => 2.0], [], 1.0), 'text'), 'nichts zu sagen');

    $db = freshDb();
    $p = registerPlayer($db, 'Kapazitaet');
    $first = foundBand($p);
    $manager = giveManager($db);
    $p->post('bandSetManager', ['bandId' => $first['id'], 'managerId' => $manager]);
    // Solokünstler dazu: Ein Manager mit Band hat keine Zeit mehr.
    $solos = [];
    for ($i = 0; $i < 5; $i++) {
        $res = $p->post('submissionsTest');
        $s = array_values(array_filter($res['state']['submissions'], static fn ($s) => $s['state'] === 'open' && $s['kind'] === 'solo'))[0];
        $db->exec('UPDATE labels SET money = 100000');
        $p->post('submissionSign', ['submissionId' => $s['submissionId']]);
        $solos = array_values(array_filter($p->get('state')['bands'], static fn ($b) => count($b['memberIds']) === 1));
    }
    check(count($solos) >= 5, 'fünf Solokünstler');
    checkApiError(fn () => $p->post('bandSetManager', ['bandId' => $solos[0]['id'], 'managerId' => $manager]), 'eine Band');
    $p->post('bandSetManager', ['bandId' => $first['id'], 'managerId' => null]);
    for ($i = 0; $i < 4; $i++) {
        $p->post('bandSetManager', ['bandId' => $solos[$i]['id'], 'managerId' => $manager]);
    }
    checkApiError(fn () => $p->post('bandSetManager', ['bandId' => $solos[4]['id'], 'managerId' => $manager]), 'Solokünstler');
    checkApiError(fn () => $p->post('bandSetManager', ['bandId' => $first['id'], 'managerId' => $manager]), 'für sich allein');
});

test('Posteingang: Interview-Anfragen passen zur Bekanntheit, bringen einen festen Anteil ohne Bremse, der Musiker ist kurz weg', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Presse');
    $band = foundBand($p);
    // Nach der eigenen Bekanntheit: Unbekannte selten, Bekannte oft, egal wie viele andere im Label sind.
    checkEquals(0.1, Inbox::interviewRate(['fame' => 0.0]));
    check(Inbox::interviewRate(['fame' => 600.0]) > 10 * Inbox::interviewRate(['fame' => 0.0]), 'Bekannte viel öfter');
    checkEquals([1, 2, 3, 4], array_map(static fn ($f) => Inbox::interviewMax(['fame' => $f]), [0.0, 30.0, 200.0, 900.0]));
    $db->exec('UPDATE musicians SET fame = 150 WHERE band_id = ?', [$band['id']]);
    $p->get('state');
    // Sie kommen zufällig verteilt: zwei Tage warten.
    Clock::advance(86400);
    $p->get('state');
    Clock::advance(86400);
    $p->get('state');
    unpackAll($db);
    $state = $p->get('state');
    $interviews = array_values(array_filter($state['mail'], static fn ($m) => $m['kind'] === 'interview'));
    check(count($interviews) >= 1, 'nach einem Tag gibt es Anfragen');
    $perMusician = array_count_values(array_column($interviews, 'musicianId'));
    check(max($perMusician) <= 3, 'höchstens 1 + Stufe je Musiker');
    foreach (array_count_values(array_map(static fn ($m) => $m['musicianId'] . '|' . $m['medium'], $interviews)) as $n) {
        checkEquals(1, $n, 'nicht zweimal dasselbe Medium');
    }
    // Bekannter Musiker: Anfrage seiner Stufe, Gewinn = Anteil des Weges zur nächsten Stufe.
    $id = (int) $db->value('SELECT id FROM musicians WHERE band_id = ? ORDER BY id LIMIT 1', [$band['id']]);
    $db->exec('UPDATE musicians SET fame = 150 WHERE id = ?', [$id]);
    $state = $p->post('mailTest')['state'];
    $mine = array_values(array_filter($state['mail'], static fn ($m) => $m['kind'] === 'interview' && $m['musicianId'] === $id))[0];
    checkEquals(2, $mine['level']);
    check(in_array($mine['medium'], array_column(Catalog::INTERVIEW_MEDIA[2], 'name'), true), 'Medium der Stufe 2');
    $share = array_column(Catalog::INTERVIEW_MEDIA[2], 'share', 'name')[$mine['medium']];
    checkEquals(round($share * 480, 2), $mine['fame'], 'Anteil am Weg von 120 bis 600');
    $res = $p->post('mailAccept', ['mailId' => $mine['mailId']]);
    checkEquals('interview', $res['activity']['type']);
    check(!in_array($mine['mailId'], array_column($res['state']['mail'], 'mailId'), true), 'Brief ist weg');
    checkApiError(fn () => $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'rehearsal']), 'unterwegs');
    Clock::advance(Balance::INTERVIEW['seconds'][2] + 5);
    $p->get('state');
    $fame = (float) $db->value('SELECT fame FROM musicians WHERE id = ?', [$id]);
    check(abs($fame - (150 + $mine['fame'])) < 0.01, "ohne Bremse gutgeschrieben: $fame");
    // Ablehnen und Ablauf.
    $other = array_values(array_filter($p->get('state')['mail'], static fn ($m) => $m['kind'] === 'interview'))[0];
    $p->post('mailDrop', ['mailId' => $other['mailId']]);
    Clock::advance(Balance::INTERVIEW['ttl'] + 5);
    $left = array_filter($p->get('state')['mail'], static fn ($m) => $m['expiresAt'] <= Clock::now());
    checkEquals([], array_values($left));
});

test('Posteingang: Bewerber werden mit dem Erfolg besser (gesättigt); Assistenz sortiert, hört Kassetten an oder macht Presse', function () {
    check(Inbox::quality(0) === 0.0);
    check(Inbox::quality(300) === 0.5, 'Hälfte bei half');
    check(Inbox::quality(100000) < 1.0, 'oben offen, nie ganz 1');
    // Scouts: bei wenig Erfolg nur die einfachen, bei viel alle Stufen.
    $tiers = static function (float $q): array {
        $out = [];
        for ($i = 0; $i < 200; $i++) {
            $out[Inbox::applicant('scout', $q)['tier']] = true;
        }
        ksort($out);
        return array_keys($out);
    };
    checkEquals([1], $tiers(0.05));
    checkEquals([1, 2, 3], $tiers(0.9));

    $db = freshDb();
    $p = registerPlayer($db, 'Sortiert');
    Clock::advance(2 * 86400);
    $p->get('state');
    unpackAll($db);
    $jobs = array_values(array_filter($p->get('state')['mail'], static fn ($m) => $m['kind'] === 'job'));
    check(in_array('scout', array_column($jobs, 'role'), true), 'Scout gleich zu Beginn');
    check(in_array('intern', array_column($jobs, 'role'), true), 'Praktikum gleich zu Beginn');
    check((bool) array_filter($jobs, static fn ($m) => $m['role'] === 'manager'), 'Manager gleich zu Beginn (günstig)');
    checkEquals(null, $p->get('state')['assistant']);
    $state = $p->post('mailTest')['state'];
    $jobs = array_values(array_filter($state['mail'], static fn ($m) => $m['kind'] === 'job' && $m['role'] === 'assistant'));
    $money = $state['label']['money'];
    $res = $p->post('mailHire', ['mailId' => $jobs[0]['mailId']]);
    checkEquals($money - Balance::ASSISTANT['tiers'][1]['price'], $res['state']['label']['money'], 'einmalig bezahlt');
    checkEquals('none', $res['state']['assistant']['staff'][0]['task'], 'fängt ohne Arbeitsgebiet an');
    checkEquals(['post' => false, 'demos' => false, 'office' => false], $res['state']['assistant']['areas']);
    checkApiError(fn () => $p->post('mailHire', ['mailId' => $jobs[0]['mailId']]), 'nicht mehr');
    // Regeln gleich von Anfang an; was nicht mitkommt, bleibt.
    $p->post('assistRules', ['rules' => ['minLevel' => 2, 'genres' => ['jazz', 'nope'], 'kind' => 'any']]);
    checkEquals(['jazz'], $p->get('state')['assistant']['rules']['genres'], 'nur bekannte Genres');
    $p->post('assistRules', ['rules' => ['genres' => []]]);
    checkEquals(2, $p->get('state')['assistant']['rules']['minLevel'], 'bleibt');
    // Kassetten: eine nach der anderen, so schnell wie man selbst; was nicht passt, fliegt raus.
    $labelId = (int) $res['state']['label']['id'];
    $repo = new Signed\Game\Repo($db, $labelId);
    $db->exec('DELETE FROM submissions');
    $db->exec('DELETE FROM mail');
    (new Signed\Game\Submissions($repo))->testClosed();
    (new Signed\Game\Submissions($repo))->testClosed();
    $db->update('labels', ['submit_at' => Clock::now() + 10 * 86400, 'job_at' => Clock::now() + 10 * 86400, 'interview_at' => Clock::now() + 10 * 86400], ['id' => $labelId]);
    $state = $p->post('assistTask', ['staffId' => $res['staffId'], 'task' => 'demos'])['state'];
    checkEquals(true, $state['assistant']['areas']['demos']);
    Clock::advance(Balance::SUBMISSIONS['listen'] * 2);
    $state = $p->get('state');
    checkEquals(2, $state['assistant']['today']['opened'] + $state['assistant']['today']['dropped'], 'zwei Kassetten in zehn Sekunden');
    checkEquals('listen', $state['assistant']['staff'][0]['doing'], 'sitzt an der nächsten');
    Clock::advance(3600);
    $state = $p->get('state');
    checkEquals([], array_values(array_filter($state['submissions'], static fn ($s) => $s['state'] !== 'open')), 'alle bearbeitet');
    check(count($state['submissions']) >= 2, 'die guten bleiben');
    foreach ($state['submissions'] as $s) {
        $level = max(array_map(static fn ($m) => max(array_map(static fn ($i) => Signed\Game\Levels::forSkill($i['skill']), $m['instruments'])), $s['members'] ?? [$s]));
        check($level >= 2, 'nur ab Stufe 2 geblieben');
        check($s['filed'], 'in den Genre-Unterreitern');
    }
    check($state['assistant']['today']['dropped'] >= 8, 'aussortiert: ' . $state['assistant']['today']['dropped']);
    checkEquals(null, $state['assistant']['staff'][0]['doing'], 'nichts mehr da');
    // Selbst angehört: Sie legt die Kassette nur noch in die Unterreiter (wirft sie nicht weg).
    $db->exec('DELETE FROM submissions');
    (new Signed\Game\Submissions($repo))->testFill();
    Clock::advance(60);
    $state = $p->get('state');
    checkEquals(10, count(array_filter($state['submissions'], static fn ($s) => $s['filed'])), 'alles in den Unterreitern, nichts weggeworfen');
    // Suchauftrag: Was passt, wird gemerkt und gemeldet.
    $db->exec('DELETE FROM submissions');
    $p->post('assistRules', ['rules' => ['minLevel' => 0]]);
    $p->post('assistSearch', ['search' => ['instrument' => 'nope', 'genre' => '', 'minLevel' => 0]]);
    checkEquals(null, $p->get('state')['assistant']['search'], 'ohne Vorgabe sucht sie nichts');
    (new Signed\Game\Submissions($repo))->testClosed();
    $card = json_decode((string) $db->value('SELECT card FROM submissions WHERE card NOT LIKE ? ORDER BY id LIMIT 1', ['%"kind":"band"%']), true);
    $state = $p->post('assistSearch', ['search' => ['instrument' => $card['instr1'], 'genre' => $card['genres'][0], 'minLevel' => 0]])['state'];
    checkEquals(['instrument' => $card['instr1'], 'genre' => $card['genres'][0], 'minLevel' => 0], $state['assistant']['search']);
    Clock::advance(3600);
    $found = array_values(array_filter($p->get('state')['submissions'], static fn ($s) => $s['found']));
    check(count($found) >= 1, 'gefunden');
    check($found[0]['saved'], 'gemerkt');
    check((int) $db->value("SELECT COUNT(*) FROM history WHERE text LIKE '%zu deiner Suche passt%'") >= 1, 'meldet sich');
    // Umschläge packt die Kassetten-Assistenz nicht selbst aus: Sie wartet, bis jemand auspackt.
    $db->exec('DELETE FROM submissions');
    (new Signed\Game\Submissions($repo))->testClosed();
    $db->exec('UPDATE submissions SET unpacked_at = NULL, sorted_at = NULL');
    Clock::advance(3600);
    $state = $p->get('state');
    checkEquals(7, count(array_filter($state['submissions'], static fn ($s) => $s['state'] === 'envelope')), 'alles noch zu');
    // Eine zweite für die Post: packt aus und legt in die Reiter, danach hört die erste an.
    $state = $p->post('mailTest')['state'];
    $second = array_values(array_filter($state['mail'], static fn ($m) => $m['kind'] === 'job' && $m['role'] === 'assistant'))[0];
    $staffId = $p->post('mailHire', ['mailId' => $second['mailId']])['staffId'];
    $p->post('assistTask', ['staffId' => $staffId, 'task' => 'post']);
    checkApiError(fn () => $p->post('assistTask', ['staffId' => $staffId, 'task' => 'kaffee']), 'Aufgabe');
    Clock::advance(3600);
    $state = $p->get('state');
    checkEquals([], array_values(array_filter([...$state['submissions'], ...$state['mail']], static fn ($s) => in_array($s['state'] ?? '', ['envelope', 'unpacking'], true) || !$s['sorted'])), 'ausgepackt und einsortiert');
    checkEquals([], array_values(array_filter($state['submissions'], static fn ($s) => $s['state'] !== 'open')), 'und angehört');
    // Büro: liest die Briefe und erzählt davon; Interviews sagt sie nur zu, wenn man es will, und nur, wer frei ist.
    checkEquals(false, (bool) array_filter($state['mail'], static fn ($m) => $m['read'] ?? false), 'ohne Büro liest niemand');
    $third = array_values(array_filter($state['mail'], static fn ($m) => $m['kind'] === 'job' && $m['role'] === 'assistant'))[0];
    $staffId = $p->post('mailHire', ['mailId' => $third['mailId']])['staffId'];
    $p->post('assistTask', ['staffId' => $staffId, 'task' => 'office']);
    Clock::advance(3600);
    $state = $p->get('state');
    checkEquals([], array_values(array_filter($state['mail'], static fn ($m) => !$m['read'])), 'alles gelesen');
    check((bool) array_filter($state['mail'], static fn ($m) => $m['kind'] === 'interview'), 'Interviews liegen noch da');
    $db->exec('UPDATE musicians SET busy_until = ? WHERE id = ?', [Clock::now() + 9999, $state['musicians'][0]['id']]);
    $state = $p->post('assistRules', ['rules' => ['accept' => true]])['state'];
    checkEquals(1, count(array_filter($state['mail'], static fn ($m) => $m['kind'] === 'interview')), 'wer unterwegs ist, wartet');
    check(count(array_filter($state['running'], static fn ($a) => $a['type'] === 'interview')) === count($state['musicians']) - 1, 'die anderen sind beim Interview');
    checkEquals(true, Assistant::fits(['instr1' => 'drums', 'skill1' => 40.0, 'instr2' => null, 'skill2' => null, 'genres' => ['metal']], ['instrument' => 'drums', 'genre' => 'metal', 'minLevel' => 1]));
    checkEquals(false, Assistant::fits(['instr1' => 'drums', 'skill1' => 20.0, 'instr2' => null, 'skill2' => null, 'genres' => ['metal']], ['instrument' => 'drums', 'genre' => '', 'minLevel' => 1]));
    checkEquals(false, Assistant::fits(['instr1' => 'guitar', 'skill1' => 90.0, 'instr2' => 'drums', 'skill2' => 10.0, 'genres' => ['metal']], ['instrument' => 'drums', 'genre' => '', 'minLevel' => 1]), 'zählt das gesuchte Instrument');
    checkEquals(true, Assistant::fits(['kind' => 'band', 'members' => [['instr1' => 'bass', 'skill1' => 10.0, 'genres' => ['pop']], ['instr1' => 'drums', 'skill1' => 10.0, 'genres' => ['pop']]]], ['instrument' => 'drums', 'genre' => 'pop', 'minLevel' => 0]));
    checkEquals(true, Assistant::matches(['kind' => 'band', 'members' => [['skill1' => 70.0, 'genres' => ['rock']], ['skill1' => 10.0, 'genres' => ['pop']]]], ['minLevel' => 2, 'genres' => ['pop'], 'kind' => 'band']));
    checkEquals(false, Assistant::matches(['skill1' => 70.0, 'genres' => ['rock']], ['minLevel' => 2, 'genres' => ['pop'], 'kind' => 'any']));
    checkEquals(false, Assistant::matches(['skill1' => 30.0, 'genres' => ['rock']], ['minLevel' => 1, 'genres' => [], 'kind' => 'solo']));
});

test('Scout: bewirbt sich zu Beginn, mit Suchauftrag unterwegs, Bericht im Posteingang, beliebig viele unterschreiben', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Sucher');
    $p->get('state');
    unpackAll($db);
    $state = $p->get('state');
    $welcome = array_values(array_filter($state['mail'], static fn ($m) => $m['kind'] === 'job' && $m['role'] === 'scout'));
    checkEquals(1, count($welcome), 'Willkommens-Brief vom Scout');
    checkEquals(1, $welcome[0]['tier']);
    checkEquals(Balance::SCOUT_STAFF['tiers'][1]['price'], $welcome[0]['price']);
    $state = $p->post('mailHire', ['mailId' => $welcome[0]['mailId']])['state'];
    checkEquals(1, count($state['scouts']));
    $scout = $state['scouts'][0];
    checkEquals(null, $scout['away']);
    checkApiError(fn () => $p->post('scoutSend', ['staffId' => $scout['id'], 'genre' => 'polka']), 'Genre');
    $state = $p->post('scoutSend', ['staffId' => $scout['id'], 'genre' => 'jazz', 'instrument' => 'bass'])['state'];
    check($state['scouts'][0]['away'] !== null, 'unterwegs');
    checkApiError(fn () => $p->post('scoutSend', ['staffId' => $scout['id']]), 'unterwegs');
    Clock::advance(Balance::SCOUT_STAFF['tiers'][1]['seconds'] - 60);
    check(!array_filter($p->get('state')['mail'], static fn ($m) => $m['kind'] === 'report'), 'noch kein Bericht');
    Clock::advance(120);
    $state = $p->get('state');
    $reports = array_values(array_filter($state['mail'], static fn ($m) => $m['kind'] === 'report'));
    checkEquals(1, count($reports));
    checkEquals(null, $state['scouts'][0]['away'], 'wieder da');
    $cards = $reports[0]['cards'];
    checkEquals(2, count($cards));
    foreach ($cards as $c) {
        checkEquals('bass', $c['instruments'][0]['type']);
        check(in_array('jazz', $c['genres'], true), 'Genre nach Auftrag');
        check($c['instruments'][0]['skill'] >= 15 && $c['instruments'][0]['skill'] <= 34, 'Können bis Stufe 1 (einfacher Scout bis 34)');
        checkEquals(0.0, (float) $c['fame'], 'unbekannt');
    }
    // Beide unterschreiben, jeder kostet seine Prämie.
    $money = $state['label']['money'];
    $res = $p->post('scoutReportSign', ['mailId' => $reports[0]['mailId'], 'index' => 0]);
    $res = $p->post('scoutReportSign', ['mailId' => $reports[0]['mailId'], 'index' => 1]);
    checkEquals($money - $cards[0]['price'] - $cards[1]['price'], $res['state']['label']['money']);
    checkApiError(fn () => $p->post('scoutReportSign', ['mailId' => $reports[0]['mailId'], 'index' => 1]), 'nicht mehr');
    $report = array_values(array_filter($res['state']['mail'], static fn ($m) => $m['kind'] === 'report'))[0];
    checkEquals([true, true], array_column($report['cards'], 'signed'));
    checkEquals('scout', $db->value('SELECT origin FROM musicians WHERE id = ?', [$res['musicianId']]));
    // Ohne Auftrag (egal): irgendwas.
    $p->post('scoutSend', ['staffId' => $scout['id'], 'genre' => '', 'instrument' => '']);
    Clock::advance(Balance::SCOUT_STAFF['tiers'][1]['seconds'] + 5);
    checkEquals(2, count(array_filter($p->get('state')['mail'], static fn ($m) => $m['kind'] === 'report')));
    // Bessere Scouts finden mehr und besser.
    $best = Signed\Game\Scouting::find(3, null, null);
    checkEquals(3, count($best));
    foreach ($best as $c) {
        check($c['skill1'] >= 32 && $c['skill1'] <= 54);
    }
});

test('Privatlehrer: für ein Instrument und einen Musiker gebucht, Können wächst über die Zeit ohne Bremse, danach geht er wieder', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Lehre');
    $state = $p->get('state');
    $db->exec('UPDATE labels SET money = 100000');
    $guitar = array_values(array_filter($state['musicians'], static fn ($m) => $m['instruments'][0]['type'] === 'guitar'))[0];
    $drums = array_values(array_filter($state['musicians'], static fn ($m) => $m['instruments'][0]['type'] === 'drums'))[0];
    $db->exec('UPDATE musicians SET skill1 = 40 WHERE id = ?', [$guitar['id']]);
    $state = $p->post('mailTest')['state'];
    $teachers = array_values(array_filter($state['mail'], static fn ($m) => $m['kind'] === 'job' && $m['role'] === 'teacher'));
    // Neben den Test-Briefen können sich weitere Lehrkräfte beworben haben: je Stufe eine nehmen.
    $teachers = array_values(array_map(static fn ($t) => array_values(array_filter($teachers, static fn ($x) => $x['tier'] === $t))[0], [1, 2, 3]));
    // Eine Lehrkraft für Gitarre auf Stufe 2 bauen, damit es sicher passt.
    $job = $teachers[1];
    $row = $db->one('SELECT data FROM mail WHERE id = ?', [$job['mailId']]);
    $data = json_decode($row['data'], true);
    $data['instrument'] = 'guitar';
    $data['genre'] = null;
    $db->update('mail', ['data' => json_encode($data)], ['id' => $job['mailId']]);
    checkApiError(fn () => $p->post('mailHire', ['mailId' => $job['mailId']]), 'Wähle');
    checkApiError(fn () => $p->post('mailHire', ['mailId' => $job['mailId'], 'target' => $drums['id']]), 'spielt kein');
    $money = (int) $db->value('SELECT money FROM labels');
    $state = $p->post('mailHire', ['mailId' => $job['mailId'], 'target' => $guitar['id']])['state'];
    checkEquals($money - Balance::TEACHER['tiers'][2]['price'], $state['label']['money']);
    checkEquals(1, count($state['tutors']));
    // Ein zweiter Lehrer für denselben Musiker geht nicht (nicht parallel).
    $other = $db->one('SELECT id, data FROM mail WHERE id = ?', [$teachers[2]['mailId']]);
    $d2 = json_decode($other['data'], true);
    $d2['instrument'] = 'guitar';
    $db->update('mail', ['data' => json_encode($d2)], ['id' => (int) $other['id']]);
    checkApiError(fn () => $p->post('mailHire', ['mailId' => (int) $other['id'], 'target' => $guitar['id']]), 'schon Unterricht');
    // Halbe Zeit: halber Gewinn, ungebremst.
    Clock::advance(Balance::TEACHER['tiers'][2]['seconds'] / 2);
    $p->get('state');
    $skill = (float) $db->value('SELECT skill1 FROM musicians WHERE id = ?', [$guitar['id']]);
    check(abs($skill - (40 + Balance::TEACHER['tiers'][2]['gain'] / 2)) < 0.05, "halber Gewinn: $skill");
    Clock::advance(Balance::TEACHER['tiers'][2]['seconds']);
    $state = $p->get('state');
    $skill = (float) $db->value('SELECT skill1 FROM musicians WHERE id = ?', [$guitar['id']]);
    check(abs($skill - (40 + Balance::TEACHER['tiers'][2]['gain'])) < 0.05, "ganzer Gewinn: $skill");
    checkEquals([], $state['tutors'], 'danach wieder weg');
    // Zu gut für eine einfache Lehrkraft.
    $db->exec('UPDATE musicians SET skill1 = 70 WHERE id = ?', [$guitar['id']]);
    $fresh = array_values(array_filter($p->post('mailTest')['state']['mail'], static fn ($m) => $m['kind'] === 'job' && $m['role'] === 'teacher' && $m['tier'] === 1))[0];
    $simple = $db->one('SELECT id, data FROM mail WHERE id = ?', [$fresh['mailId']]);
    $d0 = json_decode($simple['data'], true);
    $d0['instrument'] = 'guitar';
    $db->update('mail', ['data' => json_encode($d0)], ['id' => (int) $simple['id']]);
    checkApiError(fn () => $p->post('mailHire', ['mailId' => (int) $simple['id'], 'target' => $guitar['id']]), 'kann schon mehr');
});

test('Songwriting-Coach: eine Band schreibt in der Zeit schneller, die gecoachten Songs machen die nächste Platte besser', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Coach');
    $band = foundBand($p);
    $db->exec('UPDATE labels SET money = 100000');
    $db->exec('UPDATE musicians SET wellbeing = 90');
    $p->get('state');
    // Ohne Coach: eine Stunde Songs.
    $db->exec('UPDATE bands SET songs_written = 0, mood_at = ? WHERE id = ?', [Clock::now(), $band['id']]);
    Clock::advance(3600);
    $p->get('state');
    $plain = (float) $db->value('SELECT songs_written FROM bands WHERE id = ?', [$band['id']]);
    check($plain > 0, 'Songs entstehen');
    $state = $p->post('mailTest')['state'];
    $coach = array_values(array_filter($state['mail'], static fn ($m) => $m['kind'] === 'job' && $m['role'] === 'coach' && $m['tier'] === 3))[0];
    $p->post('mailHire', ['mailId' => $coach['mailId'], 'target' => $band['id']]);
    $db->exec('UPDATE bands SET songs_written = 0, coached_songs = 0, mood_at = ? WHERE id = ?', [Clock::now(), $band['id']]);
    $db->exec('UPDATE musicians SET wellbeing = 90');
    Clock::advance(3600);
    $state = $p->get('state');
    $coached = (float) $db->value('SELECT songs_written FROM bands WHERE id = ?', [$band['id']]);
    check($coached > $plain * 1.8, "doppelt so schnell: $plain → $coached");
    checkEquals(round($coached, 2), round((float) $db->value('SELECT coached_songs FROM bands WHERE id = ?', [$band['id']]), 2), 'alle Songs gecoacht');
    check(Signed\Game\Tutors::recordBonus(Signed\Game\Repo::hydrateBand($db->one('SELECT * FROM bands WHERE id = ?', [$band['id']])), 1) === Balance::COACH['tiers'][3]['quality']);
    // Zweiter Coach für dieselbe Band geht nicht.
    $second = array_values(array_filter($state['mail'], static fn ($m) => $m['kind'] === 'job' && $m['role'] === 'coach'))[0];
    checkApiError(fn () => $p->post('mailHire', ['mailId' => $second['mailId'], 'target' => $band['id']]), 'schon einen Coach');
    // Nach dem Ende kein Schub mehr.
    Clock::advance(Balance::COACH['tiers'][3]['seconds']);
    checkEquals([], $p->get('state')['tutors']);
});

test('Manager als Bewerbung: Passung nach Genre und Stil, Netzwerker und Vermittler wirken, er kennt seine Leute', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Chefin');
    $band = foundBand($p);
    $db->exec('UPDATE labels SET money = 100000');
    $state = $p->post('mailTest')['state'];
    $jobs = array_values(array_filter($state['mail'], static fn ($m) => $m['kind'] === 'job' && $m['role'] === 'manager'));
    checkEquals([1, 2, 3], array_values(array_unique(array_column($jobs, 'tier'))));
    $top = array_values(array_filter($jobs, static fn ($m) => $m['tier'] === 3))[0];
    // Festlegen: Rock, Netzwerker, Stil, den keiner hat.
    $row = $db->one('SELECT data FROM mail WHERE id = ?', [$top['mailId']]);
    $d = json_decode($row['data'], true);
    $d['genres'] = ['rock'];
    $d['skills'] = ['netzwerker'];
    $d['style'] = 'ruhepol';
    $db->update('mail', ['data' => json_encode($d)], ['id' => $top['mailId']]);
    $db->exec("UPDATE musicians SET personality = '[\"ehrgeizig\"]', known_traits = '[]'");
    $res = $p->post('mailHire', ['mailId' => $top['mailId']]);
    checkEquals(100000 - Balance::MANAGER_STAFF['tiers'][3]['price'], $res['state']['label']['money']);
    $managerId = $res['staffId'];
    $manager = array_values(array_filter($res['state']['managers'], static fn ($m) => $m['id'] === $managerId))[0];
    checkEquals(['rock'], $manager['genres']);
    checkEquals(1.0, (float) $manager['fits']->{$band['id']}, 'Rock-Band, Stil ohne Wirkung: passt mittel');
    // Andere Genres passen schlechter.
    $repo = new Signed\Game\Repo($db, (int) $res['state']['label']['id']);
    $members = $repo->members($band['id']);
    check(Signed\Game\Managers::fit(['genres' => ['jazz'], 'style' => null], $members) < 0.7, 'fremdes Genre');
    check(Signed\Game\Managers::fit(['genres' => ['rock'], 'style' => 'ehrgeizig'], $members) > 1.1, 'gleicher Stil passt besser');
    $state = $p->post('bandSetManager', ['bandId' => $band['id'], 'managerId' => $managerId])['state'];
    $b = bandById($state, $band['id']);
    checkEquals(5.0, (float) $b['managerLevel']);
    // Nach einem Tag kennt er seine Leute: Die Züge sind jetzt alle bekannt.
    check((bool) array_filter($state['musicians'], static fn ($m) => $m['hiddenTraits'] > 0), 'nicht sofort');
    Clock::advance(Balance::TRAIT_REVEAL['after']);
    $state = $p->get('state');
    foreach ($state['musicians'] as $m) {
        checkEquals(0, $m['hiddenTraits'], 'Manager zeigt alle Züge');
    }
    // Netzwerker: Interviews bringen mehr.
    $state = $p->post('mailTest')['state'];
    $iv = array_values(array_filter($state['mail'], static fn ($m) => $m['kind'] === 'interview'))[0];
    $res = $p->post('mailAccept', ['mailId' => $iv['mailId']]);
    $data = json_decode((string) $db->value('SELECT data FROM activities WHERE id = ?', [$res['activity']['id']]), true);
    check(abs($data['reward']['fame'] - $iv['fame'] * (1 + 5 * Balance::MANAGER_STAFF['network'])) < 0.02, 'Netzwerker: +50 %');
    // Vermittler dämpft schlechte Laune: Wer Straßenmusik nicht mag (ehrgeizig), verliert weniger.
    $drop = static function () use ($db, $repo, $band): float {
        $db->exec('UPDATE musicians SET wellbeing = 60 WHERE band_id = ?', [$band['id']]);
        Signed\Game\Mood::react($repo, $repo->members($band['id']), 'busking');
        return 60 - (float) $db->value('SELECT wellbeing FROM musicians WHERE band_id = ? LIMIT 1', [$band['id']]);
    };
    $without = $drop();
    $db->update('managers', ['skills' => json_encode(['vermittler'])], ['id' => $managerId]);
    $with = $drop();
    check($without > 0 && $with < $without * 0.5, "Vermittler: $without → $with");
});

test('Cover: Designer machen es klarer und verkaufen mehr, das Bandlogo wird einmal gewählt und hilft ab der zweiten Platte', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Grafik');
    $band = foundBand($p);
    $db->exec('UPDATE labels SET money = 100000');
    $db->exec('UPDATE musicians SET wellbeing = 90');
    $db->exec('UPDATE bands SET songs_written = 10 WHERE id = ?', [$band['id']]);
    $opts = $p->post('recordOptions', ['bandId' => $band['id'], 'songs' => 3]);
    checkEquals(null, $opts['logo']);
    checkApiError(fn () => $p->post('recordStart', ['bandId' => $band['id'], 'title' => 'Feuer', 'songs' => 3, 'studio' => 0, 'producer' => 'none', 'cover' => $opts['covers'][0], 'designer' => 'star', 'logo' => ['font' => 'comic', 'color' => 'gold']]), 'Schrift');
    checkApiError(fn () => $p->post('recordStart', ['bandId' => $band['id'], 'title' => 'Feuer', 'songs' => 3, 'studio' => 0, 'producer' => 'none', 'cover' => $opts['covers'][0], 'designer' => 'picasso']), 'gestalten');
    $money = (int) $db->value('SELECT money FROM labels');
    $p->post('recordStart', ['bandId' => $band['id'], 'title' => 'Feuer', 'songs' => 3, 'studio' => 0, 'producer' => 'none', 'cover' => $opts['covers'][0], 'designer' => 'star', 'logo' => ['font' => 'block', 'color' => 'gold']]);
    checkEquals($money - Signed\Game\Studio::cost(3, 0, 'none') - Balance::COVER_DESIGNERS['star']['fee'], (int) $db->value('SELECT money FROM labels'));
    $cover = json_decode((string) $db->value('SELECT cover FROM records WHERE band_id = ? ORDER BY id DESC LIMIT 1', [$band['id']]), true);
    checkEquals([3, 3, 3, 3], $cover['spec']['rarity'], 'Star-Designerin: Gold');
    checkEquals(['font' => 'block', 'color' => 'gold'], $cover['spec']['logo']);
    checkEquals(Balance::COVER_DESIGNERS['star']['sales'], (float) $cover['sales'], 'erste Platte: noch ohne Wiedererkennung');
    Clock::advance(3 * Balance::RECORD_SECONDS_PER_SONG + 5);
    $state = $p->get('state');
    checkEquals(['font' => 'block', 'color' => 'gold'], bandById($state, $band['id'])['logo']);
    // Zweite Platte: Das Logo bleibt (ein neues wird nicht genommen) und verkauft mit.
    $db->exec('UPDATE musicians SET wellbeing = 90');
    $db->exec('UPDATE bands SET live_songs = live_songs_needed, band_promos = 99 WHERE id = ?', [$band['id']]);
    $opts = $p->post('recordOptions', ['bandId' => $band['id'], 'songs' => 3]);
    checkEquals(true, $opts['logoSales']);
    $p->post('recordStart', ['bandId' => $band['id'], 'title' => 'Ohne Wort', 'songs' => 3, 'studio' => 0, 'producer' => 'none', 'cover' => $opts['covers'][0], 'designer' => 'student', 'logo' => ['font' => 'serif', 'color' => 'rot']]);
    $cover = json_decode((string) $db->value('SELECT cover FROM records WHERE band_id = ? ORDER BY id DESC LIMIT 1', [$band['id']]), true);
    checkEquals([0, 0, 0, 0], $cover['spec']['rarity'], 'Kunststudentin: blass');
    checkEquals('block', $cover['spec']['logo']['font'], 'Logo bleibt');
    checkEquals(Balance::COVER_LOGO_SALES, (float) $cover['sales']);
});

test('Praktikum: günstig, sortiert nur die Post, lässt sich erst nach den sieben Tagen übernehmen, sonst geht es', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Praktikum');
    $p->get('state');
    unpackAll($db);
    $state = $p->get('state');
    $intern = array_values(array_filter($state['mail'], static fn ($m) => $m['kind'] === 'job' && $m['role'] === 'intern'))[0];
    checkEquals(Balance::INTERN['price'], $intern['price']);
    $money = $state['label']['money'];
    $state = $p->post('mailHire', ['mailId' => $intern['mailId']])['state'];
    checkEquals($money - Balance::INTERN['price'], $state['label']['money']);
    checkEquals(['post' => false, 'demos' => false, 'office' => false], $state['assistant']['areas'], 'arbeitet nur auf Zuruf');
    checkEquals('post', $state['assistant']['staff'][0]['task']);
    checkEquals(true, $state['assistant']['staff'][0]['intern']);
    $id = $state['assistant']['staff'][0]['id'];
    checkApiError(fn () => $p->post('assistTask', ['staffId' => $id, 'task' => 'presse']), 'gibt es nicht');
    // Während des Praktikums geht Übernehmen (und Verabschieden) noch nicht.
    checkApiError(fn () => $p->post('assistAdopt', ['staffId' => $id]), 'erst, wenn das Praktikum vorbei ist');
    checkApiError(fn () => $p->post('assistDismiss', ['staffId' => $id]), 'noch im Praktikum');
    // Danach wartet es auf eine Antwort und arbeitet nicht mehr.
    Clock::advance(Balance::INTERN['seconds'] + 60);
    $state = $p->get('state');
    checkEquals(true, $state['assistant']['staff'][0]['ended']);
    checkApiError(fn () => $p->post('internOrder', ['staffId' => $id, 'job' => 'sort']), 'ist vorbei');
    // Übernehmen: jetzt eine feste Assistenz; ein Arbeitsgebiet bekommt sie erst, wenn man es ihr gibt.
    $state = $p->post('assistAdopt', ['staffId' => $id])['state'];
    checkEquals(false, $state['assistant']['staff'][0]['intern']);
    checkEquals('none', $state['assistant']['staff'][0]['task']);
    checkEquals(true, $p->post('assistTask', ['staffId' => $id, 'task' => 'demos'])['state']['assistant']['areas']['demos']);
    Clock::advance(30 * 86400);
    checkEquals(1, count($p->get('state')['assistant']['staff']), 'bleibt');
    // Ein zweites Praktikum ohne Antwort geht nach der Bedenkzeit.
    $p2 = registerPlayer($db, 'Kurzzeit');
    $p2->get('state');
    unpackAll($db);
    $mail = array_values(array_filter($p2->get('state')['mail'], static fn ($m) => $m['kind'] === 'job' && $m['role'] === 'intern'))[0];
    $p2->post('mailHire', ['mailId' => $mail['mailId']]);
    Clock::advance(Balance::INTERN['seconds'] + 60);
    checkEquals(true, $p2->get('state')['assistant']['staff'][0]['ended'], 'wartet noch auf eine Antwort');
    Clock::advance(Balance::INTERN['decide']);
    $p2->get('state');
    unpackAll($db);
    $state = $p2->get('state');
    checkEquals(null, $state['assistant'], 'weg');
    check((bool) array_filter($state['mail'], static fn ($m) => $m['kind'] === 'job' && $m['role'] === 'intern'), 'danach bewirbt sich wieder ein Praktikum');
});

test('Bewerbungen: je Beruf eine eigene Rate, die mit dem Erfolg wächst; Praktika am häufigsten; ein Praktikum immer', function () {
    $low = Inbox::jobRates(0);
    $high = Inbox::jobRates(2000);
    foreach ($low as $role => $rate) {
        check($high[$role] > $rate, "$role: mit Erfolg mehr");
    }
    checkEquals(['intern', 'manager', 'scout', 'assistant', 'teacher', 'coach'], array_keys($low));
    check($low['intern'] > $low['manager'] && $low['manager'] > $low['scout'] && $low['scout'] > $low['assistant'] && $low['assistant'] > $low['teacher'], 'Reihenfolge');
    // Zufällige Ankünfte über viele Tage: im Schnitt so viele wie die Rate sagt.
    $db = freshDb();
    $p = registerPlayer($db, 'Raten');
    $p->get('state');
    $db->exec("DELETE FROM mail WHERE kind = 'job'");
    $db->exec('UPDATE labels SET job_at = ' . (Clock::now() - Balance::POST['ttl']));
    $p->get('state');
    $jobs = array_column(array_map(static fn ($r) => json_decode($r['data'], true), $db->all("SELECT data FROM mail WHERE kind = 'job'")), 'role');
    $days = Balance::POST['ttl'] / 86400;
    check(count($jobs) >= array_sum($low) * $days * 0.3 && count($jobs) <= array_sum($low) * $days * 3 + 2, 'ungefähr nach Rate: ' . count($jobs));
    $db = freshDb();
    $p = registerPlayer($db, 'Viele');
    $p->get('state');
    unpackAll($db);
    $jobs = array_values(array_filter($p->get('state')['mail'], static fn ($m) => $m['kind'] === 'job'));
    foreach (['intern', 'scout', 'manager'] as $role) {
        check(in_array($role, array_column($jobs, 'role'), true), "$role gleich zu Beginn");
    }
    // Alle weg: Ein Praktikum ist gleich wieder da (im Umschlag).
    $db->exec("DELETE FROM mail WHERE kind = 'job'");
    $p->get('state');
    unpackAll($db);
    $jobs = array_values(array_filter($p->get('state')['mail'], static fn ($m) => $m['kind'] === 'job'));
    check(in_array('intern', array_column($jobs, 'role'), true), 'ein Praktikum immer');
});

test('Umschläge: Praktikum packt auf Zuruf aus, hört an, sortiert in die Fächer; nur eins zur Zeit; Assistenz macht es laufend', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Umschlag');
    $state = $p->get('state');
    // Neu im Posteingang: zählt hoch, bis man hineinschaut.
    check($state['inboxNew'] >= 5, 'neu: ' . $state['inboxNew']);
    $p->post('inboxSeen');
    checkEquals(0, $p->get('state')['inboxNew']);
    $envelopes = array_values(array_filter($state['mail'], static fn ($m) => $m['kind'] === 'envelope'));
    check(count($envelopes) >= 2, 'Bewerbungen im Umschlag');
    check(!isset($envelopes[0]['role']) && !isset($envelopes[0]['price']), 'verrät nichts');
    checkApiError(fn () => $p->post('mailHire', ['mailId' => $envelopes[0]['mailId']]), 'erst auspacken');
    // Das Praktikum selbst auspacken und einstellen.
    foreach ($envelopes as $e) {
        $p->post('postUnpack', ['table' => 'm', 'id' => $e['mailId']]);
        Clock::advance(Balance::POST['unpack']);
    }
    $intern = array_values(array_filter($p->get('state')['mail'], static fn ($m) => $m['kind'] === 'job' && $m['role'] === 'intern'))[0];
    checkEquals('', $intern['lastName'], 'nur ein Vorname');
    checkEquals(true, $intern['look']['young'], 'sieht jünger aus');
    $state = $p->post('mailHire', ['mailId' => $intern['mailId']])['state'];
    $id = $state['assistant']['staff'][0]['id'];
    // Ein zweites Praktikum geht nicht.
    $db->exec('UPDATE labels SET job_at = 1');
    $p->get('state');
    unpackAll($db);
    $other = array_values(array_filter($p->get('state')['mail'], static fn ($m) => $m['kind'] === 'job' && $m['role'] === 'intern'));
    check($other !== [], 'Praktika bewerben sich weiter');
    checkApiError(fn () => $p->post('mailHire', ['mailId' => $other[0]['mailId']]), 'schon ein Praktikum');
    // Neue Umschläge: Auf Zuruf macht es nur eine Sache, für alles, was gerade da liegt, das Neueste zuerst, so schnell wie man selbst.
    $db->exec('UPDATE submissions SET unpacked_at = NULL, opened_at = NULL, sorted_at = NULL');
    $db->exec('UPDATE mail SET sorted_at = NULL');
    $state = $p->get('state');
    $todo = $state['assistant']['todo'];
    $tapes = count($state['submissions']);
    check($tapes >= 5 && $todo['unpack'] >= $tapes);
    checkEquals(0, $todo['listen'], 'zu: noch nichts zum Anhören');
    checkApiError(fn () => $p->post('internOrder', ['staffId' => $id, 'job' => 'listen']), 'keine ungehörte');
    $state = $p->post('internOrder', ['staffId' => $id, 'job' => 'unpack'])['state'];
    $job = $state['assistant']['staff'][0]['job'];
    checkEquals('unpack', $job['kind']);
    checkEquals(0, $job['count'], 'noch nichts fertig');
    checkEquals(Clock::now() + $todo['unpack'] * Balance::POST['unpack'], $job['until']);
    checkApiError(fn () => $p->post('internOrder', ['staffId' => $id, 'job' => 'sort']), 'beschäftigt');
    // Das Neueste zuerst, und gesperrt ist nur das Stück, an dem es gerade sitzt.
    $byAge = $db->all('SELECT id, unpacked_at FROM submissions ORDER BY created_at DESC, id DESC');
    checkEquals(Clock::now() + Balance::POST['unpack'], (int) $byAge[0]['unpacked_at'], 'Neuestes zuerst');
    checkEquals(1, count(array_filter($state['submissions'], static fn ($x) => $x['state'] === 'unpacking')), 'nur eins gesperrt');
    // Man kann derweil selbst auspacken; das überspringt das Praktikum dann.
    $oldest = (int) end($byAge)['id'];
    $p->post('postUnpack', ['table' => 's', 'id' => $oldest]);
    Clock::advance(2 * Balance::POST['unpack']);
    $state = $p->get('state');
    checkEquals(2, $state['assistant']['staff'][0]['job']['count'], 'zwei geschafft');
    // Abbrechen: Fertiges bleibt fertig, das angefangene liegt wieder zu da.
    $state = $p->post('internCancel', ['staffId' => $id])['state'];
    checkEquals(null, $state['assistant']['staff'][0]['job']);
    checkEquals(3, count(array_filter($state['submissions'], static fn ($x) => $x['state'] !== 'envelope' && $x['state'] !== 'unpacking')), 'zwei vom Praktikum, eins selbst');
    checkEquals($todo['unpack'] - 3, $state['assistant']['todo']['unpack'], 'Rest wieder zu');
    checkApiError(fn () => $p->post('internCancel', ['staffId' => $id]), 'nichts zu tun');
    // Nochmal: alles öffnen, dann anhören, dann sortieren; jedes nur das, was in dem Moment da liegt.
    $p->post('internOrder', ['staffId' => $id, 'job' => 'unpack']);
    Clock::advance(($todo['unpack'] - 3) * Balance::POST['unpack'] + 1);
    $state = $p->get('state');
    checkEquals(0, $state['assistant']['todo']['unpack'], 'alles geöffnet');
    check(in_array('office', array_column($state['history'], 'kind'), true), 'meldet sich, wenn fertig');
    $listen = $state['assistant']['todo']['listen'];
    $p->post('internOrder', ['staffId' => $id, 'job' => 'listen']);
    Clock::advance($listen * Balance::SUBMISSIONS['listen'] + 1);
    $state = $p->get('state');
    checkEquals([], array_values(array_filter($state['submissions'], static fn ($x) => $x['state'] !== 'open')), 'alle angehört, keine weggeworfen');
    checkEquals(false, $state['submissions'][0]['sorted'], 'noch im Eingang');
    $sort = $state['assistant']['todo']['sort'];
    $p->post('internOrder', ['staffId' => $id, 'job' => 'sort']);
    Clock::advance($sort * Balance::POST['sort'] + 1);
    $state = $p->get('state');
    checkEquals([], array_values(array_filter($state['submissions'], static fn ($x) => !$x['sorted'])), 'alles in den Fächern');
    checkApiError(fn () => $p->post('internOrder', ['staffId' => $id, 'job' => 'sort']), 'nichts zum Sortieren');
    // Von Hand zurück in den Eingang ziehen: liegt dort, bis jemand wieder sortiert.
    $sid = $state['submissions'][0]['submissionId'];
    $state = $p->post('postMove', ['table' => 's', 'id' => $sid, 'box' => 'inbox'])['state'];
    checkEquals('inbox', $state['submissions'][0]['box']);
    checkEquals(1, $state['assistant']['todo']['sort']);
    // Übernehmen (nach dem Praktikum): bekommt einen Nachnamen und sieht erwachsen aus; im Gebiet Post packt sie laufend aus und sortiert.
    $db->exec('UPDATE staff SET busy_until = ? WHERE id = ?', [Clock::now() - 1, $id]);
    $state = $p->post('assistAdopt', ['staffId' => $id])['state'];
    check($state['assistant']['staff'][0]['lastName'] !== '', 'mit Nachnamen');
    check(!isset($state['assistant']['staff'][0]['look']['young']), 'erwachsen');
    $db->exec('UPDATE submissions SET unpacked_at = NULL, sorted_at = NULL, box = NULL');
    $p->post('assistTask', ['staffId' => $id, 'task' => 'post']);
    Clock::advance(Balance::POST['unpack']);
    $state = $p->get('state');
    checkEquals(1, count(array_filter($state['submissions'], static fn ($x) => !in_array($x['state'], ['envelope', 'unpacking'], true))), 'erst einer ausgepackt');
    Clock::advance(3600);
    $state = $p->get('state');
    checkEquals([], array_values(array_filter($state['submissions'], static fn ($x) => in_array($x['state'], ['envelope', 'unpacking'], true) || !$x['sorted'])), 'Assistenz packt aus und sortiert');
});

test('Praktikum: Auftrag endet mit dem Praktikum, verabschieden macht Platz für ein neues', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Abschied');
    $p->get('state');
    unpackAll($db);
    $intern = array_values(array_filter($p->get('state')['mail'], static fn ($m) => $m['kind'] === 'job' && $m['role'] === 'intern'))[0];
    $id = $p->post('mailHire', ['mailId' => $intern['mailId']])['state']['assistant']['staff'][0]['id'];
    // Kurz vor Schluss ein langer Auftrag: Was nach dem Ende dran wäre, bleibt liegen.
    $db->exec('UPDATE submissions SET unpacked_at = NULL, sorted_at = NULL');
    $todo = $p->get('state')['assistant']['todo']['unpack'];
    check($todo >= 2, 'genug Umschläge');
    $db->exec('UPDATE staff SET busy_until = ? WHERE id = ?', [Clock::now() + Balance::POST['unpack'] + 1, $id]);
    $p->post('internOrder', ['staffId' => $id, 'job' => 'unpack']);
    Clock::advance($todo * Balance::POST['unpack'] + 60);
    $state = $p->get('state');
    $x = $state['assistant']['staff'][0];
    checkEquals(true, $x['ended']);
    checkEquals(null, $x['job'], 'arbeitet nicht mehr');
    check($state['assistant']['todo']['unpack'] > 0, 'Rest liegt noch da');
    checkEquals(1, (int) $db->value("SELECT COUNT(*) FROM history WHERE text LIKE '%möchte gern bleiben%'"), 'fragt einmal nach');
    $p->get('state');
    checkEquals(1, (int) $db->value("SELECT COUNT(*) FROM history WHERE text LIKE '%möchte gern bleiben%'"), 'nur einmal');
    // Verabschieden: weg, und ein neues Praktikum lässt sich einstellen.
    $state = $p->post('assistDismiss', ['staffId' => $id])['state'];
    checkEquals(null, $state['assistant']);
    $next = array_values(array_filter($state['mail'], static fn ($m) => $m['kind'] === 'job' && $m['role'] === 'intern' && $m['mailId'] !== $intern['mailId']));
    if ($next) {
        $state = $p->post('mailHire', ['mailId' => $next[0]['mailId']])['state'];
        checkEquals(true, $state['assistant']['staff'][0]['intern']);
    }
});

test('Aufstellung im Raum: eigene Reihenfolge der Mitglieder bleibt gespeichert, Fremde werden ignoriert', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Aufstellung');
    $band = foundBand($p);
    $ids = $band['memberIds'];
    check(count($ids) >= 2, 'Band mit mehreren Mitgliedern');
    $want = array_reverse($ids);
    $state = $p->post('bandStageOrder', ['bandId' => $band['id'], 'order' => array_merge($want, [999999])])['state'];
    checkEquals($want, bandById($state, $band['id'])['memberIds']);
    checkEquals($want, bandById($p->get('state'), $band['id'])['memberIds'], 'bleibt nach dem Neuladen');
    // Nur ein Teil angegeben: der Rest steht hinten an.
    $state = $p->post('bandStageOrder', ['bandId' => $band['id'], 'order' => [$ids[1]]])['state'];
    checkEquals($ids[1], bandById($state, $band['id'])['memberIds'][0]);
    checkEquals(count($ids), count(bandById($state, $band['id'])['memberIds']));
});

test('Bedürfnisse: Die Wartezeit läuft nur, solange er nicht grün ist; was er mag, setzt sie zurück; offline im Roten steht sie still', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Wartend');
    $band = foundBand($p);
    $id = (int) $db->value('SELECT id FROM musicians WHERE band_id = ? ORDER BY id LIMIT 1', [$band['id']]);
    $db->exec("UPDATE musicians SET personality = '[\"rampensau\"]', wellbeing = 50 WHERE id = ?", [$id]);
    $p->get('state');
    // Online (alle paar Sekunden ein Lebenszeichen): drei Stunden
    for ($i = 0; $i < 3 * 60; $i++) {
        Clock::advance(60);
        $p->get('state');
    }
    $wait = (float) $db->value('SELECT wish_wait FROM musicians WHERE id = ?', [$id]);
    check($wait > 2.5 && $wait < 3.5, 'wartet seit etwa drei Stunden: ' . $wait);
    // Was er mag (ein Interview), setzt die Uhr zurück.
    Signed\Game\Mood::react(new Signed\Game\Repo($db, (int) $p->get('state')['label']['id']), [Signed\Game\Repo::hydrateMusician($db->one('SELECT * FROM musicians WHERE id = ?', [$id]))], 'interview');
    checkEquals(null, $db->value('SELECT wish_wait FROM musicians WHERE id = ?', [$id]));
    // Offline und schon rot: Die Uhr steht still.
    $db->exec('UPDATE musicians SET wellbeing = 20, wish_wait = 5 WHERE id = ?', [$id]);
    Clock::advance(2 * 86400);
    $p->get('state');
    checkEquals(5.0, (float) $db->value('SELECT wish_wait FROM musicians WHERE id = ?', [$id]), 'offline im Roten keine Wartezeit dazu');
});

test('Battle of the Bands: anmelden, pünktlich da sein, am Samstag auslosen, Runde für Runde zeigen, danach Preisgeld und Pokal', function () {
    $db = freshDb();
    $a = registerPlayer($db, 'Battler');
    $bandA = foundBand($a, 'Laute Lieder');
    $b = registerPlayer($db, 'Gegner');
    $bandB = foundBand($b, 'Leise Lieder');
    $next = $a->get('state')['battle']['next'];
    check($next['startsAt'] > Clock::now(), 'liegt in der Zukunft');
    check(str_starts_with($next['when'], 'Samstag'), $next['when']);
    $a->post('battleEnter', ['bandId' => $bandA['id']]);
    checkApiError(fn () => $a->post('battleEnter', ['bandId' => $bandA['id']]), 'schon angemeldet');
    checkApiError(fn () => $b->post('battleEnter', ['bandId' => $bandA['id']]), 'gibt es nicht');
    // Höchstens zwei Bands je Label
    $repoA = new Signed\Game\Repo($db, (int) $a->get('state')['label']['id']);
    $event = Signed\Game\Battle::next($db, Clock::now());
    $two = [['label_id' => $repoA->labelId], ['label_id' => $repoA->labelId]];
    check(str_contains((string) Signed\Game\Battle::blocker($repoA, $repoA->band($bandA['id']), $repoA->members($bandA['id']), $event, $two), 'höchstens 2'), 'Grenze je Label');
    // Wer angemeldet ist, muss bis zum Beginn zurück sein.
    Clock::set($next['startsAt'] - 600);
    $db->exec('UPDATE labels SET money = 5000');
    checkApiError(fn () => $a->post('bandRetreat', ['bandId' => $bandA['id'], 'option' => 'ausflug']), 'angemeldet');
    checkEquals([['kind' => 'battle', 'startsAt' => $next['startsAt'], 'accepted' => false]], bandById($a->get('state'), $bandA['id'])['events']);
    $b->post('battleEnter', ['bandId' => $bandB['id']]);
    $b->post('battleWithdraw', ['bandId' => $bandB['id']]);
    $b->post('battleEnter', ['bandId' => $bandB['id']]);
    checkEquals(2, count($b->get('state')['battle']['next']['entries']));

    // Los geht's: zwei Bands spielen gleich das Finale.
    Clock::set($next['startsAt'] + 5);
    $state = $a->get('state');
    $cur = $state['battle']['current'];
    checkEquals('running', $cur['state']);
    checkEquals(1, count($cur['rounds']));
    checkEquals('Finale', $cur['rounds'][0]['name']);
    check(count($cur['live']) === 1 && count($cur['live'][0]['lines']) >= 5, 'das Finale läuft mit Ansage');
    check(!isset($cur['rounds'][0]['matches'][0]['pa']), 'das Ergebnis ist noch geheim');
    check(bandById($state, $bandA['id'])['busyUntil'] >= $cur['endsAt'], 'die Band ist beim Battle');
    check($state['battle']['next']['startsAt'] >= $next['startsAt'] + 6 * 86400, 'Anmeldung für nächste Woche offen');
    checkApiError(fn () => $a->post('activityStart', ['bandId' => $bandA['id'], 'type' => 'rehearsal']), 'beschäftigt');

    // Vorbei: Platz, Preisgeld, Pokal; alle Labels erfahren den Sieger.
    $money = (int) $db->value('SELECT money FROM labels WHERE id = ?', [$repoA->labelId]);
    Clock::set($cur['endsAt'] + 2);
    $state = $a->get('state');
    $cur = $state['battle']['current'];
    checkEquals('done', $cur['state']);
    checkEquals([1, 2], array_column($cur['places'], 'place'));
    $res = array_values(array_filter($state['results'], static fn ($r) => $r['type'] === 'battle'))[0]['result'];
    $place = $res['battle']['place'];
    checkEquals($place, $cur['places'][0]['id'] === $bandA['id'] ? 1 : 2);
    checkEquals($money + Balance::BATTLE['money'][$place], (int) $state['label']['money'], 'Preisgeld');
    check($res['fame'] > 0, 'Ruhm');
    checkEquals(1, (int) $db->value("SELECT COUNT(*) FROM awards WHERE band_id = ? AND place = ?", [$bandA['id'], $place]), 'Pokal');
    $labelB = (int) $b->get('state')['label']['id'];
    check((int) $db->value("SELECT COUNT(*) FROM history WHERE label_id = ? AND kind = 'event' AND text LIKE '%gewinnt%'", [$labelB]) === 1, 'Nachricht an alle');
});

test('Battle: Mit nur einer Band fällt es aus; wer nicht antreten kann, fliegt raus', function () {
    $db = freshDb();
    $a = registerPlayer($db, 'Allein');
    $band = foundBand($a);
    $next = $a->get('state')['battle']['next'];
    $a->post('battleEnter', ['bandId' => $band['id']]);
    Clock::set($next['startsAt'] + 1);
    $cur = $a->get('state')['battle']['current'];
    checkEquals('cancelled', $cur['state']);
    check((int) $db->value("SELECT COUNT(*) FROM history WHERE kind = 'event' AND text LIKE '%fällt diesmal aus%'") === 1, 'Bescheid');
    checkEquals(0, (int) $db->value("SELECT COUNT(*) FROM activities WHERE type = 'battle'"));
});

/** Eine Band, die genau dieses Genre spielt (Besetzung nach Catalog::GENRE_LINEUP, mindestens zwei Musiker). */
function genreBand(Signed\Db $db, Player $p, string $genre, string $name): int
{
    $repo = new Signed\Game\Repo($db, (int) $p->get('state')['label']['id']);
    $instruments = [];
    foreach (Catalog::GENRE_LINEUP[$genre] as [$options, $need]) {
        array_push($instruments, ...array_fill(0, $need, $options[0]));
    }
    if (count($instruments) < 2) {
        $instruments[] = 'drums';
    }
    $ids = array_map(static fn ($i) => $repo->insertMusician(Signed\Game\Generator::musician('common', ['instrument' => $i, 'genre' => $genre, 'single' => true]), 'test'), $instruments);
    return $p->post('bandCreate', ['name' => $name, 'members' => array_map(static fn ($id) => ['id' => $id, 'slot' => 1], $ids)])['bandId'];
}

test('Festival: nur das Genre der Woche, Bühnen nach der Bekanntheit der Woche, Headliner zuletzt', function () {
    $db = freshDb();
    $a = registerPlayer($db, 'Festa');
    $b = registerPlayer($db, 'Festb');
    $next = $a->get('state')['festival']['next'];
    $genre = $next['genre'];
    check(str_starts_with($next['when'], 'Mittwoch') && str_starts_with($next['closeWhen'], 'Mittwoch'), $next['when']);
    check($next['closesAt'] < $next['startsAt'], 'Anmeldeschluss vor dem Beginn');
    $other = array_values(array_filter(array_keys(Catalog::GENRES), static fn ($g) => $g !== $genre))[0];
    $small = genreBand($db, $a, $genre, 'Kleine Fische');
    $wrong = genreBand($db, $a, $other, 'Falsches Genre');
    $big = genreBand($db, $b, $genre, 'Große Nummer');
    checkApiError(fn () => $a->post('festivalEnter', ['bandId' => $wrong]), 'sucht');
    $a->post('festivalEnter', ['bandId' => $small]);
    $b->post('festivalEnter', ['bandId' => $big]);
    checkApiError(fn () => $b->post('festivalEnter', ['bandId' => $big]), 'schon beworben');
    // In der Woche gesammelte Bekanntheit: Die große Band hat mehr.
    $repoA = new Signed\Game\Repo($db, (int) $a->get('state')['label']['id']);
    $repoB = new Signed\Game\Repo($db, (int) $b->get('state')['label']['id']);
    $repoB->addBandFame($big, array_fill_keys(array_column($repoB->members($big), 'id'), 10.0));
    $repoA->addBandFame($small, array_fill_keys(array_column($repoA->members($small), 'id'), 2.0));
    check(Signed\Game\Festival::weekGain($db, $big, Clock::now()) > Signed\Game\Festival::weekGain($db, $small, Clock::now()), 'Wochen-Bekanntheit');
    $db->exec('UPDATE labels SET money = 5000');
    // Zum Beginn (nicht zum Anmeldeschluss) muss die Band zurück sein.
    Clock::set($next['closesAt'] - 600);
    checkApiError(fn () => $a->post('bandRetreat', ['bandId' => $small, 'option' => 'urlaub']), 'Festival');

    // Anmeldeschluss: Das Line-up hängt aus.
    Clock::set($next['closesAt'] + 5);
    $cur = $a->get('state')['festival']['current'];
    checkEquals('lineup', $cur['state']);
    $main = $cur['stages'][0];
    checkEquals('Hauptbühne', $main['name']);
    checkEquals([$small, $big], array_column($main['acts'], 'bandId'), 'Headliner zuletzt');
    check($main['acts'][1]['headliner'], 'die große Band ist Headliner');
    checkEquals($next['startsAt'], $main['acts'][0]['at'], 'wenige Bands: es geht gleich los');
    check($a->get('state')['festival']['next']['startsAt'] > $next['startsAt'], 'Bewerbung für nächste Woche offen');
    Clock::set($next['startsAt'] - 1800);
    checkApiError(fn () => $a->post('bandRetreat', ['bandId' => $small, 'option' => 'ausflug']), 'Festival');

    // Beginn: Die erste Band spielt, die zweite ist schon dort.
    Clock::set($next['startsAt'] + 2);
    $state = $a->get('state');
    $act = array_values(array_filter($state['running'], static fn ($r) => $r['type'] === 'festival'));
    check(count($act) === 1 && $act[0]['bandId'] === $small && count($act[0]['timeline']) > 5, 'Auftritt läuft mit Ticker');
    checkEquals('openair', $act[0]['venue']);
    $stateB = $b->get('state');
    checkEquals([], array_values(array_filter($stateB['running'], static fn ($r) => $r['type'] === 'festival')), 'der Headliner spielt noch nicht');
    check(bandById($stateB, $big)['busyUntil'] > Clock::now(), 'ist aber schon auf dem Festival');

    // Vorbei: Ruhm, Gage, Interview-Anfrage (Festival-Ruhm zählt wie jeder andere); Plakat für den Headliner.
    $weekBefore = Signed\Game\Festival::weekGain($db, $big, Clock::now());
    Clock::set((int) $cur['endsAt'] + 2);
    $res = array_values(array_filter($a->get('state')['results'], static fn ($r) => $r['type'] === 'festival'))[0]['result'];
    check($res['fame'] > 0 && $res['money'] === Balance::FESTIVAL['stages']['main']['money'], 'Ruhm und Gage');
    check(!empty($res['festival']['interview']['medium']), 'Interview-Anfrage');
    check((int) $db->value("SELECT COUNT(*) FROM mail WHERE label_id = ? AND kind = 'interview' AND data LIKE ?", [$repoA->labelId, '%' . $res['festival']['interview']['medium'] . '%']) >= 1, 'Anfrage im Posteingang');
    $b->get('state');
    check(Signed\Game\Festival::weekGain($db, $big, Clock::now()) > $weekBefore, 'Festival-Ruhm zählt mit');
    checkEquals(1, (int) $db->value("SELECT COUNT(*) FROM awards WHERE band_id = ? AND kind = 'festival'", [$big]), 'Plakat');
    checkEquals('done', $a->get('state')['festival']['current']['state']);
});

test('Festival: Melden sich mehr Bands als Plätze, entscheidet das Los', function () {
    $db = freshDb();
    $a = registerPlayer($db, 'Viele');
    $next = $a->get('state')['festival']['next'];
    $slots = Signed\Game\Festival::slots();
    $ids = [];
    for ($i = 0; $i < $slots + 2; $i++) {
        $ids[] = genreBand($db, $a, $next['genre'], "Bewerber $i");
        $a->post('festivalEnter', ['bandId' => end($ids)]);
    }
    Clock::set($next['closesAt'] + 1);
    $cur = $a->get('state')['festival']['current'];
    checkEquals($slots, array_sum(array_map(static fn ($s) => count($s['acts']), $cur['stages'])));
    checkEquals(2, (int) $db->value("SELECT COUNT(*) FROM event_entries WHERE status = 'rejected'"));
    check((int) $db->value("SELECT COUNT(*) FROM history WHERE text LIKE '%für andere Bands entschieden%'") === 2, 'Absage vom Veranstalter');
});

/** Eine Band mit Platte, so bekannt wie angegeben (Ruhm auf die Mitglieder verteilt), Rampensäue (mögen Tourneen) und genug Geld. */
function tourBand(Signed\Db $db, Player $p, string $title, float $fame, int $money = 400000): array
{
    $band = foundBand($p);
    makeRecord($db, $p, $band['id'], $title, 4);
    $db->exec("UPDATE musicians SET personality = '[\"rampensau\"]', fame = ? WHERE band_id = ?", [$fame / 3, $band['id']]);
    $db->exec('UPDATE bands SET songs_written = 12 WHERE id = ?', [$band['id']]);
    $db->exec('UPDATE labels SET money = ?', [$money]);
    return $band;
}

test('Tournee: erst mit Ruhm und Bus; Europa Land für Land, in jeder Stadt ein Auftritt; unterwegs proben, zu Hause bleibt zu Hause', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Reisend');
    $band = tourBand($db, $p, 'Fernweh', 60);
    $start = static fn () => $p->post('tourStart', ['bandId' => $band['id'], 'region' => 'europa', 'stations' => 3]);
    checkApiError($start, 'Ruhm');
    checkApiError(fn () => $p->post('vehicleBuy', ['bandId' => $band['id'], 'kind' => 'bus']), 'lohnt sich erst');
    $db->exec('UPDATE musicians SET fame = 80 WHERE band_id = ?', [$band['id']]);
    checkApiError($start, 'Bus');
    $money = (int) $p->get('state')['label']['money'];
    $state = $p->post('vehicleBuy', ['bandId' => $band['id'], 'kind' => 'bus'])['state'];
    checkEquals(1, bandById($state, $band['id'])['vehicles']['bus']);
    checkEquals($money - Balance::VEHICLES['bus'][0]['cost'], (int) $state['label']['money']);
    checkApiError(fn () => $p->post('vehicleBuy', ['bandId' => $band['id'], 'kind' => 'plane']), 'lohnt sich erst');
    checkApiError(fn () => $p->post('tourStart', ['bandId' => $band['id'], 'region' => 'nordamerika']), 'Ruhm');
    checkApiError(fn () => $p->post('tourStart', ['bandId' => $band['id'], 'region' => 'europa', 'stations' => 4]), 'So lang');
    $relation = static fn () => (float) $db->value('SELECT AVG(value) FROM relations WHERE a_id IN (SELECT id FROM musicians WHERE band_id = ?)', [$band['id']]);
    $before = $relation();

    $res = $start();
    checkEquals('tourtravel', $res['activity']['type']);
    $tour = bandById($res['state'], $band['id'])['tour'];
    checkEquals('travel', $tour['state']);
    checkEquals('europa', Catalog::COUNTRIES[$tour['country']]['region'], 'ein Land in Europa');
    check($res['state']['label']['money'] < $money, 'Fahrt bezahlt');
    // Unterwegs: proben im Bus geht, alles, was nur zu Hause geht, nicht.
    checkApiError(fn () => $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'gig', 'venue' => 'kneipe']), 'Tournee');
    checkApiError(fn () => $p->post('bandRetreat', ['bandId' => $band['id'], 'option' => 'ausflug']), 'Tournee');
    checkApiError(fn () => $p->post('tourShow', ['bandId' => $band['id']]), 'nicht bereit');
    $rehearsal = $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'rehearsal'])['activity'];
    Clock::advance(120);
    $p->post('rehearsalStop', ['activityId' => $rehearsal['id']]);

    $gigs = 0;
    for ($station = 1; $station <= 3; $station++) {
        Clock::set($res['activity']['endsAt'] + 1);
        $state = $p->get('state');
        $arrived = array_values(array_filter($state['results'], static fn ($r) => $r['type'] === 'tourtravel'));
        check(count($arrived) === 1 && $arrived[0]['result']['station'] === $station, "angekommen im Land $station");
        $p->post('activityAck', ['activityId' => $arrived[0]['id']]);
        $tour = bandById($state, $band['id'])['tour'];
        checkEquals('arrived', $tour['state']);
        $cities = Catalog::COUNTRIES[$tour['country']]['cities'];
        checkEquals($cities, $tour['cities'], 'alle Städte des Landes');
        foreach ($cities as $i => $city) {
            $gig = $p->post('tourShow', ['bandId' => $band['id']])['activity'];
            checkEquals('tourgig', $gig['type']);
            check(str_contains($gig['title'], $city), "Auftritt in $city");
            Clock::set($gig['endsAt'] + 1);
            $state = $p->get('state');
            $played = array_values(array_filter($state['results'], static fn ($r) => $r['type'] === 'tourgig'))[0];
            check($played['result']['fame'] > 0 && $played['result']['money'] > 0, 'Ruhm und Gage');
            checkEquals(count($cities) - $i - 1, $played['result']['tour']['citiesLeft']);
            $p->post('activityAck', ['activityId' => $played['id']]);
            checkEquals($i === count($cities) - 1 ? 'after' : 'arrived', bandById($state, $band['id'])['tour']['state']);
            $gigs++;
        }
        if ($station < 3) {
            $next = $p->post('tourNext', ['bandId' => $band['id']]);
            check(!$next['short'] && !in_array($next['roll'], array_column($tour['legs'], 'to'), true), 'neues Land, keins doppelt');
            checkEquals('europa', Catalog::COUNTRIES[$next['roll']]['region']);
            $res = ['activity' => $next['activity']];
        }
    }
    checkApiError(fn () => $p->post('tourNext', ['bandId' => $band['id']]), 'letzte Land');
    $home = $p->post('tourHome', ['bandId' => $band['id']])['activity'];
    checkEquals('tour', $home['type']);
    Clock::set($home['endsAt'] + 1);
    $state = $p->get('state');
    $back = array_values(array_filter($state['results'], static fn ($r) => $r['type'] === 'tour'))[0]['result'];
    checkEquals($gigs, count($back['visits']), 'jede Stadt ein Auftritt');
    check($back['spent'] > 0 && $back['earned'] > 0, 'Kosten und Einnahmen');
    checkEquals(null, bandById($state, $band['id'])['tour'], 'Tournee vorbei');
    check($relation() > $before, 'die Band ist zusammengewachsen');
    $p->post('activityStart', ['bandId' => $band['id'], 'type' => 'gig', 'venue' => 'kneipe']);
    // Auch berühmt: Zu Hause gibt es keine Arena, die gibt es nur auf Tournee.
    $db->exec('UPDATE musicians SET fame = 1500 WHERE band_id = ?', [$band['id']]);
    $keys = array_column(Signed\Game\Gigs::options(new Signed\Game\Repo($db, (int) $state['label']['id']), $db->one('SELECT * FROM bands WHERE id = ?', [$band['id']]) + ['gigs_since_record' => 0], [])['options'] ?? [], 'key');
    check(!in_array('arena', $keys, true), 'keine Arena zu Hause');
});

test('Tournee: Wer Auftritte nicht mag, ärgert sich einmal je Land, nicht in jeder Stadt neu', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Gemuetlich');
    $band = tourBand($db, $p, 'Sofa', 300);
    $db->exec("UPDATE musicians SET personality = '[\"gelassen\"]', wellbeing = 60 WHERE band_id = ?", [$band['id']]);
    $p->post('vehicleBuy', ['bandId' => $band['id'], 'kind' => 'bus']);
    $res = $p->post('tourStart', ['bandId' => $band['id'], 'region' => 'europa', 'stations' => 3]);
    // Deutschland hat drei Städte
    $db->exec("UPDATE tours SET country = 'de' WHERE band_id = ?", [$band['id']]);
    Clock::set($res['activity']['endsAt'] + 1);
    $p->get('state');
    $mood = static fn () => (float) $db->value('SELECT AVG(wellbeing) FROM musicians WHERE band_id = ?', [$band['id']]);
    $after = [];
    foreach (Catalog::COUNTRIES['de']['cities'] as $city) {
        $gig = $p->post('tourShow', ['bandId' => $band['id']])['activity'];
        check(str_contains($gig['title'], $city), "Auftritt in $city");
        Clock::set($gig['endsAt'] + 1);
        $p->get('state');
        $after[] = $mood();
    }
    check($after[0] < 60 - Balance::MOOD['reactStep'] / 2, 'der erste Auftritt im Land ärgert');
    check($after[2] > $after[0] - 3, 'die weiteren Städte nicht noch einmal');
});

test('Tournee: Wer zu lange nicht weitermacht, fährt von selbst heim; mit Anmeldung zum Battle geht es nicht los', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Wartet');
    $band = tourBand($db, $p, 'Koffer', 300);
    $p->post('vehicleBuy', ['bandId' => $band['id'], 'kind' => 'bus']);
    $p->post('battleEnter', ['bandId' => $band['id']]);
    checkApiError(fn () => $p->post('tourStart', ['bandId' => $band['id'], 'region' => 'europa', 'stations' => 3]), 'angemeldet');
    $p->post('battleWithdraw', ['bandId' => $band['id']]);
    $res = $p->post('tourStart', ['bandId' => $band['id'], 'region' => 'europa', 'stations' => 3]);
    checkEquals('travel', bandById($p->get('state'), $band['id'])['tour']['state']);
    checkApiError(fn () => $p->post('battleEnter', ['bandId' => $band['id']]), 'Tournee');
    Clock::set($res['activity']['endsAt'] + 1);
    $p->get('state');
    Clock::advance(Balance::TOUR['wait'] + 60);
    $state = $p->get('state');
    checkEquals('home', bandById($state, $band['id'])['tour']['state'], 'fährt von selbst heim');
    check((bool) $db->value("SELECT id FROM history WHERE text LIKE '%zu lange gewartet%'"), 'Meldung');
    Clock::advance(4 * 3600);
    checkEquals(null, bandById($p->get('state'), $band['id'])['tour']);
});

test('Battle: nur Bands, keine Solokünstler', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Solo');
    $state = $p->get('state');
    $solo = array_values(array_filter($state['bands'], static fn ($b) => count($b['memberIds']) === 1))[0];
    checkApiError(fn () => $p->post('battleEnter', ['bandId' => $solo['id']]), 'keine Solokünstler');
    checkEquals([], array_values(array_filter($state['battle']['next']['options'], static fn ($o) => $o['bandId'] === $solo['id'])), 'nicht in der Auswahl');
});

test('Tournee: Kontinente mit dem Flugzeug, alle Länder; Welttournee im Stadion; wer überall gespielt hat, bekommt den Globus', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Globus');
    $band = tourBand($db, $p, 'Weltkarte', 1000);
    checkApiError(fn () => $p->post('tourStart', ['bandId' => $band['id'], 'region' => 'asien']), 'Flugzeug');
    $p->post('vehicleBuy', ['bandId' => $band['id'], 'kind' => 'plane']);
    checkApiError(fn () => $p->post('tourStart', ['bandId' => $band['id'], 'region' => 'welt', 'stations' => 5]), 'Ruhm');
    // Asien-Pazifik: alle Länder der Region, Reihenfolge ausgewürfelt; unterwegs fragen Medien im Land.
    $res = $p->post('tourStart', ['bandId' => $band['id'], 'region' => 'asien']);
    $tour = bandById($res['state'], $band['id'])['tour'];
    checkEquals(count(Signed\Game\Tours::countriesOf('asien')), $tour['stations']);
    checkEquals('plane', $tour['vehicle']);
    Clock::set($res['activity']['endsAt'] + 1);
    $country = bandById($p->get('state'), $band['id'])['tour']['country'];
    check(in_array($country, ['jp', 'kr', 'au'], true), 'Asien-Pazifik');
    $p->post('tourHome', ['bandId' => $band['id']]);
    Clock::advance(6 * 3600);
    checkEquals(null, bandById($p->get('state'), $band['id'])['tour'], 'zurück');

    // Welttournee: nur die größte Stadt je Land; mit Arena-Ruhm in der Arena, erst ab Stadion-Ruhm im Stadion
    $db->exec('UPDATE musicians SET fame = 1000 WHERE band_id = ?', [$band['id']]);
    $start = $p->post('tourStart', ['bandId' => $band['id'], 'region' => 'welt', 'stations' => 5]);
    Clock::set($start['activity']['endsAt'] + 1);
    $tour = bandById($p->get('state'), $band['id'])['tour'];
    $country = $tour['country'];
    checkEquals([Catalog::COUNTRIES[$country]['cities'][0]], $tour['cities'], 'nur die größte Stadt');
    $arena = $p->post('tourShow', ['bandId' => $band['id']])['activity'];
    check(str_contains($arena['title'], 'Arena'), 'unter Stadion-Ruhm in der Arena: ' . $arena['title']);
    // Für das Stadion: dasselbe Land noch einmal, jetzt mit Stadion-Ruhm (nur für den Test).
    Clock::set($arena['endsAt'] + 1);
    $p->get('state');
    $p->post('activityAck', ['activityId' => $arena['id']]);
    $db->exec("UPDATE tours SET state = 'arrived', data = ? WHERE band_id = ? AND state <> 'ended'", [json_encode(['visits' => []] + json_decode((string) $db->value("SELECT data FROM tours WHERE band_id = ? AND state <> 'ended'", [$band['id']]), true)), $band['id']]);
    $db->exec('UPDATE musicians SET fame = ? WHERE band_id = ?', [Balance::TOUR['stadium']['minFame'] / 2, $band['id']]);
    $gig = $p->post('tourShow', ['bandId' => $band['id']])['activity'];
    check(str_contains($gig['title'], 'Stadion'), 'ab Stadion-Ruhm im Stadion: ' . $gig['title']);
    checkEquals('tour-' . $country, $gig['venue'], 'Bühne des Landes');
    checkEquals(Balance::TOUR['stadium']['songs'], $gig['songs'], 'Stadion: lange Setlist');
    // Alle anderen Länder hat die Band schon auf früheren Tourneen gespielt.
    $labelId = (int) $p->get('state')['label']['id'];
    $others = array_values(array_diff(array_keys(Catalog::COUNTRIES), [$country]));
    $db->insert('tours', ['label_id' => $labelId, 'band_id' => $band['id'], 'state' => 'ended', 'stations' => count($others), 'country' => null, 'deadline' => 0, 'started_at' => 1, 'ended_at' => 2,
        'data' => json_encode(['legs' => [], 'visits' => array_map(static fn ($c) => ['country' => $c, 'match' => false, 'fame' => 1, 'money' => 1, 'meter' => 50], $others)])]);
    checkEquals(count($others), count(bandById($p->get('state'), $band['id'])['toured']));
    Clock::set($gig['endsAt'] + 1);
    $state = $p->get('state');
    $res = array_values(array_filter($state['results'], static fn ($r) => $r['type'] === 'tourgig'))[0]['result'];
    check($res['tour']['world'], 'Globus');
    checkEquals(count(Catalog::COUNTRIES), $res['tour']['played']);
    $kinds = array_column(bandById($state, $band['id'])['awards'], 'kind');
    check(in_array('world', $kinds, true) && in_array('stadium', $kinds, true), 'Globus und erstes Stadion: ' . implode(',', $kinds));
    checkEquals(1, (int) $db->value("SELECT COUNT(*) FROM awards WHERE kind = 'world'"), 'nur einmal');
    checkEquals('after', bandById($state, $band['id'])['tour']['state'], 'Land fertig');
});

test('Tournee: Medien im Tourland fragen nach Interviews, das Fahrzeug ist unterwegs der Proberaum, Ausbau macht die Reise kürzer', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Medien');
    $band = tourBand($db, $p, 'Radio', 900);
    $p->post('vehicleBuy', ['bandId' => $band['id'], 'kind' => 'bus']);
    $t = Signed\Game\Tours::class;
    $km = $t::km('home', 'es');
    check($t::travelSeconds($km, 'bus', 4) < 0.6 * $t::travelSeconds($km, 'bus', 1), 'Nightliner ist schneller');
    check($t::travelSeconds($t::km('home', 'au'), 'plane', 1) < $t::travelSeconds($t::km('home', 'au'), 'bus', 1), 'fliegen geht schneller');
    $res = $p->post('tourStart', ['bandId' => $band['id'], 'region' => 'europa', 'stations' => 3]);
    $repo = new Signed\Game\Repo($db, (int) $res['state']['label']['id']);
    $rules = Signed\Game\Rooms::here($repo, $repo->band($band['id']), 3);
    checkEquals(Balance::VEHICLES['bus'][0]['skill'], $rules['skill'], 'im Bus geprobt');
    Clock::set($res['activity']['endsAt'] + 1);
    $tour = bandById($p->get('state'), $band['id'])['tour'];
    $city = Catalog::COUNTRIES[$tour['country']]['cities'][0];
    // Viele Anfragen erzwingen: Die nächsten kommen von Medien im Land.
    $db->exec('UPDATE labels SET interview_at = ?', [Clock::now() - 3 * 86400]);
    $db->exec('UPDATE musicians SET fame = 2000 WHERE band_id = ?', [$band['id']]);
    $p->get('state');
    $media = array_map(static fn ($r) => json_decode($r['data'], true)['medium'], $db->all("SELECT data FROM mail WHERE kind = 'interview'"));
    check($media && count(array_filter($media, static fn ($m) => str_contains($m, $city))) === count($media), 'Medien aus ' . $city . ': ' . implode(' | ', $media));
});

test('Auszeichnungen: verkaufte Platten bringen Gold und Platin (einmal je Platte), große Bühnen und Platz 1; neue werden einmal gefeiert', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Gold');
    $band = foundBand($p);
    $made = makeRecord($db, $p, $band['id'], 'Goldkehlchen', 3);
    $recordId = (int) $db->value('SELECT id FROM records WHERE band_id = ?', [$band['id']]);
    $db->exec('UPDATE records SET chart_points = 200 WHERE id = ?', [$recordId]);
    Clock::advance(12 * 3600);
    $state = $p->get('state');
    $sold = bandById($state, $band['id'])['discography'][0]['sold'];
    check($sold >= Balance::AWARDS['records']['gold'], 'verkauft: ' . $sold);
    $kinds = array_column($state['awardsNew'], 'kind');
    check(in_array('gold', $kinds, true), 'Gold neu');
    $p->get('state');
    checkEquals(1, (int) $db->value("SELECT COUNT(*) FROM awards WHERE kind = 'gold' AND record_id = ?", [$recordId]), 'nur einmal');
    foreach ($state['awardsNew'] as $a) {
        $p->post('awardSeen', ['awardId' => $a['id']]);
    }
    checkEquals([], $p->get('state')['awardsNew'], 'gefeiert');
    check((bool) $db->value("SELECT id FROM history WHERE kind = 'award' AND text LIKE '%Goldene Schallplatte%'"), 'Chronik');

    // Große Bühne und Platz 1 direkt
    $repo = new Signed\Game\Repo($db, (int) $state['label']['id']);
    Signed\Game\Awards::bigStage($repo, $band['id'], 'halle');
    Signed\Game\Awards::bigStage($repo, $band['id'], 'halle');
    Signed\Game\Awards::bigStage($repo, $band['id'], 'club');
    checkEquals(1, (int) $db->value("SELECT COUNT(*) FROM awards WHERE kind = 'hall'"));
    Signed\Game\Charts::markTop($db, [['rank' => 1, 'topped' => false, 'labelId' => $repo->labelId, 'bandId' => $band['id'], 'recordId' => $recordId, 'title' => 'Goldkehlchen']]);
    checkEquals(0, (int) $db->value("SELECT COUNT(*) FROM awards WHERE kind = 'number1'"), 'ohne Konkurrenz kein Platz 1');
    $albums = [['rank' => 1, 'topped' => false, 'labelId' => $repo->labelId, 'bandId' => $band['id'], 'recordId' => $recordId, 'title' => 'Goldkehlchen'], ['rank' => 2, 'labelId' => 900], ['rank' => 3, 'labelId' => 901]];
    Signed\Game\Charts::markTop($db, $albums);
    checkEquals(1, (int) $db->value("SELECT COUNT(*) FROM awards WHERE kind = 'number1'"));
});

test('Auszeichnungen beim Umbau: bisherige Verkäufe werden geschätzt, die Auszeichnungen gibt es ohne Feier', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Altgold');
    $band = foundBand($p);
    makeRecord($db, $p, $band['id'], 'Alter Hit', 3);
    $id = (int) $db->value('SELECT id FROM records WHERE band_id = ?', [$band['id']]);
    $db->exec('UPDATE records SET chart_points = 100, finished_at = ?, royalties_until = ?, topped = 1, sold = 0 WHERE id = ?', [Clock::now() - 30 * 86400, Clock::now(), $id]);
    Signed\Game\Awards::backfill($db);
    $sold = (float) $db->value('SELECT sold FROM records WHERE id = ?', [$id]);
    check($sold > 300000 && $sold < 340000, 'geschätzt: ' . $sold);
    checkEquals(['gold', 'platinum', 'number1'], array_column($db->all('SELECT kind FROM awards WHERE record_id = ? ORDER BY id', [$id]), 'kind'));
    checkEquals([], $p->get('state')['awardsNew'], 'keine Feier');
});

test('Übergang alter Labels: Börse zu, Karten in Geld, alte Aktionen abgerechnet, Beziehungen aus der Bandzeit, Kartei zur Wahl', function () {
    $db = freshDb();
    $p = registerPlayer($db, 'Altlabel');
    $band = foundBand($p);
    $bidder = registerPlayer($db, 'Bieter');
    $L = (int) $p->get('state')['label']['id'];
    $B = (int) $bidder->get('state')['label']['id'];
    $repo = new Signed\Game\Repo($db, $L);
    // Wie im alten Spiel: Band seit zwei Wochen, Kartei mit sieben Musikern, einer davon an der Börse mit Gebot
    $db->exec('UPDATE bands SET created_at = ? WHERE id = ?', [Clock::now() - 14 * 86400, $band['id']]);
    $db->exec('UPDATE musicians SET created_at = ? WHERE band_id = ?', [Clock::now() - 20 * 86400, $band['id']]);
    $db->exec('DELETE FROM relations');
    $pool = [];
    foreach (['guitar', 'bass', 'drums', 'vocals', 'keys', 'sax', 'violin'] as $instrument) {
        $pool[] = $repo->insertMusician(Signed\Game\Generator::musician('common', ['instrument' => $instrument, 'genre' => 'rock', 'single' => true]), 'aushang');
    }
    $p->get('state');
    $db->insert('transfers', ['label_id' => $L, 'musician_id' => $pool[0], 'min_price' => 200, 'best_amount' => 900, 'best_label_id' => $B, 'bids' => 1, 'status' => 'open', 'created_at' => Clock::now(), 'ends_at' => Clock::now() + 86400]);
    $db->exec('UPDATE labels SET scout_vouchers = 1 WHERE id = ?', [$L]);
    $db->exec('UPDATE labels SET scout_vouchers = 0 WHERE id = ?', [$B]);
    $db->insert('items', ['label_id' => $L, 'kind' => 'fan', 'value' => 2.0, 'created_at' => Clock::now()]);
    $db->insert('pack_offers', ['label_id' => $L, 'pack' => 'aushang', 'cards' => '[]', 'status' => 'open', 'created_at' => Clock::now()]);
    $members = $repo->members($band['id']);
    $db->insert('activities', ['label_id' => $L, 'band_id' => $band['id'], 'type' => 'gig', 'started_at' => Clock::now() - 7200, 'ends_at' => Clock::now() - 7000, 'settled' => 1, 'seen' => 0, 'data' => '{}']);
    $db->insert('activities', ['label_id' => $L, 'band_id' => $band['id'], 'type' => 'rehearsal', 'started_at' => Clock::now() - 60, 'ends_at' => Clock::now() + 600, 'settled' => 0, 'seen' => 0,
        'data' => json_encode(['title' => 'Bandprobe', 'reward' => ['skill' => [$members[0]['id'] => 0.5], 'cohesion' => 5]])]);
    $skillBefore = (float) $db->value('SELECT skill1 FROM musicians WHERE id = ?', [$members[0]['id']]);
    $money = (int) $db->value('SELECT money FROM labels WHERE id = ?', [$L]);
    $bidderMoney = (int) $db->value('SELECT money FROM labels WHERE id = ?', [$B]);

    Signed\Game\Transition::prepare($db, 22);

    checkEquals('withdrawn', $db->value('SELECT status FROM transfers WHERE musician_id = ?', [$pool[0]]));
    checkEquals($bidderMoney + 900, (int) $db->value('SELECT money FROM labels WHERE id = ?', [$B]), 'Gebot zurück');
    $c = Balance::TRANSITION;
    checkEquals($money + (int) round(2 * $c['fanFame']) + $c['voucher'] + Balance::PACKS['aushang']['price'], (int) $db->value('SELECT money FROM labels WHERE id = ?', [$L]), 'Karten und Gutscheine in Geld');
    checkEquals(0, (int) $db->value('SELECT COUNT(*) FROM items WHERE label_id = ?', [$L]));
    checkEquals(0, (int) $db->value("SELECT COUNT(*) FROM activities WHERE label_id = ? AND seen = 0", [$L]), 'alte Ergebnisse nicht noch einmal');
    check(abs((float) $db->value('SELECT skill1 FROM musicians WHERE id = ?', [$members[0]['id']]) - $skillBefore - 0.5) < 0.001, 'alte Probe abgerechnet');
    // Beziehungen: Zielwert der Passung × (1 − e^(−2)) nach zwei Wochen
    $rel = $db->all('SELECT a_id, b_id, value FROM relations');
    checkEquals(3, count($rel));
    $byId = array_column($members, null, 'id');
    foreach ($rel as $r) {
        $target = Signed\Game\Mood::relationTarget(Signed\Game\Mood::fit($byId[(int) $r['a_id']], $byId[(int) $r['b_id']]));
        check(abs((float) $r['value'] - $target * (1 - exp(-2))) < 0.01, "Beziehung {$r['value']} zu Ziel $target");
    }

    $state = $p->get('state');
    checkEquals(3, count($state['musicians']), 'Kartei ist beiseite');
    checkEquals(7, count($state['transition']['pool']));
    checkEquals(1, $state['transition']['market']);
    checkApiError(fn () => $p->post('transitionDone', ['keep' => array_slice($pool, 0, 6)]), 'höchstens');
    checkApiError(fn () => $p->post('transitionDone', ['keep' => [$members[0]['id']]]), 'nicht zur Wahl');
    $state = $p->post('transitionDone', ['keep' => [$pool[0], $pool[3]]])['state'];
    checkEquals(null, $state['transition']);
    checkEquals(5, count($state['musicians']), 'zwei behalten');
    checkEquals(5, (int) $db->value("SELECT COUNT(*) FROM alumni WHERE label_id = ? AND reason = 'released'", [$L]), 'die übrigen gehen (WikiSignia)');
    checkEquals(null, $p->get('state')['transition']);

    // Wenige Musiker ohne Band: niemand muss gehen
    $state = $bidder->get('state');
    checkEquals([], $state['transition']['pool']);
    checkEquals(3, count($state['musicians']));
    checkEquals(null, $bidder->post('transitionDone', ['keep' => []])['state']['transition']);
});

test('Übergang in der Testversion: nur Labels mit altem Kram, neue Künstler bleiben', function () {
    $db = freshDb();
    $new = registerPlayer($db, 'Neuling');
    $db->exec("UPDATE musicians SET origin = 'submission'");
    $old = registerPlayer($db, 'Altkram');
    $repo = new Signed\Game\Repo($db, (int) $old->get('state')['label']['id']);
    foreach (range(1, 6) as $i) {
        $repo->insertMusician(Signed\Game\Generator::musician('common', ['instrument' => 'guitar', 'genre' => 'rock', 'single' => true]), 'aushang');
    }
    $signed = (int) $db->value('SELECT MIN(id) FROM musicians WHERE label_id = ?', [$repo->labelId]);
    $db->exec("UPDATE musicians SET origin = 'submission' WHERE id = ?", [$signed]);
    Signed\Game\Transition::prepare($db, 52);
    checkEquals(null, $new->get('state')['transition'], 'neues Label unberührt');
    $state = $old->get('state');
    checkEquals(8, count($state['transition']['pool']), 'nur alte Herkunft zur Wahl');
    checkEquals([$signed], array_column($state['musicians'], 'id'), 'wer aus dem Posteingang kam, bleibt');
});
