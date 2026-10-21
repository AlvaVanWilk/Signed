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
    $soundMixed = BandCalc::sound($mixed, 0, []);
    check(BandCalc::sound($strong, 0, []) > $soundMixed);
    check(BandCalc::sound($even, 0, []) > $soundMixed, 'Gleicher Durchschnitt, aber ein Schwacher soll schlechter klingen');
});

test('Vier Musiker sind nicht doppelt so stark wie zwei', function () {
    $two = [musician(['instr1' => 'guitar']), musician(['instr1' => 'drums'])];
    $four = [musician(['instr1' => 'guitar']), musician(['instr1' => 'drums']), musician(['instr1' => 'bass']), musician(['instr1' => 'vocals'])];
    $ratio = BandCalc::sound($four, 50, []) / BandCalc::sound($two, 50, []);
    check($ratio > 1.0 && $ratio < 1.25, "Verhältnis $ratio");
});

test('Genre ergibt sich aus der Besetzung: alle mögen es und die Instrumente passen', function () {
    $rock = static fn (string $instrument, array $genres = ['rock']) => musician(['instr1' => $instrument, 'genres' => $genres]);
    $none = BandCalc::identity([$rock('guitar'), $rock('bass'), $rock('vocals', ['pop'])]);
    checkEquals(null, $none['primary']);
    checkEquals(1.0, $none['bonus']);
    checkEquals('rock', $none['music'], 'Ohne Genre spielt die Musik, die die meisten mögen');

    $noDrums = BandCalc::identity([$rock('guitar'), $rock('vocals')]);
    checkEquals(null, $noDrums['primary'], 'Rock braucht ein Schlagzeug');
    check(!$noDrums['checks']['rock']['lineup'] && $noDrums['checks']['rock']['allFans']);

    $band = BandCalc::identity([$rock('guitar'), $rock('drums')]);
    checkEquals('rock', $band['primary']);
    checkEquals('Rockband', $band['title']);
    checkEquals(Balance::GENRE_BONUS, $band['bonus']);

    $hybrid = BandCalc::identity([$rock('guitar', ['rock', 'punk']), $rock('drums', ['punk', 'rock'])]);
    checkEquals('Punk-Rock-Band', $hybrid['title'], 'Fürs zweite Genre reicht es, dass alle es mögen');
    checkEquals(Balance::HYBRID_BONUS, $hybrid['bonus']);
    checkEquals('Punk-Rock-Band', BandCalc::identity([$rock('guitar', ['rock', 'punk']), $rock('drums', ['punk', 'rock'])], 'rock', 'punk')['title']);

    $sound = BandCalc::sound([$rock('guitar'), $rock('drums')], 0, [], $band['bonus']);
    check(abs($sound / BandCalc::sound([$rock('guitar'), $rock('drums')], 0, []) - Balance::GENRE_BONUS) < 0.01);
});

test('Ohne Instrument klingt es nach wenig, schon ein einfaches hilft', function () {
    $m = musician(['genres' => ['pop']]);
    check(BandCalc::memberValue($m, Catalog::GEAR['gtr_sunburst']) > 2 * BandCalc::memberValue($m, null));
});

test('Gutes Instrument entfaltet sich nur in fähigen Händen', function () {
    $model = Catalog::GEAR['gtr_sunburst'];
    $weak = BandCalc::gearBonus($model, 15);
    $good = BandCalc::gearBonus($model, 70);
    check($good > $weak * 3, "schwach $weak, gut $good");
    checkEquals($model['klang'] / 100 * Balance::GEAR_MAX_BONUS, $good);
});

test('Zusammenspiel erhöht den Sound', function () {
    $band = [musician(), musician(['instr1' => 'drums'])];
    check(BandCalc::sound($band, 100, []) > BandCalc::sound($band, 0, []));
});

test('Bekanntheit wird zu gleichen Teilen verteilt, Charisma zählt', function () {
    $quiet = [musician(), musician()];
    $loud = [musician(['abilities' => ['charisma']]), musician()];
    $gainsQuiet = BandCalc::splitFame($quiet, 10, []);
    $gainsLoud = BandCalc::splitFame($loud, 10, []);
    checkEquals(1, count(array_unique($gainsLoud)), 'Alle bekommen gleich viel');
    check(array_sum($gainsLoud) > array_sum($gainsQuiet), 'Charisma bringt mehr Bekanntheit');
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
    checkEquals(100.0, BandCalc::maxCohesion(BandCalc::chemistry($divas)), 'Chemie bremst nur, das Maximum bleibt 100');
    $notes = BandCalc::chemistryNotes($divas);
    check(count(array_filter($notes, static fn ($n) => !$n['good'] && str_contains($n['text'], 'reiben sich'))) > 0, 'Erklärt, wer sich reibt');
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
        check($m['level'] <= 1, 'Aus Packs höchstens Können-Stufe 1');
        checkEquals($m['level'], count($m['abilities']), 'So viele Fähigkeiten wie Stufen');
        checkEquals(0.0, $m['fame'], 'Neue Musiker haben keinen Ruhm');
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
            check(!empty(Catalog::ABILITIES[$technique]['technique']), "Technik $technique fehlt");
        }
        check(isset(Catalog::HIGHLIGHTS[$key]), "Highlight-Texte für $key fehlen");
    }
});

test('Stufen: Können- und Bekanntheitsleiter, Fähigkeiten passen zum Instrument', function () {
    checkEquals(0, Signed\Game\Levels::forSkill(34.9));
    checkEquals(1, Signed\Game\Levels::forSkill(35));
    checkEquals(3, Signed\Game\Levels::forSkill(99));
    checkEquals(0, Signed\Game\Levels::forFame(0));
    checkEquals(3, Signed\Game\Levels::forFame(5000));
    for ($i = 0; $i < 50; $i++) {
        foreach (Signed\Game\Levels::options(['drums'], []) as $ability) {
            $info = Catalog::ABILITIES[$ability];
            check(empty($info['technique']) || in_array($ability, Catalog::INSTRUMENTS['drums']['techniques'], true), "$ability passt nicht zum Schlagzeug");
        }
    }
    $scout = Generator::musician('epic', ['minSkill' => 35]);
    checkEquals(1, $scout['level'], 'Scout-Musiker haben Stufe 1');
});

test('Instrumente streuen um die Werte ihres Modells', function () {
    $seen = [];
    for ($i = 0; $i < 40; $i++) {
        $stats = Generator::gearStats('gtr_strato');
        $seen[$stats['klang']] = true;
        check(abs($stats['klang'] - Catalog::GEAR['gtr_strato']['klang']) <= Balance::GEAR_VARIANCE);
    }
    check(count($seen) > 5, 'Unterschiedliche Werte');
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

test('Chartpunkte: Qualität zählt, LPs mehr als EPs; Verlauf steigt erst und fällt dann', function () {
    check(Signed\Game\Charts::points(3, 0.9, 30, false, 0) > Signed\Game\Charts::points(3, 0.5, 30, false, 0));
    check(Signed\Game\Charts::points(10, 0.8, 30, false, 0) > Signed\Game\Charts::points(6, 0.8, 30, false, 0) * 1.5);
    check(Signed\Game\Charts::points(3, 0.8, 30, true, 0) > Signed\Game\Charts::points(3, 0.8, 30, false, 0));
    $curve = [Signed\Game\Charts::class, 'curve'];
    check(abs($curve(0) - 0.65) < 0.01, 'Einstieg mit rund zwei Dritteln');
    check(abs($curve(Balance::CHART_PEAK) - 1.0) < 0.001, 'Höhepunkt');
    check($curve((int) (Balance::CHART_PEAK / 2)) < 1 && $curve(2 * Balance::CHART_PEAK) < 1);
    check($curve(10 * Balance::CHART_PEAK) < 0.02, 'Nach gut anderthalb Tagen fast draußen');
});

test('Simulation: bessere Bands begeistern das Publikum mehr', function () {
    $player = static fn (float $skill) => ['name' => 'Kim', 'instrument' => 'guitar', 'skill' => $skill, 'abilities' => []];
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

test('Mischpult: bis 4 Musiker einzeln, größere Bands in Gruppen; gute Musiker schwanken weniger', function () {
    $p = static fn (string $instrument, float $skill = 50) => ['name' => $instrument, 'instrument' => $instrument, 'skill' => $skill];
    checkEquals(4, count(Signed\Game\Chart::mixerTracks([$p('vocals'), $p('guitar'), $p('bass'), $p('drums')])));
    $groups = Signed\Game\Chart::mixerTracks([$p('vocals'), $p('guitar'), $p('bass'), $p('drums'), $p('keys'), $p('sax')]);
    checkEquals(['Rhythmus', 'Harmonie', 'Melodie'], array_column($groups, 'name'));
    checkEquals(['bass', 'drums'], $groups[0]['instruments']);
    $spread = static function (float $skill): float {
        $drifts = Signed\Game\Chart::mixerDrifts([$skill], 300);
        return array_sum(array_map(static fn ($d) => abs($d['v']), $drifts)) / count($drifts);
    };
    check($spread(90) < $spread(10) / 2, 'Gute Musiker bewegen sich sanfter');
});

test('Gigs: frisches Material zählt, Charts machen voller', function () {
    checkEquals(1.0, Signed\Game\BandCalc::freshness(0));
    check(Signed\Game\BandCalc::freshness(3) < 1.0);
    checkEquals(Balance::GIG_FRESH['min'], Signed\Game\BandCalc::freshness(50));
    checkEquals(1.0, Signed\Game\Charts::rankFactor(null, 0.4));
    check(abs(Signed\Game\Charts::rankFactor(1, 0.4) - 1.4) < 1e-9);
    check(Signed\Game\Charts::rankFactor(10, 0.4) > 1.0 && Signed\Game\Charts::rankFactor(10, 0.4) < Signed\Game\Charts::rankFactor(2, 0.4));
    checkEquals(1.0, Signed\Game\Charts::rankFactor(11, 0.4));
});

test('Backup: einmal am Tag eine Kopie, alte werden aufgeräumt', function () {
    $dir = sys_get_temp_dir() . '/signed-backup-test-' . getmypid();
    @mkdir($dir);
    array_map('unlink', glob("$dir/*") ?: []);
    $file = tempnam(sys_get_temp_dir(), 'sdb');
    $db = new Signed\Db('sqlite:' . $file);
    Signed\Schema::migrate($db);
    $first = Signed\Backup::daily($db, $dir);
    check($first !== null && is_file($first), 'Kopie angelegt');
    checkEquals(null, Signed\Backup::daily($db, $dir), 'Am selben Tag nicht noch einmal');
    $copy = new Signed\Db('sqlite:' . $first);
    check((int) $copy->value('SELECT COUNT(*) FROM meta') > 0, 'Kopie ist lesbar');
    for ($i = 0; $i < 20; $i++) {
        Signed\Clock::advance(86400);
        Signed\Backup::daily($db, $dir);
    }
    checkEquals(14, count(glob("$dir/signed-*")));
    array_map('unlink', glob("$dir/*") ?: []);
    rmdir($dir);
    unlink($file);
});

test('Über Stufe 1 geht es kaum weiter: Gewinne wirken je nach Stufe nur noch zum Teil', function () {
    $m = static fn (float $skill) => ['band_slot' => 1, 'instr2' => null, 'skill1' => $skill, 'skill2' => null, 'abilities' => []];
    $raw = static fn (float $skill) => (1 - $skill / 110) ** Balance::TRAINING_CURVE;
    $gain = static fn (float $skill) => BandCalc::trainingGain($m($skill), 1.0) / $raw($skill);
    check(abs($gain(20) - Balance::LEVEL_PACE[0]) < 1e-9);
    check(abs($gain(40) - Balance::LEVEL_PACE[1]) < 1e-9);
    check(abs($gain(70) - Balance::LEVEL_PACE[2]) < 1e-9);
    checkEquals(Balance::LEVEL_PACE[2], Signed\Game\Levels::pace(3));
});

test('Gesang: Musikerinnen singen mit der Frauenstimme, Musiker mit der Männerstimme, gleiche Noten', function () {
    $stem = static fn (string $voice, string $genre = 'folk') => ['type' => 'vocals', 'level' => 0, 'genre' => $genre, 'electric' => false, 'voice' => $voice];
    // Folk hat noch keine Aufnahmen: Pop springt ein. Rock und Metal singen mit eigenen (gleichen) Spuren.
    checkEquals(['folder' => 'pop-0', 'file' => 'gesang-weiblich'], Signed\Game\Music::resolve($stem('weiblich')));
    checkEquals(['folder' => 'pop-0', 'file' => 'gesang-maennlich'], Signed\Game\Music::resolve($stem('maennlich')));
    checkEquals(['folder' => 'rock-0', 'file' => 'gesang-maennlich'], Signed\Game\Music::resolve($stem('maennlich', 'rock')));
    checkEquals(['folder' => 'metal-0', 'file' => 'gesang-weiblich'], Signed\Game\Music::resolve($stem('weiblich', 'metal')));
    check(count(Signed\Game\Music::notes(Signed\Game\Music::resolve($stem('weiblich', 'metal')))['notes']) > 20, 'Metal hat Noten');
    $notes = Signed\Game\Music::notes(Signed\Game\Music::resolve($stem('weiblich')));
    check($notes !== null && count($notes['notes']) > 20, 'Noten aus der gemeinsamen MIDI-Spur');
    checkEquals('weiblich', Signed\Game\Music::voice(['id' => 7, 'look' => ['gender' => 'f']]));
    checkEquals('maennlich', Signed\Game\Music::voice(['id' => 8, 'look' => ['gender' => 'm']]));
});

test('Musik: Noten der anderen Variante zählen, wenn die eigene fehlt (Rock-Schlagzeug)', function () {
    $r = Signed\Game\Music::resolve(['type' => 'drums', 'level' => 0, 'genre' => 'rock', 'electric' => true]);
    checkEquals(['folder' => 'rock-0', 'file' => 'schlagzeug-elektrisch'], $r);
    check(Signed\Game\Music::notes($r) !== null, 'Noten aus „schlagzeug-akustisch“');
    checkEquals('pop-0', Signed\Game\Music::resolve(['type' => 'guitar', 'level' => 0, 'genre' => 'folk', 'electric' => true])['folder'], 'Fehlt im Genre: Pop');
    checkEquals(['folder' => 'rock-0', 'file' => 'gitarre-elektrisch'], Signed\Game\Music::resolve(['type' => 'guitar', 'level' => 0, 'genre' => 'rock', 'electric' => false]), 'Im Genre zuerst die andere Variante');
    checkEquals(['folder' => 'metal-0', 'file' => 'gitarre-elektrisch'], Signed\Game\Music::resolve(['type' => 'guitar', 'level' => 0, 'genre' => 'metal', 'electric' => false]), 'Metal: immer E-Gitarre');
});

test('Musik: Die Stimme zählt nur beim Gesang – andere Instrumente finden ihre Variante', function () {
    $stem = static fn (string $type, bool $electric) => ['type' => $type, 'level' => 0, 'genre' => 'pop', 'electric' => $electric, 'voice' => 'weiblich'];
    checkEquals('gitarre-elektrisch', Signed\Game\Music::resolve($stem('guitar', true))['file']);
    checkEquals('schlagzeug-akustisch', Signed\Game\Music::resolve($stem('drums', false))['file']);
    checkEquals('saxophon', Signed\Game\Music::resolve($stem('sax', false))['file']);
    checkEquals('gesang-weiblich', Signed\Game\Music::resolve($stem('vocals', false))['file']);
});

test('Platte: gemischte Minispiele, so viele wie Lieder, nie zweimal dasselbe hintereinander, zum Schluss Master-Mix und Cover', function () {
    $band = [
        musician(['id' => 1, 'instr1' => 'guitar']),
        musician(['id' => 2, 'instr1' => 'drums']),
        musician(['id' => 3, 'instr1' => 'vocals']),
        musician(['id' => 4, 'instr1' => 'guitar']),
    ];
    foreach ([[$band, 12], [$band, 3], [array_slice($band, 0, 2), 12], [[$band[0], $band[3]], 7]] as [$members, $songs]) {
        for ($run = 0; $run < 20; $run++) {
            $plan = Signed\Game\Studio::plan($members, $songs);
            checkEquals($songs, count($plan));
            checkEquals([['type' => 'master'], ['type' => 'cover']], array_slice($plan, -2));
            for ($i = 1; $i < count($plan); $i++) {
                check($plan[$i] != $plan[$i - 1], 'Zweimal dasselbe Minispiel hintereinander');
            }
            foreach (array_slice($plan, 0, -2) as $step) {
                check($step['type'] === 'take' || ($step['type'] === 'mix' && count($step['musicians']) >= 2 && count($step['musicians']) <= 3));
            }
        }
    }
    // Lang genug, dass alles aus dem Topf drankommt: jedes Instrument wird eingespielt (Gitarre nur einmal im Topf).
    $plan = Signed\Game\Studio::plan($band, 12);
    $takes = array_unique(array_column(array_filter($plan, static fn ($s) => $s['type'] === 'take'), 'musician'));
    checkEquals(3, count($takes));
});

test('Anzeige rundet ab: Wer knapp unter einer Stufe ist, sieht nicht schon die Schwelle', function () {
    checkEquals(34.9, Signed\Game\Present::down(34.96));
    checkEquals(35.0, Signed\Game\Present::down(35.0));
    checkEquals(0.3, Signed\Game\Present::down(0.3));
});

test('Musik: Zwei Gitarren in einer Band spielen verschiedene Aufnahmen (Metal: Metal und Pop – Rock spielt fast dasselbe Riff)', function () {
    $g1 = musician(['id' => 501, 'instr1' => 'guitar', 'genres' => ['metal']]);
    $g2 = musician(['id' => 503, 'instr1' => 'guitar', 'genres' => ['metal']]);
    // Auch mit akustischer Gitarre: Im Metal klingt die E-Gitarre, der Ersatz ist die Pop-E-Gitarre.
    $acoustic = ['model' => ['electric' => false]];
    $stems = Signed\Game\Music::bandStems([$g1, $g2], [503 => ['guitar' => $acoustic['model']]], ['metal']);
    $a = Signed\Game\Music::resolve($stems[501]);
    $b = Signed\Game\Music::resolve($stems[503]);
    checkEquals('metal-0/gitarre-elektrisch', $a['folder'] . '/' . $a['file']);
    checkEquals('pop-0/gitarre-elektrisch', $b['folder'] . '/' . $b['file']);
});

test('Üben: Eine Solo-Probe bringt einem Musiker deutlich mehr Können als eine Bandprobe', function () {
    foreach ([20.0, 40.0, 70.0] as $skill) {
        $m = musician(['skill1' => $skill, 'band_slot' => 1]);
        $solo = BandCalc::practiceGain($m, 1, 0.85, null);
        $band = BandCalc::rehearsalGain($m, null);
        check($solo >= 5 * $band, "Können $skill: Solo $solo, Band $band");
    }
});

test('Charakter: keine widersprüchlichen Eigenschaften (z. B. Teamplayer und Diva)', function () {
    for ($i = 0; $i < 400; $i++) {
        $p = Generator::musician('common')['personality'];
        check(count($p) < 2 || !Generator::contradicts($p[0], $p[1]), 'Widerspruch: ' . implode(' + ', $p));
    }
    check(Generator::contradicts('diva', 'teamplayer'));
});

test('Musik: Doppelte Instrumente spielen verschiedene Parts – gleiche Noten in anderem Genre zählen als derselbe', function () {
    $parts = Signed\Game\Music::parts();
    checkEquals($parts['metal-0/gitarre-elektrisch'], $parts['rock-0/gitarre-elektrisch'], 'Metal- und Rock-Gitarre: dasselbe Riff');
    check($parts['pop-0/bass-akustisch'] !== $parts['rock-0/bass-akustisch'], 'Pop- und Rock-Bass spielen Verschiedenes');
    $b1 = musician(['id' => 601, 'instr1' => 'bass', 'genres' => ['rock']]);
    $b2 = musician(['id' => 603, 'instr1' => 'bass', 'genres' => ['rock']]);
    $b3 = musician(['id' => 605, 'instr1' => 'bass', 'genres' => ['rock']]);
    $stems = Signed\Game\Music::bandStems([$b1, $b2, $b3], [], ['rock']);
    $got = array_map(static fn ($s) => $parts[implode('/', Signed\Game\Music::resolve($s))], $stems);
    checkEquals(3, count(array_unique($got)), 'Drei Bassisten, drei verschiedene Parts (Rock, Pop, Hip-Hop)');
});

test('Musik: Elektro-Keyboard klingt immer elektrisch, ohne eigenes Instrument entscheidet das Genre', function () {
    $keys = musician(['id' => 702, 'instr1' => 'keys', 'genres' => ['elektro']]);
    $piano = ['electric' => false];
    checkEquals(true, Signed\Game\Music::stem($keys, [702 => ['keys' => $piano]], ['elektro'])['electric'], 'Elektro: auch mit Klavier elektrisch');
    checkEquals(false, Signed\Game\Music::stem($keys, [702 => ['keys' => $piano]], ['pop'])['electric'], 'Pop: das eigene Klavier zählt');
    checkEquals(true, Signed\Game\Music::stem($keys, [], ['pop'])['electric'], 'Pop ohne Instrument: elektrisch');
    checkEquals(false, Signed\Game\Music::stem($keys, [], ['jazz'])['electric'], 'Jazz ohne Instrument: akustisch');
});
