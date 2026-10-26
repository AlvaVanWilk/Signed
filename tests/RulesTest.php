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
    checkEquals(Balance::MIXED_BONUS, $none['bonus'], 'gemischte Band: weniger Sound');
    checkEquals('rock', $none['music'], 'Ohne Genre spielt die Musik, die die meisten mögen');

    $noDrums = BandCalc::identity([$rock('guitar'), $rock('vocals')]);
    checkEquals(null, $noDrums['primary'], 'Rock braucht ein Schlagzeug');
    check(!$noDrums['checks']['rock']['lineup'] && $noDrums['checks']['rock']['allFans']);

    $band = BandCalc::identity([$rock('guitar'), $rock('drums')]);
    checkEquals('rock', $band['primary']);
    checkEquals('Rockband', $band['title']);
    checkEquals(Balance::GENRE_BONUS, $band['bonus']);

    $noBass = BandCalc::identity([$rock('guitar', ['rock', 'punk']), $rock('drums', ['punk', 'rock'])]);
    checkEquals('Rockband', $noBass['title'], 'Punk braucht auch als zweites Genre einen Bass');
    $punkRock = [$rock('guitar', ['rock', 'punk']), $rock('drums', ['punk', 'rock']), $rock('bass', ['rock', 'punk'])];
    $hybrid = BandCalc::identity($punkRock);
    checkEquals('Punk-Rock-Band', $hybrid['title']);
    checkEquals(Balance::HYBRID_BONUS, $hybrid['bonus']);
    checkEquals('Punk-Rock-Band', BandCalc::identity($punkRock, 'rock', 'punk')['title']);

    $sound = BandCalc::sound([$rock('guitar'), $rock('drums')], 0, [], $band['bonus']);
    check(abs($sound / BandCalc::sound([$rock('guitar'), $rock('drums')], 0, []) - Balance::GENRE_BONUS) < 0.01);
});

test('Ohne Instrument klingt es nach wenig, schon ein einfaches hilft', function () {
    $m = musician(['genres' => ['pop']]);
    check(BandCalc::memberValue($m, Catalog::GEAR['gtr_school']) >= 2 * BandCalc::memberValue($m, null));
});

test('Instrumente: Klasse je Können-Stufe, Boni je Klasse, zu gute zählen ohne Boni', function () {
    $gold = Signed\Game\Gear::roll('legendary');
    checkEquals(['green', 'blue', 'gold'], array_column($gold, 'slot'));
    checkEquals([], Signed\Game\Gear::roll('common'));
    foreach ($gold as $b) {
        $range = Balance::GEAR_BONUS[$b['slot']][$b['kind']];
        [$lo, $hi] = $range[3] ?? $range;
        check($b['value'] >= $lo && $b['value'] <= $hi, 'im Bereich der Klasse');
    }
    $model = Catalog::GEAR['gtr_sunburst'] + ['key' => 'gtr_sunburst', 'bonuses' => $gold];
    $pro = musician(['id' => 7, 'instr1' => 'guitar', 'skill1' => 90.0]);
    $novice = musician(['id' => 8, 'instr1' => 'guitar', 'skill1' => 40.0]);
    check(Signed\Game\Gear::playable($pro, 'gtr_sunburst') && !Signed\Game\Gear::playable($novice, 'gtr_sunburst'));
    checkEquals(45.0, Signed\Game\Gear::missingSkill($novice, 'gtr_sunburst'));
    $map = [7 => ['guitar' => $model], 8 => ['guitar' => $model]];
    check(BandCalc::gearBonus(BandCalc::gearOf($pro, $map), 90) > 0.3, 'Virtuose bekommt den Klang-Bonus');
    checkEquals(0.0, BandCalc::gearBonus(BandCalc::gearOf($novice, $map), 40), 'zu gut: ohne Boni');
    check(Signed\Game\Gear::priceFactor([Signed\Game\Gear::make('green', 'lust', 1, 1.0)]) > Signed\Game\Gear::priceFactor([Signed\Game\Gear::make('green', 'lust', 1, 0.0)]), 'besser gewürfelt = teurer');
    checkEquals(3, count(Signed\Game\Gear::legacy(['id' => 1, 'model' => 'gtr_sunburst', 'klang' => 88, 'spielbarkeit' => 38, 'design' => 80, 'perks' => ['stage']])), 'alte Instrumente bekommen Boni');
});

test('Zusammenspiel erhöht den Sound', function () {
    $band = [musician(), musician(['instr1' => 'drums'])];
    check(BandCalc::sound($band, 100, []) > BandCalc::sound($band, 0, []));
});

test('Bekanntheit wird zu gleichen Teilen verteilt, Extrovertierte holen auf der Bühne mehr heraus', function () {
    $quiet = [musician(['personality' => []]), musician(['personality' => []])];
    $loud = [musician(['personality' => ['rampensau']]), musician(['personality' => []])];
    $gainsQuiet = BandCalc::splitFame($quiet, 10, [], true);
    $gainsLoud = BandCalc::splitFame($loud, 10, [], true);
    checkEquals(1, count(array_unique($gainsLoud)), 'Alle bekommen gleich viel');
    check(array_sum($gainsLoud) > array_sum($gainsQuiet), 'mehr Bekanntheit');
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
    $notes = BandCalc::chemistryNotes($divas);
    check(count(array_filter($notes, static fn ($n) => !$n['good'] && str_contains($n['text'], 'reiben sich'))) > 0, 'Erklärt, wer sich reibt');
});

test('Zusammenhalt: ergibt sich aus Durchschnitt und schwächster Beziehung und wirkt auf den Sound', function (): void {
    checkEquals(null, Signed\Game\Mood::togetherness([]), 'Solokünstler: keine Paare');
    checkEquals(100.0, Signed\Game\Mood::cohesionValue([]), 'Solokünstler streiten nicht');
    $even = Signed\Game\Mood::togetherness(['1-2' => 6.0, '1-3' => 6.0, '2-3' => 6.0]);
    $weak = Signed\Game\Mood::togetherness(['1-2' => 10.0, '1-3' => 10.0, '2-3' => -10.0]);
    check($even['value'] > $weak['value'], 'gleiche Durchschnitte: die schwächste Beziehung zieht runter');
    checkEquals(50.0, Signed\Game\Mood::cohesionValue(['1-2' => 0.0]), 'Fremde: Mitte');
    checkEquals(100.0, Signed\Game\Mood::cohesionValue(['1-2' => 10.0]));
    checkEquals(0.0, Signed\Game\Mood::cohesionValue(['1-2' => -10.0]));
    $members = [musician(['id' => 1]), musician(['id' => 2])];
    $bad = BandCalc::sound($members, Signed\Game\Mood::cohesionValue(['1-2' => -10.0]), []);
    $good = BandCalc::sound($members, Signed\Game\Mood::cohesionValue(['1-2' => 10.0]), []);
    check($good > $bad * 1.3, 'Streit kostet Sound: ' . $bad . ' gegen ' . $good);
});

test('Besetzungsregeln', function () {
    checkEquals([], BandCalc::lineupProblems([musician()]), 'Ein Solokünstler ist eine Band');
    check(BandCalc::lineupProblems([]) !== [], 'Ohne Musiker keine Band');
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

test('Manager: Stufe und längere Gigs', function () {
    $manager = ['level' => 4, 'skills' => ['verhandler']];
    checkEquals(4.0, BandCalc::managerLevel($manager));
    checkEquals(0.0, BandCalc::managerLevel(null));
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
        checkEquals(1, count($m['abilities']), 'genau eine Spieltechnik');
        check(in_array($m['abilities'][0], array_merge(...array_map(static fn ($i) => Catalog::INSTRUMENTS[$i]['techniques'], array_filter([$m['instr1'], $m['instr2']]))), true), 'passt zum Instrument');
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

test('Stufen: Können- und Bekanntheitsleiter, Techniken passen zum Instrument, je Instrument und Genre mindestens drei', function () {
    checkEquals(0, Signed\Game\Levels::forSkill(34.9));
    checkEquals(1, Signed\Game\Levels::forSkill(35));
    checkEquals(3, Signed\Game\Levels::forSkill(99));
    checkEquals(0, Signed\Game\Levels::forFame(0));
    checkEquals(3, Signed\Game\Levels::forFame(5000));
    foreach (Signed\Game\Levels::fittingTechniques(['drums'], ['metal']) as $t) {
        check(in_array($t, Catalog::INSTRUMENTS['drums']['techniques'], true) && in_array('metal', Catalog::ABILITIES[$t]['genres'], true), "$t passt nicht");
    }
    foreach (Catalog::INSTRUMENTS as $type => $instrument) {
        foreach ($instrument['genres'] as $g) {
            $n = count(array_filter($instrument['techniques'], static fn ($t) => in_array($g, Catalog::ABILITIES[$t]['genres'], true)));
            check($n >= 3, "$type/$g: nur $n Techniken");
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
    check(Signed\Game\Charts::points(3, 0.9, 30, false) > Signed\Game\Charts::points(3, 0.5, 30, false));
    check(Signed\Game\Charts::points(10, 0.8, 30, false) > Signed\Game\Charts::points(6, 0.8, 30, false) * 1.5);
    check(Signed\Game\Charts::points(3, 0.8, 30, true) > Signed\Game\Charts::points(3, 0.8, 30, false));
    $curve = [Signed\Game\Charts::class, 'curve'];
    checkEquals(1.0, $curve(0), 'Einstieg mit den vollen Punkten');
    check(abs($curve(3600) - (1 - Balance::CHART_DECAY)) < 1e-9, 'jede Stunde derselbe Anteil weniger');
    check(abs($curve(5 * 3600) / $curve(4 * 3600) - $curve(3600)) < 1e-9, 'jede Stunde derselbe Anteil weniger');
    check($curve(4 * 86400) < 0.1, 'nach vier Tagen fast draußen');
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

test('Gigs: frisches Material zählt', function () {
    checkEquals(1.0, Signed\Game\BandCalc::freshness(0));
    check(Signed\Game\BandCalc::freshness(3) < 1.0);
    checkEquals(Balance::GIG_FRESH['min'], Signed\Game\BandCalc::freshness(50));
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

test('Studio: Produzenten schalten Studios frei, die Legende geht überall hin, aber nur mit gutem Sound', function () {
    checkEquals(null, Signed\Game\Studio::refusal(0, 'none', 10));
    check(Signed\Game\Studio::refusal(1, 'none', 10) !== null);
    checkEquals(null, Signed\Game\Studio::refusal(1, 'mo', 10));
    check(Signed\Game\Studio::refusal(2, 'mo', 10) !== null);
    checkEquals(null, Signed\Game\Studio::refusal(2, 'roxy', 10));
    checkEquals(null, Signed\Game\Studio::refusal(3, 'diamond', 10));
    check(Signed\Game\Studio::refusal(0, 'legend', 10) !== null);
    checkEquals(null, Signed\Game\Studio::refusal(0, 'legend', 99));
    $members = [musician(['id' => 1, 'personality' => []])];
    $q = fn ($s, $p) => Signed\Game\Studio::quality($members, [], $s, $p);
    check($q(0, 'none') < $q(0, 'mo') && $q(0, 'mo') < $q(1, 'mo') && $q(3, 'diamond') <= 1.0);
    checkEquals(Balance::RECORD_QUALITY_BASE, $q(0, 'none'));
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

test('Proben: Hauptquelle für Können, ohne Stufen-Bremse – Stufe 1 in wenigen Stunden, Stufe 3 erreichbar', function () {
    $skill = 15.0;
    $probes = 0;
    $reached = [];
    while ($skill < 85.0 && $probes < 1000) {
        $m = musician(['skill1' => $skill, 'band_slot' => 1]);
        $skill += BandCalc::rehearsalGain($m, ['klang' => 10, 'spielbarkeit' => 0, 'perks' => []]);
        $probes++;
        foreach ([35, 60, 85] as $mark) {
            if (!isset($reached[$mark]) && $skill >= $mark) {
                $reached[$mark] = $probes;
            }
        }
    }
    // Eine Probe dauert 15 Minuten Spielzeit.
    $hours = static fn (int $mark): float => $reached[$mark] * Balance::REHEARSAL['seconds'] / 3600;
    check($hours(35) <= 4, 'Stufe 1 nach höchstens 4 Stunden: ' . $hours(35));
    check($hours(60) <= 15, 'Stufe 2 nach höchstens 15 Stunden: ' . $hours(60));
    check($hours(85) <= 60, 'Stufe 3 nach höchstens 60 Stunden: ' . $hours(85));
    check($hours(60) > $hours(35) && $hours(85) > $hours(60), 'jede Stufe dauert länger als die vorige');
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

test('Musik: Elektro hat eigene Aufnahmen, Turntables mit Noten fürs Rhythmus-Spiel', function () {
    $r = Signed\Game\Music::resolve(['type' => 'turntables', 'level' => 0, 'genre' => 'elektro', 'electric' => true]);
    checkEquals('elektro-0/turntables', $r['folder'] . '/' . $r['file']);
    check(count(Signed\Game\Music::notes($r)['notes']) > 20, 'Turntables haben Noten');
    // Stufe 1 scratcht mit Doppeltönen, Stufe 0 nur Einzeltöne.
    $hard = Signed\Game\Music::resolve(['type' => 'turntables', 'level' => 1, 'genre' => 'elektro', 'electric' => true]);
    checkEquals('elektro-1/turntables', $hard['folder'] . '/' . $hard['file']);
    check(count(Signed\Game\Music::notes($hard)['notes']) > count(Signed\Game\Music::notes($r)['notes']));
    $k1 = Signed\Game\Music::resolve(['type' => 'keys', 'level' => 1, 'genre' => 'elektro', 'electric' => true]);
    checkEquals('elektro-0/keyboard-elektrisch', $k1['folder'] . '/' . $k1['file'], 'Andere Spuren von Stufe 0');
    $k = Signed\Game\Music::resolve(['type' => 'keys', 'level' => 0, 'genre' => 'elektro', 'electric' => true]);
    checkEquals('elektro-0/keyboard-elektrisch', $k['folder'] . '/' . $k['file']);
    $m = Signed\Game\Music::resolve(['type' => 'vocals', 'level' => 0, 'genre' => 'elektro', 'electric' => false, 'voice' => 'maennlich']);
    checkEquals('elektro-0/gesang-maennlich', $m['folder'] . '/' . $m['file']);
});

test('Etüden: alle Genres und Instrumente, mehr Können = mehr Noten, dieselbe Nummer = dieselbe Etüde', function () {
    foreach (Signed\Game\Styles::GENRES as $genre => $style) {
        $steps = $style['meter'] * 4;
        foreach (['drums', 'bass', 'comp', 'lead'] as $role) {
            foreach ($style[$role] as $set) {
                foreach ($set as $part) {
                    foreach ((array) $part as $pattern) {
                        checkEquals($steps, strlen($pattern), "$genre $role: Musterlänge");
                    }
                }
            }
        }
        foreach (array_keys(Signed\Game\Styles::ROLES) as $instrument) {
            $easy = Signed\Game\Etude::build($instrument, $genre, 5, 11, 24);
            $hard = Signed\Game\Etude::build($instrument, $genre, 90, 11, 24);
            check(count($easy['notes']) > 0, "$genre $instrument: Anfänger haben Noten");
            check(count($hard['notes']) >= count($easy['notes']), "$genre $instrument: mit Können nicht weniger");
            if ($easy['mode'] === 'rhythm') {
                $key = static fn ($n) => $n['t'] . '/' . $n['l'];
                checkEquals([], array_values(array_diff(array_map($key, $easy['notes']), array_map($key, $hard['notes']))), "$genre $instrument: dieselbe Etüde, nur voller");
            }
        }
    }
    // Gleiches Können, gleiche Dichte – egal welches Muster und Tempo die Nummer zieht.
    foreach (['drums', 'bass', 'guitar'] as $instrument) {
        $counts = array_map(static fn ($seed) => count(Signed\Game\Etude::build($instrument, 'rock', 40, $seed, 24)['notes']), range(1, 30));
        check(min($counts) >= 0.85 * max($counts), "$instrument: Etüden gleich dicht");
    }
    checkEquals(Signed\Game\Etude::build('keys', 'jazz', 40, 99, 24), Signed\Game\Etude::build('keys', 'jazz', 40, 99, 24));
    checkEquals(['root' => 10, 'minor' => false, 'tones' => [0, 4, 7]], array_diff_key(Signed\Game\Etude::chord('bVII', 'major'), ['name' => 1]));
    checkEquals([0, 3, 7, 10], Signed\Game\Etude::chord('ii7', 'major')['tones']);
});

test('Namen: Vornamen, die im Label schon vorkommen, werden gemieden', function (): void {
    $keep = ['Lena', 'Finn', 'Kim'];
    $avoid = array_values(array_diff(array_merge(...array_values(Signed\Game\Catalog::FIRST_NAMES)), $keep));
    for ($i = 0; $i < 30; $i++) {
        $card = Signed\Game\Generator::musician('common', ['avoid' => $avoid]);
        check(in_array($card['first_name'], $keep, true), 'freier Vorname: ' . $card['first_name']);
    }
    foreach (Signed\Game\Catalog::FIRST_NAMES as $gender => $names) {
        checkEquals(count($names), count(array_unique($names)), "$gender: keine doppelten Vornamen");
    }
});

test('Große Bands: mehr Sound und mehr Gage je Mitglied über zwei', function (): void {
    $sound = static fn (int $n) => Signed\Game\BandCalc::sound(array_map(static fn ($i) => musician(['id' => $i, 'instr1' => 'guitar', 'skill1' => 50.0]), range(1, $n)), 100, []);
    checkEquals(round($sound(2) * 1.2, 1), $sound(6), 'sechs Gitarren: × 1,2 gegenüber zwei');
    $venue = Signed\Game\BandCalc::venue(0);
    checkEquals((int) round(Signed\Game\BandCalc::gagePerSong($venue, 0.0) * 1.4), Signed\Game\BandCalc::gagePerSong($venue, 0.0, 6));
});

test('Crossover: alle teilen drei Genres', function (): void {
    $m = static fn ($id, $type) => musician(['id' => $id, 'instr1' => $type, 'genres' => ['pop', 'rock', 'folk']]);
    $identity = BandCalc::identity([$m(1, 'guitar'), $m(2, 'drums'), $m(3, 'vocals'), $m(4, 'violin')]);
    check($identity['third'] !== null, 'drittes Genre erkannt');
    checkEquals(Balance::CROSSOVER_BONUS, $identity['bonus']);
    check(str_contains($identity['title'], 'Crossover'), $identity['title']);
});

test('Jede Genre-Kombination (Hybrid und Crossover) lässt sich besetzen', function (): void {
    $instruments = array_keys(Catalog::INSTRUMENTS);
    $lineups = [];
    $grow = static function (array $lineup, int $from) use (&$grow, &$lineups, $instruments): void {
        if (count($lineup) >= Balance::MAX_MEMBERS) {
            return;
        }
        for ($i = $from; $i < count($instruments); $i++) {
            $next = [...$lineup, $instruments[$i]];
            $counts = array_count_values($next);
            foreach (Balance::MAX_PER_INSTRUMENT as $type => $max) {
                if (($counts[$type] ?? 0) > $max) {
                    continue 2;
                }
            }
            $lineups[] = $counts;
            $grow($next, $i);
        }
    };
    $grow([], 0);
    $meets = static function (array $counts, string $genre): bool {
        foreach (Catalog::GENRE_LINEUP[$genre] as [$options, $need]) {
            if (array_sum(array_map(static fn ($t) => $counts[$t] ?? 0, $options)) < $need) {
                return false;
            }
        }
        return true;
    };
    $genres = array_keys(Catalog::GENRE_LINEUP);
    foreach ($genres as $i => $a) {
        foreach (array_slice($genres, $i + 1) as $j => $b) {
            foreach ([null, ...array_slice($genres, $i + $j + 2)] as $c) {
                $combo = array_filter([$a, $b, $c]);
                $possible = array_filter($lineups, static fn ($l) => array_sum($l) >= Balance::MIN_MEMBERS
                    && !in_array(false, array_map(static fn ($g) => $meets($l, $g), $combo), true));
                check($possible !== [], implode(' + ', $combo) . ' braucht mehr als ' . Balance::MAX_MEMBERS . ' Musiker');
            }
        }
    }
    // Musiker: Sänger mögen jedes Genre und können als zweites Instrument alles spielen.
    checkEquals($genres, array_values(array_intersect($genres, Catalog::INSTRUMENTS['vocals']['genres'])));
});

test('Beziehung: Passung nach Charakter, Genres und Können; Zielwert und Wachstum', function (): void {
    $a = musician(['personality' => ['diva'], 'genres' => ['rock', 'punk']]);
    $b = musician(['personality' => ['diva'], 'genres' => ['pop']]);
    check(Signed\Game\Mood::fit($a, $b) < 0, 'zwei Divas, kein gemeinsames Genre: Reibung');
    $c = musician(['personality' => ['teamplayer'], 'genres' => ['rock', 'punk']]);
    $d = musician(['personality' => ['partytier'], 'genres' => ['rock', 'punk']]);
    check(Signed\Game\Mood::fit($c, $d) > 0.4, 'Teamplayer, zwei gemeinsame Genres: gute Passung');
    $far = musician(['skill1' => 90.0, 'genres' => ['rock']]);
    $near = musician(['skill1' => 20.0, 'genres' => ['rock']]);
    check(Signed\Game\Mood::fit($far, $near) < Signed\Game\Mood::fit($near, musician(['skill1' => 25.0, 'genres' => ['rock']])), 'großer Können-Abstand verschlechtert');
    check(Signed\Game\Mood::relationTarget(1.0) === 10.0 && Signed\Game\Mood::relationTarget(-1.0) === -7.0, 'Ziel begrenzt auf ±10');
    check(Signed\Game\Mood::relationTarget(-0.6) < 0, 'starke Reibung: nie gut');
});

test('Stimmung: Ampel nach dem Unzufriedensten, Miesepeter zieht die anderen mit runter', function (): void {
    checkEquals('green', Signed\Game\Mood::bandMood([80.0, 70.0, 65.0])['level']);
    checkEquals('yellow', Signed\Game\Mood::bandMood([90.0, 90.0, 50.0])['level'], 'einer gelb: Band gelb');
    checkEquals('red', Signed\Game\Mood::bandMood([90.0, 90.0, 20.0])['level']);
    check(Signed\Game\Mood::bandMood([90.0, 90.0, 20.0])['value'] < 60, 'der Wert folgt dem Unzufriedensten');
    $band = ['genre' => 'rock'];
    $a = musician(['id' => 1, 'genres' => ['rock'], 'personality' => []]);
    $b = musician(['id' => 2, 'genres' => ['rock'], 'personality' => []]);
    $calm = Signed\Game\Mood::target($a, $band, [$a, $b], [2 => 70.0], ['1-2' => 0.0], 0.0);
    $foeCalm = Signed\Game\Mood::target($a, $band, [$a, $b], [2 => 70.0], ['1-2' => -8.0], 0.0);
    $foeHit = Signed\Game\Mood::target($a, $band, [$a, $b], [2 => 10.0], ['1-2' => -8.0], 0.0);
    $friendCalm = Signed\Game\Mood::target($a, $band, [$a, $b], [2 => 70.0], ['1-2' => 8.0], 0.0);
    $friendHit = Signed\Game\Mood::target($a, $band, [$a, $b], [2 => 10.0], ['1-2' => 8.0], 0.0);
    check($foeHit < $foeCalm - 5, 'ein Unzufriedener mit schlechter Beziehung zieht runter');
    check($friendHit < $friendCalm - 2, 'auch Freunde leiden mit');
    check($foeCalm - $foeHit > $friendCalm - $friendHit, 'bei Reibung stärker als bei Freundschaft');
    $stau = Signed\Game\Mood::target($a, $band, [$a, $b], [2 => 70.0], ['1-2' => 0.0], 24.0);
    check($stau < $calm - 10, 'Songstau drückt die Stimmung');
    $mixed = Signed\Game\Mood::target($a, ['genre' => ''], [$a, $b], [2 => 70.0], ['1-2' => 0.0], 0.0);
    check($mixed < $calm, 'gemischte Band ist schlechter drauf');
});

test('Solokünstler: Band-Wunsch drückt Teamplayer, Diva freut sich, Songstau zählt', function (): void {
    $solo = static fn (array $traits) => musician(['id' => 7, 'personality' => $traits, 'genres' => ['pop']]);
    $band = ['genre' => ''];
    $normal = Signed\Game\Mood::target($solo(['gelassen']), $band, [$solo(['gelassen'])], [], [], 0.0);
    $team = Signed\Game\Mood::target($solo(['teamplayer']), $band, [$solo(['teamplayer'])], [], [], 0.0);
    $diva = Signed\Game\Mood::target($solo(['diva']), $band, [$solo(['diva'])], [], [], 0.0);
    check($team < $normal, 'Teamplayer wünscht sich eine Band');
    check($diva > $normal, 'Diva liebt das Rampenlicht');
    check(Signed\Game\Mood::target($solo(['gelassen']), $band, [$solo(['gelassen'])], [], [], 24.0) < $normal, 'Songstau drückt auch Solokünstler');
    $one = [musician(['personality' => ['gelassen']])];
    checkEquals(1.0, BandCalc::identity($one)['bonus'], 'kein Malus der gemischten Band');
});

test('Songs schreiben: Tempo nach Stimmung, Schub bei bester Laune, Pause bei Auftritt und Promo, langsamer beim Proben', function (): void {
    $rate = static fn (string $level, float $value) => Signed\Game\Mood::songRate(['level' => $level, 'value' => $value]);
    checkEquals(0.0, $rate('red', 20.0));
    checkEquals(0.5, $rate('yellow', 50.0));
    checkEquals(1.0, $rate('green', 65.0));
    check($rate('green', 90.0) > $rate('green', 65.0), 'Schub bei bester Laune');
    checkEquals(0.0, Signed\Game\Mood::activityFactor('gig'));
    checkEquals(0.0, Signed\Game\Mood::activityFactor('bandpromo'));
    check(Signed\Game\Mood::activityFactor('rehearsal') > 0 && Signed\Game\Mood::activityFactor('rehearsal') < 1);
    checkEquals(1.0, Signed\Game\Mood::activityFactor(null));
});

test('Genre: gemeinsames Genre ohne Besetzung gibt weder Bonus noch Abzug, kein gemeinsames Genre den Abzug', function () {
    $folkA = ['genres' => ['folk'], 'instr1' => 'guitar', 'instr2' => null, 'band_slot' => 1];
    $folkB = ['genres' => ['folk'], 'instr1' => 'bass', 'instr2' => null, 'band_slot' => 1];
    $metal = ['genres' => ['metal'], 'instr1' => 'bass', 'instr2' => null, 'band_slot' => 1];
    $violin = ['genres' => ['folk'], 'instr1' => 'violin', 'instr2' => null, 'band_slot' => 1];
    checkEquals(1.0, BandCalc::identity([$folkA, $folkB])['bonus'], 'gleiches Genre, Besetzung fehlt');
    checkEquals(Balance::MIXED_BONUS, BandCalc::identity([$folkA, $metal])['bonus'], 'verschiedene Genres');
    checkEquals(Balance::GENRE_BONUS, BandCalc::identity([$folkA, $violin])['bonus'], 'gleiches Genre mit Besetzung');
});

test('Solo-Genre-Bonus: nur Pop (Gesang) und Elektro, und nur halb', function () {
    $singer = ['genres' => ['pop'], 'instr1' => 'vocals', 'instr2' => null, 'band_slot' => 1];
    $keys = ['genres' => ['elektro'], 'instr1' => 'keys', 'instr2' => null, 'band_slot' => 1];
    $guitar = ['genres' => ['rock'], 'instr1' => 'guitar', 'instr2' => null, 'band_slot' => 1];
    $half = 1 + (Balance::GENRE_BONUS - 1) * Balance::SOLO_GENRE_SHARE;
    checkEquals(round($half, 4), round(BandCalc::identity([$singer])['bonus'], 4), 'Pop solo');
    checkEquals(round($half, 4), round(BandCalc::identity([$keys])['bonus'], 4), 'Elektro solo');
    checkEquals(1.0, BandCalc::identity([$guitar])['bonus'], 'Rock solo: kein Bonus');
});

test('Songschreiben: jedes Mitglied trägt bei, Kreative viel mehr, Technische nichts; allein sehr langsam', function () {
    $m = static fn (array $traits = []) => ['personality' => $traits, 'abilities' => []];
    $f = static fn (array $members) => Signed\Game\Mood::crewFactor($members);
    checkEquals(1.0, round($f([$m(), $m(), $m(), $m()]), 4), 'Vierer-Band = Grundtempo');
    check($f([$m()]) <= 0.3, 'allein sehr langsam');
    check(4 * $f([$m()]) <= 0.6 * $f([$m(), $m(), $m(), $m()]), 'vier Solokünstler zusammen deutlich langsamer als die Band');
    check($f([$m(['kreativ'])]) > 2.5 * $f([$m()]), 'Kreative schreiben viel mehr');
    check($f([$m(['kreativ'])]) < $f([$m(), $m(), $m(), $m()]), 'auch kreativ allein langsamer als eine Band');
    checkEquals(0.0, $f([$m(['technisch'])]), 'Technische schreiben solo gar nichts');
    check($f([$m(['technisch']), $m(), $m(), $m()]) < $f([$m(), $m(), $m(), $m()]), 'und bremsen die Band');
    check($f([$m(['chaot'])]) > $f([$m()]) && $f([$m(['perfektionist'])]) < $f([$m()]), 'Impulsive schneller, Perfektionisten langsamer');
});

test('Charakterzüge: Altdaten (Songwriter, Interpret) werden zu kreativ und technisch', function () {
    $row = ['id' => 1, 'personality' => json_encode(['ehrgeizig']), 'abilities' => json_encode(['talent', 'songwriter'])];
    $m = (new ReflectionMethod(Signed\Game\Repo::class, 'legacyTraits'))->invoke(null, ['ehrgeizig'], ['talent', 'songwriter']);
    checkEquals(['ehrgeizig', 'kreativ'], $m);
    $m = (new ReflectionMethod(Signed\Game\Repo::class, 'legacyTraits'))->invoke(null, [], ['nowriter']);
    checkEquals(['technisch'], $m);
});

test('Charakterzüge: jeder hat eine Stärke und eine Schwäche', function () {
    $t = Balance::TRAITS;
    $band = ['genre' => 'rock', 'stageIdle' => 30.0, 'recordIdle' => 80.0];
    $fresh = ['genre' => 'rock', 'stageIdle' => 1.0, 'recordIdle' => 1.0];
    $x = static fn (array $traits) => musician(['id' => 1, 'genres' => ['rock'], 'personality' => $traits]);
    $y = musician(['id' => 2, 'genres' => ['rock'], 'personality' => []]);
    $target = static fn (array $m, array $b, float $songs = 0.0, float $other = 70.0) => Signed\Game\Mood::target($m, $b, [$m, $y], [2 => $other], ['1-2' => 0.0], $songs);
    // Extrovertierte und Gesellige werden unruhig ohne Auftritte, Ehrgeizige ohne neue Platte
    check($target($x(['rampensau']), $band) < $target($x(['rampensau']), $fresh), 'extrovertiert: unruhig ohne Bühne');
    check($target($x(['partytier']), $band) < $target($x(['partytier']), $fresh), 'gesellig: unruhig ohne Bühne');
    check($target($x(['ehrgeizig']), $band) < $target($x(['ehrgeizig']), $fresh), 'ehrgeizig: ohne neue Platte unzufrieden');
    checkEquals($target($x([]), $band), $target($x([]), $fresh), 'ohne solche Züge egal');
    // Gemütliche nehmen liegende Songs gelassener
    check($target($x(['gelassen']), $fresh, 24.0) > $target($x([]), $fresh, 24.0), 'gemütlich: Songstau zählt weniger');
    // Kooperative leiden mit, wenn es jemandem schlecht geht
    check($target($x(['teamplayer']), $fresh, 0.0, 10.0) < $target($x([]), $fresh, 0.0, 10.0), 'kooperativ: leidet mit');
    // Kreative lernen beim Proben langsamer, Technische schneller
    $model = ['klang' => 50, 'spielbarkeit' => 50, 'design' => 50, 'perks' => []];
    $gain = static fn (array $traits) => BandCalc::rehearsalGain(musician(['personality' => $traits, 'skill1' => 30.0]), $model);
    check($gain(['technisch']) > $gain([]) && $gain(['kreativ']) < $gain([]), 'Probe: technisch schneller, kreativ langsamer');
    // Auf der Bühne: Extrovertierte bekommen mehr Ruhm ab
    check(BandCalc::fameShare($x(['rampensau']), null, true) > BandCalc::fameShare($x([]), null, true), 'extrovertiert: mehr Ruhm auf der Bühne');
    checkEquals(BandCalc::fameShare($x(['rampensau']), null, false), BandCalc::fameShare($x([]), null, false), 'aber nicht im Studio');
    check(isset($t['recordQuality']['perfektionist'], $t['recordSales']['diva'], $t['live']['chaot'], $t['live']['ruhepol']), 'Studio- und Live-Wirkungen sind gesetzt');
    foreach (array_keys(Signed\Game\Catalog::PERSONALITIES) as $p) {
        check(isset(Signed\Game\Catalog::PERSONALITY_INFO[$p], Signed\Game\Catalog::LIKES[$p], Signed\Game\Catalog::LUST[$p], Signed\Game\Catalog::WISHES[$p], Signed\Game\Catalog::WISHES_SOLO[$p]), "$p ist vollständig");
    }
});

test('Solokünstler: allein ist es einsam, Teamplayer und Rampensau leiden mehr, Diva genießt es', function () {
    $band = ['genre' => '', 'room' => 0];
    $solo = static fn (array $traits) => ['id' => 1, 'personality' => $traits, 'genres' => ['pop'], 'skill1' => 30.0, 'skill2' => null, 'instr1' => 'vocals', 'instr2' => null, 'band_slot' => 1, 'abilities' => []];
    $t = static fn (array $traits) => Signed\Game\Mood::target($solo($traits), $band, [$solo($traits)], [], [], 0.0);
    check($t([]) < Balance::MOOD['start'], 'einsam');
    check($t(['teamplayer']) < $t(['rampensau']) && $t(['rampensau']) < $t([]), 'Teamplayer am meisten, dann Rampensau');
    check($t(['diva']) > $t([]), 'Diva freut sich');
});

test('Spieltechniken: nur lernbar, wenn man eins ihrer Genres mag, und Sound nur im passenden Genre', function () {
    foreach (Signed\Game\Catalog::ABILITIES as $key => $a) {
        if (!empty($a['technique'])) {
            checkEquals(2, count($a['genres']), "$key hat zwei Genres");
        }
    }
    $pop = Signed\Game\Levels::fittingTechniques(['vocals'], ['pop']);
    check(!in_array('growl', $pop, true) && in_array('falsett', $pop, true), 'Pop-Sänger: Falsett ja, Growl nein');
    check(in_array('growl', Signed\Game\Levels::fittingTechniques(['vocals'], ['metal']), true), 'Metal-Sänger: Growl ja');
    $growler = ['personality' => [], 'genres' => ['metal'], 'abilities' => ['growl']];
    check(BandCalc::techniqueBonus([$growler], ['metal']) > 1.0, 'Growl in einer Metal-Band bringt Sound');
    checkEquals(1.0, BandCalc::techniqueBonus([$growler], ['pop']), 'in einer Pop-Band nicht');
    checkEquals(1.0, BandCalc::techniqueBonus([$growler, $growler], []), 'gemischte Band: nichts');
    check(BandCalc::techniqueBonus([$growler], []) > 1.0, 'solo zählen die eigenen Genres');
});
