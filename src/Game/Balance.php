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
    public const MANAGER_MAX_BANDS = 3;

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
    /** Chance auf den Tipp des Verkäufers (Läden mit tip): Eine Karte passt zu einem Musiker ohne Instrument, Bandmitglieder zuerst. */
    public const STORE_TIP_CHANCE = 0.25;
    /** Pro Scout-Stufe sinkt die Chance auf gewöhnliche Karten um diesen Anteil. */
    public const SCOUT_SHIFT = 0.06;

    // ---------- Band ----------
    /** Band-Plätze: die ersten beiden sofort, jede weitere ab dem Dreifachen (50, 150, 450 …) Label-Ruhm (Summe des Ruhms aller eigenen Bands). */
    public const BAND_SLOTS = [0, 0, 50, 150, 450, 1350, 4050, 12150];
    /** Namensänderung nach dem ersten Auftritt: Gebühr; der Ruhm am alten Namen geht verloren. */
    public const RENAME_COST = 300;
    public const MIN_MEMBERS = 2;

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
        // Songs je Stunde nach Ampel, Vorrat höchstens cap
        'songsPerHour' => ['green' => 1.0, 'yellow' => 0.5, 'red' => 0.0], 'songCap' => 24,
        // Geduld (Sekunden Spielzeit im Roten) bis die Band geht; erst eingeschaltet, wenn die Anordnungen da sind
        'patience' => 1800.0, 'patienceRecover' => 2.0, 'leaving' => false,
    ];
    /**
     * Beziehung zwischen zwei Musikern (−10 bis +10, Start 0): Gemeinsame Erlebnisse ziehen sie zum Zielwert `base + scale × Passung`;
     * wer nicht mehr zusammen ist, driftet je Stunde um drift auf 0 zurück.
     */
    public const RELATION = [
        'max' => 10.0, 'scale' => 10.0, 'base' => 3.0, 'drift' => 0.2,
        'bond' => ['rehearsal' => 0.12, 'gig' => 0.10, 'record' => 0.08, 'bandpromo' => 0.05],
        'clash' => -0.5, 'teamplayer' => 0.25, 'party' => 0.3,
        'genres' => [0 => -0.3, 1 => 0.1, 2 => 0.3, 3 => 0.4], 'gapFrom' => 30, 'gapBig' => 50, 'gapPenalty' => [-0.2, -0.4],
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
    /** … und wenn sie dazu noch ein zweites Genre teilen (Hybrid, z. B. Punk-Rock). */
    public const HYBRID_BONUS = 1.25;
    /** … und wenn alle sogar ein drittes teilen (Crossover, sehr selten). Zusammenspiel wie Hybrid. */
    public const CROSSOVER_BONUS = 1.3;
    /** Maximaler Bonus eines Instruments mit Klang 100 (+50 %). */
    public const GEAR_MAX_BONUS = 0.5;
    /** Wie schnell sich der Klang mit dem Können entfaltet: (Können / nötiges Können) hoch diesem Wert. */
    public const GEAR_UNLOCK_CURVE = 2.0;
    /** Ohne eigenes Instrument spielt man auf irgendwas Geliehenem – das klingt nach nichts. */
    public const NO_GEAR_FACTOR = 0.5;
    /** Jedes Instrument weicht pro Wert bis zu so viel vom Modell ab. */
    public const GEAR_VARIANCE = 12;
    /** Eigenschaften je Seltenheit (siehe Catalog::GEAR_PERKS) und ihre Stärke. */
    public const GEAR_PERKS = ['common' => 0, 'rare' => 0, 'epic' => 1, 'legendary' => 2];
    public const PERK_STAGE = 0.15;
    public const PERK_TEACHER = 1.3;
    public const PERK_STUDIO = 0.06;
    /** Verkauf an den Second-Hand-Laden: Grundpreis je Seltenheit, je Eigenschaft +25 %. */
    public const GEAR_SELL = ['common' => 25, 'rare' => 80, 'epic' => 260, 'legendary' => 750];
    public const GEAR_SELL_PERK = 0.25;
    /** +5 % je weiterem unterschiedlichen Instrument, höchstens für 4 weitere. */
    public const LINEUP_BONUS = 0.05;
    public const LINEUP_BONUS_MAX_TYPES = 4;
    /**
     * Große Bands: Jedes Mitglied über MIN_MEMBERS bringt mehr Sound (volle Bühne) und mehr Gage (zahlt Instrumente und
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
        'base' => 0.8, 'ausstrahlung' => 0.15, 'charisma' => 0.3, 'unusual' => 0.3, 'design' => 0.2,
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
    public const REHEARSAL = ['songs' => 2, 'cohesion' => 2.5, 'skill' => 0.05];
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
    /** Auftrittsorte für Gigs nach Bandruhm. gage und fame gelten pro Lied, expect = erwarteter Sound. */
    public const VENUES = [
        ['key' => 'kneipe', 'name' => 'Kneipe', 'minFame' => 0, 'gage' => 30, 'fame' => 0.25, 'expect' => 15],
        ['key' => 'club', 'name' => 'Club', 'minFame' => 30, 'gage' => 70, 'fame' => 0.5, 'expect' => 28],
        ['key' => 'saal', 'name' => 'Konzertsaal', 'minFame' => 150, 'gage' => 160, 'fame' => 1.0, 'expect' => 42],
        ['key' => 'halle', 'name' => 'Halle', 'minFame' => 600, 'gage' => 380, 'fame' => 2.0, 'expect' => 58],
        ['key' => 'arena', 'name' => 'Arena', 'minFame' => 2500, 'gage' => 900, 'fame' => 4.0, 'expect' => 75],
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
    /**
     * Über Stufe 1 hinaus geht es mit den heutigen Mitteln kaum weiter: Können- und Bekanntheitsgewinne wirken
     * je nach aktueller Stufe nur noch zu diesem Anteil (Stufe 0, 1, 2+). Profi/Szenegröße ist sehr schwer,
     * Virtuose/Star praktisch unerreichbar – Platz für Tourneen und Manager, die später dazukommen.
     */
    public const LEVEL_PACE = [1.0, 0.25, 0.03];
    public const PRACTICE_GAIN = 0.8;
    /**
     * Tageskurse: Jeden Tag ein zufälliges Instrument und eine zufällige Kursart, für alle Spieler gleich.
     * Kursart: [Name, Häufigkeit, Dauer in Sekunden, Können, Grundpreis] – der Zuwachs ist für jeden Teilnehmer gleich,
     * ohne Lernkurve und ohne Stufen-Bremse (der Weg nach oben). Angeboten wird sie in drei Schwierigkeiten
     * (Anfänger, Fortgeschrittene, Profis = Stufe 0, 1, 2+); jeder nur in seiner, der Preis × PRICE_FACTOR.
     * Ein Musiker hat etwa jeden zehnten Tag sein Instrument: im Schnitt +4,5 Können, eine Stufe (25 Punkte) allein
     * durch Kurse in rund zwei Monaten.
     */
    public const COURSE = [
        'kinds' => [['Kurs', 50, 600, 3.0, 80], ['Workshop', 35, 1800, 5.0, 150], ['Meisterklasse', 15, 3600, 8.0, 300]],
        'levels' => ['Anfänger', 'Fortgeschrittene', 'Profis'],
        'priceFactor' => [1, 3, 8],
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
    public const TALENT_FACTOR = 1.5;
    /** So lange (Sekunden) bleibt ein gestartetes Minispiel gültig. */
    public const MINIGAME_TTL = 180;

    // ---------- Promo-Mission ----------
    public const PROMO_SECONDS = 600;
    /** Promo-Mission: Bekanntheit [min, max], geteilt durch (1 + Bekanntheit / Abfall). */
    public const PROMO_FAME = [0.12, 0.3];
    public const PROMO_FAME_FALLOFF = 40;
    public const ATTRACTIVE_FACTOR = 1.5;

    // ---------- Platte ----------
    /** Studiozeit: Grundpreis plus pro Lied. */
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
    /** Nachrichten beim Wiederkommen: nach so langer Pause, diese Arten aus der Chronik. */
    public const NEWS_AWAY = 900;
    public const NEWS_KINDS = ['chart1', 'chart', 'transfer', 'scout', 'level', 'fame', 'fanjoin'];
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
    /**
     * Katalog: Jede fertige Platte verkauft sich für immer ein wenig – so viel ₲ pro Stunde und Chartpunkt (Startwert
     * der Platte), zusätzlich zu den Charts. Eine Platte mit 60 Punkten bringt so rund 14 ₲ am Tag: spürbar, wenn sich
     * Platten ansammeln, aber klein gegen Gigs (ein Kneipen-Gig ≈ 90 ₲).
     */
    public const CATALOG_MONEY = 0.01;
    public const ROYALTY_FAME = 0.0125;
}
