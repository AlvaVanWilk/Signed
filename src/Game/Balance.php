<?php
declare(strict_types=1);

namespace Signed\Game;

/**
 * Alle Zahlen des Spiels an einem Ort. Das sind Startwerte, die beim Testen nachjustiert werden.
 * Wie die Werte zusammenwirken, steht in docs/kern.md.
 */
final class Balance
{
    // ---------- Label ----------
    public const START_MONEY = 2000;

    /** Ein Lied dauert überall 24 Sekunden: 12 Takte 4/4 (oder 16 Takte 3/4) bei 120 BPM – im Minispiel und bei Auftritten. */
    public const SONG_SECONDS = 24;
    public const BPM = 120;

    // ---------- Musiker ----------
    /**
     * Startwerte je Pack-Qualität [min, max]. Aus Packs kommen Musiker höchstens mit Können-Stufe 1,
     * deshalb bleibt das Können unter der zweiten Schwelle. Ruhm haben neue Musiker nie.
     */
    public const MUSICIAN = [
        'common' => ['skill' => [5, 30], 'presence' => [5, 25]],
        'rare' => ['skill' => [15, 42], 'presence' => [10, 35]],
        'epic' => ['skill' => [25, 52], 'presence' => [15, 45]],
        'legendary' => ['skill' => [35, 58], 'presence' => [25, 55]],
    ];
    /** Können-Stufen 1–3 ab diesen Werten; Bekanntheits-Stufen 1–3 ab diesem Ruhm. */
    public const SKILL_LEVELS = [35, 60, 85];
    public const FAME_LEVELS = [20, 120, 600];
    public const MAX_STAT = 100;
    public const SECOND_INSTRUMENT_CHANCE = 0.2;
    /** Wer zwei Instrumente spielt, ist in beidem schwächer als ein Spezialist. */
    public const MULTI_MAIN_FACTOR = 0.85;
    public const MULTI_SECOND_FACTOR = [0.55, 0.8];
    /** Lieblingsgenre passt nicht zum Klischee des Instruments (Flötist im Metal). */
    public const UNUSUAL_CHANCE = 0.02;
    /** Chance auf ein zweites bzw. drittes Genre-Symbol. */
    public const EXTRA_GENRE_CHANCE = [0.35, 0.08];

    // ---------- Manager ----------
    public const MANAGER_LEVEL = ['common' => [1, 1], 'rare' => [2, 2], 'epic' => [3, 4], 'legendary' => [5, 5]];
    public const MANAGER_SECOND_SKILL = 0.3;

    // ---------- Packs ----------
    /**
     * Packs sind Orte zum Stöbern: Man sieht `choices` verdeckte Karten und nimmt eine mit.
     * kinds = Gewichte, welche Kartenart je Platz kommt; slots = feste Kartenarten.
     * tip = manchmal ist eine Karte ein Instrument für einen Musiker, der noch keins hat; chooseType = Instrumentenart selbst wählen.
     */
    public const PACKS = [
        'troedel' => [
            'name' => 'Trödelmarkt', 'price' => 120, 'desc' => 'Meist Ramsch, manchmal ein Schatz – und ab und zu jemand, der Musik macht.',
            'choices' => 4, 'kinds' => ['gear' => 70, 'musician' => 24, 'manager' => 6],
            'odds' => ['common' => 86, 'rare' => 11, 'epic' => 2.5, 'legendary' => 0.5],
        ],
        'aushang' => [
            'name' => 'Schwarzes Brett', 'price' => 200, 'desc' => 'Musiker suchen eine Band.',
            'choices' => 4, 'kinds' => ['musician' => 100],
            'odds' => ['common' => 68, 'rare' => 26, 'epic' => 5, 'legendary' => 1],
        ],
        'secondhand' => [
            'name' => 'Second-Hand-Laden', 'price' => 350, 'desc' => 'Gebrauchte Instrumente in ordentlichem Zustand.',
            'choices' => 4, 'kinds' => ['gear' => 100],
            'odds' => ['common' => 50, 'rare' => 38, 'epic' => 10, 'legendary' => 2],
        ],
        'musicstore' => [
            'name' => 'Music Store', 'price' => 800, 'desc' => 'Neue Instrumente. Manchmal hat der Verkäufer einen Tipp für einen deiner Musiker.',
            'choices' => 4, 'kinds' => ['gear' => 100], 'tip' => true,
            'odds' => ['common' => 10, 'rare' => 55, 'epic' => 30, 'legendary' => 5],
        ],
        'backstage' => [
            'name' => 'Backstage-Pass', 'price' => 1500, 'desc' => 'Hinter der Bühne triffst du Musiker, Manager und ihr Equipment.',
            'choices' => 4, 'slots' => ['musician', 'musician', 'manager', 'gear'],
            'odds' => ['common' => 0, 'rare' => 55, 'epic' => 35, 'legendary' => 10],
        ],
        'werkstatt' => [
            'name' => 'Meisterwerkstatt', 'price' => 2400, 'desc' => 'Eine Privat-Anfertigung für ein Instrument deiner Wahl.',
            'choices' => 3, 'kinds' => ['gear' => 100], 'chooseType' => true,
            'odds' => ['common' => 0, 'rare' => 0, 'epic' => 75, 'legendary' => 25],
        ],
    ];
    /**
     * Music Store: unten die Grundausstattung (jedes gewöhnliche Modell, immer da, beliebig oft), oben das Angebot des Tages.
     * Das Angebot wechselt alle `hours` Stunden (um `offset` Uhr UTC, also früh morgens) und ist für alle Labels gleich:
     * Im Regal stehen immer `shelf` Instrumente, meist seltene, mit der Chance `blue` ist eins davon episch. Mit der Chance `gold` kommt
     * ein legendäres dazu (im Schnitt etwa einmal die Woche) – das steht extra im Schaufenster über dem Regal.
     * Jedes Angebot kauft ein Label höchstens einmal.
     */
    public const STORE = [
        'hours' => 24, 'offset' => 4,
        'offer' => ['shelf' => 3, 'blue' => 0.35, 'gold' => 0.13],
        // Gewöhnliches ist leicht zu haben, Hochwertiges ein Sparziel (Startkapital 2.000 ₲).
        'price' => ['common' => 40, 'rare' => 320, 'epic' => 1500, 'legendary' => 5200],
    ];
    /**
     * Proberaum in Stufen: Jede Band hat ihren eigenen, auch jeder Solokünstler (zum vollen Preis).
     * skill = Faktor auf das Können je Probe, lust = Faktor darauf, wie lange die Band Lust hat, mood = Wohlbefinden je 15 Minuten Probe für alle.
     */
    public const ROOM = [
        'levels' => [
            ['name' => 'Bruchbude', 'cost' => 0, 'skill' => 1.0, 'lust' => 1.0, 'mood' => 0.0],
            ['name' => 'Aufgeräumter Keller', 'cost' => 400, 'skill' => 1.08, 'lust' => 1.15, 'mood' => 1.0],
            ['name' => 'Gemütlicher Proberaum', 'cost' => 1200, 'skill' => 1.16, 'lust' => 1.3, 'mood' => 2.5],
            ['name' => 'Profi-Proberaum', 'cost' => 3000, 'skill' => 1.25, 'lust' => 1.5, 'mood' => 4.0],
        ],
    ];
    /** Chance auf den Tipp des Verkäufers (Läden mit tip): Eine Karte passt zu einem Musiker ohne Instrument, Bandmitglieder zuerst. */
    public const STORE_TIP_CHANCE = 0.25;
    /** Pro Scout-Stufe sinkt die Chance auf gewöhnliche Karten um diesen Anteil. */
    public const SCOUT_SHIFT = 0.06;

    // ---------- Band ----------
    // Bands lassen sich ohne Obergrenze führen: Geld und Aufmerksamkeit bremsen von selbst (siehe docs/label-konzept.md).
    /** Auch ein Solokünstler ist eine Band (mit einem Mitglied). Genre-Bands und der Besetzungs-Bonus beginnen ab zwei Mitgliedern. */
    public const MIN_MEMBERS = 1;
    public const GENRE_MIN_MEMBERS = 2;
    /** Solokünstler können den Genre-Bonus nur in Genres bekommen, deren Besetzung eine Person erfüllt – und nur zum Teil (Anteil am Bonus). */
    public const SOLO_GENRES = ['pop', 'elektro'];
    public const SOLO_GENRE_SHARE = 0.5;
    public const SIZE_BONUS_FROM = 2;

    // ---------- Label-Umbau: Stimmung, Beziehung, Songs (siehe docs/label-konzept.md) ----------
    /**
     * Wohlbefinden der Musiker (0–100) nähert sich einem Zielwert mit der Zeitkonstante tau (Sekunden). Ampel der Band nach dem
     * unzufriedensten Mitglied: grün ab green, gelb ab yellow, darunter rot. Für Songtempo und Anzeige zählt der Mischwert
     * (minWeight × unzufriedenster + Rest × Durchschnitt). Rot friert eine Band offline ein; step ist die Rechenschrittweite.
     */
    public const MOOD = [
        'start' => 60.0, 'tau' => 12 * 3600, 'green' => 60.0, 'yellow' => 35.0, 'minWeight' => 0.7,
        'step' => 1800, 'maxCatchUp' => 14 * 86400, 'onlineCredit' => 90,
        // Einflüsse auf das Ziel: Beziehungen (je Punkt Durchschnitt), Können-Unterschied, Genre der Band, Songstau, Miesepeter
        'relationWeight' => 3.0,
        'gap' => ['good' => [5, 15, 4.0], 'weakFrom' => 25, 'weakPer' => 0.6, 'strongFrom' => 20, 'strongPer' => 0.5, 'max' => 20.0],
        'genre' => ['fits' => 6.0, 'misfit' => -8.0, 'mixed' => -4.0],
        'stau' => ['from' => 12, 'per' => 1.5, 'max' => 18.0],
        'pull' => ['per' => 0.15, 'max' => 20.0, 'friend' => 0.5],
        // Instrument: keins drückt, eins unter dem eigenen Können drückt je Stufe, ein passendes freut; Diven nehmen es doppelt so wichtig
        'gear' => ['none' => -6.0, 'behind' => -3.0, 'fits' => 2.0, 'traits' => ['diva' => 2.0]],
        // Songs je Stunde nach Ampel, Vorrat höchstens cap
        'songsPerHour' => ['green' => 1.0, 'yellow' => 0.5, 'red' => 0.0], 'songCap' => 24,
        // Wer ausgesprochen gut drauf ist (grün ab Wert `from`), schreibt schneller; auf der Bühne oder Promo-Tour ruht das Schreiben, beim Proben geht es langsamer weiter
        'songsBoost' => ['from' => 80.0, 'factor' => 1.5],
        'songsActivity' => ['stop' => ['gig', 'busking', 'openstage', 'bandpromo', 'retreat'], 'rehearsal' => 0.6],
        // Geduld (Sekunden Spielzeit im Roten) bis die Band geht; erst eingeschaltet, wenn die Anordnungen da sind
        'patience' => 1800.0, 'patienceRecover' => 2.0, 'leaving' => false,
        // Allein ist es einsam (soloBase für alle); wer eine Band oder Publikum um sich braucht, leidet stärker, Diven genießen es
        'soloBase' => -4.0,
        'solo' => ['teamplayer' => -12.0, 'rampensau' => -8.0, 'partytier' => -6.0, 'diva' => 8.0],
        // Anordnung nach Charakter (Catalog::LIKES): so viele Punkte Wohlbefinden gibt sie sofort (mag) oder nimmt sie (mag nicht)
        'reactStep' => 14.0,
    ];
    /**
     * Beziehung zwischen zwei Musikern (−10 bis +10, Start 0): Gemeinsame Erlebnisse ziehen sie zum Zielwert `base + scale × Passung`;
     * wer nicht mehr zusammen ist, driftet je Stunde um drift auf 0 zurück.
     */
    public const RELATION = [
        'max' => 10.0, 'scale' => 10.0, 'base' => 3.0, 'drift' => 0.2,
        'bond' => ['rehearsal' => 0.12, 'gig' => 0.10, 'record' => 0.08, 'bandpromo' => 0.05, 'retreat' => 0.08, 'battle' => 0.10, 'festival' => 0.12, 'tourgig' => 0.12, 'tour' => 0.35],
        'clash' => -0.5, 'teamplayer' => 0.25, 'party' => 0.3,
        'genres' => [0 => -0.3, 1 => 0.1, 2 => 0.3, 3 => 0.4], 'gapFrom' => 30, 'gapBig' => 50, 'gapPenalty' => [-0.2, -0.4],
    ];
    /**
     * Einsendungen (Bewerbungen): je Tag so viele, wie der Erfolg des Labels hergibt (Stützpunkte [Erfolg, pro Tag], dazwischen linear);
     * Verfall nach `ttl`; Anteil der Guten (ab Können-Stufe 1) wächst mit dem Erfolg; Unterschriftsprämie = base + skill × Können² + fame × Bekanntheit.
     */
    public const SUBMISSIONS = [
        // Kassetten je Tag = start + root × √Erfolg (fließend, ohne Obergrenze)
        'perDay' => ['start' => 8.0, 'root' => 1.8],
        'bandChance' => 0.15, 'bandBond' => 4.0,
        'listen' => 5, 'ttl' => 4 * 86400, 'maxCatchUp' => 4 * 86400, 'maxPerCall' => 120,
        'good' => ['from' => 0.05, 'to' => 0.25, 'at' => 5000],
        'goodLevels' => [1 => 70, 2 => 25, 3 => 5],
        'skill' => [0 => [6, 33], 1 => [35, 58], 2 => [60, 83], 3 => [85, 98]],
        'fameChance' => 0.3, 'fame' => [5, 110],
        'price' => ['base' => 40, 'skill' => 0.05, 'fame' => 1.5],
        // Nur einen Teil einer Band aufnehmen: Die anderen ziehen enttäuscht weiter, wer bleibt, ist gekränkt (Wohlbefinden sinkt einmalig,
        // erholt sich danach wie sonst). Teamplayer trifft es stärker, Ehrgeizige weniger.
        'splitHurt' => 20.0, 'splitTrait' => ['teamplayer' => 1.6, 'ehrgeizig' => 0.5],
    ];
    /**
     * Posteingang: Interview-Anfragen. Jeder Musiker für sich, nach seiner eigenen Bekanntheit: je Tag `perDay` = start + root × √Bekanntheit
     * (Unbekannte selten, Bekannte oft), offen höchstens 1 + Bekanntheitsstufe je Musiker (wer bekannt ist, hat mehrere Anfragen zugleich).
     * Wie viele Bands das Label sonst hat, spielt keine Rolle. Sie liegen `ttl` Sekunden.
     * Das Medium passt zur Bekanntheitsstufe (Catalog::INTERVIEW_MEDIA, dort auch der Anteil je Medium). Gewinn = Anteil × Weg von dieser Stufe
     * zur nächsten (FAME_LEVELS); auf der höchsten Stufe zählt `topSpan`. Annehmen schickt den Musiker `seconds[Stufe]` weg.
     */
    public const INTERVIEW = ['perDay' => ['start' => 0.1, 'root' => 0.05], 'ttl' => 4 * 86400, 'seconds' => [900, 1200, 1800, 2700], 'topSpan' => 600.0, 'maxCatchUp' => 86400];
    /**
     * Posteingang: Bewerbungen von Leuten fürs Label. Jeder Beruf hat seine eigene Rate (`rates`, je Tag), die mit dem Erfolg wächst; die
     * Ankünfte sind zufällig verteilt, und alles liegt so lange wie alle Post (POST['ttl']). Ein Praktikum liegt immer da.
     * Wie gut die Bewerber sind, folgt dem Erfolg des Labels, aber gesättigt (oben offen): Güte = Erfolg / (Erfolg + `half`).
     * Eine Stufe bewirbt sich erst ab ihrer Güte `from`; die beste erreichbare kommt am häufigsten (`weights`: beste, zweitbeste, Rest).
     */
    public const JOBS = [
        'half' => 300.0, 'weights' => [3, 2, 1],
        // Je Beruf: Bewerbungen je Tag = start + root × √Erfolg (fließend, ohne Obergrenze).
        'rates' => [
            'intern' => ['start' => 0.8, 'root' => 0.033],
            'manager' => ['start' => 0.3, 'root' => 0.019],
            'scout' => ['start' => 0.2, 'root' => 0.014],
            'assistant' => ['start' => 0.12, 'root' => 0.011],
            'teacher' => ['start' => 0.08, 'root' => 0.01],
            'coach' => ['start' => 0.05, 'root' => 0.009],
        ],
    ];
    /**
     * Post (Kassetten, Anfragen, Bewerbungen) kommt in Umschlägen: Auspacken dauert `unpack` Sekunden (selbst oder per Praktikum, gleich schnell),
     * das Praktikum sortiert in die Fächer je Brief in `sort` Sekunden; eine Assistenz macht beides laufend und sofort. Alles liegt `ttl`.
     */
    public const POST = ['unpack' => 2, 'sort' => 1, 'ttl' => 4 * 86400, 'refresh' => 60];
    /**
     * Privatlehrer: für ein Instrument, manchmal mit Lieblingsgenre. Gebucht für `seconds` und genau einen Musiker (einmal bezahlt), der in der Zeit
     * `gain` Können dazugewinnt, gleichmäßig verteilt und ohne Lernkurve (mit passendem Genre × `genreFactor`). Unterrichtet nur bis `maxSkill`.
     * `ownInstrument`: so oft bewirbt sich jemand für ein Instrument, das im Label gespielt wird.
     */
    public const TEACHER = [
        'tiers' => [
            1 => ['price' => 300, 'seconds' => 2 * 86400, 'gain' => 8.0, 'maxSkill' => 55, 'from' => 0.0],
            2 => ['price' => 900, 'seconds' => 2 * 86400, 'gain' => 12.0, 'maxSkill' => 80, 'from' => 0.3],
            3 => ['price' => 2500, 'seconds' => 2 * 86400, 'gain' => 16.0, 'maxSkill' => 100, 'from' => 0.6],
        ],
        'genreChance' => 0.4, 'genreFactor' => 1.3, 'ownInstrument' => 0.7,
    ];
    /**
     * Manager aus dem Posteingang: einmalig bezahlt, die einfachen günstig. Ein Manager betreut **eine Band oder bis zu `solos` Solokünstler**.
     * Nur wer einen Manager hat, zeigt dem Label seine Bedürfnisse (Wünsche, „Warum?“) und Charakterzüge; ohne sieht man nur die Stimmung,
     * und erst wer rot ist, sagt laut, was er will. Beziehungen genauso: ohne Manager nur großer Streit (`notes.fight`). Der Manager schreibt Notizen auf die Übersicht (`notes`: so lange ohne Bühne in Stunden,
     * ab welcher Beziehung zwei sich nicht verstehen). Stufe `level`, dazu ein bis zwei Schwerpunkt-Genres und ein Stil (Charakterzug). Passung zur Band: `fit` nach gemeinsamen Genres
     * (0, 1, 2), `style` mal Anteil der Mitglieder mit demselben Zug (mit widersprüchlichem Zug minus), begrenzt auf `fitRange`.
     * Wirkungen je Stufe: Verhandler (NEGOTIATOR_BONUS, Gage), PR-Profi (PR_BONUS, Ruhm bei Auftritten), Netzwerker (`network`, Interviews bringen mehr),
     * Vermittler (`mediator`, schlechte Laune nach Anordnungen fällt schwächer aus, höchstens `mediatorMax`).
     */
    public const MANAGER_STAFF = [
        'tiers' => [
            1 => ['price' => 300, 'level' => 1, 'skills' => 1, 'from' => 0.0],
            2 => ['price' => 3000, 'level' => 3, 'skills' => 2, 'from' => 0.35],
            3 => ['price' => 10000, 'level' => 5, 'skills' => 2, 'from' => 0.7],
        ],
        'solos' => 4,
        // fight: so schlecht, dass es auch ohne Manager laut wird (sonst sieht man Beziehungen nur mit Manager)
        'notes' => ['stageIdle' => 24, 'conflict' => -3, 'fight' => -7],
        'skills' => ['verhandler', 'prprofi', 'netzwerker', 'vermittler'],
        'fit' => [0 => 0.6, 1 => 1.0, 2 => 1.25], 'style' => 0.2, 'fitRange' => [0.4, 1.45],
        'network' => 0.1, 'mediator' => 0.15, 'mediatorMax' => 0.8,
    ];
    /**
     * Songwriting-Coach: gebucht für `seconds` und genau eine Band oder einen Solokünstler. In der Zeit entstehen Songs × `speed`, und die Songs
     * aus dieser Zeit machen die nächste Platte besser (+`quality`, anteilig nach gecoachten Songs).
     */
    public const COACH = [
        'tiers' => [
            1 => ['price' => 400, 'seconds' => 86400, 'speed' => 1.5, 'quality' => 0.04, 'from' => 0.0],
            2 => ['price' => 1200, 'seconds' => 86400, 'speed' => 1.75, 'quality' => 0.08, 'from' => 0.3],
            3 => ['price' => 3000, 'seconds' => 86400, 'speed' => 2.0, 'quality' => 0.12, 'from' => 0.6],
        ],
    ];
    /**
     * Scout: einmalig bezahlt, dann mit Suchauftrag (Genre und/oder Instrument oder egal) losschicken. Nach `seconds` liegt ein Bericht mit
     * `candidates` Talenten im Posteingang (er liegt `ttl`). Sie sind unbekannt (Bekanntheit 0) und haben Können `skill` [von, bis]; bessere Scouts
     * finden schneller, mehr und treffsicherer. Ein einfacher Scout bewirbt sich gleich zu Beginn (Willkommens-Brief).
     */
    public const SCOUT_STAFF = [
        'tiers' => [
            1 => ['price' => 400, 'seconds' => 6 * 3600, 'candidates' => 2, 'skill' => [15, 34], 'from' => 0.0],
            2 => ['price' => 1200, 'seconds' => 5 * 3600, 'candidates' => 3, 'skill' => [22, 44], 'from' => 0.25],
            3 => ['price' => 3000, 'seconds' => 4 * 3600, 'candidates' => 3, 'skill' => [32, 54], 'from' => 0.55],
        ],
        'ttl' => 4 * 86400,
    ];
    /**
     * Assistenz: einmalig bezahlt, alle gleich gut. Jede hat ein Arbeitsgebiet und arbeitet darin laufend, Stück für Stück, so schnell wie man
     * selbst (POST, SUBMISSIONS['listen'], dazu `seconds`): `post` = Umschläge auspacken und in die Reiter legen, meldet, was bald abläuft
     * (`soon` Sekunden vorher); `demos` = Kassetten anhören, in die Genre-Unterreiter legen (selbst Angehörte: `file`), nach den Regeln
     * wegwerfen, Suchauftrag; `office` = Briefe lesen (`read`) und auf der Übersicht davon erzählen, auf Wunsch Interviews zusagen.
     * Mehrere im selben Gebiet teilen sich die Arbeit. Liegen geblieben ist höchstens `catchUp` Sekunden Arbeit (offline).
     */
    public const ASSISTANT = [
        'tiers' => [1 => ['price' => 400, 'from' => 0.0]],
        // none = kein Arbeitsgebiet; so fangen neue an
        'tasks' => ['none', 'post', 'demos', 'office'],
        'seconds' => ['file' => 1, 'read' => 5],
        'soon' => 12 * 3600,
        'catchUp' => 4 * 86400,
    ];
    /**
     * Praktikum: bewirbt sich gleich zu Beginn und danach immer wieder (Balance::JOBS), günstig und für `seconds`. Arbeitet nur auf Zuruf: Briefe öffnen, Demos anhören
     * oder Post sortieren (ohne wegzuwerfen). Ist das Praktikum vorbei, wartet sie noch `decide` Sekunden auf eine Antwort: übernehmen
     * (Preis der Assistenz) oder verabschieden. Vorher lässt sie sich nicht übernehmen; ohne Antwort geht sie.
     */
    public const INTERN = ['price' => 50, 'seconds' => 7 * 86400, 'decide' => 86400];
    /**
     * Künstlername: Nicht jeder will einen. Wer ihn sich wünscht, hängt am Charakter (Eigenschaft => ab welchem Ruhm): Diven früh, Rampensäue etwas später,
     * Ehrgeizige erst, wenn sie richtig bekannt sind. Das Label sucht einen von drei aus, das freut (Wohlbefinden +mood, höchstens bis 100).
     */
    public const STAGE_NAME = ['want' => ['diva' => 20.0, 'rampensau' => 40.0, 'ehrgeizig' => 120.0], 'mood' => 12.0, 'choices' => 3];
    /**
     * Songschreiben: Jedes Mitglied trägt `share` bei, mal den Faktor seiner Charakterzüge (TRAITS['write']: Kreative viel mehr, Technische nichts).
     * Tempo der Band = (Summe / norm) ^ exp: Zusammen sprudeln die Ideen, deshalb wächst es stärker als die Zahl der Mitglieder.
     * Eine Vierer-Band ohne besondere Züge schreibt so schnell wie MOOD['songsPerHour'], allein dauert es achtmal so lang
     * (vier Solokünstler zusammen schaffen nur halb so viel wie die Band); allein und kreativ etwa 0,65.
     */
    public const SONGWRITING = ['share' => 0.25, 'norm' => 1.0, 'exp' => 1.5];
    /**
     * Unerfüllte Bedürfnisse wachsen, wie Hunger: Wer seinen Wunsch geäußert hat (nicht mehr grün, Charakter-Wunsch aus Catalog::WISHES),
     * wird nach `grace` Stunden mit jeder weiteren Stunde um `perHour` Punkte (Ziel des Wohlbefindens) unzufriedener, bis die Band etwas macht,
     * das er mag (Mood::react setzt die Uhr zurück) oder er wieder grün ist. Ohne Instrument oder mit einem, über das er hinausgewachsen ist,
     * genauso (`gear`, Diven × MOOD['gear']['traits']), bis er ein passendes hat. Gezählt wird nur gerechnete Zeit: Offline im Roten steht die Uhr still.
     */
    public const NEEDS = ['wish' => ['grace' => 2.0, 'perHour' => 0.4], 'gear' => ['grace' => 4.0, 'perHour' => 0.3]];
    /**
     * Charakterzüge: Stärke und Schwäche je Zug (Schlüssel wie Catalog::PERSONALITIES).
     * write: Faktor auf den Beitrag zum Songschreiben. rehearsalGain: Faktor aufs Können je Probe (die Ausdauer steht in Catalog::LUST).
     * live: Zuschläge auf die Chance, live zu glänzen oder zu patzen. stageFame: Zuschlag auf den eigenen Anteil am Ruhm bei Auftritten.
     * recordQuality: Zuschlag auf die Qualität einer Platte je Mitglied. recordSales: Zuschlag auf den Erstverkauf je Mitglied.
     * restless: so viele Punkte weniger Wohlbefinden-Ziel, wenn seit `hours` kein Auftritt war, und mit jeder weiteren Stunde mehr (`grow`).
     * stagnant: dasselbe ohne neue Platte.
     * calm: sinkt langsamer (tau × `tau`, wenn das Ziel darunter liegt), Songstau zählt × `stau`, kleine Bühnen ärgern nicht.
     * pulled: Faktor, wie stark Unzufriedene in der Band jemanden mit herunterziehen.
     */
    public const TRAITS = [
        'write' => ['kreativ' => 3.0, 'technisch' => 0.0, 'chaot' => 1.6, 'perfektionist' => 0.6],
        'rehearsalGain' => ['kreativ' => 0.85, 'technisch' => 1.25],
        'live' => ['chaot' => ['highlight' => 0.04, 'mistake' => 0.04], 'ruhepol' => ['highlight' => -0.03], 'technisch' => ['mistake' => -0.03]],
        'stageFame' => ['rampensau' => 0.2, 'partytier' => 0.15],
        'recordQuality' => ['perfektionist' => 0.04],
        'recordSales' => ['diva' => 0.1],
        // grow: ab der Schwelle wird es mit jeder Stunde um diesen Anteil des Grundwerts schlimmer (6 Punkte, grow 0,04: nach einem Tag 11,8)
        'restless' => ['hours' => 8, 'grow' => 0.04, 'traits' => ['rampensau' => 6.0, 'partytier' => 5.0]],
        'stagnant' => ['hours' => 48, 'grow' => 0.015, 'traits' => ['ehrgeizig' => 6.0]],
        'calm' => ['trait' => 'gelassen', 'tau' => 1.8, 'stau' => 0.5],
        'pulled' => ['teamplayer' => 1.5],
    ];
    /**
     * Verborgene Charakterzüge (nur mit Manager): Ein Zug zeigt sich, sobald er zum ersten Mal etwas bewirkt. Nach `after` Sekunden mit dem
     * Manager kennt man alle. Nach jeder Probe von mindestens `minRehearsal` Sekunden zeigt sich mit der Chance `rehearsal` noch ein Zug.
     */
    /**
     * Label-Ruhm (steuert die Post): Jede Band zählt mit ihrem Ruhm hoch `power`, am Ende die `power`-te Wurzel. Mit 2: Wurzel aus der Summe
     * der Quadrate. Eine Band allein zählt genau mit ihrem Ruhm; jeder Ruhmpunkt einer großen Band wiegt mehr als einer einer kleinen, viele
     * kleine Bands bringen wenig, und neue Unbekannte ziehen nichts herunter.
     */
    public const LABEL_FAME = ['power' => 2.0];
    public const TRAIT_REVEAL = ['after' => 86400, 'rehearsal' => 0.35, 'minRehearsal' => 60];
    /**
     * Versteckter Wert eines Musikers (nie angezeigt): Charakterzüge zählen je nach Lage etwa gleich (meist 0), was im Schnitt mehr hilft +1,
     * was im Schnitt eher bremst −1 (die Technik zählt nicht, jeder hat eine). Die Unterschriftsprämie wächst um `price` je Punkt
     * (höchstens bis `min`/`max` des Grundpreises): Glückstreffer kosten mehr, Pechvögel (etwa egozentrisch und technisch) sind billig.
     */
    public const VALUE = [
        'traits' => ['ehrgeizig' => 1, 'teamplayer' => 1, 'ruhepol' => 1, 'kreativ' => 1, 'diva' => -1, 'technisch' => -1],
        'price' => 0.12, 'min' => 0.6, 'max' => 1.6,
    ];
    public const MAX_MEMBERS = 7;
    public const MAX_PER_INSTRUMENT = ['drums' => 1, 'guitar' => 2];

    // ---------- Sound ----------
    /** Sound = (Gewicht × Durchschnitt + Rest × schwächster Musiker) × Besetzung × Zusammenspiel. */
    public const SOUND_MEAN_WEIGHT = 0.75;
    /** Gemischte Band (kein gemeinsames Genre): Keiner versteht so recht, was der andere macht – weniger Sound. */
    public const MIXED_BONUS = 0.8;
    /** Sound-Bonus, wenn alle Mitglieder ein Genre teilen und die Besetzung dazu passt (Genre-Band) … */
    public const GENRE_BONUS = 1.15;
    /** Je Spieltechnik, die zu einem Genre der Band passt, so viel mehr Sound (höchstens zwei Techniken je Musiker; Catalog::ABILITIES[…]['genres']). */
    public const TECHNIQUE_SOUND = 0.04;
    /** … und wenn sie dazu noch ein zweites Genre teilen (Hybrid, z. B. Punk-Rock). */
    public const HYBRID_BONUS = 1.25;
    /** … und wenn alle sogar ein drittes teilen (Crossover, sehr selten). Zusammenspiel wie Hybrid. */
    public const CROSSOVER_BONUS = 1.3;
    /** Ohne eigenes Instrument spielt man auf irgendwas Geliehenem – das klingt nach nichts. */
    public const NO_GEAR_FACTOR = 0.5;
    /** Alte Werte (Klang, Spielbarkeit, Design) streuen so viel um das Modell; heute nur noch Grundlage für Gear::legacy. */
    public const GEAR_VARIANCE = 12;
    /** Früher: Eigenschaften je Seltenheit (nur noch für alte Migrationen). */
    public const GEAR_PERKS = ['common' => 0, 'rare' => 0, 'epic' => 1, 'legendary' => 2];
    /** Verkauf an den Second-Hand-Laden: Grundpreis je Seltenheit, mal Güte innerhalb der Klasse (GEAR_PRICE_SPREAD). */
    public const GEAR_SELL = ['common' => 25, 'rare' => 80, 'epic' => 260, 'legendary' => 750];
    /**
     * Boni der Instrumente nach Platz (grün = Übung, blau = Klang, gold = etwas Besonderes) und Art; Spanne je Klasse (1–3) oder für alle.
     * lust: hält beim Proben länger durch; learn: lernt je Probe und Platte mehr; sound: Sound des Musikers;
     * magnet: Anteil am Ruhm bei Auftritten; cult: Erstverkauf; studio: Qualität der Platte; show: Glanzmomente live; hit: Chartpunkte.
     */
    public const GEAR_BONUS = [
        'green' => [
            'lust' => [1 => [0.10, 0.25], 2 => [0.15, 0.35], 3 => [0.25, 0.45]],
            'learn' => [1 => [0.05, 0.12], 2 => [0.08, 0.18], 3 => [0.12, 0.24]],
        ],
        'blue' => [
            'sound' => [2 => [0.20, 0.35], 3 => [0.35, 0.50]],
        ],
        'gold' => [
            'magnet' => [0.15, 0.35],
            'cult' => [0.10, 0.25],
            'studio' => [0.03, 0.06],
            'show' => [0.03, 0.06],
            'hit' => [0.10, 0.25],
        ],
    ];
    /** Preis und Verkaufswert schwanken nach Güte innerhalb der Klasse: schwach gewürfelt × min, stark × max. */
    public const GEAR_PRICE_SPREAD = [0.8, 1.3];
    /** +5 % je weiterem unterschiedlichen Instrument, höchstens für 4 weitere. */
    public const LINEUP_BONUS = 0.05;
    public const LINEUP_BONUS_MAX_TYPES = 4;
    /**
     * Große Bands: Jedes Mitglied über SIZE_BONUS_FROM bringt mehr Sound (volle Bühne) und mehr Gage (zahlt Instrumente und
     * Kurse). Sechs Leute auf hohes Können zu bringen dauert und kostet – das soll sich lohnen.
     */
    public const MEMBER_SOUND_BONUS = 0.05;
    public const MEMBER_GAGE_BONUS = 0.10;
    /**
     * Zusammenspiel je nach Genre-Linie: Höchstwert und wie schnell es wächst. Gemischte Bands harmonieren kaum
     * (halb so schnell, höchstens 70), Hybrid-Bands teilen sogar zwei Genres (bis 120, Sound dann × 1,06).
     */
    public const COHESION_BY_GENRE = [
        'mixed' => ['max' => 70.0, 'growth' => 0.5],
        'genre' => ['max' => 100.0, 'growth' => 1.0],
        'hybrid' => ['max' => 120.0, 'growth' => 1.0],
    ];
    /** Sound-Faktor bei Zusammenspiel 0; bei 100 ist er 1 (darüber weiter steigend). */
    public const COHESION_FLOOR = 0.7;

    // ---------- Zusammenspiel & Chemie ----------
    public const COHESION_PER_SONG = 1.2;
    public const COHESION_ADD_PENALTY = 5;
    public const COHESION_REMOVE_PENALTY = 30;
    public const COHESION_SWITCH_PENALTY = 5;
    public const CHEMISTRY_CLASH = 0.15;
    public const CHEMISTRY_TEAMPLAYER = 0.04;
    public const CHEMISTRY_PARTY = 0.06;

    // ---------- Ruhm ----------
    /** Dieser Anteil jedes Ruhmgewinns bleibt am Bandnamen hängen; der Rest wird Bekanntheit der Mitglieder. */
    public const BAND_FAME_SHARE = 0.25;
    /** Einzelanteil am Ruhmgewinn: Grundwert + Boni. */
    public const FAME_SHARE = [
        'base' => 0.8, 'unusual' => 0.3, 'design' => 0.2,
    ];
    /** Bei diesem Sound gibt ein Auftritt genau den Grund-Ruhm des Ortes. */
    public const SOUND_FAME_REF = 30;

    // ---------- Auftritte ----------
    /** Live-Simulation: jedes Lied hat mehrere Abschnitte, in denen jeder Musiker glänzen oder patzen kann. */
    public const LIVE_PHASES = 4;
    /** Grundstimmung pro Abschnitt: Leute mögen Live-Musik. */
    public const LIVE_MOOD = 1.0;
    /** Pro Abschnitt rückt die Leiste um diesen Anteil Richtung Mitte (das Publikum beruhigt sich). */
    public const LIVE_SETTLE = 0.08;
    /** Die Leiste startet hier; im ersten Lied muss die Band das Publikum erst überzeugen. */
    public const LIVE_START = 0.0;
    /** Im ersten Lied rückt die Leiste pro Abschnitt um diesen Anteil zu ihrem Gleichgewicht (Aufwärmen). */
    public const LIVE_WARMUP = 0.4;
    /** Ziel des Aufwärmens: 50 plus dieser Anteil des Abstands zum Gleichgewicht der Band. */
    public const LIVE_WARMUP_REACH = 0.6;
    /** Ausschlag eines Patzers bzw. Glanzmoments [min, max]. */
    public const LIVE_SWING = [7.0, 13.0];
    /** Straßenmusik: Das Geld behalten die Musiker; fürs Label bleibt etwas Bekanntheit. */
    public const BUSKING = ['songs' => 3, 'fame' => 0.06, 'expect' => 25, 'falloff' => 150, 'minFactor' => 0.1];
    /**
     * Notgroschen: Reicht das Geld des Labels nicht für die kleinste Platte, geht bei der Straßenmusik der Hut rum –
     * so viel ₲ fürs Label [schwacher, starker Auftritt]. Sonst gibt Straßenmusik kein Geld.
     */
    public const BUSKING_HAT = [20, 45];
    /** Bandprobe: keine Zuschauer, dafür doppeltes Zusammenspiel und etwas Können für alle. */
    /**
     * Bandprobe: offen, die Band probt, bis ein Mitglied keine Lust mehr hat. Gewinne gelten je `seconds` (Bezugszeit) Probe und
     * werden laufend gutgeschrieben. `lust` = Sekunden bis jemand genug hat (nach Charakter, Catalog::LUST), mindestens `minLust`.
     */
    public const REHEARSAL = ['seconds' => 900, 'lust' => 3600, 'minLust' => 60, 'cohesion' => 5.0, 'skill' => 4.0, 'songSpan' => 150];
    /**
     * Auszeit für eine Band (oder einen Solokünstler): Ausflug, Wochenende oder Urlaub. Die Band ist so lange weg (keine Songs, keine Anordnungen),
     * kostet `perMember` je Mitglied und hebt danach das Wohlbefinden aller um `gain` (Gelassene und Ruhepole mehr, Ehrgeizige weniger: `traits`).
     * Geht auch, wenn die Band rot ist: Sie ist ein Ausweg.
     */
    public const RETREAT = [
        'options' => [
            'ausflug' => ['name' => 'Ausflug', 'seconds' => 3600, 'perMember' => 20, 'gain' => 8.0],
            'wochenende' => ['name' => 'Wochenende', 'seconds' => 4 * 3600, 'perMember' => 60, 'gain' => 16.0],
            'urlaub' => ['name' => 'Urlaub', 'seconds' => 12 * 3600, 'perMember' => 150, 'gain' => 28.0],
        ],
        'traits' => ['gelassen' => 1.5, 'ruhepol' => 1.5, 'ehrgeizig' => 0.5],
    ];
    /** Band-Promo: alle Mitglieder sind unterwegs, jeder bringt Bekanntheit wie bei der Promo-Mission. */
    public const BAND_PROMO = ['seconds' => 600, 'fame' => [0.1, 0.25]];
    /** Jam-Café: nur Musiker ohne Band; danach drei passende Musiker (Stufe 0) zur Auswahl für eine Band. */
    /**
     * Im Publikum sitzen Musiker – sie erwarten einiges. Wie gut die Session lief (Publikumsleiste 0–100),
     * bestimmt, wie gut die Musiker sind, die sich danach melden: Seltenheit und Können werden zwischen
     * „schlecht“ und „begeistert“ gemischt. Können bleibt immer unter Stufe 1.
     */
    public const JAM = [
        'songs' => 2, 'cards' => 3, 'fame' => 0.03, 'expect' => 35,
        'odds' => [
            'low' => ['common' => 85, 'rare' => 14, 'epic' => 1, 'legendary' => 0],
            'high' => ['common' => 35, 'rare' => 45, 'epic' => 20, 'legendary' => 0],
        ],
        'minSkill' => [5.0, 26.0],
    ];
    public const OPENSTAGE = [
        'songs' => 2, 'fame' => 0.18, 'expect' => 12, 'falloff' => 120,
        'managerChance' => 0.07, 'fanChance' => 0.15, 'fanValue' => [1, 2],
    ];
    /**
     * Fan will einsteigen: Nach einem Gig mit begeistertem Publikum (Durchschnitt ab minMeter) wartet ganz selten
     * ein Musiker am Bühnenausgang – höchstens einmal pro cooldown und Label. Gigs gibt es viele, darum so selten.
     * Je größer die Bühne, desto besser die Fans (odds je Auftrittsort); Können höchstens skillShare × Bandschnitt.
     * Ein Weg nach oben: In Halle und Arena kommen auch epische und legendäre Fans.
     */
    public const FAN_MUSICIAN = [
        'minMeter' => 75, 'chance' => 0.015, 'cooldown' => 7 * 24 * 3600, 'skillShare' => 0.9,
        'odds' => [
            'kneipe' => ['common' => 90, 'rare' => 10, 'epic' => 0, 'legendary' => 0],
            'club' => ['common' => 78, 'rare' => 20, 'epic' => 2, 'legendary' => 0],
            'saal' => ['common' => 62, 'rare' => 30, 'epic' => 8, 'legendary' => 0],
            'halle' => ['common' => 45, 'rare' => 37, 'epic' => 15, 'legendary' => 3],
            'arena' => ['common' => 30, 'rare' => 40, 'epic' => 22, 'legendary' => 8],
        ],
    ];
    /**
     * Cover-Automat (letzter Schritt jeder Platte): je Band 14 Felder, davon gewöhnlich/grün/blau/gold;
     * speeds = Felder pro Sekunde je Band (Motiv, Farben, Stil, Anordnung). Punkte je Wertigkeit ergeben die
     * Cover-Güte (0–1); sie bringt beim Erstverkauf bis salesMax mehr Geld. Chartpunkte bleiben bei Musik und Sound.
     */
    public const COVER = [
        'cells' => 14, 'rarity' => [8, 3, 2, 1], 'points' => [0, 1, 2, 4],
        'speeds' => [6.0, 7.5, 9.0, 10.5], 'salesMax' => 0.10,
    ];
    /**
     * Auftrittsorte nach Bandruhm. gage und fame gelten pro Lied, expect = erwarteter Sound. Die Arena gibt es nur auf Tournee (`tour`):
     * Zu Hause spielt man höchstens in der Halle, wer berühmter ist, geht auf Tournee.
     */
    public const VENUES = [
        ['key' => 'kneipe', 'name' => 'Kneipe', 'minFame' => 0, 'gage' => 30, 'fame' => 0.25, 'expect' => 15],
        ['key' => 'club', 'name' => 'Club', 'minFame' => 30, 'gage' => 70, 'fame' => 0.5, 'expect' => 28],
        ['key' => 'saal', 'name' => 'Konzertsaal', 'minFame' => 150, 'gage' => 160, 'fame' => 1.0, 'expect' => 42],
        ['key' => 'halle', 'name' => 'Halle', 'minFame' => 600, 'gage' => 380, 'fame' => 2.0, 'expect' => 58],
        ['key' => 'arena', 'name' => 'Arena', 'minFame' => 2500, 'gage' => 900, 'fame' => 4.0, 'expect' => 75, 'tour' => true],
    ];
    /**
     * Auftritt buchen: je Ort feste Kosten (Miete, Technik), Länge in Liedern. Die Gage hängt am Auftritt: Füllgrad nach Sound gegen Erwartung
     * (`fillMin` bis voll) mal Stimmung im Saal (`perfBase + perfWeight × Leistung`). Solokünstler buchen Studiomusiker dazu (`soloCost`, Pop billiger).
     * Wie die Band einen Ort findet: unter ihrem Anspruch ärgert er (`below`), darüber freut er (`above`), je Charakter stärker (`strong`).
     */
    public const GIG_BOOKING = [
        'cost' => ['kneipe' => 20, 'club' => 90, 'saal' => 350, 'halle' => 1100, 'arena' => 3200],
        // Länge auch für Tournee-Auftritte (dort ohne Miete; das Stadion steht in TOUR)
        'songs' => ['kneipe' => 3, 'club' => 4, 'saal' => 5, 'halle' => 7, 'arena' => 9],
        // Füllgrad = Sound gegen Erwartung × Bekanntheit gegen Größe des Ortes ((Ruhm + fameSoft) / (Mindestruhm + fameSoft)); Unbekannte füllen keinen Saal.
        'fillMin' => 0.05, 'fameSoft' => 10, 'perfBase' => 0.7, 'perfWeight' => 0.3,
        'soloCost' => 1.5, 'soloCostPop' => 1.2,
        'above' => 4.0, 'below' => -6.0, 'strong' => 1.7, 'strongTraits' => ['ehrgeizig', 'diva', 'rampensau'],
        'reach' => 2, 'estimateRuns' => 6,
    ];
    /** Lieder pro Gig ohne Manager; mit Manager kommen pro (effektiver) Stufe welche dazu. */
    public const GIG_SONG_CAP = 3;
    /** Ein richtiger Gig hat mindestens so viele Lieder. */
    public const GIG_MIN_SONGS = 3;
    /** Frisches Material: Jeder Gig seit der letzten Platte senkt Gage und Ruhm um diesen Anteil, höchstens bis min. */
    public const GIG_FRESH = ['decay' => 0.08, 'min' => 0.45];
    public const MANAGER_SONGS_PER_LEVEL = 1.4;
    public const NEGOTIATOR_BONUS = 0.08;
    public const PR_BONUS = 0.08;

    // ---------- Minispiel ----------
    /**
     * Tempo: Alle Können- und Ruhmgewinne sind so bemessen, dass ein Anfänger nach etwa 3–4 Stunden Spielzeit
     * fortgeschritten ist (Profi nach rund 13, Virtuose nach rund 50 Stunden). Je besser jemand ist, desto
     * langsamer geht es: Zuwachs × (1 − Können/110) hoch TRAINING_CURVE.
     */
    public const TRAINING_CURVE = 2.5;
    public const PRACTICE_GAIN = 0.8;
    /**
     * Tageskurse: Jeden Tag ein zufälliges Instrument und eine zufällige Kursart, für alle Spieler gleich.
     * Kursart: [Name, Häufigkeit, Dauer in Sekunden, Können, Grundpreis] – der Zuwachs ist für jeden Teilnehmer gleich,
     * ohne Lernkurve. Angeboten wird sie in drei Schwierigkeiten
     * (Anfänger, Fortgeschrittene, Profis = Stufe 0, 1, 2+); jeder nur in seiner, der Preis × PRICE_FACTOR.
     * Ein Musiker hat etwa jeden zehnten Tag sein Instrument: im Schnitt +4,5 Können, eine Stufe (25 Punkte) allein
     * durch Kurse in rund zwei Monaten.
     */
    public const COURSE = [
        'kinds' => [['Kurs', 50, 600, 3.0, 80], ['Workshop', 35, 1800, 5.0, 150], ['Meisterklasse', 15, 3600, 8.0, 300]],
        'levels' => ['Anfänger', 'Fortgeschrittene', 'Profis'],
        'priceFactor' => [1, 3, 8],
        'perDay' => 1,
    ];
    /**
     * Etüden: Noten pro Sekunde je Rolle bei Können 0 und 100, dazwischen nach Können^ETUDE_CURVE.
     * So ist jede Etüde bei gleichem Können gleich dicht, egal welches Muster, Tempo oder Genre gezogen wurde.
     */
    public const ETUDE_RATE = ['drums' => [1.4, 5.8], 'bass' => [0.55, 2.1], 'comp' => [0.55, 3.4], 'lead' => [0.55, 3.0]];
    public const ETUDE_CURVE = 1.5;
    /**
     * Allein üben lassen (ohne Minispiel): dauert länger und bringt nur diesen Anteil einer perfekten Solo-Probe.
     * Wer selbst mitspielt, bekommt je nach Treffern bis zum Vierfachen – ab etwa 40 % Treffern lohnt sich Mitspielen.
     */
    public const AUTO_PRACTICE = ['seconds' => 600, 'share' => 0.25];
    /**
     * Solo-Probe auf Stufe 0 (Einsteiger) zählt so viel mehr: Der erste Aufstieg soll sich schnell anfühlen.
     * Ab Stufe 1 gilt wieder das normale Tempo – die höheren Stufen bleiben genauso schwer.
     */
    public const PRACTICE_BEGINNER_BOOST = 3.0;
    /** Fenster des Rhythmusspiels in ms bei Können 0; mit Können wachsen sie (Chart::windows). */
    public const RHYTHM_WINDOWS = ['perfect' => 60, 'good' => 135];
    /** So lange (Sekunden) bleibt ein gestartetes Minispiel gültig. */
    public const MINIGAME_TTL = 180;

    // ---------- Promo-Mission ----------
    public const PROMO_SECONDS = 600;
    /** Promo-Mission: Bekanntheit [min, max], geteilt durch (1 + Bekanntheit / Abfall). */
    public const PROMO_FAME = [0.12, 0.3];
    public const PROMO_FAME_FALLOFF = 40;

    // ---------- Platte ----------
    /**
     * Aufnehmen (ohne Minispiele): Die Band ist songs × RECORD_SECONDS_PER_SONG im Studio. Das Label wählt Studio und Produzent.
     * Qualität = RECORD_QUALITY_BASE + Studio + Produzent (+ Charakter und goldene Instrumente), höchstens 1; ab RECORD_PERFECT_FROM gilt sie als perfekt.
     * Studio: `quality`, Preis je Lied. Produzent: `quality`, Honorar je Platte, `unlocks` = bestes Studio, in das er die Band bringt
     * (ohne Produzent nur die Garage). Jeder Produzent hilft auch in einem schlechteren Studio.
     * Die Legende geht in jedes Studio, nimmt aber nur Bands ab `minSound`. `mood`: Wohlbefinden für alle, wenn sie mit ihr aufnehmen.
     */
    public const RECORD_SECONDS_PER_SONG = 300;
    public const RECORD_QUALITY_BASE = 0.35;
    public const RECORD_PERFECT_FROM = 0.95;
    public const STUDIOS = [
        ['key' => 'garage', 'name' => 'Garage', 'quality' => 0.0, 'perSong' => 20],
        ['key' => 'standard', 'name' => 'Tonstudio', 'quality' => 0.12, 'perSong' => 60],
        ['key' => 'profi', 'name' => 'Profi-Studio', 'quality' => 0.24, 'perSong' => 150],
        ['key' => 'legende', 'name' => 'Legendäres Studio', 'quality' => 0.36, 'perSong' => 350],
    ];
    public const PRODUCERS = [
        'none' => ['name' => 'Ohne Produzent', 'quality' => 0.0, 'fee' => 0, 'unlocks' => 0, 'level' => 0],
        'mo' => ['name' => 'Mo Beats', 'quality' => 0.08, 'fee' => 150, 'unlocks' => 1, 'level' => 1],
        'roxy' => ['name' => 'Roxy Reverb', 'quality' => 0.16, 'fee' => 500, 'unlocks' => 2, 'level' => 2],
        'diamond' => ['name' => 'Diamond Dee', 'quality' => 0.24, 'fee' => 1500, 'unlocks' => 3, 'level' => 3],
        'legend' => ['name' => 'Ruben Stille', 'quality' => 0.3, 'fee' => 2500, 'unlocks' => 3, 'level' => 3, 'minSound' => 45, 'mood' => 6.0],
    ];
    /**
     * Anspruch: Ruhm-Stufe der Band gegen Produktionsniveau (Mittel aus Studio und Produzent, gerundet). Je Stufe darunter
     * `down` weniger Wohlbefinden (Egozentrische und Perfektionistische × `picky`), je Stufe darüber `up` mehr.
     */
    public const RECORD_CLAIM = ['down' => 6.0, 'up' => 3.0, 'picky' => ['diva' => 1.5, 'perfektionist' => 1.5]];
    /** Tantiemen-Schätzung: so viele „volle Stunden“ zahlt eine Platte, die durchgehend chartet (Summe des Verlaufs über CHART_MAX_AGE); untere Schätzung `low`. */
    public const RECORD_ESTIMATE = ['low' => 0.4];
    /** Drei neue Cover-Entwürfe kosten ein kleines Honorar fürs Grafikbüro. */
    public const COVER_REDRAW = 25;
    /**
     * Wer das Cover gestaltet: Honorar, wie klar und glänzend es wird (Wertigkeit 1–3 der Zutaten, cover.js) und wie viel besser sich die Platte
     * verkauft (Erstverkauf). Ein Bandlogo, das schon auf einer früheren Platte war, verkauft dazu `COVER_LOGO_SALES` mehr (Wiedererkennung).
     */
    public const COVER_DESIGNERS = [
        'student' => ['name' => 'Kunststudentin', 'fee' => 20, 'rarity' => 0, 'sales' => 0.0],
        'agentur' => ['name' => 'Agentur', 'fee' => 150, 'rarity' => 2, 'sales' => 0.03],
        'star' => ['name' => 'Star-Designerin', 'fee' => 600, 'rarity' => 3, 'sales' => 0.06],
    ];
    public const COVER_LOGO_SALES = 0.03;
    /** Früher: Studiozeit als Grundpreis plus pro Lied (nur noch für die Erstattung alter, offener Produktionen). */
    public const RECORD_COST = 150;
    public const RECORD_COST_PER_SONG = 80;
    public const RECORD_MIN_SONGS = 3;
    public const RECORD_MAX_SONGS = 12;
    /**
     * Album-Zyklus: Vor der nächsten Platte spielt die Band die letzte plays-mal komplett live (plays × Lieder der Platte,
     * auf Gigs) und macht promos Band-Promos. Die Promos kosten jede Platte gleich viel Zeit (je 10 Minuten, auch offline),
     * damit kurze EPs nicht viel schneller sind als LPs. (Früher: 5 Gigs, davor 2 Lieder live pro Plattenlied.)
     */
    public const RECORD_CYCLE = ['plays' => 3, 'promos' => 3];
    public const RECORD_SONG_SKILL = 0.12;
    public const RECORD_SONG_COHESION = 2.0;
    /** Erstverkauf beim Erscheinen; das eigentliche Einkommen sind die Tantiemen, solange die Platte chartet. */
    public const RECORD_MONEY_PER_SONG = 60;
    public const RECORD_FAME_PER_SONG = 0.6;
    /** Pro Lied über 3 bringt jedes Lied 4 % mehr, damit sich das Fehlerrisiko einer LP lohnt. */
    public const RECORD_LP_BONUS = 0.04;
    public const RECORD_PERFECT_BONUS = 1.5;
    /** Verkäufe steigen mit dem Bandruhm: +1 pro 400 Ruhm, höchstens +4. */
    public const RECORD_FAME_SALES = 400;
    public const RECORD_FAME_SALES_MAX = 4;

    // ---------- Talent-Scout ----------
    /** Gutscheine zum Label-Start; weitere gibt es für Meilensteine. */
    public const SCOUT_START_VOUCHERS = 1;
    public const SCOUT_PRICE = 3000;
    /** Kauf ohne Gutschein höchstens einmal pro Woche. */
    public const SCOUT_COOLDOWN = 7 * 24 * 3600;
    public const SCOUT_CARDS = 5;
    /** Der Scout bringt nur Musiker mit Können-Stufe 1. */
    public const SCOUT_ODDS = ['epic' => 70, 'legendary' => 30];

    // ---------- Wechselbörse ----------
    /** Laufzeit eines Angebots, Verlängerung bei späten Geboten, Mindestschritt beim Überbieten. */
    public const TRANSFER = ['seconds' => 172800, 'snipe' => 600, 'step' => 0.05, 'minStep' => 10];
    /** Marktwert = base + skill × Können² + fame × Bekanntheit + level × (beide Stufen) + ability × Fähigkeiten. */
    public const TRANSFER_VALUE = ['base' => 170, 'skill' => 0.8, 'fame' => 5, 'level' => 300, 'ability' => 100];

    // ---------- Album-Charts ----------
    /** Chartpunkte = Basis × Qualität² × Soundfaktor × Format × Perfekt × Ruhm. */
    public const CHART_BASE = 100;
    public const CHART_EP_FACTOR = 0.6;
    /** LPs ab dieser Liedzahl; jedes weitere Lied +4 %. */
    public const CHART_LP_SONGS = 7;
    public const CHART_LP_PER_SONG = 0.04;
    public const CHART_PERFECT = 1.3;
    /**
     * Verlauf: Einstieg mit den vollen Punkten, dann verliert jede Platte jede Stunde CHART_DECAY ihrer Punkte
     * (3 %: nach einem Tag noch knapp die Hälfte, nach drei Tagen gut ein Zehntel). Von selbst steigt sie nie, nur durch Schub. Älter als CHART_MAX_AGE zählt nicht mehr.
     * Die Plätze werden alle CHART_INTERVAL Sekunden neu vergeben – oder sofort, wenn eine Platte einsteigt oder Schub bekommt.
     */
    public const CHART_INTERVAL = 3 * 3600;
    /**
     * Battle of the Bands: jeden Samstag (`weekday`, ISO: 1 = Montag) um `time` deutscher Zeit, Anmeldung die ganze Woche davor,
     * höchstens `perLabel` Bands je Label und `max` insgesamt; unter `min` fällt es aus. Keine Computer-Bands, keine Solokünstler
     * (eine Band hat mindestens `members` Musiker).
     * Ablauf wie bei einer WM: Gruppen (jeder gegen jeden), dann K.-o.-Runden. Alle `round` Sekunden eine Runde; in einem Spiel spielt
     * jede Band `songs` Lieder, danach berät die Jury `jury` Sekunden.
     * Wer gewinnt: Sound × Laune × Tagesform. Laune = 1 + (Stimmung − mood.at) × mood.per; Tagesform zufällig zwischen 1 − luck und 1 + luck.
     * Ruhm, Proben und Klassen spielen keine Rolle.
     * Belohnung: Ruhm je Spiel und je Sieg, dazu nach Platz (`fame`); Preisgeld nach Platz (`money`); Freude nach Platz (`cheer`,
     * Wohlbefinden); Pokale für die ersten drei.
     */
    public const BATTLE = [
        'weekday' => 6, 'time' => [16, 0],
        'perLabel' => 2, 'min' => 2, 'max' => 32, 'members' => 2,
        'round' => 300, 'songs' => 2, 'jury' => 12,
        'luck' => 0.2,
        'mood' => ['at' => 60, 'per' => 0.0025],
        'fame' => ['match' => 1.5, 'win' => 1.5, 'place' => [1 => 20, 2 => 12, 3 => 7, 4 => 4]],
        'money' => [1 => 600, 2 => 300, 3 => 150, 4 => 80],
        'cheer' => [1 => 12, 2 => 6, 3 => 4],
    ];
    /**
     * Festival: jeden Mittwoch (`weekday`) um `time`, Anmeldeschluss am selben Tag um `close` (deutsche Zeit). Jedes Festival sucht ein
     * Genre (aus der Woche berechnet, nie zweimal hintereinander dasselbe, Catalog::FESTIVALS); genommen werden nur Bands mit diesem Genre
     * (auch hybrid). Jedes Label darf alle passenden Bands anmelden. Plätze je Bühne (`slots`); melden sich mehr an, entscheidet das Los.
     * Wer auf welche Bühne kommt, entscheidet die Bekanntheit, die die Band in den letzten `days` Tagen gesammelt hat: die meiste spielt
     * als Headliner zuletzt auf der Hauptbühne. Auftritte alle `gap` Sekunden, auf allen Bühnen zugleich.
     * Je Bühne: Lieder, Ruhm je Lied (× Sound wie bei Gigs, × Publikum), Gage, Erwartung des Publikums, Bild der Bühne und Fan-Chance (`fanVenue`).
     * Danach fragt ein größeres Medium nach einem Interview; der Headliner bekommt ein Plakat für den Proberaum.
     */
    public const FESTIVAL = [
        'weekday' => 3, 'time' => [17, 0], 'close' => [12, 0], 'days' => 7, 'gap' => 600, 'fanChance' => 0.2,
        'stages' => [
            'main' => ['name' => 'Hauptbühne', 'slots' => 2, 'songs' => 6, 'fame' => 5.0, 'money' => 300, 'expect' => 45, 'venue' => 'openair', 'fanVenue' => 'arena'],
            'side' => ['name' => 'Nebenbühne', 'slots' => 3, 'songs' => 5, 'fame' => 3.0, 'money' => 150, 'expect' => 35, 'venue' => 'openair', 'fanVenue' => 'halle'],
            'tent' => ['name' => 'Zeltbühne', 'slots' => 3, 'songs' => 4, 'fame' => 1.8, 'money' => 80, 'expect' => 25, 'venue' => 'zelt', 'fanVenue' => 'saal'],
        ],
    ];
    /**
     * Tournee in Stufen (Tours): Zu Hause spielt die Band in Kneipe bis Halle; wer bekannt genug ist und das Fahrzeug hat, geht auf Tournee.
     * `regions`: Europa (Bus, ab Konzertsaal-Ruhm), die Kontinente (Flugzeug, ab Hallen-Ruhm), Welttournee (Flugzeug, ab Arena-Ruhm, nur die
     * größte Stadt je Land, ab `stadium` minFame im Stadion, davor in der Arena). `lengths`: Länder zur Wahl (null = alle Länder der Region, Reihenfolge ausgewürfelt). Jedes Ziel wird
     * ausgewürfelt, keins doppelt. In jedem Land spielt die Band in jeder seiner Städte einmal (Catalog::COUNTRIES 'cities'), ohne Fahrt dazwischen.
     * Reisezeit = (base + Entfernung × perKm) × Tempo des Fahrzeugs (VEHICLES `speed`), Kosten je Strecke = Mitglieder × (perMember + km × perKm) ₲
     * (Sprit, Kerosin), die Heimreise ist frei. Unterwegs ist die Band nicht beschäftigt: Sie probt im Fahrzeug, schreibt Songs, gibt Interviews.
     * Ein Auftritt wie ein Gig am Ort nach Ruhm (Welttournee ab 8.000 Ruhm: `stadium`), im Ausland kennt noch niemand die Lieder (keine Abnutzung), und es
     * kommt neues Publikum (`abroad`). Mag das Land das Genre der Band (`match`): noch mehr Ruhm und Gage, das Publikum ist leichter zu begeistern.
     * Im Land wartet die Band höchstens `wait` Sekunden auf das nächste Antippen, dann fährt sie selbst nach Hause.
     * Beziehungen: je Auftritt RELATION['bond']['tourgig'], am Ende ['tour'], dazu am Ende `lift` je bespieltes Land obendrauf
     * (höchstens `liftMax`): Das gemeinsame Abenteuer schweißt zusammen, auch wer sich sonst eher reibt.
     */
    public const TOUR = [
        'regions' => [
            'europa' => ['name' => 'Europa-Tournee', 'fame' => 150, 'vehicle' => 'bus', 'lengths' => [3, 5, 7]],
            'nordamerika' => ['name' => 'Nordamerika-Tournee', 'fame' => 600, 'vehicle' => 'plane', 'lengths' => null],
            'suedamerika' => ['name' => 'Südamerika-Tournee', 'fame' => 600, 'vehicle' => 'plane', 'lengths' => null],
            'afrika' => ['name' => 'Afrika-Tournee', 'fame' => 600, 'vehicle' => 'plane', 'lengths' => null],
            'asien' => ['name' => 'Asien-Pazifik-Tournee', 'fame' => 600, 'vehicle' => 'plane', 'lengths' => null],
            'welt' => ['name' => 'Welttournee', 'fame' => 2500, 'vehicle' => 'plane', 'lengths' => [5, 7, 9], 'stadium' => true],
        ],
        'travel' => ['bus' => ['base' => 1200, 'perKm' => 1.5], 'plane' => ['base' => 1800, 'perKm' => 0.6]],
        'cost' => ['perMember' => 15, 'perKm' => 0.01],
        'stadium' => ['key' => 'stadion', 'name' => 'Stadion', 'minFame' => 8000, 'gage' => 2000, 'fame' => 7.0, 'expect' => 82, 'songs' => 10],
        'abroad' => ['fame' => 2.0, 'money' => 1.25],
        'match' => ['fame' => 1.5, 'money' => 1.5, 'expect' => 0.85],
        'wait' => 2 * 86400,
        'lift' => 0.6, 'liftMax' => 3.0,
    ];
    /**
     * Fahrzeuge einer Band, wie der Proberaum in vier Stufen und für immer gekauft: der Bus für Europa, das Flugzeug für die Welt.
     * `cost` je Stufe, `speed` Faktor auf die Reisezeit, und unterwegs ist das Fahrzeug der Proberaum (`skill`, `lust`, `mood` wie Balance::ROOM).
     * Gekauft werden kann erst, wenn die Band bekannt genug für die erste Tournee damit ist.
     */
    public const VEHICLES = [
        'bus' => [
            ['name' => 'VW-Bus', 'cost' => 1500, 'speed' => 1.0, 'skill' => 0.85, 'lust' => 0.8, 'mood' => 0.0],
            ['name' => 'Kleinbus mit Anhänger', 'cost' => 4000, 'speed' => 0.85, 'skill' => 0.95, 'lust' => 0.95, 'mood' => 0.5],
            ['name' => 'Tourbus', 'cost' => 9000, 'speed' => 0.7, 'skill' => 1.05, 'lust' => 1.1, 'mood' => 1.5],
            ['name' => 'Nightliner', 'cost' => 18000, 'speed' => 0.55, 'skill' => 1.15, 'lust' => 1.3, 'mood' => 3.0],
        ],
        'plane' => [
            ['name' => 'Propellerflugzeug', 'cost' => 25000, 'speed' => 1.0, 'skill' => 0.9, 'lust' => 0.9, 'mood' => 0.5],
            ['name' => 'Privatjet', 'cost' => 60000, 'speed' => 0.8, 'skill' => 1.0, 'lust' => 1.05, 'mood' => 1.5],
            ['name' => 'Großer Jet', 'cost' => 120000, 'speed' => 0.65, 'skill' => 1.1, 'lust' => 1.2, 'mood' => 2.5],
            ['name' => 'Tour-Jumbo', 'cost' => 250000, 'speed' => 0.5, 'skill' => 1.2, 'lust' => 1.4, 'mood' => 4.0],
        ],
    ];
    /**
     * Auszeichnungen (Awards): Verkäufe einer Platte = Chartpunkt-Stunden × `copies` Stück (nur solange sie chartet und Tantiemen bringt,
     * Charts::payRoyalties). Goldene, Platin- und Diamant-Schallplatte ab `records` Stück, je Platte einmal. Dazu Platz 1 der Charts
     * (je Platte) und der erste Auftritt in einer Halle bzw. Arena (je Band).
     */
    /**
     * Übergang alter Labels (einmalig, Transition): Fan-Karten werden zu `fanFame` ₲ je Ruhmpunkt, jeder Scout-Gutschein und jedes offene
     * Scout-Angebot zu `voucher` ₲ (so viel wie ein guter Scout heute), ungeöffnete Packs zum Kaufpreis. Beziehungen aus gemeinsamer Bandzeit:
     * Zielwert der Passung × (1 − e^(−Tage / `bondDays`)), nach einer Woche also etwa zwei Drittel. Aus der Kartei bleiben bis zu `keep` Musiker.
     */
    public const TRANSITION = ['fanFame' => 100, 'voucher' => 1200, 'bondDays' => 7.0, 'keep' => 5];
    public const AWARDS = ['copies' => 100, 'records' => ['gold' => 100000, 'platinum' => 250000, 'diamond' => 1000000]];
    /** Nachrichten beim Wiederkommen: nach so langer Pause, diese Arten aus der Chronik. */
    public const NEWS_AWAY = 900;
    public const NEWS_KINDS = ['chart1', 'chart', 'transfer', 'scout', 'level', 'fame', 'fanjoin', 'trait', 'event', 'tour', 'award'];
    public const CHART_DECAY = 0.03;
    public const CHART_MAX_AGE = 7 * 86400;
    /**
     * Schub für die neueste Platte der Band, als Aufschlag auf ihre Punkte: Gig pro gespieltes Lied (egal wo),
     * Promo fest. Höchstens CHART_BOOST_MAX, halbiert sich alle 3 Stunden. Prozent statt Punkte: Wer gleich viel
     * spielt, bekommt denselben Aufschlag – der Schub belohnt Fleiß, verschiebt aber nicht die Stärke der Platten.
     */
    public const CHART_GIG_BOOST = 0.025;
    public const CHART_PROMO_BOOST = ['band' => 0.1, 'solo' => 0.04];
    public const CHART_BOOST_MAX = 0.6;
    public const CHART_BOOST_HALF = 3 * 3600;
    /** Unter dieser Punktzahl fällt eine Platte aus den Charts. */
    public const CHART_MIN_POINTS = 1.0;
    /** So viele Plätze zeigen die Charts (Top 10 groß, der Rest klein darunter). */
    public const CHART_LIST = 100;
    public const CHART_SIZE = 10;
    /** Platz 1 bringt einen Scout-Gutschein erst, wenn so viele Labels Platten in den Charts haben. */
    public const CHART_TOP_MIN_LABELS = 3;
    /** Tantiemen pro Chartpunkt und Stunde – ohne Platz-Bonus (die Punkte sind schon die Platzierung). */
    public const ROYALTY_MONEY = 0.6;
    /** Tantiemen gibt es nur, solange die Platte unter den ersten Plätzen der Charts steht; weiter unten (und nach CHART_MAX_AGE) nicht mehr. Bis dahin sinken die Punkte stündlich (CHART_DECAY). */
    public const ROYALTY_RANKS = 50;
    /** Eine ehemalige Band behält ihren Namen geschützt (niemand sonst darf ihn nehmen), wenn sie am Ende mindestens so viel Ruhm am Namen hatte: erste Bekanntheitsstufe. */
    public const NAME_PROTECT_FAME = 20.0;
    /** Verbindet man zwei Namen („Solo & Band“), bleibt von dem Ruhm beider Namen dieser Anteil erhalten (bei einem ganz neuen Namen nichts). */
    public const NAME_COMBO_KEEP = 0.5;
    /**
     * Katalog: Jede fertige Platte verkauft sich für immer ein wenig – so viel ₲ pro Stunde und Chartpunkt (Startwert
     * der Platte), zusätzlich zu den Charts. Eine Platte mit 60 Punkten bringt so rund 14 ₲ am Tag: spürbar, wenn sich
     * Platten ansammeln, aber klein gegen Gigs (ein Kneipen-Gig ≈ 90 ₲).
     */
    public const CATALOG_MONEY = 0.01;
    public const ROYALTY_FAME = 0.0125;

    // ---------- Neuer Kern (Fans, Hype, Verkäufe, Konzerte) ----------
    // Rechenwerk in src/Game/Core, noch nicht ins Spiel eingebaut. Herleitung und Beschlüsse: Dokument „Signed – Rechenwege“.
    // Zeit: „Tag“ meint immer einen echten Tag, gezählt ab dem Erscheinen der neuesten Platte.

    /** Ruhmstufen nach Fans (ab so vielen Fans; Stufe = Zahl der Sterne). */
    public const CORE_STAGES = [0 => 0, 1 => 500, 2 => 5000, 3 => 50000];
    /** Dach: höchstens so viele Fans je Art der Band (Solokünstler mit Pop oder Elektro zählen als Genre, mit beidem als Hybrid). */
    public const CORE_ROOF = ['mixed' => 4999, 'genre' => 50000, 'hybrid' => 75000, 'crossover' => 90000];
    /** Rechen-Pool beim Erscheinen einer Platte: max(`base`, Fans + `above`). */
    public const CORE_POOL = ['base' => 50000, 'above' => 10000];
    /**
     * Hype einer Platte: beim Erscheinen Q × Q / 100, danach je Tag mal `decay` (perfekte Platten ab `perfect` mal `decayPerfect`).
     * Schübe durch Ereignisse: jeder weitere für dieselbe Platte zählt `halve`-mal so viel wie der vorige, höchstens bis `max`.
     * Flammen in den Charts ab diesem Hype (sonst keine).
     */
    public const CORE_HYPE = [
        'decay' => 0.5, 'decayPerfect' => 0.55, 'perfect' => 95.0, 'halve' => 0.5, 'max' => 100.0,
        'flames' => ['blaze' => 85, 'red' => 55, 'weak' => 25, 'ember' => 5],
    ];
    /**
     * Verkauf je Tag (Sandras Modell): p Grundentdeckung, q Mundpropaganda (aus den Fans beim Erscheinen), k Anteil der Käufer, die Fans werden,
     * r Anteil der kaufwilligen Fans, die an einem Tag kaufen. Kaufanteil der Fans nach Qualität: min(1, buyBase × (Q / buyRef)²).
     * Ausgelaufen, wenn an einem Tag weniger als `endBelow` verkauft werden. Das Label bekommt `price` ₲ je Platte.
     */
    public const CORE_SALES = ['p' => 0.0015, 'q' => 0.06, 'k' => 0.6, 'r' => 0.5, 'buyBase' => 0.64, 'buyRef' => 80.0, 'endBelow' => 1.0, 'price' => 10];
    /** Katalog je Tag = Fans × `rate` × Q / 100 × 0,5 hoch (Alter in Wochen); zählt für Gold und Platin. Unter `endBelow` je Tag hört das Rechnen auf. */
    public const CORE_CATALOG = ['rate' => 0.012, 'endBelow' => 0.01];
    /**
     * Konzert (je Auftritt, Sandras Formel): Fans × (b + c × A) kommen, dazu Neugierige (Pool − Fans) × (n0 + n × A × (1 + w × F0 / Pool)).
     * Von den Neugierigen im Saal werden u × Live-Qualität / 100 Fans. Frische: nach jedem Auftritt mal `freshAfter`, dann in `freshHours` Stunden
     * gleichmäßig wieder ganz.
     */
    public const CORE_CONCERT = ['b' => 0.02, 'c' => 0.3, 'n' => 0.003, 'n0' => 0.0005, 'w' => 20.0, 'u' => 0.3, 'freshAfter' => 0.5, 'freshHours' => 24.0];
    /**
     * Sound-Erwartung je Ruhmstufe; Sound-Anteil einer Platte = min(1, Sound / Erwartung). So viel Sound hat eine Zweierband (Genre-Band,
     * zwei Instrumentenarten, je eine passende Spieltechnik, Zusammenhalt 100), wenn beide auf der nächsten Können-Stufe ankommen
     * (100 / 400 / 1.600, für Stufe 3 viermal so viel: 6.400) und deren Instrument spielen. Wer etwa eine halbe Stunde am Tag probt, hält Schritt.
     */
    public const CORE_SOUND_EXPECT = [0 => 130.0, 1 => 660.0, 2 => 3000.0, 3 => 12000.0];
    /**
     * Können: Stufen ab `levels`, `perHour` je echte Probestunde, ohne Lernkurve, nach oben offen.
     * Übergang alter Musiker: altes Können (0–100) → neues an derselben Stelle der Stufe, zwischen den Punkten gleichmäßig.
     */
    public const CORE_SKILL = ['levels' => [100, 400, 1600], 'perHour' => 50.0, 'legacy' => [0 => 0.0, 35 => 100.0, 60 => 400.0, 85 => 1600.0, 100 => 2320.0]];
    /** So viel ₲ verdient das Label je Zuschauer, nach Ruhmstufe der Band (Kosten gibt es keine). */
    public const CORE_EARNINGS = [0 => 5, 1 => 6, 2 => 9, 3 => 12];
    /** Orte zu Hause: ab Ruhmstufe, Größen (Plätze). Halle und Arena nur auf Tournee. Das Spiel nimmt die kleinste Größe, in die alle passen. */
    public const CORE_VENUES = [
        'kneipe' => ['stage' => 0, 'sizes' => [60, 150, 300]],
        'club' => ['stage' => 1, 'sizes' => [500, 1000, 1500]],
        'halle' => ['stage' => 2, 'sizes' => [3000, 5000, 8000], 'tour' => true],
        'arena' => ['stage' => 3, 'sizes' => [10000, 15000, 20000], 'tour' => true],
    ];
    /**
     * Ereignisse: Zuschauer, Anteil der Neugierigen, die bei perfekter Show Fans werden, und Hype-Schub. Battle: je gespieltes Spiel, Schub nach Platz.
     * Anfragen: Dauer in Sekunden und Schub; Werbung bringt dazu `adPerFan` ₲ je Fan.
     */
    public const CORE_EVENTS = [
        'festival' => ['main' => ['audience' => 20000, 'boost' => 10.0], 'side' => ['audience' => 8000, 'boost' => 6.0], 'tent' => ['audience' => 2000, 'boost' => 3.0]],
        'battle' => ['audience' => 2000, 'boost' => [1 => 15.0, 2 => 10.0, 3 => 6.0]],
        'share' => 0.02,
        'requests' => [
            'interview' => ['seconds' => 900, 'boost' => 2.0],
            'photos' => ['seconds' => 1200, 'boost' => 3.0],
            'article' => ['seconds' => 900, 'boost' => 4.0],
            'report' => ['seconds' => 1800, 'boost' => 6.0],
            'ad' => ['seconds' => 1200, 'boost' => 1.0],
        ],
        'adPerFan' => 1.0,
    ];
    /** Battle-Preise: Geld nach Platz (4 = im Halbfinale ausgeschieden); der Sieger wählt `winner` ₲ oder den Wochenpreis. */
    public const CORE_BATTLE_PRIZES = ['money' => [2 => 6000, 3 => 3000, 4 => 1500], 'winner' => 12000, 'weekly' => ['blue_instrument', 'free_production', 'room_upgrade', 'coach_days' => 3]];
    /**
     * Fans bei Änderungen an der Band. Austritt: Gewicht 1, `strong` für die Charakterzüge in `strongTraits` oder mit Künstlername; von seinem
     * Anteil an den Fans geht `follow` mit, `quit` hört ganz auf. Zusammentun: die größere plus `mergeSmall` der kleineren. Umbenennen: `rename` gehen.
     */
    public const CORE_FAN_MOVES = ['strong' => 2.0, 'strongTraits' => ['rampensau', 'diva', 'partytier'], 'follow' => 0.5, 'quit' => 0.2, 'mergeSmall' => 0.5, 'rename' => 0.1];
    /**
     * Qualität einer Platte: Q = 100 × (Gewichte Sound, Songs, Produktion, Stimmung). Stimmung voll ab `moodFull` Wohlbefinden. Perfekt ab `perfect`.
     * Songqualität: base + creative × kreative Kraft K + Regler + Coach + Zufall (± random), begrenzt auf 0–100.
     */
    public const CORE_QUALITY = [
        'weights' => ['sound' => 0.40, 'songs' => 0.25, 'production' => 0.20, 'mood' => 0.15],
        'moodFull' => 60.0, 'perfect' => 95.0,
        'song' => [
            'base' => 50.0, 'creative' => 25.0, 'slider' => ['fast' => -20.0, 'normal' => 0.0, 'careful' => 20.0], 'coach' => [1 => 5.0, 2 => 10.0, 3 => 15.0], 'random' => 10.0,
            // kreative Kraft K: Anteil der Songschreiber mit diesen Zügen (Perfektionisten schreiben langsam, aber die besten Songs)
            'creativeTraits' => ['perfektionist' => 1.0, 'kreativ' => 0.5],
        ],
    ];
    /**
     * Songs je Tag = base × (1 + spread × (n − 1) / (n + 1)) × Regler × Stimmung × Tätigkeit × Genre × Coach; n = Songschreiber, nach Charakter gewichtet.
     */
    public const CORE_SONGWRITING = [
        'base' => 1.2, 'spread' => 1.2,
        'writers' => ['kreativ' => 1.5, 'chaot' => 1.2, 'perfektionist' => 0.8, 'technisch' => 0.0],
        'slider' => ['fast' => 1.5, 'normal' => 1.0, 'careful' => 0.67],
        'mood' => ['green' => 1.0, 'yellow' => 0.5, 'red' => 0.0],
        'activity' => ['record' => 0.0, 'gig' => 0.0, 'rehearsal' => 0.6, 'idle' => 1.0],
        'genre' => ['genre' => 1.0, 'hybrid' => 0.85, 'crossover' => 0.7, 'mixed' => 0.7],
        'coach' => [1 => 1.5, 2 => 1.75, 3 => 2.0],
    ];
    /** Geld: Startgeld, Produktion je Stufe (= Ruhmstufe), Instrumente je Farbe (grau, grün, blau, gold). */
    public const CORE_MONEY = ['start' => 5000, 'production' => [0 => 300, 1 => 2000, 2 => 8000, 3 => 30000], 'gear' => [0 => 100, 1 => 1500, 2 => 8000, 3 => 40000]];
    /** Bedürfnisse tauchen jederzeit auf, wachsen aber nur alle `stepHours` Stunden um so viel, wie heute in dieser Zeit dazukommt. */
    public const CORE_NEEDS = ['stepHours' => 24];
}
