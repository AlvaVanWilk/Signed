<?php
declare(strict_types=1);

use Signed\Game\Balance;
use Signed\Game\BandCalc;
use Signed\Game\Catalog;
use Signed\Game\Chart;
use Signed\Game\Generator;
use Signed\Game\Performance;

test('Ein schwacher Musiker zieht den Sound runter', function () {
    $strong = [musician(['skill1' => 60]), musician(['skill1' => 60, 'instr1' => 'drums'])];
    $mixed = [musician(['skill1' => 60]), musician(['skill1' => 20, 'instr1' => 'drums'])];
    $even = [musician(['skill1' => 40]), musician(['skill1' => 40, 'instr1' => 'drums'])];
    $soundMixed = BandCalc::sound($mixed, 'rock', 0, []);
    check(BandCalc::sound($strong, 'rock', 0, []) > $soundMixed);
    check(BandCalc::sound($even, 'rock', 0, []) > $soundMixed, 'Gleicher Durchschnitt, aber ein Schwacher soll schlechter klingen');
});

test('Vier Musiker sind nicht doppelt so stark wie zwei', function () {
    $two = [musician(['instr1' => 'guitar']), musician(['instr1' => 'drums'])];
    $four = [musician(['instr1' => 'guitar']), musician(['instr1' => 'drums']), musician(['instr1' => 'bass']), musician(['instr1' => 'vocals'])];
    $ratio = BandCalc::sound($four, 'rock', 50, []) / BandCalc::sound($two, 'rock', 50, []);
    check($ratio > 1.0 && $ratio < 1.25, "Verhältnis $ratio");
});

test('Außerhalb des Genres gibt es weniger Bonus', function () {
    $m = musician(['genres' => ['pop']]);
    checkEquals(40.0 * Balance::OFF_GENRE_FACTOR, BandCalc::memberValue($m, 'elektro', null));
    checkEquals(40.0, BandCalc::memberValue($m, 'pop', null));
});

test('Gutes Instrument entfaltet sich nur in fähigen Händen', function () {
    $model = Catalog::GEAR['gtr_sunburst'];
    $weak = BandCalc::gearBonus($model, 15);
    $good = BandCalc::gearBonus($model, 70);
    check($good > $weak * 3, "schwach $weak, gut $good");
    checkEquals($model['klang'] / 100 * Balance::GEAR_MAX_BONUS, $good);
});

test('Eingespieltheit erhöht den Sound', function () {
    $band = [musician(), musician(['instr1' => 'drums'])];
    check(BandCalc::sound($band, 'rock', 100, []) > BandCalc::sound($band, 'rock', 0, []));
});

test('Ruhm wird zu gleichen Teilen verteilt, Präsenz zählt', function () {
    $quiet = [musician(['presence' => 0]), musician(['presence' => 0])];
    $loud = [musician(['presence' => 80]), musician(['presence' => 0])];
    $gainsQuiet = BandCalc::splitFame($quiet, 10, []);
    $gainsLoud = BandCalc::splitFame($loud, 10, []);
    checkEquals(1, count(array_unique($gainsLoud)), 'Alle bekommen gleich viel');
    check(array_sum($gainsLoud) > array_sum($gainsQuiet), 'Mehr Präsenz bringt mehr Ruhm');
});

test('Bandruhm ist die Summe der Bekanntheit', function () {
    checkEquals(12.5, BandCalc::fame([musician(['fame' => 10]), musician(['fame' => 2.5])]));
});

test('Chemie: Divas reiben sich, ein Ruhepol dämpft', function () {
    $divas = [musician(['personality' => ['diva']]), musician(['personality' => ['diva']])];
    $calm = [...$divas, musician(['personality' => ['ruhepol']])];
    $team = [musician(['personality' => ['teamplayer']]), musician(['personality' => ['gelassen']])];
    check(BandCalc::chemistry($divas) < 1.0);
    check(BandCalc::chemistry($calm) > BandCalc::chemistry($divas));
    check(BandCalc::chemistry($team) > 1.0);
    check(BandCalc::maxCohesion(BandCalc::chemistry($divas)) < BandCalc::maxCohesion(1.0));
});

test('Besetzungsregeln', function () {
    check(BandCalc::lineupProblems([musician()]) !== [], 'Ein Musiker reicht nicht');
    check(BandCalc::lineupProblems([musician(['instr1' => 'drums']), musician(['instr1' => 'drums'])]) !== [], 'Zwei Schlagzeuger');
    $threeGuitars = [musician(), musician(), musician()];
    check(BandCalc::lineupProblems($threeGuitars) !== [], 'Drei Gitarristen');
    check(BandCalc::lineupProblems([musician(), musician()]) === [], 'Zwei Gitarristen sind erlaubt');
    $singerWithGuitar = musician(['instr1' => 'vocals', 'instr2' => 'guitar', 'skill2' => 20.0, 'band_slot' => 2]);
    check(BandCalc::lineupProblems([musician(), musician(), $singerWithGuitar]) !== [], 'Zählt als Gitarrist, wenn er Gitarre spielt');
});

test('Auftrittsorte nach Ruhm', function () {
    checkEquals('kneipe', BandCalc::venue(0)['key']);
    checkEquals('club', BandCalc::venue(30)['key']);
    checkEquals('arena', BandCalc::venue(99999)['key']);
    checkEquals(0.5, BandCalc::venue(15)['progress']);
});

test('Manager: geteilte Leistung und längere Gigs', function () {
    $manager = ['level' => 4, 'skills' => ['verhandler']];
    checkEquals(4.0, BandCalc::managerLevel($manager, 1));
    checkEquals(2.0, BandCalc::managerLevel($manager, 2));
    checkEquals(Balance::GIG_SONG_CAP, BandCalc::gigSongCap(0));
    check(BandCalc::gigSongCap(5) >= 10, 'Ein Top-Manager schafft 10 Lieder');
    checkEquals(3, BandCalc::maxGigSongs(3, 5), 'Nie mehr Lieder als produziert');
    check(BandCalc::managerBonus($manager, 4, 'verhandler', 0.1) > 0);
    checkEquals(0.0, BandCalc::managerBonus($manager, 4, 'prprofi', 0.1));
});

test('Open Stage lohnt sich ab einer Schwelle nicht mehr', function () {
    checkEquals(1.0, BandCalc::falloff(0, 120));
    checkEquals(0.0, BandCalc::falloff(500, 120));
    checkEquals(0.1, BandCalc::falloff(500, 150, 0.1));
});

test('Generator: höchstens zwei Instrumente, Spezialisten sind besser', function () {
    $multi = 0;
    for ($i = 0; $i < 600; $i++) {
        $m = Generator::musician((string) array_rand(Balance::MUSICIAN));
        check(isset(Catalog::INSTRUMENTS[$m['instr1']]));
        check($m['genres'] !== [] && count($m['genres']) === count(array_unique($m['genres'])));
        check(count($m['traits']) <= 2);
        if ($m['instr2'] !== null) {
            $multi++;
            check($m['instr2'] !== $m['instr1']);
            check($m['skill2'] < $m['skill1'], 'Zweitinstrument ist schwächer');
            check($m['skill1'] <= Balance::MUSICIAN[$m['rarity']]['skill'][1] * Balance::MULTI_MAIN_FACTOR + 0.1);
        }
        $typical = Catalog::INSTRUMENTS[$m['instr1']]['genres'];
        checkEquals(!in_array($m['genres'][0], $typical, true), $m['unusual'], 'Ungewöhnlich genau dann, wenn das Lieblingsgenre untypisch ist');
        foreach ($m['personality'] as $a) {
            foreach ($m['personality'] as $b) {
                check($a === $b || !BandCalc::clashes($a, $b), 'Keine widersprüchlichen Charakterzüge');
            }
        }
    }
    check($multi > 60 && $multi < 200, "Anteil Multi-Instrumentalisten: $multi");
});

test('Generator: Instrumentenmodelle gibt es für jede Art und Seltenheit', function () {
    foreach (array_keys(Catalog::INSTRUMENTS) as $type) {
        foreach (array_keys(Catalog::RARITIES) as $rarity) {
            $model = Generator::gear($rarity, $type);
            checkEquals($type, Catalog::GEAR[$model]['type']);
            checkEquals($rarity, Catalog::GEAR[$model]['rarity']);
        }
    }
    foreach (Catalog::INSTRUMENTS as $key => $instrument) {
        foreach ($instrument['techniques'] as $technique) {
            check(isset(Catalog::TECHNIQUES[$technique]), "Technik $technique fehlt");
        }
        check(isset(Catalog::HIGHLIGHTS[$key]), "Highlight-Texte für $key fehlen");
    }
});

test('Minispiele: Tonhöhen-Melodie und Mischpult', function () {
    $melody = Chart::melody(110, 30);
    check(count($melody) >= 6);
    foreach ($melody as $n) {
        check($n['p'] >= 0 && $n['p'] <= 7 && $n['t'] + $n['d'] <= 29.5);
    }
    check(Chart::pitchTolerance(80) > Chart::pitchTolerance(10));
    $drifts = Chart::mixerDrifts([10.0, 90.0], 30);
    $weak = count(array_filter($drifts, static fn ($d) => $d['k'] === 0));
    $strong = count(array_filter($drifts, static fn ($d) => $d['k'] === 1));
    check($weak > $strong, 'Schwache Musiker schwanken öfter');
    check(Chart::mixerZone(80) > Chart::mixerZone(10));
});

test('Chartpunkte: Qualität zählt, LPs mehr als EPs, Punkte schrumpfen', function () {
    check(Signed\Game\Charts::points(3, 0.9, 30, false, 0) > Signed\Game\Charts::points(3, 0.5, 30, false, 0));
    check(Signed\Game\Charts::points(10, 0.8, 30, false, 0) > Signed\Game\Charts::points(6, 0.8, 30, false, 0) * 1.5);
    check(Signed\Game\Charts::points(3, 0.8, 30, true, 0) > Signed\Game\Charts::points(3, 0.8, 30, false, 0));
    checkEquals(round(100 * Balance::CHART_DECAY, 3), round(Signed\Game\Charts::current(100, 86400), 3));
});

test('Simulation: bessere Bands begeistern das Publikum mehr', function () {
    $player = static fn (float $skill) => ['name' => 'Kim', 'instrument' => 'guitar', 'skill' => $skill, 'presence' => 30.0, 'traits' => [], 'techniques' => []];
    $weak = 0.0;
    $strong = 0.0;
    for ($i = 0; $i < 60; $i++) {
        $weak += Performance::simulate([$player(10), $player(10)], 10, 30, 3)['meter'];
        $strong += Performance::simulate([$player(70), $player(70)], 60, 30, 3)['meter'];
    }
    check($strong > $weak + 600, "stark $strong, schwach $weak");
    $sim = Performance::simulate([$player(40)], 30, 30, 2);
    check($sim['timeline'][0]['kind'] === 'song');
    check(end($sim['timeline'])['t'] < 2 * Balance::SONG_SECONDS);
    check($sim['perf'] >= 0.4 && $sim['perf'] <= 1.6);
});

test('Minispiel: Noten liegen im Lied, Fenster wachsen mit Können', function () {
    $notes = Chart::generate(172, Chart::density(100), 30);
    check(count($notes) > 30);
    foreach ($notes as $n) {
        check($n['t'] > 0 && $n['t'] < 29 && $n['l'] >= 0 && $n['l'] < 4);
    }
    check(Chart::windows(80)['good'] > Chart::windows(10)['good']);
    check(Chart::windows(10, true)['good'] > Chart::windows(10)['good']);
    checkEquals(0.8, Chart::accuracy(5, 5, 0));
});
