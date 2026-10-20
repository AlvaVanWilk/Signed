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

    /** Ein Lied dauert überall 30 Sekunden: im Minispiel und bei Auftritten. */
    public const SONG_SECONDS = 30;

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
     * fit = der Laden sucht Instrumente für Musiker ohne Instrument; chooseType = Instrumentenart selbst wählen.
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
            'name' => 'Second-Hand-Laden', 'price' => 350, 'desc' => 'Gebrauchte Instrumente in ordentlichem Zustand, passend zu deiner Besetzung.',
            'choices' => 4, 'kinds' => ['gear' => 100], 'fit' => true,
            'odds' => ['common' => 50, 'rare' => 38, 'epic' => 10, 'legendary' => 2],
        ],
        'musicstore' => [
            'name' => 'Music Store', 'price' => 800, 'desc' => 'Neue Instrumente. Der Verkäufer sucht passend zu deiner Besetzung aus.',
            'choices' => 4, 'kinds' => ['gear' => 100], 'fit' => true,
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
    /** In Läden mit fit kommen Instrumente für Musiker ohne Instrument so viel häufiger. */
    public const STORE_FIT_FACTOR = 5;
    /** Pro Scout-Stufe sinkt die Chance auf gewöhnliche Karten um diesen Anteil. */
    public const SCOUT_SHIFT = 0.06;

    // ---------- Band ----------
    public const MIN_MEMBERS = 2;
    public const MAX_MEMBERS = 6;
    public const MAX_PER_INSTRUMENT = ['drums' => 1, 'guitar' => 2];

    // ---------- Sound ----------
    /** Sound = (Gewicht × Durchschnitt + Rest × schwächster Musiker) × Besetzung × Zusammenspiel. */
    public const SOUND_MEAN_WEIGHT = 0.75;
    public const OFF_GENRE_FACTOR = 0.75;
    /** Maximaler Bonus eines Instruments mit Klang 100 (+50 %). */
    public const GEAR_MAX_BONUS = 0.5;
    /** Ohne eigenes Instrument spielt man auf irgendwas Geliehenem – das klingt nach nichts. */
    public const NO_GEAR_FACTOR = 0.5;
    /** Jedes Instrument weicht pro Wert bis zu so viel vom Modell ab. */
    public const GEAR_VARIANCE = 12;
    /** +5 % je weiterem unterschiedlichen Instrument, höchstens für 4 weitere. */
    public const LINEUP_BONUS = 0.05;
    public const LINEUP_BONUS_MAX_TYPES = 4;
    /** Sound-Faktor bei Zusammenspiel 0; bei 100 ist er 1. */
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
    public const BUSKING = ['songs' => 3, 'fame' => 0.3, 'expect' => 25, 'falloff' => 150, 'minFactor' => 0.1];
    /** Bandprobe: keine Zuschauer, dafür doppeltes Zusammenspiel und etwas Können für alle. */
    public const REHEARSAL = ['songs' => 2, 'cohesion' => 2.5, 'skill' => 0.4];
    /** Band-Promo: alle Mitglieder sind unterwegs, jeder bringt Bekanntheit wie bei der Promo-Mission. */
    public const BAND_PROMO = ['seconds' => 600, 'fame' => [0.8, 2.0]];
    /** Jam-Café: nur Musiker ohne Band; danach drei passende Musiker (Stufe 0) zur Auswahl für eine Band. */
    /**
     * Im Publikum sitzen Musiker – sie erwarten einiges. Wie gut die Session lief (Publikumsleiste 0–100),
     * bestimmt, wie gut die Musiker sind, die sich danach melden: Seltenheit und Können werden zwischen
     * „schlecht“ und „begeistert“ gemischt. Können bleibt immer unter Stufe 1.
     */
    public const JAM = [
        'songs' => 2, 'cards' => 3, 'fame' => 0.2, 'expect' => 35,
        'odds' => [
            'low' => ['common' => 85, 'rare' => 14, 'epic' => 1, 'legendary' => 0],
            'high' => ['common' => 35, 'rare' => 45, 'epic' => 20, 'legendary' => 0],
        ],
        'minSkill' => [5.0, 26.0],
    ];
    public const OPENSTAGE = [
        'songs' => 2, 'fame' => 1.4, 'expect' => 12, 'falloff' => 120,
        'managerChance' => 0.07, 'fanChance' => 0.15, 'fanValue' => [3, 10],
    ];
    /** Auftrittsorte für Gigs nach Bandruhm. gage und fame gelten pro Lied, expect = erwarteter Sound. */
    public const VENUES = [
        ['key' => 'kneipe', 'name' => 'Kneipe', 'minFame' => 0, 'gage' => 30, 'fame' => 2.0, 'expect' => 15],
        ['key' => 'club', 'name' => 'Club', 'minFame' => 30, 'gage' => 70, 'fame' => 4.0, 'expect' => 28],
        ['key' => 'saal', 'name' => 'Konzertsaal', 'minFame' => 150, 'gage' => 160, 'fame' => 8.0, 'expect' => 42],
        ['key' => 'halle', 'name' => 'Halle', 'minFame' => 600, 'gage' => 380, 'fame' => 16.0, 'expect' => 58],
        ['key' => 'arena', 'name' => 'Arena', 'minFame' => 2500, 'gage' => 900, 'fame' => 32.0, 'expect' => 75],
    ];
    /** Lieder pro Gig ohne Manager; mit Manager kommen pro (effektiver) Stufe welche dazu. */
    public const GIG_SONG_CAP = 3;
    /** Ein richtiger Gig hat mindestens so viele Lieder. */
    public const GIG_MIN_SONGS = 3;
    public const MANAGER_SONGS_PER_LEVEL = 1.4;
    public const NEGOTIATOR_BONUS = 0.08;
    public const PR_BONUS = 0.08;

    // ---------- Minispiel ----------
    public const PRACTICE_GAIN = 4.0;
    /** Fenster des Rhythmusspiels in ms bei Können 0; mit Können wachsen sie (Chart::windows). */
    public const RHYTHM_WINDOWS = ['perfect' => 60, 'good' => 135];
    public const TALENT_FACTOR = 1.5;
    /** So lange (Sekunden) bleibt ein gestartetes Minispiel gültig. */
    public const MINIGAME_TTL = 180;

    // ---------- Promo-Mission ----------
    public const PROMO_SECONDS = 600;
    /** Promo-Mission: Bekanntheit [min, max], geteilt durch (1 + Bekanntheit / Abfall). */
    public const PROMO_FAME = [1.0, 2.5];
    public const PROMO_FAME_FALLOFF = 40;
    public const ATTRACTIVE_FACTOR = 1.5;

    // ---------- Platte ----------
    public const RECORD_COST = 500;
    public const RECORD_MIN_SONGS = 3;
    public const RECORD_MAX_SONGS = 12;
    /** Vor der nächsten Platte spielt die Band die letzte live: so viele Lieder pro Lied der Platte, auf Gigs. */
    public const LIVE_SONGS_PER_RECORD_SONG = 2;
    public const RECORD_SONG_SKILL = 0.6;
    public const RECORD_SONG_COHESION = 2.0;
    /** Erstverkauf beim Erscheinen; das eigentliche Einkommen sind die Tantiemen, solange die Platte chartet. */
    public const RECORD_MONEY_PER_SONG = 60;
    public const RECORD_FAME_PER_SONG = 5.0;
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
    /** Bandruhm hebt die Punkte: +1 pro 500 Ruhm, höchstens +2. */
    public const CHART_FAME = 500;
    public const CHART_FAME_MAX = 2;
    /** Die Punkte schrumpfen jeden Tag auf diesen Anteil. */
    public const CHART_DECAY = 0.88;
    /** Unter dieser Punktzahl fällt eine Platte aus den Charts. */
    public const CHART_MIN_POINTS = 1.0;
    public const CHART_SIZE = 50;
    /** Platz 1 bringt einen Scout-Gutschein erst, wenn so viele Labels Platten in den Charts haben. */
    public const CHART_TOP_MIN_LABELS = 3;
    /** Tantiemen pro Chartpunkt und Tag. */
    public const ROYALTY_MONEY = 2.0;
    public const ROYALTY_FAME = 0.05;
}
