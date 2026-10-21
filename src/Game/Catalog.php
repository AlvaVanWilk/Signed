<?php
declare(strict_types=1);

namespace Signed\Game;

/** Feste Spieldaten: Genres, Instrumente, Eigenschaften, Instrumentenmodelle, Namen und Texte. */
final class Catalog
{
    public const RARITIES = [
        'common' => ['name' => 'Gewöhnlich'],
        'rare' => ['name' => 'Selten'],
        'epic' => ['name' => 'Episch'],
        'legendary' => ['name' => 'Legendär'],
    ];

    /** Alle Spuren haben dasselbe Tempo und dieselbe Tonart, damit sich jedes Genre mit jedem mischen lässt. */
    /**
     * electric = Klang ohne eigenes Instrument (elektrisch oder akustisch);
     * alwaysElectric = Instrumente, die in diesem Genre immer elektrisch klingen, egal was der Musiker in der Hand hat.
     */
    public const GENRES = [
        'pop' => ['name' => 'Pop', 'bpm' => Balance::BPM, 'electric' => true],
        'rock' => ['name' => 'Rock', 'bpm' => Balance::BPM, 'electric' => true],
        'metal' => ['name' => 'Metal', 'bpm' => Balance::BPM, 'electric' => true, 'alwaysElectric' => ['guitar', 'bass']],
        'punk' => ['name' => 'Punk', 'bpm' => Balance::BPM, 'electric' => true],
        'jazz' => ['name' => 'Jazz', 'bpm' => Balance::BPM, 'electric' => false],
        'elektro' => ['name' => 'Elektro', 'bpm' => Balance::BPM, 'electric' => true, 'alwaysElectric' => ['keys', 'drums', 'bass']],
        'hiphop' => ['name' => 'Hip-Hop', 'bpm' => Balance::BPM, 'electric' => true],
        'folk' => ['name' => 'Folk', 'bpm' => Balance::BPM, 'electric' => false],
    ];

    /** Klingt dieses Instrument elektrisch? Genre-Pflicht vor eigenem Instrument vor Genre-Gewohnheit. */
    public static function soundsElectric(string $genre, string $type, ?bool $own): bool
    {
        $g = self::GENRES[$genre] ?? null;
        if ($g !== null && in_array($type, $g['alwaysElectric'] ?? [], true)) {
            return true;
        }
        return $own ?? ($g['electric'] ?? true);
    }

    /** genres = typische Genres (Klischee). Ein Musiker außerhalb davon ist „ungewöhnlich“. at = „ist … jetzt Profi“. */
    public const INSTRUMENTS = [
        'vocals' => ['name' => 'Gesang', 'at' => 'beim Gesang', 'icon' => '🎤', 'weight' => 20,
            'genres' => ['pop', 'rock', 'metal', 'punk', 'jazz', 'elektro', 'hiphop', 'folk'],
            'techniques' => ['falsett', 'growl', 'belting', 'rap', 'beatbox']],
        'guitar' => ['name' => 'Gitarre', 'at' => 'an der Gitarre', 'icon' => '🎸', 'weight' => 18,
            'genres' => ['pop', 'rock', 'metal', 'punk', 'jazz', 'folk'],
            'techniques' => ['tapping', 'shredding', 'fingerstyle']],
        'bass' => ['name' => 'Bass', 'at' => 'am Bass', 'icon' => '🎸', 'weight' => 14,
            'genres' => ['pop', 'rock', 'metal', 'punk', 'jazz', 'hiphop', 'folk'],
            'techniques' => ['slap', 'walking']],
        'drums' => ['name' => 'Schlagzeug', 'at' => 'am Schlagzeug', 'icon' => '🥁', 'weight' => 14,
            'genres' => ['pop', 'rock', 'metal', 'punk', 'jazz', 'hiphop', 'folk'],
            'techniques' => ['doublebass', 'blastbeat']],
        'keys' => ['name' => 'Keyboard', 'at' => 'am Keyboard', 'icon' => '🎹', 'weight' => 15,
            'genres' => ['pop', 'rock', 'jazz', 'elektro', 'hiphop'],
            'techniques' => ['arpeggio', 'improvisation', 'sounddesign']],
        'turntables' => ['name' => 'Turntables', 'at' => 'an den Turntables', 'icon' => '💿', 'weight' => 4,
            'genres' => ['hiphop', 'elektro'],
            'techniques' => ['scratching', 'juggling']],
        'sax' => ['name' => 'Saxophon', 'at' => 'am Saxophon', 'icon' => '🎷', 'weight' => 4,
            'genres' => ['jazz', 'pop', 'rock'],
            'techniques' => ['zirkular', 'improvisation']],
        'trumpet' => ['name' => 'Trompete', 'at' => 'an der Trompete', 'icon' => '🎺', 'weight' => 3,
            'genres' => ['jazz', 'pop', 'punk', 'folk'],
            'techniques' => ['highnotes', 'zirkular']],
        'violin' => ['name' => 'Geige', 'at' => 'an der Geige', 'icon' => '🎻', 'weight' => 3,
            'genres' => ['folk', 'pop', 'jazz'],
            'techniques' => ['flageolett', 'improvisation']],
        'flute' => ['name' => 'Flöte', 'at' => 'an der Flöte', 'icon' => '🪈', 'weight' => 3,
            'genres' => ['folk', 'jazz', 'pop'],
            'techniques' => ['zirkular', 'flatterzunge']],
    ];

    /**
     * Was eine Band braucht, um sich nach einem Genre zu nennen (zusätzlich müssen alle Mitglieder das Genre mögen).
     * Je Bedingung: Instrumente (eins davon genügt) und Anzahl.
     */
    public const GENRE_LINEUP = [
        'pop' => [[['vocals'], 1]],
        'rock' => [[['guitar'], 1], [['drums'], 1]],
        'metal' => [[['guitar'], 2], [['drums'], 1]],
        'punk' => [[['guitar'], 1], [['bass'], 1], [['drums'], 1]],
        'jazz' => [[['bass'], 1], [['sax', 'trumpet'], 1]],
        'elektro' => [[['keys', 'turntables'], 1]],
        'hiphop' => [[['vocals'], 1], [['turntables'], 1]],
        'folk' => [[['violin', 'flute', 'guitar'], 1]],
    ];

    /** Diese Instrumente üben im Tonhöhen-Spiel statt im Rhythmus-Spiel. */
    public const PITCH_INSTRUMENTS = ['vocals', 'violin'];
    /** In diesen Genres wird gerappt: Gesang übt dann im Rhythmus-Spiel (Silben im Takt) statt nach Tonhöhe. */
    public const RAP_GENRES = ['hiphop'];

    /**
     * Fähigkeiten: Ein Musiker hat so viele, wie seine Können-Stufe angibt (höchstens 3).
     * technique = an ein Instrument gebunden (siehe INSTRUMENTS[...]['techniques']).
     */
    public const ABILITIES = [
        'talent' => ['name' => 'Talent', 'icon' => '🌱', 'desc' => 'Können wächst schneller.'],
        'ausstrahlung' => ['name' => 'Ausstrahlung', 'icon' => '✨', 'desc' => 'Promo bringt mehr Bekanntheit.'],
        'charisma' => ['name' => 'Charisma', 'icon' => '💫', 'desc' => 'Mehr Ruhm bei Auftritten.'],
        'nervenstark' => ['name' => 'Nervenstark', 'icon' => '🧊', 'desc' => 'Patzt live seltener.'],
        'gehoer' => ['name' => 'Absolutes Gehör', 'icon' => '👂', 'desc' => 'Mehr Spielraum in der Solo-Probe.'],
        'buehnentier' => ['name' => 'Bühnentier', 'icon' => '🔥', 'desc' => 'Mehr Glanzmomente live.'],
        'studiofuchs' => ['name' => 'Studiofuchs', 'icon' => '🎚️', 'desc' => 'Leichter zu mischen im Studio.'],
        'falsett' => ['name' => 'Falsett', 'icon' => '🪶', 'technique' => true],
        'growl' => ['name' => 'Growl', 'icon' => '🐺', 'technique' => true],
        'belting' => ['name' => 'Belting', 'icon' => '📣', 'technique' => true],
        'rap' => ['name' => 'Rap', 'icon' => '🗣️', 'technique' => true],
        'beatbox' => ['name' => 'Beatbox', 'icon' => '👄', 'technique' => true],
        'tapping' => ['name' => 'Tapping', 'icon' => '👆', 'technique' => true],
        'shredding' => ['name' => 'Shredding', 'icon' => '⚡', 'technique' => true],
        'fingerstyle' => ['name' => 'Fingerstyle', 'icon' => '🤌', 'technique' => true],
        'slap' => ['name' => 'Slap', 'icon' => '👋', 'technique' => true],
        'walking' => ['name' => 'Walking Bass', 'icon' => '🚶', 'technique' => true],
        'doublebass' => ['name' => 'Double Bass', 'icon' => '🦶', 'technique' => true],
        'blastbeat' => ['name' => 'Blastbeat', 'icon' => '💥', 'technique' => true],
        'arpeggio' => ['name' => 'Arpeggio-Läufe', 'icon' => '🌊', 'technique' => true],
        'improvisation' => ['name' => 'Improvisation', 'icon' => '🎲', 'technique' => true],
        'sounddesign' => ['name' => 'Sounddesign', 'icon' => '🧪', 'technique' => true],
        'scratching' => ['name' => 'Scratching', 'icon' => '🌀', 'technique' => true],
        'juggling' => ['name' => 'Beat Juggling', 'icon' => '🤹', 'technique' => true],
        'zirkular' => ['name' => 'Zirkularatmung', 'icon' => '🔄', 'technique' => true],
        'highnotes' => ['name' => 'High Notes', 'icon' => '🏔️', 'technique' => true],
        'flageolett' => ['name' => 'Flageolett', 'icon' => '🔔', 'technique' => true],
        'flatterzunge' => ['name' => 'Flatterzunge', 'icon' => '🦋', 'technique' => true],
    ];

    /** Namen der Stufen: Können-Leiter und Bekanntheits-Leiter (0–3). */
    public const LEVEL_NAMES = ['Anfänger', 'Fortgeschritten', 'Profi', 'Virtuose'];
    /** Für Sätze wie „Ada ist an der Gitarre jetzt Profi“. */
    public const LEVEL_WORDS = ['Anfänger', 'fortgeschritten', 'Profi', 'virtuos'];
    public const FAME_NAMES = ['Unbekannt', 'Lokal bekannt', 'Szenegröße', 'Star'];

    /** Was eine Spezialtechnik bewirkt (gilt für alle Techniken). */
    public const TECHNIQUE_DESC = 'Spezialtechnik: mehr Glanzmomente live.';

    /** Charakterzüge für die Chemie. */
    public const PERSONALITIES = [
        'ehrgeizig' => 'Ehrgeizig',
        'gelassen' => 'Gelassen',
        'perfektionist' => 'Perfektionist',
        'chaot' => 'Chaot',
        'rampensau' => 'Rampensau',
        'diva' => 'Diva',
        'teamplayer' => 'Teamplayer',
        'ruhepol' => 'Ruhepol',
        'partytier' => 'Partytier',
    ];

    /** Was eine Eigenschaft bedeutet und wie sie in der Band wirkt (Chemie). */
    public const PERSONALITY_INFO = [
        'ehrgeizig' => 'Will immer mehr erreichen. Reibt sich mit Gelassenen.',
        'gelassen' => 'Nimmt alles locker. Reibt sich mit Ehrgeizigen.',
        'perfektionist' => 'Alles muss genau sitzen. Reibt sich mit Chaoten.',
        'chaot' => 'Kreativ, aber unordentlich. Reibt sich mit Perfektionisten.',
        'rampensau' => 'Liebt die Bühne über alles. Zwei Rampensäue in einer Band reiben sich.',
        'diva' => 'Will im Mittelpunkt stehen. Zwei Divas in einer Band reiben sich.',
        'teamplayer' => 'Hilft allen in der Band: Die Chemie wird besser.',
        'ruhepol' => 'Beruhigt alle: Jede Reibung in der Band zählt nur halb.',
        'partytier' => 'Feiert gern. Zwei Partytiere in einer Band verstehen sich prächtig.',
    ];

    /** Eigenschaften, die nicht zusammen in einem Menschen vorkommen (wären widersprüchlich). */
    public const CONTRADICTIONS = [
        ['teamplayer', 'diva'],
        ['ruhepol', 'chaot'],
        ['ruhepol', 'rampensau'],
        ['perfektionist', 'chaot'],
        ['ehrgeizig', 'gelassen'],
    ];

    /** Paare, die sich reiben. */
    public const CLASHES = [
        ['diva', 'diva'],
        ['rampensau', 'rampensau'],
        ['perfektionist', 'chaot'],
        ['ehrgeizig', 'gelassen'],
    ];

    /** Zutaten des Cover-Automaten, in der Reihenfolge der Bänder (Bilder dazu in public/assets/js/cover.js). */
    public const COVER_PARTS = [
        'motif' => ['blitz', 'herz', 'stern', 'mond', 'totenkopf', 'auge', 'welle', 'rakete', 'krone', 'katze', 'pilz', 'flamme', 'vinyl'],
        'palette' => ['neon', 'pastell', 'schwarzrot', 'goldlila', 'ozean', 'wald', 'sonne', 'kaugummi', 'mono', 'gift'],
        'style' => ['popart', 'neon', 'holzschnitt', 'aquarell', 'kaleido', 'retro', 'pixel', 'graffiti'],
        'layout' => ['mitte', 'riesig', 'muster', 'schraeg'],
    ];

    /** active = wirkt schon im Kern; die anderen brauchen spätere Features. */
    public const MANAGER_SKILLS = [
        'verhandler' => ['name' => 'Verhandler', 'desc' => 'Mehr Gage bei Gigs seiner Bands.', 'active' => true],
        'prprofi' => ['name' => 'PR-Profi', 'desc' => 'Mehr Ruhm bei Auftritten seiner Bands.', 'active' => true],
        'scout' => ['name' => 'Scout', 'desc' => 'Weniger gewöhnliche Karten in allen Packs des Labels – auch ohne Band. Der beste Scout zählt.', 'active' => true],
        'tourplaner' => ['name' => 'Tourplaner', 'desc' => 'Mehr und passendere Locations bei Tourneen.', 'active' => false],
        'netzwerker' => ['name' => 'Netzwerker', 'desc' => 'Zugang zu ausgeschriebenen und exklusiven Gigs.', 'active' => false],
    ];

    /**
     * Instrumentenmodelle. klang = maximaler Sound-Bonus, spielbarkeit = hoch heißt leicht spielbar
     * (entfaltet sich schon bei wenig Können), design = Bonus auf Ruhm.
     */
    /** Besondere Eigenschaften epischer und legendärer Instrumente; Stärke in Balance (PERK_*). */
    public const GEAR_PERKS = [
        'stage' => ['name' => 'Bühnenmagnet', 'icon' => '✨', 'desc' => 'Wer es spielt, bekommt bei Auftritten mehr Bekanntheit.'],
        'teacher' => ['name' => 'Lehrmeister', 'icon' => '📚', 'desc' => 'Wer darauf übt, lernt schneller (Können).'],
        'studio' => ['name' => 'Studioklang', 'icon' => '🎚', 'desc' => 'Platten der Band bekommen mehr Chartpunkte.'],
    ];

    public const GEAR = [
        'mic_basic' => ['type' => 'vocals', 'name' => 'Standardmikro', 'rarity' => 'common', 'klang' => 10, 'spielbarkeit' => 90, 'design' => 5],
        'mic_condenser' => ['type' => 'vocals', 'name' => 'Studio-Kondensator', 'rarity' => 'rare', 'klang' => 35, 'spielbarkeit' => 70, 'design' => 15],
        'mic_tube' => ['type' => 'vocals', 'name' => 'Vintage-Röhrenmikro', 'rarity' => 'epic', 'klang' => 60, 'spielbarkeit' => 55, 'design' => 45],
        'mic_gold' => ['type' => 'vocals', 'name' => 'Goldkehlchen', 'rarity' => 'legendary', 'klang' => 86, 'spielbarkeit' => 45, 'design' => 85],

        // electric: nur fürs Spiel – wählt die akustische oder elektrische Spur. Angezeigt wird es nicht.
        'gtr_school' => ['type' => 'guitar', 'name' => 'Einsteiger-E-Gitarre', 'rarity' => 'common', 'klang' => 10, 'spielbarkeit' => 85, 'design' => 5, 'electric' => true],
        'gtr_strato' => ['type' => 'guitar', 'name' => 'Strato Standard', 'rarity' => 'rare', 'klang' => 36, 'spielbarkeit' => 68, 'design' => 25, 'electric' => true],
        'gtr_monster' => ['type' => 'guitar', 'name' => 'Monster-Axt', 'rarity' => 'epic', 'klang' => 58, 'spielbarkeit' => 50, 'design' => 75, 'electric' => true],
        'gtr_sunburst' => ['type' => 'guitar', 'name' => 'Sunburst ’59', 'rarity' => 'legendary', 'klang' => 88, 'spielbarkeit' => 38, 'design' => 80, 'electric' => true],
        'gtr_camp' => ['type' => 'guitar', 'name' => 'Wandergitarre', 'rarity' => 'common', 'klang' => 10, 'spielbarkeit' => 88, 'design' => 8, 'electric' => false],
        'gtr_western' => ['type' => 'guitar', 'name' => 'Westerngitarre', 'rarity' => 'rare', 'klang' => 35, 'spielbarkeit' => 70, 'design' => 22, 'electric' => false],
        'gtr_jumbo' => ['type' => 'guitar', 'name' => 'Jumbo-Western', 'rarity' => 'epic', 'klang' => 60, 'spielbarkeit' => 50, 'design' => 55, 'electric' => false],
        'gtr_master' => ['type' => 'guitar', 'name' => 'Meisterstück Akustik', 'rarity' => 'legendary', 'klang' => 87, 'spielbarkeit' => 40, 'design' => 82, 'electric' => false],

        'bass_entry' => ['type' => 'bass', 'name' => 'Einsteigerbass', 'rarity' => 'common', 'klang' => 10, 'spielbarkeit' => 85, 'design' => 5],
        'bass_jazz' => ['type' => 'bass', 'name' => 'Jazzbass Deluxe', 'rarity' => 'rare', 'klang' => 38, 'spielbarkeit' => 66, 'design' => 20],
        'bass_abyss' => ['type' => 'bass', 'name' => 'Fünfsaiter Abyss', 'rarity' => 'epic', 'klang' => 62, 'spielbarkeit' => 48, 'design' => 45],
        'bass_deep' => ['type' => 'bass', 'name' => 'Tiefseebass', 'rarity' => 'legendary', 'klang' => 85, 'spielbarkeit' => 40, 'design' => 75],

        'drm_rehearsal' => ['type' => 'drums', 'name' => 'Proberaum-Set', 'rarity' => 'common', 'klang' => 10, 'spielbarkeit' => 85, 'design' => 5, 'electric' => false],
        'drm_maple' => ['type' => 'drums', 'name' => 'Ahorn-Kit', 'rarity' => 'rare', 'klang' => 36, 'spielbarkeit' => 68, 'design' => 25, 'electric' => false],
        'drm_double' => ['type' => 'drums', 'name' => 'Doppelfuß-Monster', 'rarity' => 'epic', 'klang' => 60, 'spielbarkeit' => 48, 'design' => 60, 'electric' => false],
        'drm_thunder' => ['type' => 'drums', 'name' => 'Donnerkuppel', 'rarity' => 'legendary', 'klang' => 87, 'spielbarkeit' => 40, 'design' => 85, 'electric' => false],
        'drm_pads' => ['type' => 'drums', 'name' => 'Übungs-E-Drums', 'rarity' => 'common', 'klang' => 9, 'spielbarkeit' => 90, 'design' => 8, 'electric' => true],
        'drm_eset' => ['type' => 'drums', 'name' => 'E-Drum-Set', 'rarity' => 'rare', 'klang' => 35, 'spielbarkeit' => 72, 'design' => 30, 'electric' => true],
        'drm_epro' => ['type' => 'drums', 'name' => 'E-Drum Pro', 'rarity' => 'epic', 'klang' => 60, 'spielbarkeit' => 52, 'design' => 55, 'electric' => true],
        'drm_neon' => ['type' => 'drums', 'name' => 'Neon-E-Drums', 'rarity' => 'legendary', 'klang' => 86, 'spielbarkeit' => 42, 'design' => 90, 'electric' => true],

        'key_home' => ['type' => 'keys', 'name' => 'Heimkeyboard', 'rarity' => 'common', 'klang' => 8, 'spielbarkeit' => 90, 'design' => 5, 'electric' => true],
        'key_stage' => ['type' => 'keys', 'name' => 'Polysynth 80', 'rarity' => 'rare', 'klang' => 36, 'spielbarkeit' => 66, 'design' => 28, 'electric' => true],
        'key_organ' => ['type' => 'keys', 'name' => 'Synth-Workstation', 'rarity' => 'epic', 'klang' => 62, 'spielbarkeit' => 48, 'design' => 60, 'electric' => true],
        'key_neon' => ['type' => 'keys', 'name' => 'Neonwelle Prime', 'rarity' => 'legendary', 'klang' => 86, 'spielbarkeit' => 40, 'design' => 90, 'electric' => true],
        'key_upright' => ['type' => 'keys', 'name' => 'Wohnzimmerklavier', 'rarity' => 'common', 'klang' => 10, 'spielbarkeit' => 86, 'design' => 8, 'electric' => false],
        'key_studio' => ['type' => 'keys', 'name' => 'Studioklavier', 'rarity' => 'rare', 'klang' => 37, 'spielbarkeit' => 68, 'design' => 22, 'electric' => false],
        'key_stagepiano' => ['type' => 'keys', 'name' => 'Bühnenklavier', 'rarity' => 'epic', 'klang' => 61, 'spielbarkeit' => 50, 'design' => 50, 'electric' => false],
        'key_grand' => ['type' => 'keys', 'name' => 'Goldenes Klavier', 'rarity' => 'legendary', 'klang' => 88, 'spielbarkeit' => 40, 'design' => 85, 'electric' => false],

        'tt_entry' => ['type' => 'turntables', 'name' => 'Einsteiger-Deck', 'rarity' => 'common', 'klang' => 10, 'spielbarkeit' => 85, 'design' => 10],
        'tt_direct' => ['type' => 'turntables', 'name' => 'Direktantrieb 1200', 'rarity' => 'rare', 'klang' => 36, 'spielbarkeit' => 68, 'design' => 25],
        'tt_battle' => ['type' => 'turntables', 'name' => 'Battle-Mixer Pro', 'rarity' => 'epic', 'klang' => 60, 'spielbarkeit' => 50, 'design' => 55],
        'tt_gold' => ['type' => 'turntables', 'name' => 'Goldene Decks', 'rarity' => 'legendary', 'klang' => 86, 'spielbarkeit' => 40, 'design' => 90],

        'sax_rental' => ['type' => 'sax', 'name' => 'Leih-Saxophon', 'rarity' => 'common', 'klang' => 10, 'spielbarkeit' => 85, 'design' => 5],
        'sax_alto' => ['type' => 'sax', 'name' => 'Altsax Messing', 'rarity' => 'rare', 'klang' => 36, 'spielbarkeit' => 68, 'design' => 25],
        'sax_tenor' => ['type' => 'sax', 'name' => 'Tenorsax Rauchig', 'rarity' => 'epic', 'klang' => 62, 'spielbarkeit' => 50, 'design' => 45],
        'sax_midnight' => ['type' => 'sax', 'name' => 'Mitternachtssax', 'rarity' => 'legendary', 'klang' => 87, 'spielbarkeit' => 40, 'design' => 80],

        'tpt_school' => ['type' => 'trumpet', 'name' => 'Schultrompete', 'rarity' => 'common', 'klang' => 10, 'spielbarkeit' => 85, 'design' => 5],
        'tpt_silver' => ['type' => 'trumpet', 'name' => 'Silbertrompete', 'rarity' => 'rare', 'klang' => 36, 'spielbarkeit' => 66, 'design' => 30],
        'tpt_flugel' => ['type' => 'trumpet', 'name' => 'Flügelhorn Samt', 'rarity' => 'epic', 'klang' => 60, 'spielbarkeit' => 50, 'design' => 50],
        'tpt_king' => ['type' => 'trumpet', 'name' => 'Königsfanfare', 'rarity' => 'legendary', 'klang' => 86, 'spielbarkeit' => 40, 'design' => 85],

        'vln_school' => ['type' => 'violin', 'name' => 'Schülergeige', 'rarity' => 'common', 'klang' => 10, 'spielbarkeit' => 85, 'design' => 5],
        'vln_master' => ['type' => 'violin', 'name' => 'Meistergeige', 'rarity' => 'rare', 'klang' => 38, 'spielbarkeit' => 64, 'design' => 25],
        'vln_neon' => ['type' => 'violin', 'name' => 'E-Violine Neon', 'rarity' => 'epic', 'klang' => 58, 'spielbarkeit' => 52, 'design' => 80],
        'vln_italian' => ['type' => 'violin', 'name' => 'Alte Italienerin', 'rarity' => 'legendary', 'klang' => 90, 'spielbarkeit' => 35, 'design' => 70],

        'flt_silver' => ['type' => 'flute', 'name' => 'Querflöte Silber', 'rarity' => 'common', 'klang' => 10, 'spielbarkeit' => 85, 'design' => 10],
        'flt_wood' => ['type' => 'flute', 'name' => 'Holzflöte Erle', 'rarity' => 'rare', 'klang' => 36, 'spielbarkeit' => 66, 'design' => 25],
        'flt_monster' => ['type' => 'flute', 'name' => 'Monster-Flöte', 'rarity' => 'epic', 'klang' => 62, 'spielbarkeit' => 45, 'design' => 75],
        'flt_crystal' => ['type' => 'flute', 'name' => 'Kristallflöte', 'rarity' => 'legendary', 'klang' => 86, 'spielbarkeit' => 40, 'design' => 90],
    ];

    public const FIRST_NAMES = [
        'f' => [
            'Lena', 'Mia', 'Zoe', 'Nora', 'Ida', 'Lou', 'Maja', 'Elif', 'Aylin', 'Jana', 'Kira', 'Romy', 'Tessa',
            'Nina', 'Paula', 'Greta', 'Yara', 'Selin', 'Amara', 'Ronja', 'Frieda', 'Leni', 'Mara', 'Ella', 'Juna',
            'Svea', 'Malin', 'Nele', 'Ivy', 'Rosa', 'Carla', 'Hanna', 'Leyla', 'Ines', 'Alma', 'Mila', 'Thea',
            'Esra', 'Sanna', 'Fenja', 'Lotta', 'Merle', 'Clara', 'Emma', 'Luisa', 'Marlene', 'Pia', 'Sophie', 'Zara',
            'Ayla', 'Dilara', 'Nika', 'Kaja', 'Lia', 'Liv', 'Smilla', 'Wanda', 'Hedda', 'Elina', 'Fiona', 'Julia',
            'Laura', 'Nadia', 'Olivia', 'Priya', 'Amira', 'Noemi', 'Imani', 'Chiara', 'Bianca', 'Valentina', 'Sofia',
            'Marta', 'Agnes', 'Vera', 'Tilda', 'Ylva', 'Elsa', 'Saga', 'Ingrid', 'Mira', 'Hira', 'Nour', 'Leila',
            'Asha', 'Keira', 'Maeve', 'Siobhan', 'Niamh', 'Aiko', 'Yuna', 'Mei', 'Linh', 'Hana', 'Ada', 'Ebba',
            'Juli', 'Edda', 'Lilly', 'Mona',
        ],
        'm' => [
            'Finn', 'Jonas', 'Emil', 'Mats', 'Ole', 'Ben', 'Luca', 'Noah', 'Arda', 'Milan', 'Theo', 'Jannik', 'Bo',
            'Can', 'Malik', 'Tarek', 'Levi', 'Henri', 'Matteo', 'Kian', 'Elias', 'Anton', 'Jasper', 'Rafael',
            'Linus', 'Juri', 'Nils', 'Oskar', 'Yusuf', 'Pablo', 'Kofi', 'Sami', 'Joscha', 'Ruben', 'Vince', 'Lasse',
            'Timo', 'Aaron', 'Enzo', 'Moritz', 'Felix', 'Paul', 'Leon', 'Max', 'Jakob', 'Karl', 'Fritz', 'Hugo',
            'Johann', 'Konstantin', 'Lennart', 'Mads', 'Sören', 'Torben', 'Hannes', 'Bastian', 'Fabian', 'Dario',
            'Marco', 'Nico', 'Diego', 'Mateo', 'Tiago', 'Rui', 'Omar', 'Karim', 'Emre', 'Deniz', 'Baran', 'Kerem',
            'Ilias', 'Nikolai', 'Dmitri', 'Pavel', 'Marek', 'Tomasz', 'Jakub', 'Ivo', 'Goran', 'Luka', 'Kenji',
            'Haruto', 'Minh', 'Jae', 'Arjun', 'Ravi', 'Kwame', 'Tunde', 'Desmond', 'Jamal', 'Silas', 'Elio',
            'Mattis', 'Piet', 'Jarno', 'Aksel', 'Erik', 'Gustav', 'Viktor', 'Ezra',
        ],
        'd' => [
            'Kim', 'Robin', 'Charlie', 'Alex', 'Sascha', 'Noa', 'Sky', 'Jo', 'Toni', 'Kai', 'Mika', 'Sam', 'Ari',
            'Rio', 'Quinn', 'Luan', 'Eden', 'River', 'Jules', 'Andy', 'Nico', 'Lumi', 'Sasha', 'Ash', 'Remy', 'Jona',
            'Fynn', 'Elia', 'Nova', 'Wren', 'Indigo', 'Phoenix', 'Rune', 'Sunny', 'Yuki', 'Ren',
        ],
    ];

    public const LAST_NAMES = [
        'Berger', 'Kowalski', 'Yılmaz', 'Novak', 'Lindqvist', 'Okafor', 'Moreau', 'Petrov', 'Schulz', 'Haas',
        'Brandt', 'Falk', 'Vogel', 'Engel', 'Stern', 'Winter', 'Sommer', 'Krüger', 'Rossi', 'Silva', 'Nakamura',
        'Larsen', 'Dumont', 'Kaya', 'Demir', 'Andersen', 'Hoffmann', 'Wolff', 'Fuchs', 'Lorenz', 'Kraft', 'Blum',
        'Roth', 'Adler', 'Seidel', 'Voss', 'Graf', 'Costa', 'Santos', 'Nguyen', 'Tran', 'Park', 'Ortega', 'Mendes',
        'Eriksen', 'Funke', 'Donner', 'Hertz', 'Kling', 'Marlow', 'Quast', 'Reyes', 'Asante', 'Hayashi', 'Oyelaran',
        'Brenner', 'Albrecht', 'Arnold', 'Bauer', 'Beck', 'Behrens', 'Böhm', 'Busch', 'Dietrich', 'Ebert', 'Ernst',
        'Fischer', 'Frank', 'Friedrich', 'Gärtner', 'Günther', 'Hahn', 'Hartmann', 'Heinrich', 'Herrmann', 'Huber',
        'Jäger', 'Jung', 'Kaiser', 'Keller', 'Klein', 'Koch', 'Köhler', 'König', 'Krause', 'Kuhn', 'Lang', 'Lehmann',
        'Lindner', 'Ludwig', 'Maier', 'Martin', 'Meyer', 'Möller', 'Morgenstern', 'Neumann', 'Otto', 'Peters',
        'Pohl', 'Richter', 'Ritter', 'Sauer', 'Schäfer', 'Scholz', 'Schreiber', 'Schwarz', 'Simon', 'Stark', 'Thiel',
        'Ulrich', 'Walter', 'Weber', 'Weiß', 'Werner', 'Winkler', 'Ziegler', 'Zimmermann', 'Abara', 'Achterberg',
        'Aguilar', 'Ahmadi', 'Alvarez', 'Amari', 'Arslan', 'Aydın', 'Barros', 'Bianchi', 'Bjørnstad', 'Blanc',
        'Bondarenko', 'Brooks', 'Byrne', 'Calloway', 'Carvalho', 'Castillo', 'Chen', 'Contreras', 'Dahl', 'Dalton',
        'De Luca', 'Delacroix', 'Diallo', 'Dos Santos', 'Dubois', 'Duarte', 'Ekström', 'Esposito', 'Fabre',
        'Ferreira', 'Fontaine', 'Gallagher', 'García', 'Gomes', 'Greco', 'Halvorsen', 'Hansen', 'Hendricks', 'Holm',
        'Horvat', 'Ibrahim', 'Ivanova', 'Jansen', 'Jensen', 'Johansson', 'Kapoor', 'Karlsson', 'Kawasaki', 'Kelly',
        'Kim', 'Kovač', 'Kristiansen', 'Lambert', 'Laurent', 'Lee', 'Lund', 'Mahler', 'Marino', 'Mbeki', 'Mercier',
        'Moretti', 'Murphy', 'Mwangi', 'Nascimento', 'Nielsen', 'O’Brien', 'Okonkwo', 'Olsen',
        'Osei', 'Özdemir', 'Pereira', 'Popescu', 'Quinn', 'Ramos', 'Rasmussen', 'Ricci', 'Rivera', 'Romano',
        'Rousseau', 'Russo', 'Saito', 'Sánchez', 'Schmitt', 'Sato', 'Shah', 'Singh', 'Sørensen', 'Suzuki',
        'Takahashi', 'Tanaka', 'Torres', 'Toure', 'Valdez', 'Van Dijk', 'Varga', 'Vega', 'Wagner', 'Watanabe',
        'Wiśniewski', 'Yamamoto', 'Zhang', 'Zielinski', 'Abendroth', 'Bergmann', 'Blau', 'Brückner', 'Dornbusch',
        'Eisenberg', 'Feldmann', 'Finkenzeller', 'Flieder', 'Glanz', 'Hagedorn', 'Heller', 'Himmel', 'Holler',
        'Kirchner', 'Klang', 'Lerche', 'Lichtenberg', 'Mondschein', 'Nachtigall', 'Regen', 'Rosenthal', 'Sänger',
        'Silberstein', 'Sonntag', 'Steinmann', 'Sturm', 'Tanner', 'Taube', 'Wiesner', 'Wildner', 'Wunder',
    ];

    public const SKIN_TONES = ['#f6d7c3', '#eac0a2', '#d9a27e', '#c58c63', '#a86f4b', '#8a5536', '#6b3f27', '#4b2c1c'];
    public const HAIR_COLORS = ['#1d1612', '#3b2a20', '#5a3b26', '#8a5a33', '#c9a15b', '#e3c88a', '#a8442a', '#9a9a9a', '#e8e4dc'];
    public const FUN_HAIR_COLORS = ['#ff4fa3', '#4f8bff', '#38d39f', '#9b5cff', '#ff8a1f'];
    public const SHIRT_COLORS = ['#2b2d42', '#8d99ae', '#ef233c', '#3a86ff', '#ffbe0b', '#06d6a0', '#8338ec', '#fb5607', '#222222', '#f1faee'];

    public const SONG_WORDS = [
        ['Neon', 'Mitternachts', 'Asphalt', 'Großstadt', 'Sommer', 'Herzschlag', 'Sternen', 'Gold', 'Rost', 'Echo',
            'Funken', 'Wolken', 'Kaffee', 'Beton', 'Nordlicht', 'Kirsch', 'Sturm', 'Glitzer', 'Rauch', 'Kellerkind'],
        ['herz', 'rausch', 'blues', 'fieber', 'lichter', 'regen', 'traum', 'tanz', 'nacht', 'flug', 'puls', 'gold',
            'sirene', 'walzer', 'ballade', 'hymne', 'sehnsucht', 'zug', 'funk', 'fieberwahn'],
    ];

    /** Bausteine für Zufallsnamen von Bands und Platten. */
    public const NAME_WORDS = [
        'adjectives' => ['Wilde', 'Leise', 'Rostige', 'Goldene', 'Verlorene', 'Elektrische', 'Schlaflose', 'Kalte',
            'Brennende', 'Neue', 'Letzte', 'Schöne', 'Laute', 'Blaue', 'Heimliche', 'Zerbrochene'],
        'nouns' => ['Herzen', 'Wölfe', 'Lichter', 'Funken', 'Sirenen', 'Wellen', 'Gespenster', 'Nächte', 'Träume',
            'Raketen', 'Stimmen', 'Spiegel', 'Wolken', 'Geister', 'Löwen', 'Kometen'],
        'bandPlural' => ['Kellerkinder', 'Nachtfalter', 'Straßenkatzen', 'Mondscheinbrüder', 'Flutlichter',
            'Stadtneurotiker', 'Hinterhofhelden', 'Glücksritter', 'Dachbodenpiraten', 'Funkenflieger', 'Wellenreiter'],
        'english' => ['Neon', 'Velvet', 'Electric', 'Silent', 'Golden', 'Midnight', 'Broken', 'Wild', 'Paper', 'Crystal',
            'Static', 'Lonely', 'Cosmic', 'Rusty'],
        'englishPlural' => ['Wolves', 'Echoes', 'Ghosts', 'Rebels', 'Dreamers', 'Hearts', 'Satellites', 'Strangers',
            'Foxes', 'Lights', 'Machines', 'Saints', 'Riots'],
        'prefixes' => ['Neon', 'Kupfer', 'Asphalt', 'Beton', 'Glitzer', 'Mitternachts', 'Nordlicht', 'Kirsch', 'Sturm'],
        'suffixes' => ['Kollektiv', 'Orchester', 'Express', 'Syndikat', 'Club', 'Projekt', 'Allstars', 'Brigade'],
        'places' => ['am Hafen', 'im Keller', 'auf dem Dach', 'in der Großstadt', 'unter Neonlicht', 'am Bahnhof',
            'im Regen', 'am Meer'],
    ];

    public const JAM_CAFES = ['Café Klangfarbe', 'Jazzkeller Blaue Note', 'Session im Hinterhof', 'Bar Zum Plattenspieler', 'Kulturcafé Kreuzton'];
    public const REHEARSAL_LINES = ['%s feilt am Übergang.', '%s zählt neu ein.', 'Die Band spielt die Bridge noch einmal.',
        '%s schlägt ein neues Intro vor.', 'Alle grinsen – das saß!', '%s probiert ein anderes Solo.'];

    public const STREETS = ['Fußgängerzone', 'U-Bahn-Station', 'Marktplatz', 'Flohmarkt', 'Hafenpromenade', 'Bahnhofsvorplatz'];
    public const OPEN_STAGES = ['Kulturcafé Funkloch', 'Open Mic im Schlachthof', 'Bühne frei! im Jugendzentrum', 'Die Kleine Kneipe', 'Studentenkeller'];

    public const VENUE_NAMES = [
        'kneipe' => ['Zum Goldenen Anker', 'Bei Uschi', 'Eckkneipe Treibgut', 'Schanigarten', 'Bierhimmel'],
        'club' => ['Club Neonlicht', 'Kellerbar Tiefgang', 'Rotes Sofa', 'Fabrikhalle 7', 'Club Kontrast'],
        'saal' => ['Konzertsaal Lichtspiel', 'Alte Oper Süd', 'Kulturpalast', 'Theater am Ring'],
        'halle' => ['Messehalle Nord', 'Stadthalle', 'Zenith-Halle', 'Eisenhalle'],
        'arena' => ['Arena am Fluss', 'Sportpalast', 'Westfalen-Arena', 'Kolosseum'],
    ];

    public const MISTAKES = [
        'any' => ['%s verspielt sich.', '%s verpasst den Einsatz.', '%s ist kurz aus dem Takt.'],
        'vocals' => ['%s trifft den Ton nicht.', '%s vergisst eine Textzeile.'],
        'guitar' => ['%s reißt eine Saite.', '%s greift daneben.'],
        'bass' => ['%s rumpelt am Bass daneben.'],
        'drums' => ['%s verliert einen Stick.', '%s schleppt hinterher.'],
        'keys' => ['%s erwischt die falsche Taste.'],
        'turntables' => ['Bei %s springt die Platte.'],
        'sax' => ['%s quietscht am Saxophon.'],
        'trumpet' => ['%s kiekst auf der Trompete.'],
        'violin' => ['%s kratzt auf der Geige.'],
        'flute' => ['%s verliert die Luft.'],
    ];

    public const HIGHLIGHTS = [
        'vocals' => ['%s hält den Schlusston ewig – Gänsehaut!', '%s singt sich die Seele aus dem Leib.'],
        'guitar' => ['Gitarrensolo von %s – die Menge tobt!', '%s spielt ein Riff, das hängen bleibt.'],
        'bass' => ['%s groovt, dass der Boden bebt.'],
        'drums' => ['%s haut ein Fill raus, das sitzt.', '%s treibt die Band nach vorn.'],
        'keys' => ['%s zaubert ein Solo auf den Tasten.'],
        'turntables' => ['%s scratcht wie ein Weltmeister.'],
        'sax' => ['%s spielt ein Solo, das unter die Haut geht.'],
        'trumpet' => ['%s schmettert ein Solo bis in die letzte Reihe.'],
        'violin' => ['%s spielt ein Geigensolo zum Dahinschmelzen.'],
        'flute' => ['%s verzaubert alle mit der Flöte.'],
    ];

    public const CROWD = [
        'high' => ['Das Publikum klatscht mit!', 'Die ersten Reihen singen mit.', 'Handys und Feuerzeuge gehen hoch.', 'Zugabe-Rufe!'],
        'mid' => ['Höflicher Applaus.', 'Ein paar Leute wippen mit.', 'Das Publikum hört aufmerksam zu.'],
        'low' => ['Vereinzelte Buhrufe.', 'Die Leute holen sich lieber was zu trinken.', 'Gähnen in der letzten Reihe.'],
    ];
}
