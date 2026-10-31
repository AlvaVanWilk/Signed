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
            'techniques' => ['falsett', 'belting', 'growl', 'scream', 'shouting', 'rap', 'doubletime', 'beatbox', 'vocalchops', 'vocoder', 'scat', 'crooning', 'jodeln', 'harmonie']],
        'guitar' => ['name' => 'Gitarre', 'at' => 'an der Gitarre', 'icon' => '🎸', 'weight' => 18,
            'genres' => ['pop', 'rock', 'metal', 'punk', 'jazz', 'folk'],
            'techniques' => ['tapping', 'shredding', 'palmmute', 'downpicking', 'powerchords', 'slide', 'fingerstyle', 'rasgueado', 'chordmelody', 'funkstrum', 'picking']],
        'bass' => ['name' => 'Bass', 'at' => 'am Bass', 'icon' => '🎸', 'weight' => 14,
            'genres' => ['pop', 'rock', 'metal', 'punk', 'jazz', 'hiphop', 'folk'],
            'techniques' => ['slap', 'octaves', 'sub808', 'ghostbass', 'walking', 'fretless', 'basstapping', 'gallop', 'plectrum', 'eighths', 'downtuned', 'drone']],
        'drums' => ['name' => 'Schlagzeug', 'at' => 'am Schlagzeug', 'icon' => '🥁', 'weight' => 14,
            'genres' => ['pop', 'rock', 'metal', 'punk', 'jazz', 'hiphop', 'folk'],
            'techniques' => ['doublebass', 'blastbeat', 'dbeat', 'fills', 'polyrhythm', 'swing', 'brushes', 'shuffle', 'cajon', 'fourfloor', 'breakbeat', 'traphats', 'ghostsnare']],
        'keys' => ['name' => 'Keyboard', 'at' => 'am Keyboard', 'icon' => '🎹', 'weight' => 15,
            'genres' => ['pop', 'rock', 'jazz', 'elektro', 'hiphop'],
            'techniques' => ['arpeggio', 'sounddesign', 'synthlead', 'stabs', 'lofi', 'ballad', 'voicings', 'organ', 'boogie', 'improvisation']],
        'turntables' => ['name' => 'Turntables', 'at' => 'an den Turntables', 'icon' => '💿', 'weight' => 4,
            'genres' => ['hiphop', 'elektro'],
            'techniques' => ['scratching', 'juggling', 'beatmatching', 'cutting', 'transitions']],
        'sax' => ['name' => 'Saxophon', 'at' => 'am Saxophon', 'icon' => '🎷', 'weight' => 4,
            'genres' => ['jazz', 'pop', 'rock'],
            'techniques' => ['improvisation', 'zirkular', 'subtone', 'altissimo', 'honking', 'saxgrowl']],
        'trumpet' => ['name' => 'Trompete', 'at' => 'an der Trompete', 'icon' => '🎺', 'weight' => 3,
            'genres' => ['jazz', 'pop', 'punk', 'folk'],
            'techniques' => ['zirkular', 'highnotes', 'mute', 'shake', 'ska', 'fanfare', 'mariachi']],
        'violin' => ['name' => 'Geige', 'at' => 'an der Geige', 'icon' => '🎻', 'weight' => 3,
            'genres' => ['folk', 'pop', 'jazz'],
            'techniques' => ['improvisation', 'flageolett', 'doublestops', 'pizzicato', 'tremolo']],
        'flute' => ['name' => 'Flöte', 'at' => 'an der Flöte', 'icon' => '🪈', 'weight' => 3,
            'genres' => ['folk', 'jazz', 'pop'],
            'techniques' => ['zirkular', 'flatterzunge', 'ornaments', 'multiphonics', 'humflute']],
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
        'folk' => [[['guitar'], 1], [['violin', 'flute'], 1]],
    ];

    /** Diese Instrumente üben im Tonhöhen-Spiel statt im Rhythmus-Spiel. */
    public const PITCH_INSTRUMENTS = ['vocals', 'violin'];
    /** In diesen Genres wird gerappt: Gesang übt dann im Rhythmus-Spiel (Silben im Takt) statt nach Tonhöhe. */
    public const RAP_GENRES = ['hiphop'];

    /**
     * Spieltechniken (früher „Fähigkeiten“): Jeder Musiker hat genau eine, von Anfang an (man hört sie in der Einsendung).
     * Sie gehört zu einem Instrument (INSTRUMENTS[…]['techniques']) und zu zwei Genres; man bekommt eine, deren Genre man mag,
     * und nur in einer Band dieses Genres bringt sie Sound (Balance::TECHNIQUE_SOUND). Je Instrument und Genre gibt es mindestens drei.
     */
    public const ABILITIES = [
        'falsett' => ['name' => 'Falsett', 'icon' => '🪶', 'technique' => true, 'genres' => ['pop', 'jazz']],
        'belting' => ['name' => 'Belting', 'icon' => '📣', 'technique' => true, 'genres' => ['pop', 'rock']],
        'growl' => ['name' => 'Growl', 'icon' => '🐺', 'technique' => true, 'genres' => ['metal', 'punk']],
        'scream' => ['name' => 'Scream', 'icon' => '😱', 'technique' => true, 'genres' => ['metal', 'punk']],
        'shouting' => ['name' => 'Shouting', 'icon' => '🗯️', 'technique' => true, 'genres' => ['punk', 'rock']],
        'rap' => ['name' => 'Rap', 'icon' => '🗣️', 'technique' => true, 'genres' => ['hiphop', 'pop']],
        'doubletime' => ['name' => 'Doubletime-Rap', 'icon' => '⏩', 'technique' => true, 'genres' => ['hiphop', 'elektro']],
        'beatbox' => ['name' => 'Beatbox', 'icon' => '👄', 'technique' => true, 'genres' => ['hiphop', 'elektro']],
        'vocalchops' => ['name' => 'Vocal Chops', 'icon' => '✂️', 'technique' => true, 'genres' => ['elektro', 'hiphop']],
        'vocoder' => ['name' => 'Vocoder', 'icon' => '🤖', 'technique' => true, 'genres' => ['elektro', 'pop']],
        'scat' => ['name' => 'Scat', 'icon' => '🎶', 'technique' => true, 'genres' => ['jazz', 'pop']],
        'crooning' => ['name' => 'Crooning', 'icon' => '🌙', 'technique' => true, 'genres' => ['jazz', 'folk']],
        'jodeln' => ['name' => 'Jodeln', 'icon' => '🏔️', 'technique' => true, 'genres' => ['folk', 'metal']],
        'harmonie' => ['name' => 'Mehrstimmiger Gesang', 'icon' => '🎼', 'technique' => true, 'genres' => ['folk', 'rock']],
        'tapping' => ['name' => 'Tapping', 'icon' => '👆', 'technique' => true, 'genres' => ['metal', 'rock']],
        'shredding' => ['name' => 'Shredding', 'icon' => '⚡', 'technique' => true, 'genres' => ['metal', 'rock']],
        'palmmute' => ['name' => 'Palm Muting', 'icon' => '✋', 'technique' => true, 'genres' => ['metal', 'punk']],
        'downpicking' => ['name' => 'Downpicking', 'icon' => '⬇️', 'technique' => true, 'genres' => ['punk', 'metal']],
        'powerchords' => ['name' => 'Powerchords', 'icon' => '💪', 'technique' => true, 'genres' => ['punk', 'rock']],
        'slide' => ['name' => 'Slide', 'icon' => '🧪', 'technique' => true, 'genres' => ['folk', 'rock']],
        'fingerstyle' => ['name' => 'Fingerstyle', 'icon' => '🤌', 'technique' => true, 'genres' => ['folk', 'jazz']],
        'rasgueado' => ['name' => 'Rasgueado', 'icon' => '🌹', 'technique' => true, 'genres' => ['folk', 'jazz']],
        'chordmelody' => ['name' => 'Akkordmelodie', 'icon' => '🎵', 'technique' => true, 'genres' => ['jazz', 'pop']],
        'funkstrum' => ['name' => 'Funk-Anschlag', 'icon' => '🕺', 'technique' => true, 'genres' => ['pop', 'jazz']],
        'picking' => ['name' => 'Picking', 'icon' => '🪕', 'technique' => true, 'genres' => ['pop', 'folk']],
        'slap' => ['name' => 'Slap', 'icon' => '👋', 'technique' => true, 'genres' => ['pop', 'rock']],
        'octaves' => ['name' => 'Oktavläufe', 'icon' => '🪜', 'technique' => true, 'genres' => ['pop', 'hiphop']],
        'sub808' => ['name' => '808-Bass', 'icon' => '🔊', 'technique' => true, 'genres' => ['hiphop', 'pop']],
        'ghostbass' => ['name' => 'Ghost Notes', 'icon' => '👻', 'technique' => true, 'genres' => ['hiphop', 'jazz']],
        'walking' => ['name' => 'Walking Bass', 'icon' => '🚶', 'technique' => true, 'genres' => ['jazz', 'folk']],
        'fretless' => ['name' => 'Bundloser Bass', 'icon' => '🌊', 'technique' => true, 'genres' => ['jazz', 'folk']],
        'basstapping' => ['name' => 'Bass-Tapping', 'icon' => '👆', 'technique' => true, 'genres' => ['metal', 'jazz']],
        'gallop' => ['name' => 'Galopp', 'icon' => '🐎', 'technique' => true, 'genres' => ['metal', 'punk']],
        'plectrum' => ['name' => 'Plektrum', 'icon' => '🔺', 'technique' => true, 'genres' => ['punk', 'rock']],
        'eighths' => ['name' => 'Durchgehende Achtel', 'icon' => '🚂', 'technique' => true, 'genres' => ['punk', 'rock']],
        'downtuned' => ['name' => 'Tiefe Stimmung', 'icon' => '⚓', 'technique' => true, 'genres' => ['metal', 'rock']],
        'drone' => ['name' => 'Bordun', 'icon' => '🐝', 'technique' => true, 'genres' => ['folk', 'rock']],
        'doublebass' => ['name' => 'Double Bass', 'icon' => '🦶', 'technique' => true, 'genres' => ['metal', 'punk']],
        'blastbeat' => ['name' => 'Blastbeat', 'icon' => '💥', 'technique' => true, 'genres' => ['metal', 'punk']],
        'dbeat' => ['name' => 'D-Beat', 'icon' => '🔨', 'technique' => true, 'genres' => ['punk', 'rock']],
        'fills' => ['name' => 'Wirbel-Fills', 'icon' => '🌀', 'technique' => true, 'genres' => ['rock', 'metal']],
        'polyrhythm' => ['name' => 'Polyrhythmik', 'icon' => '🔀', 'technique' => true, 'genres' => ['jazz', 'metal']],
        'swing' => ['name' => 'Swing', 'icon' => '🎩', 'technique' => true, 'genres' => ['jazz', 'folk']],
        'brushes' => ['name' => 'Besen', 'icon' => '🧹', 'technique' => true, 'genres' => ['jazz', 'folk']],
        'shuffle' => ['name' => 'Shuffle', 'icon' => '🃏', 'technique' => true, 'genres' => ['folk', 'rock']],
        'cajon' => ['name' => 'Cajón', 'icon' => '📦', 'technique' => true, 'genres' => ['folk', 'pop']],
        'fourfloor' => ['name' => 'Four on the Floor', 'icon' => '🟦', 'technique' => true, 'genres' => ['pop', 'rock']],
        'breakbeat' => ['name' => 'Breakbeat', 'icon' => '🧱', 'technique' => true, 'genres' => ['hiphop', 'pop']],
        'traphats' => ['name' => 'Trap-Hi-Hats', 'icon' => '🎩', 'technique' => true, 'genres' => ['hiphop', 'pop']],
        'ghostsnare' => ['name' => 'Ghost Notes', 'icon' => '👻', 'technique' => true, 'genres' => ['hiphop', 'jazz']],
        'arpeggio' => ['name' => 'Arpeggio-Läufe', 'icon' => '🌊', 'technique' => true, 'genres' => ['elektro', 'pop']],
        'sounddesign' => ['name' => 'Sounddesign', 'icon' => '🎛️', 'technique' => true, 'genres' => ['elektro', 'hiphop']],
        'synthlead' => ['name' => 'Synth-Lead', 'icon' => '🚀', 'technique' => true, 'genres' => ['elektro', 'rock']],
        'stabs' => ['name' => 'Stabs', 'icon' => '🔪', 'technique' => true, 'genres' => ['hiphop', 'elektro']],
        'lofi' => ['name' => 'Lo-Fi-Akkorde', 'icon' => '☕', 'technique' => true, 'genres' => ['hiphop', 'pop']],
        'ballad' => ['name' => 'Klavierballade', 'icon' => '🕯️', 'technique' => true, 'genres' => ['pop', 'rock']],
        'voicings' => ['name' => 'Jazz-Voicings', 'icon' => '🍸', 'technique' => true, 'genres' => ['jazz', 'pop']],
        'organ' => ['name' => 'Orgel-Glissando', 'icon' => '⛪', 'technique' => true, 'genres' => ['rock', 'jazz']],
        'boogie' => ['name' => 'Boogie', 'icon' => '👞', 'technique' => true, 'genres' => ['jazz', 'rock']],
        'improvisation' => ['name' => 'Improvisation', 'icon' => '🎲', 'technique' => true, 'genres' => ['jazz', 'folk']],
        'scratching' => ['name' => 'Scratching', 'icon' => '🌀', 'technique' => true, 'genres' => ['hiphop', 'elektro']],
        'juggling' => ['name' => 'Beat Juggling', 'icon' => '🤹', 'technique' => true, 'genres' => ['hiphop', 'elektro']],
        'beatmatching' => ['name' => 'Beatmatching', 'icon' => '🎯', 'technique' => true, 'genres' => ['elektro', 'hiphop']],
        'cutting' => ['name' => 'Cutting', 'icon' => '✂️', 'technique' => true, 'genres' => ['hiphop', 'elektro']],
        'transitions' => ['name' => 'Fließende Übergänge', 'icon' => '🌈', 'technique' => true, 'genres' => ['elektro', 'hiphop']],
        'zirkular' => ['name' => 'Zirkularatmung', 'icon' => '🔄', 'technique' => true, 'genres' => ['jazz', 'folk']],
        'subtone' => ['name' => 'Subtone', 'icon' => '🌫️', 'technique' => true, 'genres' => ['jazz', 'pop']],
        'altissimo' => ['name' => 'Altissimo', 'icon' => '🦅', 'technique' => true, 'genres' => ['pop', 'rock']],
        'honking' => ['name' => 'Honking', 'icon' => '📯', 'technique' => true, 'genres' => ['rock', 'pop']],
        'saxgrowl' => ['name' => 'Sax-Growl', 'icon' => '🐻', 'technique' => true, 'genres' => ['rock', 'jazz']],
        'highnotes' => ['name' => 'High Notes', 'icon' => '🏔️', 'technique' => true, 'genres' => ['jazz', 'pop']],
        'mute' => ['name' => 'Dämpfer', 'icon' => '🔇', 'technique' => true, 'genres' => ['jazz', 'pop']],
        'shake' => ['name' => 'Shake', 'icon' => '〰️', 'technique' => true, 'genres' => ['jazz', 'punk']],
        'ska' => ['name' => 'Ska-Stöße', 'icon' => '🏁', 'technique' => true, 'genres' => ['punk', 'pop']],
        'fanfare' => ['name' => 'Fanfare', 'icon' => '🎺', 'technique' => true, 'genres' => ['punk', 'folk']],
        'mariachi' => ['name' => 'Mariachi-Vibrato', 'icon' => '🌵', 'technique' => true, 'genres' => ['folk', 'pop']],
        'flageolett' => ['name' => 'Flageolett', 'icon' => '🔔', 'technique' => true, 'genres' => ['folk', 'jazz']],
        'doublestops' => ['name' => 'Doppelgriffe', 'icon' => '✌️', 'technique' => true, 'genres' => ['folk', 'pop']],
        'pizzicato' => ['name' => 'Pizzicato', 'icon' => '🤏', 'technique' => true, 'genres' => ['pop', 'jazz']],
        'tremolo' => ['name' => 'Tremolo', 'icon' => '💫', 'technique' => true, 'genres' => ['pop', 'folk']],
        'flatterzunge' => ['name' => 'Flatterzunge', 'icon' => '🦋', 'technique' => true, 'genres' => ['jazz', 'folk']],
        'ornaments' => ['name' => 'Verzierungen', 'icon' => '🌸', 'technique' => true, 'genres' => ['folk', 'pop']],
        'multiphonics' => ['name' => 'Mehrklänge', 'icon' => '🔮', 'technique' => true, 'genres' => ['jazz', 'pop']],
        'humflute' => ['name' => 'Summflöte', 'icon' => '🐝', 'technique' => true, 'genres' => ['pop', 'jazz']],
    ];

    /** Namen der Stufen: Können-Leiter und Bekanntheits-Leiter (0–3). */
    public const LEVEL_NAMES = ['Anfänger', 'Fortgeschritten', 'Profi', 'Virtuose'];
    /** Für Sätze wie „Ada ist an der Gitarre jetzt Profi“. */
    public const LEVEL_WORDS = ['Anfänger', 'fortgeschritten', 'Profi', 'virtuos'];
    public const FAME_NAMES = ['Unbekannt', 'Lokal bekannt', 'Szenegröße', 'Star'];

    /** Was eine Spezialtechnik bewirkt (gilt für alle Techniken). */
    public const TECHNIQUE_DESC = 'Spezialtechnik: in einer %s- oder %s-Band +%d %% Sound, live etwas mehr Glanzmomente.';

    /**
     * Charakterzüge: Jeder hat eine Stärke und eine Schwäche, je nach Lage (siehe docs/label-konzept.md, Charakterzüge).
     * Die Schlüssel bleiben die alten (gespeichert in musicians.personality), nur die Namen sind jetzt Eigenschaftswörter.
     */
    public const PERSONALITIES = [
        'ehrgeizig' => 'Ehrgeizig',
        'gelassen' => 'Gemütlich',
        'perfektionist' => 'Perfektionistisch',
        'chaot' => 'Impulsiv',
        'rampensau' => 'Extrovertiert',
        'diva' => 'Egozentrisch',
        'teamplayer' => 'Kooperativ',
        'ruhepol' => 'Ausgeglichen',
        'partytier' => 'Gesellig',
        'kreativ' => 'Kreativ',
        'technisch' => 'Technisch',
    ];

    /**
     * Anordnungen nach Charakter: +1 mag er, −1 mag er nicht, fehlt = egal (siehe docs/label-konzept.md).
     * Jeder Charakter mag mindestens eine Anordnung, damit eine rote Band immer einen Ausweg hat.
     */
    public const LIKES = [
        'ehrgeizig' => ['rehearsal' => 1, 'record' => 1, 'gig' => 1, 'busking' => -1, 'battle' => 1, 'festival' => 1, 'tour' => 1],
        'gelassen' => ['record' => 1, 'gig' => -1, 'busking' => -1, 'battle' => -1, 'festival' => -1, 'tour' => -1],
        'perfektionist' => ['rehearsal' => 1, 'record' => 1, 'busking' => -1, 'openstage' => -1],
        'chaot' => ['gig' => 1, 'openstage' => 1, 'busking' => 1, 'rehearsal' => -1, 'festival' => 1, 'tour' => 1],
        'rampensau' => ['gig' => 1, 'openstage' => 1, 'busking' => 1, 'interview' => 1, 'battle' => 1, 'festival' => 1, 'tour' => 1],
        'diva' => ['gig' => 1, 'record' => 1, 'busking' => -1, 'openstage' => -1, 'interview' => 1, 'battle' => 1, 'festival' => 1],
        'teamplayer' => ['rehearsal' => 1, 'record' => 1, 'interview' => -1],
        'ruhepol' => ['rehearsal' => 1, 'record' => 1, 'tour' => -1],
        'partytier' => ['gig' => 1, 'openstage' => 1, 'busking' => 1, 'rehearsal' => -1, 'festival' => 1, 'tour' => 1],
        'kreativ' => ['record' => 1, 'openstage' => 1, 'rehearsal' => -1],
        'technisch' => ['rehearsal' => 1, 'record' => 1],
    ];

    /** Wer bei Interview-Anfragen fragt, je Bekanntheitsstufe; share = Anteil des Weges zur nächsten Stufe (Balance::INTERVIEW). */
    /** Anfragen im neuen Kern (Balance::CORE_EVENTS['requests']): Name und was der Musiker dort macht. */
    public const REQUESTS = [
        'interview' => ['name' => 'Interview', 'text' => 'Interview'],
        'photos' => ['name' => 'Fotoserie', 'text' => 'Fotoserie'],
        'article' => ['name' => 'Artikel', 'text' => 'Artikel über die Band'],
        'report' => ['name' => 'Reportage', 'text' => 'Reportage'],
        'ad' => ['name' => 'Werbung', 'text' => 'Werbung'],
    ];

    public const INTERVIEW_MEDIA = [
        0 => [
            ['name' => 'Schülerzeitung „Pausenhof“', 'share' => 0.08], ['name' => 'Stadtteil-Blog „Um die Ecke“', 'share' => 0.10],
            ['name' => 'Uni-Radio „Welle 7“', 'share' => 0.12], ['name' => 'Gemeindeblatt', 'share' => 0.08],
        ],
        1 => [
            ['name' => 'Stadtmagazin „Szene“', 'share' => 0.10], ['name' => 'Lokalradio „Radio Mitte“', 'share' => 0.12],
            ['name' => 'Musik-Podcast „Proberaum“', 'share' => 0.10], ['name' => 'Tageszeitung (Kulturteil)', 'share' => 0.12],
        ],
        2 => [
            ['name' => 'Musikzeitschrift „Klangwelt“', 'share' => 0.10], ['name' => 'Radio „Pop 1“', 'share' => 0.12],
            ['name' => 'Online-Magazin „Lautstark“', 'share' => 0.10], ['name' => 'Musiksender „Kanal M“', 'share' => 0.14],
        ],
        3 => [
            ['name' => 'Fernsehen: Abendshow', 'share' => 0.12], ['name' => 'Großes Magazin „Stern der Woche“', 'share' => 0.10],
            ['name' => 'Late-Night-Show', 'share' => 0.14], ['name' => 'Weltweiter Musikpodcast', 'share' => 0.12],
        ],
    ];
    /** Auf Tournee fragen die Medien im Land (%s = Stadt), je Bekanntheitsstufe wie INTERVIEW_MEDIA. */
    public const INTERVIEW_MEDIA_ABROAD = [
        0 => [['name' => 'Studentenradio in %s', 'share' => 0.10], ['name' => 'Stadtblog aus %s', 'share' => 0.10]],
        1 => [['name' => 'Stadtmagazin in %s', 'share' => 0.11], ['name' => 'Lokalradio in %s', 'share' => 0.12]],
        2 => [['name' => 'Musikzeitschrift aus %s', 'share' => 0.11], ['name' => 'Radiosender in %s', 'share' => 0.13]],
        3 => [['name' => 'Fernsehshow in %s', 'share' => 0.13], ['name' => 'Großes Magazin aus %s', 'share' => 0.11]],
    ];

    /** Leute, die sich beim Label bewerben: Name der Rolle und der Stufen. */
    public const STAFF_ROLES = [
        'assistant' => ['name' => 'Assistenz', 'tiers' => [1 => 'Assistenz'], 'tasks' => ['none' => 'Kein Arbeitsgebiet', 'post' => 'Post', 'demos' => 'Kassetten', 'office' => 'Büro']],
        'intern' => ['name' => 'Praktikum', 'tiers' => [1 => 'Praktikum']],
        'scout' => ['name' => 'Scout', 'tiers' => [1 => 'Scout', 2 => 'Erfahrener Scout', 3 => 'Top-Scout']],
        'manager' => ['name' => 'Manager', 'tiers' => [1 => 'Manager', 2 => 'Erfahrener Manager', 3 => 'Star-Manager']],
        'teacher' => ['name' => 'Privatunterricht', 'tiers' => [1 => 'Lehrkraft', 2 => 'Profi-Lehrkraft', 3 => 'Meister-Lehrkraft']],
        'coach' => ['name' => 'Songwriting-Coach', 'tiers' => [1 => 'Songwriting-Coach', 2 => 'Erfahrener Coach', 3 => 'Hit-Coach']],
    ];

    /** Woran man einen bisher unbekannten Charakterzug bemerkt (%s = Vorname). */
    public const TRAIT_REVEAL = [
        'like' => '%s hatte richtig Lust auf %s.',
        'dislike' => '%s hatte gar keine Lust auf %s.',
        'restless' => '%s will endlich wieder auf die Bühne.',
        'stagnant' => '%s will endlich eine neue Platte machen.',
        'calm' => '%s bleibt ruhig, obwohl so viele Songs liegen.',
        'stageName' => '%s wünscht sich einen Künstlernamen.',
        'unhappy' => '%s ist unzufrieden und sagt, was fehlt.',
        'loud' => '%s ist richtig unzufrieden und sagt laut, was fehlt.',
        'songs' => '%s steckt voller Ideen für neue Songs.',
        'noSongs' => '%s schreibt bei den Songs nicht mit, spielt aber sehr genau.',
        'lust' => '%s hatte bei der Probe schnell keine Lust mehr.',
        'rehearsal' => 'Bei der Probe hat sich %s von einer neuen Seite gezeigt.',
        'manager' => '%2$s kennt %1$s inzwischen gut.',
    ];
    /** Anordnungen im Satz („Lust auf …“). */
    /**
     * Länder für Tourneen (Tours): Region (Balance::TOUR['regions']), Städte (so viele Auftritte gibt es dort, die erste ist die größte und
     * wird auf der Welttournee bespielt), Lieblings-Genre des Publikums, Lage der ersten Stadt (Breite, Länge) für Karte und Entfernung;
     * `in`, wo „in …“ anders heißt. Jedes Land hat eine eigene Bühne (stage-world.js).
     */
    public const COUNTRIES = [
        'de' => ['name' => 'Deutschland', 'region' => 'europa', 'cities' => ['Berlin', 'München', 'Köln'], 'genre' => 'elektro', 'lat' => 52.5, 'lon' => 13.4],
        'gb' => ['name' => 'England', 'region' => 'europa', 'cities' => ['London', 'Manchester', 'Birmingham'], 'genre' => 'rock', 'lat' => 51.5, 'lon' => -0.1],
        'fr' => ['name' => 'Frankreich', 'region' => 'europa', 'cities' => ['Paris', 'Lyon', 'Marseille'], 'genre' => 'pop', 'lat' => 48.9, 'lon' => 2.4],
        'es' => ['name' => 'Spanien', 'region' => 'europa', 'cities' => ['Madrid', 'Barcelona'], 'genre' => 'folk', 'lat' => 40.4, 'lon' => -3.7],
        'it' => ['name' => 'Italien', 'region' => 'europa', 'cities' => ['Rom', 'Mailand'], 'genre' => 'jazz', 'lat' => 41.9, 'lon' => 12.5],
        'nl' => ['name' => 'Niederlande', 'region' => 'europa', 'cities' => ['Amsterdam', 'Rotterdam'], 'genre' => 'elektro', 'lat' => 52.4, 'lon' => 4.9, 'in' => 'in den Niederlanden'],
        'ie' => ['name' => 'Irland', 'region' => 'europa', 'cities' => ['Dublin'], 'genre' => 'folk', 'lat' => 53.3, 'lon' => -6.3],
        'se' => ['name' => 'Schweden', 'region' => 'europa', 'cities' => ['Stockholm'], 'genre' => 'pop', 'lat' => 59.3, 'lon' => 18.1],
        'no' => ['name' => 'Norwegen', 'region' => 'europa', 'cities' => ['Oslo'], 'genre' => 'metal', 'lat' => 59.9, 'lon' => 10.7],
        'fi' => ['name' => 'Finnland', 'region' => 'europa', 'cities' => ['Helsinki'], 'genre' => 'metal', 'lat' => 60.2, 'lon' => 24.9],
        'us' => ['name' => 'USA', 'region' => 'nordamerika', 'cities' => ['New York', 'Los Angeles', 'Chicago', 'Nashville', 'Atlanta', 'Las Vegas'], 'genre' => 'hiphop', 'lat' => 40.7, 'lon' => -74.0, 'in' => 'in den USA'],
        'ca' => ['name' => 'Kanada', 'region' => 'nordamerika', 'cities' => ['Toronto', 'Montreal', 'Vancouver'], 'genre' => 'punk', 'lat' => 43.7, 'lon' => -79.4],
        'mx' => ['name' => 'Mexiko', 'region' => 'nordamerika', 'cities' => ['Mexiko-Stadt', 'Guadalajara'], 'genre' => 'folk', 'lat' => 19.4, 'lon' => -99.1],
        'br' => ['name' => 'Brasilien', 'region' => 'suedamerika', 'cities' => ['Rio de Janeiro', 'São Paulo', 'Salvador'], 'genre' => 'jazz', 'lat' => -22.9, 'lon' => -43.2],
        'ar' => ['name' => 'Argentinien', 'region' => 'suedamerika', 'cities' => ['Buenos Aires', 'Córdoba'], 'genre' => 'rock', 'lat' => -34.6, 'lon' => -58.4],
        'za' => ['name' => 'Südafrika', 'region' => 'afrika', 'cities' => ['Kapstadt', 'Johannesburg', 'Durban'], 'genre' => 'jazz', 'lat' => -33.9, 'lon' => 18.4],
        'eg' => ['name' => 'Ägypten', 'region' => 'afrika', 'cities' => ['Kairo'], 'genre' => 'pop', 'lat' => 30.0, 'lon' => 31.2],
        'jp' => ['name' => 'Japan', 'region' => 'asien', 'cities' => ['Tokio', 'Osaka', 'Nagoya'], 'genre' => 'pop', 'lat' => 35.7, 'lon' => 139.7],
        'kr' => ['name' => 'Südkorea', 'region' => 'asien', 'cities' => ['Seoul', 'Busan'], 'genre' => 'pop', 'lat' => 37.6, 'lon' => 127.0],
        'au' => ['name' => 'Australien', 'region' => 'asien', 'cities' => ['Sydney', 'Melbourne', 'Brisbane'], 'genre' => 'rock', 'lat' => -33.9, 'lon' => 151.2],
    ];
    /** Von hier geht jede Tournee los, und hierhin kommt die Band zurück. */
    public const HOME = ['name' => 'Deutschland', 'city' => 'Berlin', 'lat' => 52.5, 'lon' => 13.4];

    /** Festivals je Genre (Balance::FESTIVAL): Name nach Woche. */
    public const FESTIVALS = [
        'rock' => ['Rock am Deich', 'Riff im Park', 'Gitarrensommer'],
        'pop' => ['Sommerklang', 'Popcorn Open Air', 'Glitzerwiese'],
        'punk' => ['Krach am Bach', 'Lärm im Hof', 'Pogo-Wiese'],
        'metal' => ['Eisenwald Open Air', 'Donnerberg', 'Stahlgewitter'],
        'jazz' => ['Jazz im Park', 'Blue Notes am See', 'Swingwiese'],
        'elektro' => ['Neon Nights', 'Bass im Wald', 'Pixelstrand'],
        'folk' => ['Lagerfeuer-Festival', 'Wiesenklang', 'Fiedel am Fluss'],
        'hiphop' => ['Beats am Hafen', 'Reimzeit Open Air', 'Block-Party'],
    ];

    public const TRAIT_REVEAL_ORDERS = [
        'rehearsal' => 'die Probe', 'gig' => 'den Auftritt', 'record' => 'die Aufnahme', 'busking' => 'Straßenmusik', 'openstage' => 'die offene Bühne', 'interview' => 'das Interview',
        'battle' => 'das Battle', 'festival' => 'das Festival', 'tour' => 'die Tournee',
    ];

    /** Wie lange ein Charakter bei der Probe Lust hat, als Faktor auf Balance::REHEARSAL['lust'] (bei zwei Eigenschaften der Mittelwert). */
    public const LUST = [
        'ehrgeizig' => 1.5, 'perfektionist' => 1.5, 'teamplayer' => 1.2, 'ruhepol' => 1.2,
        'rampensau' => 0.9, 'diva' => 0.8, 'gelassen' => 0.8, 'chaot' => 0.6, 'partytier' => 0.6,
        'kreativ' => 0.7, 'technisch' => 1.6,
    ];

    /** Der Wunsch eines unzufriedenen Musikers nach Charakter: [Satz, Anordnung, die ihn erfüllt]. */
    public const WISHES = [
        'ehrgeizig' => ['Wir wollen weiter!', 'gig'],
        'gelassen' => ['Lasst uns in Ruhe aufnehmen.', 'record'],
        'perfektionist' => ['Wir müssen besser werden.', 'rehearsal'],
        'chaot' => ['Lasst uns spontan spielen!', 'gig'],
        'rampensau' => ['Ab auf die Bühne!', 'gig'],
        'diva' => ['Wir haben was Besseres verdient.', 'gig'],
        'teamplayer' => ['Lasst uns was zusammen machen.', 'rehearsal'],
        'ruhepol' => ['Gemeinsam Musik machen wäre schön.', 'rehearsal'],
        'partytier' => ['Wann ist der nächste Gig?', 'gig'],
        'kreativ' => ['Lasst uns die neuen Songs aufnehmen!', 'record'],
        'technisch' => ['Wir sollten mehr üben.', 'rehearsal'],
    ];

    /** Dieselben Wünsche, wenn der Musiker allein ist (ohne „wir“ und „zusammen“). */
    public const WISHES_SOLO = [
        'ehrgeizig' => ['Ich will weiter!', 'gig'],
        'gelassen' => ['Lass mich in Ruhe aufnehmen.', 'record'],
        'perfektionist' => ['Ich muss besser werden.', 'rehearsal'],
        'chaot' => ['Lass mich spontan spielen!', 'gig'],
        'rampensau' => ['Ab auf die Bühne!', 'gig'],
        'diva' => ['Ich habe was Besseres verdient.', 'gig'],
        'teamplayer' => ['Ich würde gern mal wieder üben.', 'rehearsal'],
        'ruhepol' => ['In Ruhe Musik machen wäre schön.', 'rehearsal'],
        'partytier' => ['Wann ist der nächste Gig?', 'gig'],
        'kreativ' => ['Ich will meine Songs aufnehmen!', 'record'],
        'technisch' => ['Ich sollte mehr üben.', 'rehearsal'],
    ];

    /** Was eine Eigenschaft bedeutet und wie sie wirkt: Stärke und Schwäche, in einfachen Worten. */
    public const PERSONALITY_INFO = [
        'ehrgeizig' => 'Will immer weiter und übt lange. Wird unzufrieden, wenn lange keine neue Platte kommt.',
        'gelassen' => 'Lässt sich kaum aus der Ruhe bringen und ärgert sich nicht über kleine Bühnen. Tritt aber nicht gern auf.',
        'perfektionist' => 'Alles muss genau sitzen: übt lange, Aufnahmen werden besser. Schreibt aber langsam Songs.',
        'chaot' => 'Macht spontan, was ihm einfällt: schreibt schnell Songs, glänzt live öfter, patzt aber auch öfter.',
        'rampensau' => 'Liebt die Bühne: bringt mehr Ruhm bei Auftritten. Wird unruhig, wenn es lange keine gibt.',
        'diva' => 'Will im Mittelpunkt stehen: verkauft mehr Platten und ist gern allein. Will aber gute Bühnen.',
        'teamplayer' => 'Hilft allen in der Band, die Chemie wird besser. Leidet aber mit, wenn es jemandem schlecht geht.',
        'ruhepol' => 'Bringt Ruhe in die Band: Streit zählt nur halb. Auf der Bühne glänzt er seltener.',
        'partytier' => 'Feiert gern: bringt mehr Fans zu Auftritten. Wird unruhig, wenn es lange keine gibt, und übt nicht gern lange.',
        'kreativ' => 'Hat ständig Ideen: schreibt viel schneller Songs. Übt aber ungern und lernt beim Proben langsamer.',
        'technisch' => 'Übt wie verrückt und lernt schnell, patzt live seltener. Schreibt aber keine eigenen Songs.',
    ];

    /** Eigenschaften, die nicht zusammen in einem Menschen vorkommen (wären widersprüchlich). */
    public const CONTRADICTIONS = [
        ['teamplayer', 'diva'],
        ['ruhepol', 'chaot'],
        ['ruhepol', 'rampensau'],
        ['perfektionist', 'chaot'],
        ['ehrgeizig', 'gelassen'],
        ['kreativ', 'technisch'],
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

    /** Bandlogo: Schrift und Farbe, einmal bei der ersten Platte gewählt, danach auf jedem Cover (gezeichnet in cover.js). */
    public const LOGO_FONTS = ['block' => 'Kräftig', 'serif' => 'Klassisch', 'script' => 'Geschwungen', 'mono' => 'Schreibmaschine', 'round' => 'Rund', 'marker' => 'Filzstift'];
    public const LOGO_COLORS = ['weiss' => 'Weiß', 'gold' => 'Gold', 'rot' => 'Rot', 'tuerkis' => 'Türkis', 'pink' => 'Pink', 'schwarz' => 'Schwarz'];
    /** Steckt eines dieser Wörter im Plattentitel, zeigen die Cover-Entwürfe das passende Motiv. */
    public const COVER_WORDS = [
        'blitz' => ['blitz', 'donner', 'gewitter', 'strom', 'energie', 'thunder', 'lightning'],
        'herz' => ['herz', 'liebe', 'love', 'heart', 'kuss'],
        'stern' => ['stern', 'star', 'sterne', 'galaxie', 'himmel'],
        'mond' => ['mond', 'nacht', 'moon', 'night', 'traum'],
        'totenkopf' => ['tod', 'toten', 'skull', 'geist', 'gruft', 'grusel'],
        'auge' => ['auge', 'augen', 'blick', 'sehen', 'eye'],
        'welle' => ['welle', 'meer', 'ozean', 'see', 'wasser', 'surf', 'strand', 'wave'],
        'rakete' => ['rakete', 'weltall', 'space', 'flug', 'rocket', 'mars'],
        'krone' => ['krone', 'könig', 'königin', 'king', 'queen', 'gold'],
        'katze' => ['katze', 'kater', 'cat', 'miau', 'tiger'],
        'pilz' => ['pilz', 'wald', 'zauber', 'märchen'],
        'flamme' => ['feuer', 'flamme', 'fire', 'brand', 'heiß', 'glut', 'sommer'],
        'vinyl' => ['platte', 'vinyl', 'musik', 'song', 'radio', 'disco'],
    ];

    /** active = wirkt schon im Kern; die anderen brauchen spätere Features. */
    public const MANAGER_SKILLS = [
        'verhandler' => ['name' => 'Verhandler', 'desc' => 'Mehr Gage bei Gigs seiner Bands.', 'active' => true],
        'prprofi' => ['name' => 'PR-Profi', 'desc' => 'Mehr Ruhm bei Auftritten seiner Bands.', 'active' => true],
        'scout' => ['name' => 'Scout', 'desc' => 'Weniger gewöhnliche Karten in allen Packs des Labels – auch ohne Band. Der beste Scout zählt.', 'active' => true],
        'tourplaner' => ['name' => 'Tourplaner', 'desc' => 'Mehr und passendere Locations bei Tourneen.', 'active' => false],
        'netzwerker' => ['name' => 'Netzwerker', 'desc' => 'Interviews seiner Musiker bringen mehr Bekanntheit.', 'active' => true],
        'vermittler' => ['name' => 'Vermittler', 'desc' => 'Wenn seiner Band etwas nicht gefällt, ärgert sie sich weniger.', 'active' => true],
    ];
    /** Neuer Kern (Fans und Hype): was diese Fähigkeiten dort bewirken. */
    public const MANAGER_SKILLS_CORE = [
        'verhandler' => ['desc' => 'Das Label verdient mehr an jedem Zuschauer seiner Bands.'],
        'prprofi' => ['desc' => 'Mehr neue Fans bei Auftritten seiner Bands.'],
        'netzwerker' => ['desc' => 'Anfragen an seine Musiker bringen mehr Hype.'],
    ];

    /**
     * Instrumentenmodelle. klang = maximaler Sound-Bonus, spielbarkeit = hoch heißt leicht spielbar
     * (entfaltet sich schon bei wenig Können), design = Bonus auf Ruhm.
     */
    /** Boni der Instrumente (Balance::GEAR_BONUS): Name und Text, {v} = Stärke in Prozent. */
    public const GEAR_BONUS_INFO = [
        'lust' => ['name' => 'Ausdauer', 'desc' => 'Hält beim Proben {v} länger durch.'],
        'learn' => ['name' => 'Lernhilfe', 'desc' => 'Lernt beim Proben und im Studio {v} mehr.'],
        'sound' => ['name' => 'Klang', 'desc' => 'Sound {v} besser.'],
        'magnet' => ['name' => 'Publikumsmagnet', 'desc' => 'Bei Auftritten {v} mehr Ruhm.', 'ad' => 'Zieht alle Blicke auf die Bühne.'],
        'cult' => ['name' => 'Kultobjekt', 'desc' => 'Platten verkaufen sich {v} besser.', 'ad' => 'Bringt Glück – und verkauft Platten.'],
        'studio' => ['name' => 'Studio-Legende', 'desc' => 'Platten werden {v} besser.', 'ad' => 'Klingt im Studio wie ein Traum.'],
        'show' => ['name' => 'Showstück', 'desc' => 'Live {v} öfter Glanzmomente.', 'ad' => 'Der Hingucker für jede Bühne.'],
        'hit' => ['name' => 'Hitmaschine', 'desc' => 'Platten bekommen {v} mehr Chartpunkte.', 'ad' => 'Macht aus Songs Hits.'],
    ];

    /** Früher: besondere Eigenschaften epischer und legendärer Instrumente (heute GEAR_BONUS_INFO, siehe Gear::legacy). */
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
            'Svea', 'Malin', 'Nele', 'Ivy', 'Rosa', 'Carla', 'Hanna', 'Leyla', 'Ines', 'Alma', 'Mila', 'Thea', 'Esra',
            'Sanna', 'Fenja', 'Lotta', 'Merle', 'Clara', 'Emma', 'Luisa', 'Marlene', 'Pia', 'Sophie', 'Zara', 'Ayla',
            'Dilara', 'Nika', 'Kaja', 'Lia', 'Liv', 'Smilla', 'Wanda', 'Hedda', 'Elina', 'Fiona', 'Julia', 'Laura',
            'Nadia', 'Olivia', 'Priya', 'Amira', 'Noemi', 'Imani', 'Chiara', 'Bianca', 'Valentina', 'Sofia', 'Marta',
            'Agnes', 'Vera', 'Tilda', 'Ylva', 'Elsa', 'Saga', 'Ingrid', 'Mira', 'Hira', 'Nour', 'Leila', 'Asha',
            'Keira', 'Maeve', 'Siobhan', 'Niamh', 'Aiko', 'Yuna', 'Mei', 'Linh', 'Hana', 'Ada', 'Ebba', 'Juli',
            'Edda', 'Lilly', 'Mona', 'Anni', 'Antonia', 'Annika', 'Aurelia', 'Bea', 'Bella', 'Birte', 'Charlotte',
            'Celine', 'Dana', 'Daria', 'Delia', 'Denise', 'Doro', 'Elena', 'Eleni', 'Elisa', 'Emilia', 'Enya', 'Erin',
            'Eva', 'Fatima', 'Femke', 'Flora', 'Franka', 'Gesa', 'Gina', 'Hailey', 'Helena', 'Hilde', 'Ilka', 'Ilvy',
            'Inga', 'Irma', 'Isabel', 'Isla', 'Jade', 'Jasmin', 'Jette', 'Johanna', 'Josefine', 'Judith', 'Kalina',
            'Karla', 'Katja', 'Klara', 'Lara', 'Lea', 'Lina', 'Livia', 'Loreen', 'Lucie', 'Ludmila', 'Lyra', 'Madita',
            'Magda', 'Malia', 'Mareike', 'Marie', 'Marina', 'Maxi', 'Melina', 'Milena', 'Mina', 'Miriam', 'Nadja',
            'Naomi', 'Natalia', 'Neele', 'Nell', 'Odile', 'Paloma', 'Penny', 'Philippa', 'Rahel', 'Rebekka', 'Rieke',
            'Rita', 'Ruby', 'Sabine', 'Sanaa', 'Sara', 'Selma', 'Sina', 'Stella', 'Svenja', 'Talia', 'Tamara', 'Tara',
            'Tia', 'Tuana', 'Ulla', 'Uma', 'Vanessa', 'Vicky', 'Viola', 'Wiebke', 'Xenia', 'Yasemin', 'Yasmin',
            'Zelda', 'Zeynep', 'Zofia', 'Zora', 'Alva', 'Ava', 'Bente', 'Dagny', 'Elske', 'Fenna', 'Frauke', 'Gunda',
            'Hella', 'Inken', 'Janne', 'Lieke', 'Lotte', 'Malou', 'Nanna', 'Oda', 'Sölvi', 'Tove', 'Ylvi', 'Kalani',
            'Leilani', 'Mariam', 'Noor', 'Samira', 'Soraya', 'Layla', 'Malika', 'Ayana', 'Zuri', 'Nia', 'Abeni',
            'Adaeze', 'Nkechi', 'Sakura', 'Emi', 'Rin', 'Haruka', 'Jiwoo', 'Seo', 'Anh', 'Thu', 'Lan', 'Priyanka',
            'Meera', 'Anaya', 'Isabela', 'Lucía', 'Ximena', 'Camila', 'Paz', 'Inés', 'Ana', 'Joana', 'Beatriz',
            'Giulia', 'Martina', 'Aurora', 'Agata', 'Hanka', 'Jelena', 'Ilona', 'Vesna', 'Tatjana',
        ],
        'm' => [
            'Finn', 'Jonas', 'Emil', 'Mats', 'Ole', 'Ben', 'Luca', 'Noah', 'Arda', 'Milan', 'Theo', 'Jannik', 'Bo',
            'Can', 'Malik', 'Tarek', 'Levi', 'Henri', 'Matteo', 'Kian', 'Elias', 'Anton', 'Jasper', 'Rafael', 'Linus',
            'Juri', 'Nils', 'Oskar', 'Yusuf', 'Pablo', 'Kofi', 'Sami', 'Joscha', 'Ruben', 'Vince', 'Lasse', 'Timo',
            'Aaron', 'Enzo', 'Moritz', 'Felix', 'Paul', 'Leon', 'Max', 'Jakob', 'Karl', 'Fritz', 'Hugo', 'Johann',
            'Konstantin', 'Lennart', 'Mads', 'Sören', 'Torben', 'Hannes', 'Bastian', 'Fabian', 'Dario', 'Marco',
            'Nico', 'Diego', 'Mateo', 'Tiago', 'Rui', 'Omar', 'Karim', 'Emre', 'Deniz', 'Baran', 'Kerem', 'Ilias',
            'Nikolai', 'Dmitri', 'Pavel', 'Marek', 'Tomasz', 'Jakub', 'Ivo', 'Goran', 'Luka', 'Kenji', 'Haruto',
            'Minh', 'Jae', 'Arjun', 'Ravi', 'Kwame', 'Tunde', 'Desmond', 'Jamal', 'Silas', 'Elio', 'Mattis', 'Piet',
            'Jarno', 'Aksel', 'Erik', 'Gustav', 'Viktor', 'Ezra', 'Adam', 'Adrian', 'Alberto', 'Alessio', 'Alfred',
            'Amir', 'Andrej', 'Ansgar', 'Armin', 'Arne', 'Arvid', 'Bela', 'Benedikt', 'Bennet', 'Bjarne', 'Bruno',
            'Carl', 'Carlos', 'Cem', 'Chris', 'Claas', 'Damian', 'David', 'Dominik', 'Eddi', 'Edgar', 'Emilio',
            'Erwin', 'Esteban', 'Ferdinand', 'Florian', 'Frederik', 'Gabriel', 'Georg', 'Gerrit', 'Gideon', 'Hamza',
            'Hassan', 'Helge', 'Henrik', 'Ian', 'Ibrahim', 'Ilja', 'Ingo', 'Isak', 'Ivan', 'Jamie', 'Janosch',
            'Jesper', 'Joel', 'Jorge', 'Josef', 'Julian', 'Justus', 'Kasimir', 'Kilian', 'Knut', 'Lars', 'Laurin',
            'Leander', 'Lenny', 'Leo', 'Lion', 'Lorenz', 'Lucas', 'Ludwig', 'Magnus', 'Malte', 'Manuel', 'Marius',
            'Marlon', 'Martin', 'Mathis', 'Maurice', 'Maximilian', 'Mehmet', 'Micha', 'Mika', 'Mirko', 'Morten',
            'Nepomuk', 'Niklas', 'Noel', 'Olaf', 'Otto', 'Pepe', 'Philipp', 'Quentin', 'Raphael', 'Robert', 'Roman',
            'Rune', 'Samuel', 'Sebastian', 'Severin', 'Simon', 'Stefan', 'Sven', 'Tamino', 'Tilman', 'Tim', 'Tobias',
            'Tom', 'Ture', 'Valentin', 'Vincent', 'Wim', 'Xaver', 'Yannick', 'Yasin', 'Younes', 'Zacharias', 'Ahmed',
            'Bilal', 'Emir', 'Murat', 'Selim', 'Tayfun', 'Yunus', 'Ali', 'Farid', 'Navid', 'Reza', 'Kaveh', 'Jomo',
            'Kojo', 'Kwabena', 'Obi', 'Chidi', 'Femi', 'Sipho', 'Thabo', 'Hiroshi', 'Kaito', 'Riku', 'Sora', 'Takumi',
            'Jun', 'Hoang', 'Duc', 'Bao', 'Vikram', 'Rohan', 'Dev', 'Nikhil', 'Santiago', 'Joaquín', 'Andrés',
            'Mateus', 'João', 'Rodrigo', 'Lorenzo', 'Giovanni', 'Federico', 'Vasco', 'Bogdan', 'Stanislav',
            'Miroslav', 'Milo', 'Oscar', 'Björn', 'Leif', 'Sigurd', 'Torvald', 'Eik', 'Hauke', 'Keno', 'Fiete',
            'Momme',
        ],
        'd' => [
            'Kim', 'Robin', 'Charlie', 'Alex', 'Sascha', 'Noa', 'Sky', 'Jo', 'Toni', 'Kai', 'Mika', 'Sam', 'Ari',
            'Rio', 'Quinn', 'Luan', 'Eden', 'River', 'Jules', 'Andy', 'Nico', 'Lumi', 'Sasha', 'Ash', 'Remy', 'Jona',
            'Fynn', 'Elia', 'Nova', 'Wren', 'Indigo', 'Phoenix', 'Rune', 'Sunny', 'Yuki', 'Ren', 'Ali', 'Andi',
            'Bobbie', 'Cameron', 'Casey', 'Chris', 'Dani', 'Elian', 'Emery', 'Eli', 'Elly', 'Fabi', 'Flo', 'Gabi',
            'Hayden', 'Jamie', 'Jay', 'Jesse', 'Jordan', 'Joy', 'Juna', 'Kaya', 'Kiran', 'Lee', 'Lenny', 'Linden',
            'Lio', 'Luca', 'Lux', 'Marlo', 'Max', 'Merlin', 'Mo', 'Morgan', 'Nicky', 'Niki', 'Noor', 'Ocean', 'Pat',
            'Raven', 'Reese', 'Riley', 'Rowan', 'Sage', 'Sora', 'Stevie', 'Tal', 'Taylor', 'Tiger', 'Tove', 'Uli',
            'Vale', 'Zen',
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
    /** Bausteine für Künstlernamen (Names::stageOptions). */
    public const STAGE_WORDS = [
        'single' => ['Nova', 'Kometa', 'Velvet', 'Echo', 'Lumen', 'Sirena', 'Orbit', 'Saphir', 'Mondo', 'Blitz', 'Zephyr', 'Indigo', 'Polaris', 'Neon'],
        'after' => ['Nova', 'Sky', 'Moon', 'Fox', 'Storm', 'Star', 'Blue', 'Rose', 'Gold', 'Wolf', 'Night', 'Ray'],
        'before' => ['Lil', 'Big', 'Young', 'Lady', 'Sir', 'Miss', 'Mister'],
        'tag' => ['vocals' => 'MC', 'turntables' => 'DJ', 'keys' => 'DJ'],
    ];

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
    public const REHEARSAL_LINES = ['%s feilt am Übergang.', '%s zählt neu ein.', '%s spielt die Bridge noch einmal.',
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
