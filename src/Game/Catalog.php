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

    public const GENRES = [
        'pop' => ['name' => 'Pop', 'bpm' => 112],
        'rock' => ['name' => 'Rock', 'bpm' => 126],
        'metal' => ['name' => 'Metal', 'bpm' => 160],
        'punk' => ['name' => 'Punk', 'bpm' => 172],
        'jazz' => ['name' => 'Jazz', 'bpm' => 132],
        'elektro' => ['name' => 'Elektro', 'bpm' => 126],
        'hiphop' => ['name' => 'Hip-Hop', 'bpm' => 92],
        'folk' => ['name' => 'Folk', 'bpm' => 104],
    ];

    /** genres = typische Genres (Klischee). Ein Musiker außerhalb davon ist „ungewöhnlich“. */
    public const INSTRUMENTS = [
        'vocals' => ['name' => 'Gesang', 'icon' => '🎤', 'weight' => 20,
            'genres' => ['pop', 'rock', 'metal', 'punk', 'jazz', 'elektro', 'hiphop', 'folk'],
            'techniques' => ['falsett', 'growl', 'belting', 'rap', 'beatbox']],
        'guitar' => ['name' => 'Gitarre', 'icon' => '🎸', 'weight' => 18,
            'genres' => ['pop', 'rock', 'metal', 'punk', 'jazz', 'folk'],
            'techniques' => ['tapping', 'shredding', 'fingerstyle']],
        'bass' => ['name' => 'Bass', 'icon' => '🎸', 'weight' => 14,
            'genres' => ['pop', 'rock', 'metal', 'punk', 'jazz', 'hiphop', 'folk'],
            'techniques' => ['slap', 'walking']],
        'drums' => ['name' => 'Schlagzeug', 'icon' => '🥁', 'weight' => 14,
            'genres' => ['pop', 'rock', 'metal', 'punk', 'jazz', 'hiphop', 'folk'],
            'techniques' => ['doublebass', 'blastbeat']],
        'keys' => ['name' => 'Keyboard', 'icon' => '🎹', 'weight' => 10,
            'genres' => ['pop', 'rock', 'jazz', 'elektro', 'hiphop'],
            'techniques' => ['arpeggio', 'improvisation']],
        'synth' => ['name' => 'Synthesizer', 'icon' => '🎛️', 'weight' => 7,
            'genres' => ['pop', 'elektro', 'hiphop'],
            'techniques' => ['sounddesign', 'arpeggio']],
        'turntables' => ['name' => 'Turntables', 'icon' => '💿', 'weight' => 4,
            'genres' => ['hiphop', 'elektro'],
            'techniques' => ['scratching', 'juggling']],
        'sax' => ['name' => 'Saxophon', 'icon' => '🎷', 'weight' => 4,
            'genres' => ['jazz', 'pop', 'rock'],
            'techniques' => ['zirkular', 'improvisation']],
        'trumpet' => ['name' => 'Trompete', 'icon' => '🎺', 'weight' => 3,
            'genres' => ['jazz', 'pop', 'punk', 'folk'],
            'techniques' => ['highnotes', 'zirkular']],
        'violin' => ['name' => 'Geige', 'icon' => '🎻', 'weight' => 3,
            'genres' => ['folk', 'pop', 'jazz'],
            'techniques' => ['flageolett', 'improvisation']],
        'flute' => ['name' => 'Flöte', 'icon' => '🪈', 'weight' => 3,
            'genres' => ['folk', 'jazz', 'pop'],
            'techniques' => ['zirkular', 'flatterzunge']],
    ];

    /** Diese Instrumente üben im Tonhöhen-Spiel statt im Rhythmus-Spiel. */
    public const PITCH_INSTRUMENTS = ['vocals', 'violin'];

    /**
     * Fähigkeiten: Ein Musiker hat so viele, wie seine Können-Stufe angibt (höchstens 3).
     * technique = an ein Instrument gebunden (siehe INSTRUMENTS[...]['techniques']).
     */
    public const ABILITIES = [
        'talent' => ['name' => 'Talent', 'icon' => '🌱', 'desc' => 'Können wächst schneller.'],
        'ausstrahlung' => ['name' => 'Ausstrahlung', 'icon' => '✨', 'desc' => 'Präsenz wächst schneller.'],
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

    /** Paare, die sich reiben. */
    public const CLASHES = [
        ['diva', 'diva'],
        ['rampensau', 'rampensau'],
        ['perfektionist', 'chaot'],
        ['ehrgeizig', 'gelassen'],
    ];

    /** active = wirkt schon im Kern; die anderen brauchen spätere Features. */
    public const MANAGER_SKILLS = [
        'verhandler' => ['name' => 'Verhandler', 'desc' => 'Gigs werden besser bezahlt.', 'active' => true],
        'prprofi' => ['name' => 'PR-Profi', 'desc' => 'Mehr Ruhm bei Auftritten.', 'active' => true],
        'scout' => ['name' => 'Scout', 'desc' => 'Bessere Chancen beim Pack-Öffnen.', 'active' => true],
        'tourplaner' => ['name' => 'Tourplaner', 'desc' => 'Mehr und passendere Locations bei Tourneen.', 'active' => false],
        'netzwerker' => ['name' => 'Netzwerker', 'desc' => 'Zugang zu ausgeschriebenen und exklusiven Gigs.', 'active' => false],
    ];

    /**
     * Instrumentenmodelle. klang = maximaler Sound-Bonus, spielbarkeit = hoch heißt leicht spielbar
     * (entfaltet sich schon bei wenig Können), design = Bonus auf Ruhm.
     */
    public const GEAR = [
        'mic_basic' => ['type' => 'vocals', 'name' => 'Standardmikro', 'rarity' => 'common', 'klang' => 10, 'spielbarkeit' => 90, 'design' => 5],
        'mic_condenser' => ['type' => 'vocals', 'name' => 'Studio-Kondensator', 'rarity' => 'rare', 'klang' => 35, 'spielbarkeit' => 70, 'design' => 15],
        'mic_tube' => ['type' => 'vocals', 'name' => 'Vintage-Röhrenmikro', 'rarity' => 'epic', 'klang' => 60, 'spielbarkeit' => 55, 'design' => 45],
        'mic_gold' => ['type' => 'vocals', 'name' => 'Goldkehlchen', 'rarity' => 'legendary', 'klang' => 86, 'spielbarkeit' => 45, 'design' => 85],

        'gtr_school' => ['type' => 'guitar', 'name' => 'Schülergitarre', 'rarity' => 'common', 'klang' => 10, 'spielbarkeit' => 85, 'design' => 5],
        'gtr_strato' => ['type' => 'guitar', 'name' => 'Strato Standard', 'rarity' => 'rare', 'klang' => 36, 'spielbarkeit' => 68, 'design' => 25],
        'gtr_monster' => ['type' => 'guitar', 'name' => 'Monster-Axt', 'rarity' => 'epic', 'klang' => 58, 'spielbarkeit' => 50, 'design' => 75],
        'gtr_sunburst' => ['type' => 'guitar', 'name' => 'Sunburst ’59', 'rarity' => 'legendary', 'klang' => 88, 'spielbarkeit' => 38, 'design' => 80],

        'bass_entry' => ['type' => 'bass', 'name' => 'Einsteigerbass', 'rarity' => 'common', 'klang' => 10, 'spielbarkeit' => 85, 'design' => 5],
        'bass_jazz' => ['type' => 'bass', 'name' => 'Jazzbass Deluxe', 'rarity' => 'rare', 'klang' => 38, 'spielbarkeit' => 66, 'design' => 20],
        'bass_abyss' => ['type' => 'bass', 'name' => 'Fünfsaiter Abyss', 'rarity' => 'epic', 'klang' => 62, 'spielbarkeit' => 48, 'design' => 45],
        'bass_deep' => ['type' => 'bass', 'name' => 'Tiefseebass', 'rarity' => 'legendary', 'klang' => 85, 'spielbarkeit' => 40, 'design' => 75],

        'drm_rehearsal' => ['type' => 'drums', 'name' => 'Proberaum-Set', 'rarity' => 'common', 'klang' => 10, 'spielbarkeit' => 85, 'design' => 5],
        'drm_maple' => ['type' => 'drums', 'name' => 'Ahorn-Kit', 'rarity' => 'rare', 'klang' => 36, 'spielbarkeit' => 68, 'design' => 25],
        'drm_double' => ['type' => 'drums', 'name' => 'Doppelfuß-Monster', 'rarity' => 'epic', 'klang' => 60, 'spielbarkeit' => 48, 'design' => 60],
        'drm_thunder' => ['type' => 'drums', 'name' => 'Donnerkuppel', 'rarity' => 'legendary', 'klang' => 87, 'spielbarkeit' => 40, 'design' => 85],

        'key_home' => ['type' => 'keys', 'name' => 'Heimkeyboard', 'rarity' => 'common', 'klang' => 8, 'spielbarkeit' => 90, 'design' => 5],
        'key_stage' => ['type' => 'keys', 'name' => 'Stage Piano', 'rarity' => 'rare', 'klang' => 36, 'spielbarkeit' => 70, 'design' => 20],
        'key_organ' => ['type' => 'keys', 'name' => 'Röhrenorgel B3', 'rarity' => 'epic', 'klang' => 62, 'spielbarkeit' => 50, 'design' => 50],
        'key_grand' => ['type' => 'keys', 'name' => 'Bühnenflügel Nocturne', 'rarity' => 'legendary', 'klang' => 88, 'spielbarkeit' => 40, 'design' => 80],

        'syn_mini' => ['type' => 'synth', 'name' => 'Mini-Synth', 'rarity' => 'common', 'klang' => 10, 'spielbarkeit' => 85, 'design' => 10],
        'syn_poly' => ['type' => 'synth', 'name' => 'Polysynth 80', 'rarity' => 'rare', 'klang' => 36, 'spielbarkeit' => 65, 'design' => 30],
        'syn_modular' => ['type' => 'synth', 'name' => 'Modular-Wand', 'rarity' => 'epic', 'klang' => 64, 'spielbarkeit' => 42, 'design' => 65],
        'syn_neon' => ['type' => 'synth', 'name' => 'Neonwelle Prime', 'rarity' => 'legendary', 'klang' => 86, 'spielbarkeit' => 40, 'design' => 90],

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
        'f' => ['Lena', 'Mia', 'Zoe', 'Nora', 'Ida', 'Lou', 'Maja', 'Elif', 'Aylin', 'Jana', 'Kira', 'Romy', 'Tessa', 'Nina',
            'Paula', 'Greta', 'Yara', 'Selin', 'Amara', 'Ronja', 'Frieda', 'Leni', 'Mara', 'Ella', 'Juna', 'Svea', 'Malin',
            'Nele', 'Ivy', 'Rosa', 'Carla', 'Hanna', 'Leyla', 'Ines', 'Alma', 'Mila', 'Thea', 'Esra', 'Sanna', 'Fenja'],
        'm' => ['Finn', 'Jonas', 'Emil', 'Mats', 'Ole', 'Ben', 'Luca', 'Noah', 'Arda', 'Milan', 'Theo', 'Jannik', 'Bo', 'Can',
            'Malik', 'Tarek', 'Levi', 'Henri', 'Matteo', 'Kian', 'Elias', 'Anton', 'Jasper', 'Rafael', 'Linus', 'Juri', 'Nils',
            'Oskar', 'Yusuf', 'Pablo', 'Kofi', 'Sami', 'Joscha', 'Ruben', 'Vince', 'Lasse', 'Timo', 'Aaron', 'Enzo', 'Moritz'],
        'd' => ['Kim', 'Robin', 'Charlie', 'Alex', 'Sascha', 'Noa', 'Sky', 'Jo', 'Toni', 'Kai', 'Mika', 'Sam', 'Ari', 'Rio',
            'Quinn', 'Luan'],
    ];

    public const LAST_NAMES = ['Berger', 'Kowalski', 'Yılmaz', 'Novak', 'Lindqvist', 'Okafor', 'Moreau', 'Petrov', 'Schulz',
        'Haas', 'Brandt', 'Falk', 'Vogel', 'Engel', 'Stern', 'Winter', 'Sommer', 'Krüger', 'Rossi', 'Silva', 'Nakamura',
        'Larsen', 'Dumont', 'Kaya', 'Demir', 'Andersen', 'Hoffmann', 'Wolff', 'Fuchs', 'Lorenz', 'Kraft', 'Blum', 'Roth',
        'Adler', 'Seidel', 'Voss', 'Graf', 'Costa', 'Santos', 'Nguyen', 'Tran', 'Park', 'Ortega', 'Mendes', 'Eriksen',
        'Funke', 'Donner', 'Hertz', 'Kling', 'Marlow', 'Quast', 'Reyes', 'Asante', 'Hayashi', 'Oyelaran', 'Brenner'];

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
        'synth' => ['Der Sound von %s klingt plötzlich schief.'],
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
        'synth' => ['%s lässt einen fetten Drop los.'],
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
