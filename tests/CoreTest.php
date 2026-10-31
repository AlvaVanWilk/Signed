<?php
declare(strict_types=1);

use Signed\Game\Core\Career;
use Signed\Game\Core\Concert;
use Signed\Game\Core\Events;
use Signed\Game\Core\Fans;
use Signed\Game\Core\Hype;
use Signed\Game\Core\Needs;
use Signed\Game\Core\Quality;
use Signed\Game\Core\Sales;
use Signed\Game\Core\Skill;
use Signed\Game\Core\Songs;

// Neuer Kern (src/Game/Core): Die Zahlen stammen aus dem Dokument „Signed – Rechenwege“.

/** Gleich bis auf $tolerance (Anteil, 0,03 = 3 %) bzw. absolut bei erwartetem 0. */
function coreNear(float $expected, float $actual, float $tolerance = 1e-9, string $message = ''): void
{
    $ok = $expected == 0.0 ? abs($actual) <= $tolerance : abs($actual - $expected) <= abs($expected) * $tolerance;
    check($ok, trim("$message – erwartet $expected, erhalten $actual"));
}

/** Eine Platte ohne Konzerte laufen lassen, bis sie an einem Tag weniger als ein Stück verkauft: [Tage, verkauft, neue Fans]. */
function coreRun(float $fans, float $quality): array
{
    $record = Sales::release($fans, $quality, 0);
    $sold = $gained = 0.0;
    while (true) {
        [$record, $s, $g] = Sales::flow($record, Sales::nextDayAt($record));
        $sold += $s;
        $gained += $g;
        if (Sales::isOver($record)) {
            return [$record['day'] + 1, $sold, $gained];
        }
        $record = Sales::nextDay($record, $fans + $gained);
    }
}

/**
 * Eine Woche einer Genre-Band: Platte zum Wochenbeginn, Auftritte zu diesen Stunden (jeden $every-ten Tag), Live-Qualität 70.
 * @return array{records: float, gigs: float, catalog: float, fans: float, career: array}
 */
function coreWeek(float $fans, float $quality, array $hours, int $every = 1, int $days = 7, string $kind = 'genre'): array
{
    $t0 = 1_800_000_000;
    $career = Career::release(Career::start($fans, $kind, $t0), $t0, $quality);
    for ($d = 0; $d < $days; $d++) {
        if ($d % $every === 0) {
            foreach ($hours as $h) {
                [$career] = Career::gig($career, $t0 + $d * 86400 + $h * 3600, 70.0);
            }
        }
    }
    $career = Career::settle($career, $t0 + $days * 86400);
    return ['records' => $career['earned']['records'], 'gigs' => $career['earned']['gigs'], 'catalog' => $career['catalogSold'], 'fans' => $career['fans'], 'career' => $career];
}

/** Laufbahn einer neuen Genre-Band: eine Platte je Woche (Zweierband), Auftritte zu diesen Stunden. Woche, in der sie $goal Fans hat. */
function coreCareer(float $quality, array $hours, float $goal = 50000, int $weeksMax = 200): int
{
    $t0 = 1_800_000_000;
    $career = Career::start(0, 'genre', $t0);
    for ($week = 1; $week <= $weeksMax; $week++) {
        $start = $t0 + ($week - 1) * 7 * 86400;
        $career = Career::release($career, $start, $quality);
        for ($d = 0; $d < 7; $d++) {
            foreach ($hours as $h) {
                [$career] = Career::gig($career, $start + $d * 86400 + $h * 3600, 70.0);
            }
        }
        $career = Career::settle($career, $start + 7 * 86400);
        if ($career['fans'] >= $goal - 1) {
            return $week;
        }
    }
    return 0;
}

test('Neuer Kern: Ruhmstufen, Dach und Rechen-Pool', function () {
    foreach ([0 => 0, 499.9 => 0, 500 => 1, 4999 => 1, 5000 => 2, 49999.5 => 2, 50000 => 3, 90000 => 3] as $fans => $stage) {
        checkEquals($stage, Fans::stage((float) $fans), "Stufe bei $fans Fans");
    }
    checkEquals(4999, Fans::roof('mixed'));
    checkEquals(50000, Fans::roof('genre'));
    checkEquals(75000, Fans::roof('hybrid'));
    checkEquals(90000, Fans::roof('crossover'));
    checkEquals(4999, Fans::roof('unbekannt'), 'Unbekannte Art zählt wie gemischt');
    coreNear(50000.0, Fans::capped(60000.0, 'genre'));
    foreach ([0 => 50000, 40000 => 50000, 45000 => 55000, 70000 => 80000] as $fans => $pool) {
        coreNear((float) $pool, Fans::pool((float) $fans), 1e-9, "Pool bei $fans Fans");
    }
});

test('Neuer Kern: Hype halbiert sich jeden Tag, perfekte Platten halten länger', function () {
    coreNear(64.0, Hype::start(80));
    foreach ([1 => 32.0, 2 => 16.0, 3 => 8.0, 4 => 4.0] as $days => $hype) {
        coreNear($hype, Hype::afterDays(80, $days), 1e-9, "Tag $days");
    }
    checkEquals('red', Hype::flame(64));
    checkEquals('weak', Hype::flame(32));
    checkEquals('ember', Hype::flame(8));
    checkEquals('none', Hype::flame(4), 'Am fünften Tag ist die Flamme aus');
    checkEquals('blaze', Hype::flame(90.25));
    coreNear(90.25, Hype::start(95));
    coreNear(42.25, Hype::start(65));
    coreNear(90.25 * 0.55, Hype::next(90.25, 95), 1e-9, 'Perfekte Platte');
    // Perfekte Platte: Mit × 0,55 ist ihr Hype erst am neunten Tag unter 1, mit × 0,5 schon am achten.
    check(Hype::afterDays(95, 7) >= 1 && Hype::afterDays(95, 8) < 1 && Hype::start(95) * 0.5 ** 7 < 1, 'Eine perfekte Platte hält etwa einen Tag länger');
});

test('Neuer Kern: Jeder weitere Schub zählt halb so viel, Hype höchstens 100', function () {
    checkEquals([60.0, 1], Hype::boost(50.0, 10.0, 0));
    checkEquals([65.0, 2], Hype::boost(60.0, 10.0, 1));
    checkEquals([67.5, 3], Hype::boost(65.0, 10.0, 2));
    checkEquals([100.0, 1], Hype::boost(95.0, 10.0, 0));
    [$hype, $count] = [0.0, 0];
    for ($i = 0; $i < 40; $i++) {
        [$hype, $count] = Hype::boost($hype, 10.0, $count);
    }
    check($hype < 20.0 && $hype > 19.99, "Viele Schübe bringen höchstens das Doppelte des größten: $hype");
});

test('Neuer Kern: Fans kaufen eine gute Platte eher', function () {
    foreach ([65 => 0.4225, 75 => 0.5625, 80 => 0.64, 88 => 0.7744, 95 => 0.9025, 100 => 1.0] as $q => $share) {
        coreNear($share, Sales::buyShare($q), 1e-9, "Q $q");
    }
});

test('Neuer Kern: Probe-Rechnungen aus dem Dokument', function () {
    // [Fans, Q] => [Tage, verkauft, neue Fans], gerundet wie im Dokument
    $cases = [[0, 50, 6, 37, 22], [0, 80, 7, 95, 57], [0, 100, 9, 166, 99], [5000, 80, 12, 3630, 258]];
    foreach ($cases as [$fans, $q, $days, $sold, $gained]) {
        [$d, $s, $g] = coreRun($fans, $q);
        checkEquals($days, $d, "Laufzeit bei $fans Fans, Q $q");
        coreNear($sold, $s, 0.01, "Verkauft bei $fans Fans, Q $q");
        coreNear($gained, $g, 0.01, "Neue Fans bei $fans Fans, Q $q");
    }
    // Erster Tag einer neuen Band, Q 80: 0,0015 × 0,64 × 50.000 = 48 verkauft, 60 % davon werden Fans
    $record = Sales::release(0, 80, 0);
    coreNear(48.0, $record['soldRate']);
    coreNear(28.8, $record['fanRate']);
});

test('Neuer Kern: So viele kommen zu einem Auftritt', function () {
    // [Fans, Hype] => [Zuschauer, Ort, Plätze], erster Tag einer Platte, volle Frische
    $cases = [[500, 64, 244.8, 'kneipe', 300], [500, 0, 34.75, 'kneipe', 60], [5000, 64, 1341.7, 'club', 1500], [5000, 100, 1500, 'club', 1500], [20000, 64, 1500, 'club', 1500], [0, 0, 25, 'kneipe', 60]];
    foreach ($cases as [$fans, $hype, $tickets, $venue, $size]) {
        $gig = Concert::play($fans, $hype, $fans, Fans::pool($fans), 1.0, 70.0);
        coreNear($tickets, $gig['tickets'], 1e-4, "Zuschauer bei $fans Fans, Hype $hype");
        checkEquals([$venue, $size], [$gig['venue'], $gig['size']], "Ort bei $fans Fans, Hype $hype");
    }
    coreNear(2027.5, Concert::play(5000, 100, 5000, 50000, 1.0, 70.0)['demand'], 1e-9, 'Club voll: Es wollten mehr kommen');
    coreNear(4773.4, Concert::play(20000, 64, 20000, 50000, 1.0, 70.0)['demand'], 1e-4);
    coreNear(7225.0, Concert::play(20000, 100, 20000, 50000, 1.0, 70.0)['demand'], 1e-9);
    // Ohne Hype: 500 Fans gut 200 ₲ (zwei graue Instrumente), neue Band 125 ₲
    coreNear(208.5, Concert::play(500, 0, 500, 50000, 1.0, 70.0)['earnings']);
    coreNear(125.0, Concert::play(0, 0, 0, 50000, 1.0, 70.0)['earnings']);
    // Auf Tournee passen mehr hinein (Halle ab Stufe 2)
    checkEquals(['halle', 5000], Concert::venue(2, 4773.4, true));
    checkEquals(['club', 1500], Concert::venue(2, 4773.4, false));
    checkEquals(['club', 1500], Concert::venue(3, 30000, false), 'Zu Hause höchstens der Club');
    checkEquals(['arena', 20000], Concert::venue(3, 30000, true));
    // Ist der Ort voll, werden nur die Neugierigen Fans, die hineinpassen
    $full = Concert::play(5000, 100, 5000, 50000, 1.0, 100.0);
    coreNear(427.5 * (1500 / 2027.5) * 0.3, $full['newFans']);
    checkEquals(0.0, Concert::play(500, 64, 500, 50000, 1.0, 0.0)['newFans'], 'Ohne Show keine neuen Fans');
});

test('Neuer Kern: Frische halbiert sich je Auftritt und ist nach einem Tag wieder ganz', function () {
    $t = 1_800_000_000;
    coreNear(1.0, Concert::freshness(0.3, null, $t), 1e-9, 'Vor dem ersten Auftritt');
    $fresh = Concert::afterGig(1.0);
    coreNear(0.5, $fresh);
    coreNear(0.875, Concert::freshness($fresh, $t, $t + 9 * 3600));
    coreNear(1.0, Concert::freshness($fresh, $t, $t + 12 * 3600));
    coreNear(1.0, Concert::freshness(0.0, $t, $t + 24 * 3600), 1e-9, 'Von nichts in einem Tag ganz');
    coreNear(0.25, Concert::afterGig(Concert::freshness($fresh, $t, $t)), 1e-9, 'Gleich noch einmal: halb so viele');
});

test('Neuer Kern: Eine Woche bringt, was im Dokument steht', function () {
    // Platte Q 80, Konzerte um 15 Uhr bzw. 9 und 18 Uhr; Dokument: Platte | einmal | zweimal | jeden zweiten Tag (₲)
    $doc = [0 => [1402, 1956, 3811, 1186], 500 => [4988, 4154, 7982, 2582], 5000 => [36866, 30247, 57258, 19292], 25000 => [169291, 70880, 138154, 40267]];
    foreach ($doc as $fans => [$record, $once, $twice, $second]) {
        $week = coreWeek($fans, 80, [15]);
        coreNear($record, $week['records'], 0.03, "Platte bei $fans Fans");
        coreNear($once, $week['gigs'], 0.03, "Einmal am Tag bei $fans Fans");
        coreNear($twice, coreWeek($fans, 80, [9, 18])['gigs'], 0.03, "Zweimal am Tag bei $fans Fans");
        coreNear($second, coreWeek($fans, 80, [15], 2)['gigs'], 0.03, "Jeden zweiten Tag bei $fans Fans");
    }
    // Erste Platte einer neuen Band, einmal am Tag aufgetreten: rund 140 verkauft, 134 Fans
    $first = coreWeek(0, 80, [15]);
    coreNear(134.0, $first['fans'], 0.03);
    coreNear(140.0, $first['career']['sold'], 0.05);
    // Öfter auftreten bringt mehr, aber jedes Mal weniger
    $twelve = coreWeek(0, 80, range(9, 20))['gigs'];
    check($twelve > coreWeek(0, 80, [9, 18])['gigs'] && $twelve < 3 * 3811, "Zwölfmal am Tag: $twelve ₲");
});

test('Neuer Kern: Katalog verkauft weiter, jede Woche halb so viel', function () {
    coreNear(48.0, Sales::catalogPerDay(5000, 80, 0));
    coreNear(24.0, Sales::catalogPerDay(5000, 80, 7));
    $week = 0.0;
    for ($d = 0; $d < 7; $d++) {
        $week += Sales::catalogPerDay(5000, 80, $d);
    }
    coreNear(255.0, $week, 0.01, 'Erste Woche');
    // In der Laufbahn wachsen die Fans dabei etwas; Katalog bringt 10 ₲ je Stück wie jede Platte
    $career = coreWeek(5000, 80, [])['career'];
    check($career['catalogSold'] > 255 && $career['catalogSold'] < 265, 'Katalog der ersten Woche: ' . $career['catalogSold']);
    coreNear($career['catalogSold'] * 10, $career['earned']['catalog']);
});

test('Neuer Kern: Ein Schub wirkt sofort für den Rest des Tages', function () {
    $t0 = 1_800_000_000;
    $plain = Career::release(Career::start(5000, 'genre', $t0), $t0, 80);
    $at = $t0 + 2 * 86400 + 12 * 3600;
    $before = Career::settle($plain, $at);
    $boosted = Career::boost($plain, $at, 10.0);
    coreNear(Career::hype($before) + 10, Career::hype($boosted), 1e-9, 'Hype steigt sofort');
    $record = Career::newest($before);
    $extra = (0.0015 + 0.06 * 5000 / 50000) * 0.1 * $record['room'] * 0.5;
    $end = $t0 + 3 * 86400 - 1;
    $gain = Career::settle($boosted, $end)['sold'] - Career::settle($plain, $end)['sold'];
    coreNear($extra, $gain, 0.001, 'Der halbe Tag verkauft entsprechend mehr');
    // Am nächsten Tag hat sich auch der Schub halbiert
    $next = $t0 + 3 * 86400;
    coreNear(Career::hype(Career::settle($plain, $next)) + 5, Career::hype(Career::settle($boosted, $next)));
    // Ohne Platte bringt ein Schub nichts
    $empty = Career::start(100, 'genre', $t0);
    checkEquals(0.0, Career::hype(Career::boost($empty, $t0 + 60, 10.0)));
});

test('Neuer Kern: Neue Fans aus Konzerten kaufen die Platte ab dem nächsten Tag', function () {
    $t0 = 1_800_000_000;
    $career = Career::release(Career::start(500, 'genre', $t0), $t0, 80);
    $rateBefore = Career::newest(Career::settle($career, $t0 + 15 * 3600))['soldRate'];
    [$after, $gig] = Career::gig($career, $t0 + 15 * 3600, 70.0);
    $record = Career::newest($after);
    coreNear($rateBefore, $record['soldRate'], 1e-9, 'Heute verkauft sie nicht mehr');
    coreNear($gig['newFans'], $record['gained'], 1e-9);
    // Von den 320 kaufwilligen Fans hat heute die Hälfte gekauft; dazu wollen 64 % der neuen Fans kaufen
    coreNear(160 + Sales::buyShare(80) * $gig['newFans'], $record['willing'], 1e-9, 'Ein Teil will kaufen');
    $tomorrow = Career::newest(Career::settle($after, $t0 + 86400));
    $plain = Career::newest(Career::settle($career, $t0 + 86400));
    check($tomorrow['soldRate'] > $plain['soldRate'], 'Am nächsten Tag kaufen auch die neuen Fans');
});

test('Neuer Kern: Weiterrechnen in kleinen oder großen Schritten ergibt dasselbe', function () {
    $t0 = 1_800_000_000;
    $career = Career::release(Career::start(3000, 'genre', $t0 - 3 * 86400), $t0 - 3 * 86400, 70);
    $career = Career::release($career, $t0, 90);
    $once = Career::settle($career, $t0 + 9 * 86400 + 1234);
    $steps = $career;
    for ($t = $t0; $t < $t0 + 9 * 86400 + 1234; $t += 1777) {
        $steps = Career::settle($steps, $t);
    }
    $steps = Career::settle($steps, $t0 + 9 * 86400 + 1234);
    coreNear($once['fans'], $steps['fans'], 1e-9, 'Fans');
    coreNear($once['sold'], $steps['sold'], 1e-9, 'Verkauft');
    coreNear($once['catalogSold'], $steps['catalogSold'], 1e-6, 'Katalog');
    coreNear(Career::money($once), Career::money($steps), 1e-9, 'Geld');
    checkEquals(count($once['records']), count($steps['records']));
    // Die ältere Platte ist ausgelaufen und verkauft nur noch über den Katalog
    check($once['records'][0]['ended'] ?? false, 'Ältere Platte ausgelaufen');
    checkEquals(0.0, $once['records'][0]['soldRate']);
    check($once['records'][0]['catalogRate'] > 0, 'Katalog läuft weiter');
});

test('Neuer Kern: Vorschau zeigt, was ein Auftritt bringt', function () {
    $t0 = 1_800_000_000;
    $career = Career::release(Career::start(500, 'genre', $t0), $t0, 80);
    $preview = Career::preview($career, $t0 + 3600);
    [, $gig] = Career::gig($career, $t0 + 3600, 70.0);
    coreNear($preview['tickets'], $gig['tickets']);
    coreNear($preview['earnings'], $gig['earnings']);
    checkEquals(0.0, $preview['newFans'], 'Neue Fans hängen von der Show ab');
});

test('Neuer Kern: Festival, Battle und Anfragen', function () {
    coreNear(277.2, Events::fans(20000, 500, 50000, 70.0));
    coreNear(0.0, Events::fans(2000, 50000, 50000, 100.0), 1e-9, 'Wer schon Fan ist, zählt nicht');
    checkEquals(['audience' => 20000, 'boost' => 10.0], Events::festival('main'));
    checkEquals(2000, Events::battleAudience());
    checkEquals([15.0, 10.0, 6.0, 0.0], [Events::battleBoost(1), Events::battleBoost(2), Events::battleBoost(3), Events::battleBoost(4)]);
    checkEquals([12000, 6000, 3000, 1500, 0], array_map([Events::class, 'battleMoney'], [1, 2, 3, 4, 5]));
    checkEquals(['seconds' => 1800, 'boost' => 6.0], Events::request('report'));
    $t0 = 1_800_000_000;
    $career = Career::release(Career::start(5000, 'genre', $t0), $t0, 80);
    [$after, $gained] = Career::event($career, $t0 + 3600, 2000, 70.0, 3.0);
    check($gained > 0 && $gained < 2000 * 0.02, "Zeltbühne: $gained neue Fans");
    coreNear(Career::hype(Career::settle($career, $t0 + 3600)) + 3, Career::hype($after));
    $ad = Career::ad($career, $t0 + 7200);
    coreNear(Career::settle($career, $t0 + 7200)['fans'], $ad['earned']['ads'], 1e-9, 'Werbung: so viele ₲, wie die Band Fans hat');
});

test('Neuer Kern: Fans bei Austritt, Zusammentun und Umbenennen', function () {
    $normal = ['personality' => ['gelassen'], 'stage_name' => null];
    $loud = ['personality' => ['rampensau'], 'stage_name' => null];
    $named = ['personality' => ['teamplayer'], 'stage_name' => 'Blitz'];
    checkEquals([1.0, 2.0, 2.0], [Fans::weight($normal), Fans::weight($loud), Fans::weight($named)]);
    checkEquals([1250.0, 8250.0], Fans::leave(10000, [1, 1, 1, 1], 0));
    checkEquals([2000.0, 7200.0], Fans::leave(10000, [2, 1, 1, 1], 0));
    coreNear(12000.0, Fans::merge(4000, 10000));
    coreNear(9000.0, Fans::renamed(10000));
    coreNear(15500.0, Fans::labelTotal([10000, 5000, 500]));
    [$takes, $keeps] = Fans::leave(10000, [1, 1, 1, 1], 2);
    check($takes + $keeps < 10000, 'Umbauen macht die Summe nie größer');
});

test('Neuer Kern: Songwriting nach Zahl der wirklichen Songschreiber', function () {
    foreach ([1 => 1.20, 2 => 1.68, 3 => 1.92, 4 => 2.064, 5 => 2.16, 7 => 2.28] as $n => $perDay) {
        coreNear($perDay, Songs::perDay($n), 1e-9, "$n Songschreiber");
    }
    $band = [['personality' => ['kreativ']], ['personality' => ['technisch']], ['personality' => ['perfektionist', 'ehrgeizig']]];
    coreNear(2.3, Songs::writers($band));
    checkEquals(0.0, Songs::perDay(Songs::writers([['personality' => ['technisch']]])), 'Technische schreiben nichts');
    coreNear(1.68 * 0.67 * 0.5 * 0.6 * 0.85 * 2.0, Songs::perDay(2, 'careful', 'yellow', 'rehearsal', 'hybrid', 3));
    checkEquals(0.0, Songs::perDay(2, 'normal', 'red'), 'Rote Band schreibt nicht');
    checkEquals(0.0, Songs::perDay(2, 'normal', 'green', 'gig'), 'Auf der Bühne ruht das Schreiben');
});

test('Neuer Kern: Qualität einer Platte und ihrer Songs', function () {
    coreNear(100.0, Quality::album(1, 1, 1, 1));
    coreNear(93.75, Quality::album(1, 0.75, 1, 1));
    coreNear(0.5, Quality::soundShare(65, 0));
    coreNear(1.0, Quality::soundShare(5000, 2));
    coreNear(1.0, Quality::productionShare(0, 0), 1e-9, 'Bei Ruhmstufe 0 immer voll');
    coreNear(0.5, Quality::productionShare(1, 2));
    coreNear(1.0, Quality::productionShare(3, 2));
    coreNear(0.75, Quality::moodShare([60, 30]));
    check(Quality::isPerfect(95) && !Quality::isPerfect(94.9), 'Perfekt ab 95');
    coreNear(95.0, Quality::song(1.0, 'careful', 0, 0.0));
    coreNear(20.0, Quality::song(0.0, 'fast', 0, -1.0));
    coreNear(100.0, Quality::song(1.0, 'careful', 3, 1.0), 1e-9, 'Höchstens 100');
    coreNear(0.5, Quality::creativeShare([['personality' => ['perfektionist']], ['personality' => ['gelassen']]]));
    coreNear(0.5, Quality::creativeShare([['personality' => ['kreativ']], ['personality' => ['technisch']]]), 1e-9, 'Wer nicht schreibt, zählt nicht');
    coreNear(0.0, Quality::creativeShare([]));
});

test('Neuer Kern: Bedürfnisse wachsen nur alle 24 Stunden', function () {
    coreNear(0.0, Needs::penalty(23.9, 0.4), 1e-9, 'Auftauchen kostet nichts');
    coreNear(9.6, Needs::penalty(24, 0.4));
    coreNear(9.6, Needs::penalty(47, 0.4));
    coreNear(19.2, Needs::penalty(48, 0.4));
    $normal = ['personality' => ['gelassen']];
    coreNear(7.2, Needs::penalty(24, Needs::perHour('gear', $normal)));
    coreNear(14.4, Needs::penalty(24, Needs::perHour('gear', ['personality' => ['diva']])), 1e-9, 'Diven nehmen das Instrument doppelt so wichtig');
    coreNear(5.76, Needs::penalty(24, Needs::perHour('restless', ['personality' => ['rampensau']])));
    coreNear(4.8, Needs::penalty(24, Needs::perHour('restless', ['personality' => ['partytier']])));
    check(Needs::appears('restless', ['personality' => ['partytier']], 8) && !Needs::appears('restless', ['personality' => ['partytier']], 7.9), 'Unruhe nach 8 Stunden');
    check(!Needs::appears('restless', $normal, 100), 'Gemütliche werden nicht unruhig');
    // Ehrgeizige wollen nach 48 Stunden eine neue Platte; auch das wächst nur alle 24 Stunden (6 × 0,015 × 24 = 2,16)
    check(Needs::appears('stagnant', ['personality' => ['ehrgeizig']], 48) && !Needs::appears('stagnant', ['personality' => ['ehrgeizig']], 47), 'Neue Platte nach 48 Stunden');
    coreNear(2.16, Needs::penalty(24, Needs::perHour('stagnant', ['personality' => ['ehrgeizig']])));
});

test('Neuer Kern: Können-Stufen und Übergang alter Musiker', function () {
    checkEquals([0, 1, 1, 2, 3], array_map([Skill::class, 'stage'], [99.9, 100, 399, 400, 1600]));
    coreNear(100.0, Skill::gain(2), 1e-9, 'Zwei Probestunden bis Fortgeschritten');
    foreach ([[0, 0], [35, 100], [47.5, 250], [60, 400], [85, 1600], [100, 2320]] as [$old, $new]) {
        coreNear((float) $new, Skill::fromLegacy((float) $old), 1e-9, "Altes Können $old");
    }
    checkEquals(2, Skill::keptGear(3, Skill::fromLegacy(62)), 'Gold wird zu Blau, wer nur Profi ist');
    checkEquals(1, Skill::keptGear(1, 2000));
    checkEquals([0, 1, 1], [Skill::voucher(50), Skill::voucher(150), Skill::voucher(2000)]);
});

test('Neuer Kern: Dach begrenzt, ohne die Zugewinne zu ändern', function () {
    $t0 = 1_800_000_000;
    $mixed = Career::release(Career::start(4900, 'mixed', $t0), $t0, 95);
    for ($d = 0; $d < 7; $d++) {
        [$mixed] = Career::gig($mixed, $t0 + $d * 86400 + 15 * 3600, 90.0);
    }
    $mixed = Career::settle($mixed, $t0 + 7 * 86400);
    coreNear(4999.0, $mixed['fans'], 1e-9, 'Gemischte Band bleibt unter 5.000');
    checkEquals(1, Fans::stage($mixed['fans']));
});

test('Neuer Kern: Tempo der Laufbahn wie im Dokument, ohne Bremse', function () {
    // Dokument (Tempo der Karriere): einmal am Tag Q 80 nach 103 Wochen Superstar, perfekte Platten nach 70, zweimal am Tag nach 60
    $once = coreCareer(80, [15]);
    check($once >= 100 && $once <= 106, "Einmal am Tag, Q 80: Woche $once");
    $perfect = coreCareer(95, [15]);
    check($perfect >= 68 && $perfect <= 72, "Einmal am Tag, perfekt: Woche $perfect");
    $twice = coreCareer(95, [9, 18]);
    check($twice >= 58 && $twice <= 62, "Zweimal am Tag, perfekt: Woche $twice");
    check(coreCareer(95, [15], 5000) <= 16, 'Stufe 2 nach etwa 15 Wochen');
});

test('Neuer Kern: Ruhm verstärkt den eigenen Zuwachs nicht, hinten raus bleibt es gleich schwer', function () {
    // Eine Platte bringt bei vielen Fans in ihrer Woche etwa 400 bis 650 neue Fans (einmal am Tag aufgetreten, Q 80 bis 95)
    foreach ([[45000, 80, 'genre'], [45000, 95, 'genre'], [70000, 80, 'hybrid'], [70000, 95, 'hybrid']] as [$fans, $q, $kind]) {
        $gained = coreWeek($fans, $q, [15], 1, 7, $kind)['fans'] - $fans;
        check($gained > 380 && $gained < 680, "$fans Fans, Q $q: $gained neue Fans");
    }
    $small = coreWeek(5000, 95, [15])['fans'] - 5000;
    $big = coreWeek(45000, 95, [15])['fans'] - 45000;
    check($big < 3 * $small, "Neunmal so viele Fans bringen nicht neunmal so viele neue: $small und $big");
});

test('Neuer Kern: Zwischendurch weiterrechnen ändert nichts, Fans und Geld gehen nie zurück', function () {
    // Zufällige Abfolge von Platten, Auftritten, Ereignissen und Schüben; dieselbe Abfolge einmal mit vielen Zwischenständen
    $t0 = 1_800_000_000;
    $actions = [];
    $t = $t0;
    for ($i = 0; $i < 300; $i++) {
        $t += mt_rand(60, 30 * 3600);
        $actions[] = [$t, mt_rand(0, 9)];
    }
    $run = static function (bool $peek) use ($actions, $t0): array {
        $career = Career::start(200, 'genre', $t0);
        $fans = $career['fans'];
        $money = 0.0;
        foreach ($actions as $n => [$at, $kind]) {
            if ($peek) {
                $career = Career::settle($career, $at - 1 - ($n % 7) * 600);
            }
            $career = match (true) {
                $kind === 0 => Career::release($career, $at, 60 + ($n % 5) * 10),
                $kind <= 5 => Career::gig($career, $at, 40.0 + ($n % 6) * 10)[0],
                $kind <= 7 => Career::boost($career, $at, 2.0 + $n % 5),
                $kind === 8 => Career::event($career, $at, 2000, 70.0, 3.0)[0],
                default => Career::ad($career, $at),
            };
            check($career['fans'] >= $fans - 1e-9 && Career::money($career) >= $money - 1e-9, 'Fans und Geld gehen nicht zurück');
            check(is_finite($career['fans']) && is_finite(Career::money($career)), 'Keine kaputten Zahlen');
            [$fans, $money] = [$career['fans'], Career::money($career)];
        }
        return Career::settle($career, $actions[count($actions) - 1][0] + 30 * 86400);
    };
    $plain = $run(false);
    $peeked = $run(true);
    coreNear($plain['fans'], $peeked['fans'], 1e-9, 'Fans');
    coreNear(Career::money($plain), Career::money($peeked), 1e-9, 'Geld');
    coreNear($plain['sold'], $peeked['sold'], 1e-9, 'Verkauft');
    check($plain['fans'] <= Fans::roof('genre'), 'Nie über dem Dach');
});
