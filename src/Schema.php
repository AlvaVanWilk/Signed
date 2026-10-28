<?php
declare(strict_types=1);

namespace Signed;

/**
 * Legt die Tabellen an und bringt die Datenbank auf den aktuellen Stand.
 * Neue Änderungen kommen als weiterer Eintrag ans Ende von migrations(); bestehende nie umschreiben.
 */
final class Schema
{
    public static function migrate(Db $db): void
    {
        $db->raw('CREATE TABLE IF NOT EXISTS meta (k VARCHAR(40) NOT NULL PRIMARY KEY, v TEXT)');
        if (self::version($db) >= count(self::migrations())) {
            return;
        }

        if ($db->driver() === 'sqlite') {
            $db->transaction(static fn () => self::apply($db));
            return;
        }
        // MySQL: DDL beendet Transaktionen, deshalb eine Sperre gegen gleichzeitige Migrationen.
        $db->value("SELECT GET_LOCK('signed_migrate', 30)");
        try {
            self::apply($db);
        } finally {
            $db->value("SELECT RELEASE_LOCK('signed_migrate')");
        }
    }

    private static function version(Db $db): int
    {
        return (int) ($db->value("SELECT v FROM meta WHERE k = 'schema'") ?? 0);
    }

    /** Stand der Datenbank, bevor die laufenden Umbauschritte begannen (für den Übergang alter Labels). */
    private static int $from = 0;

    private static function apply(Db $db): void
    {
        $current = self::version($db);
        self::$from = $current;
        foreach (self::migrations() as $index => $statements) {
            $version = $index + 1;
            if ($version <= $current) {
                continue;
            }
            foreach ($statements as $step) {
                // Ein Schritt ist SQL oder eine PHP-Funktion für Datenumbauten.
                is_callable($step) ? $step($db) : $db->raw(self::dialect($db, $step));
            }
            $db->exec("DELETE FROM meta WHERE k = 'schema'");
            $db->exec("INSERT INTO meta (k, v) VALUES ('schema', ?)", [(string) $version]);
        }
    }

    private static function dialect(Db $db, string $sql): string
    {
        $mysql = $db->driver() === 'mysql';
        return strtr($sql, [
            '{PK}' => $mysql ? 'INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT',
            '{OPTS}' => $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '',
        ]);
    }

    /** @return list<list<string|callable>> */
    private static function migrations(): array
    {
        return [
            // 1: Kern
            [
                'CREATE TABLE labels (
                    id {PK},
                    username VARCHAR(40) NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    name VARCHAR(60) NOT NULL,
                    name_key VARCHAR(60) NOT NULL,
                    money INTEGER NOT NULL DEFAULT 0,
                    created_at INTEGER NOT NULL,
                    last_seen INTEGER NOT NULL DEFAULT 0
                ){OPTS}',
                'CREATE UNIQUE INDEX labels_username ON labels (username)',
                'CREATE UNIQUE INDEX labels_name_key ON labels (name_key)',

                'CREATE TABLE musicians (
                    id {PK},
                    label_id INTEGER NULL,
                    first_name VARCHAR(40) NOT NULL,
                    last_name VARCHAR(40) NOT NULL,
                    rarity VARCHAR(12) NOT NULL,
                    instr1 VARCHAR(16) NOT NULL,
                    skill1 REAL NOT NULL,
                    instr2 VARCHAR(16) NULL,
                    skill2 REAL NULL,
                    presence REAL NOT NULL,
                    fame REAL NOT NULL DEFAULT 0,
                    genres TEXT NOT NULL,
                    traits TEXT NOT NULL,
                    techniques TEXT NOT NULL,
                    personality TEXT NOT NULL,
                    unusual INTEGER NOT NULL DEFAULT 0,
                    look TEXT NOT NULL,
                    band_id INTEGER NULL,
                    band_slot INTEGER NOT NULL DEFAULT 1,
                    busy_until INTEGER NOT NULL DEFAULT 0,
                    origin VARCHAR(16) NOT NULL,
                    created_at INTEGER NOT NULL
                ){OPTS}',
                'CREATE INDEX musicians_label ON musicians (label_id)',
                'CREATE INDEX musicians_band ON musicians (band_id)',

                'CREATE TABLE gear (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    model VARCHAR(32) NOT NULL,
                    musician_id INTEGER NULL,
                    created_at INTEGER NOT NULL
                ){OPTS}',
                'CREATE INDEX gear_label ON gear (label_id)',

                'CREATE TABLE managers (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    first_name VARCHAR(40) NOT NULL,
                    last_name VARCHAR(40) NOT NULL,
                    rarity VARCHAR(12) NOT NULL,
                    level INTEGER NOT NULL,
                    skills TEXT NOT NULL,
                    look TEXT NOT NULL,
                    created_at INTEGER NOT NULL
                ){OPTS}',
                'CREATE INDEX managers_label ON managers (label_id)',

                'CREATE TABLE bands (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    name VARCHAR(60) NOT NULL,
                    genre VARCHAR(16) NOT NULL,
                    manager_id INTEGER NULL,
                    cohesion REAL NOT NULL DEFAULT 0,
                    sound REAL NOT NULL DEFAULT 0,
                    songs_recorded INTEGER NOT NULL DEFAULT 0,
                    records INTEGER NOT NULL DEFAULT 0,
                    perfect_records INTEGER NOT NULL DEFAULT 0,
                    gigs_played INTEGER NOT NULL DEFAULT 0,
                    gigs_since_record INTEGER NOT NULL DEFAULT 0,
                    released INTEGER NOT NULL DEFAULT 0,
                    busy_until INTEGER NOT NULL DEFAULT 0,
                    created_at INTEGER NOT NULL
                ){OPTS}',
                'CREATE UNIQUE INDEX bands_label_genre ON bands (label_id, genre)',

                'CREATE TABLE items (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    kind VARCHAR(16) NOT NULL,
                    value REAL NOT NULL,
                    created_at INTEGER NOT NULL
                ){OPTS}',
                'CREATE INDEX items_label ON items (label_id)',

                'CREATE TABLE activities (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    band_id INTEGER NULL,
                    musician_id INTEGER NULL,
                    type VARCHAR(16) NOT NULL,
                    started_at INTEGER NOT NULL,
                    ends_at INTEGER NOT NULL,
                    settled INTEGER NOT NULL DEFAULT 0,
                    seen INTEGER NOT NULL DEFAULT 0,
                    data TEXT NOT NULL,
                    result TEXT NULL
                ){OPTS}',
                'CREATE INDEX activities_due ON activities (settled, ends_at)',
                'CREATE INDEX activities_label ON activities (label_id, settled)',

                'CREATE TABLE records (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    band_id INTEGER NOT NULL,
                    title VARCHAR(80) NOT NULL,
                    songs_total INTEGER NOT NULL,
                    songs_done INTEGER NOT NULL DEFAULT 0,
                    song_results TEXT NOT NULL,
                    status VARCHAR(8) NOT NULL,
                    money_reward INTEGER NOT NULL DEFAULT 0,
                    fame_reward REAL NOT NULL DEFAULT 0,
                    perfect INTEGER NOT NULL DEFAULT 0,
                    created_at INTEGER NOT NULL,
                    finished_at INTEGER NULL
                ){OPTS}',
                'CREATE INDEX records_band ON records (band_id, status)',

                'CREATE TABLE minigames (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    kind VARCHAR(10) NOT NULL,
                    musician_id INTEGER NULL,
                    band_id INTEGER NULL,
                    record_id INTEGER NULL,
                    slot INTEGER NULL,
                    chart TEXT NOT NULL,
                    started_at INTEGER NOT NULL,
                    expires_at INTEGER NOT NULL,
                    status VARCHAR(10) NOT NULL
                ){OPTS}',
                'CREATE INDEX minigames_label ON minigames (label_id, status)',

                'CREATE TABLE history (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    band_id INTEGER NULL,
                    musician_id INTEGER NULL,
                    kind VARCHAR(16) NOT NULL,
                    text VARCHAR(255) NOT NULL,
                    created_at INTEGER NOT NULL
                ){OPTS}',
                'CREATE INDEX history_label ON history (label_id, id)',
                'CREATE INDEX history_band ON history (band_id, id)',
            ],
            // 2: Talent-Scout, Album-Charts, eindeutige Bandnamen
            [
                'ALTER TABLE labels ADD COLUMN scout_vouchers INTEGER NOT NULL DEFAULT 0',
                'ALTER TABLE labels ADD COLUMN scout_bought_at INTEGER NOT NULL DEFAULT 0',
                'UPDATE labels SET scout_vouchers = 1',
                'CREATE TABLE scout_offers (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    cards TEXT NOT NULL,
                    status VARCHAR(10) NOT NULL,
                    created_at INTEGER NOT NULL
                ){OPTS}',
                'CREATE INDEX scout_offers_label ON scout_offers (label_id, status)',
                'ALTER TABLE records ADD COLUMN chart_points REAL NOT NULL DEFAULT 0',
                'ALTER TABLE records ADD COLUMN royalties_until INTEGER NOT NULL DEFAULT 0',
                'ALTER TABLE records ADD COLUMN topped INTEGER NOT NULL DEFAULT 0',
                'CREATE INDEX records_status ON records (status, finished_at)',
                "ALTER TABLE bands ADD COLUMN name_key VARCHAR(60) NOT NULL DEFAULT ''",
                'UPDATE bands SET name_key = LOWER(name)',
                'CREATE INDEX bands_name_key ON bands (name_key)',
            ],
            // 3: Stufen und Fähigkeiten, Favoriten, Jam-Café, eigene Werte pro Instrument
            [
                'ALTER TABLE musicians ADD COLUMN level INTEGER NOT NULL DEFAULT 0',
                "ALTER TABLE musicians ADD COLUMN abilities VARCHAR(255) NOT NULL DEFAULT '[]'",
                'ALTER TABLE musicians ADD COLUMN ability_choice VARCHAR(255) NULL',
                'ALTER TABLE musicians ADD COLUMN favorite INTEGER NOT NULL DEFAULT 0',
                'ALTER TABLE musicians ADD COLUMN jam_at INTEGER NOT NULL DEFAULT 0',
                'ALTER TABLE gear ADD COLUMN klang INTEGER NOT NULL DEFAULT 0',
                'ALTER TABLE gear ADD COLUMN spielbarkeit INTEGER NOT NULL DEFAULT 0',
                'ALTER TABLE gear ADD COLUMN design INTEGER NOT NULL DEFAULT 0',
                [Migrations::class, 'levelsAndGear'],
            ],
            // 4: Jam-Café mit Auswahl
            [
                'CREATE TABLE jam_offers (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    musician_id INTEGER NOT NULL,
                    cards TEXT NOT NULL,
                    status VARCHAR(10) NOT NULL,
                    created_at INTEGER NOT NULL
                ){OPTS}',
                'CREATE INDEX jam_offers_label ON jam_offers (label_id, status)',
            ],
            // 5: Wechselbörse
            [
                'CREATE TABLE transfers (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    musician_id INTEGER NOT NULL,
                    min_price INTEGER NOT NULL,
                    best_amount INTEGER NOT NULL DEFAULT 0,
                    best_label_id INTEGER NULL,
                    bids INTEGER NOT NULL DEFAULT 0,
                    status VARCHAR(10) NOT NULL,
                    created_at INTEGER NOT NULL,
                    ends_at INTEGER NOT NULL,
                    closed_at INTEGER NULL
                ){OPTS}',
                'CREATE INDEX transfers_status ON transfers (status, ends_at)',
                'CREATE INDEX transfers_musician ON transfers (musician_id, status)',
            ],
            // 6: Packs mit Auswahl, Kontoauszug, Live-Pflicht vor der nächsten Platte
            [
                'CREATE TABLE pack_offers (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    pack VARCHAR(20) NOT NULL,
                    cards TEXT NOT NULL,
                    status VARCHAR(10) NOT NULL,
                    created_at INTEGER NOT NULL
                ){OPTS}',
                'CREATE INDEX pack_offers_label ON pack_offers (label_id, status)',
                'CREATE TABLE ledger (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    amount INTEGER NOT NULL,
                    text VARCHAR(120) NOT NULL,
                    created_at INTEGER NOT NULL
                ){OPTS}',
                'CREATE INDEX ledger_label ON ledger (label_id, id)',
                'ALTER TABLE bands ADD COLUMN live_songs_needed INTEGER NOT NULL DEFAULT 0',
            ],
            // 7: Ruhm, der am Bandnamen hängt
            [
                'ALTER TABLE bands ADD COLUMN fame_own REAL NOT NULL DEFAULT 0',
            ],
            // 8: Genre ergibt sich aus der Besetzung, Keyboard und Synthesizer sind ein Instrument
            [
                'ALTER TABLE bands ADD COLUMN genre2 VARCHAR(16) NULL',
                [Migrations::class, 'keysAndGenres'],
            ],
            // 9: Tantiemen laufen ständig (Bruchteile eines ₲ werden aufgehoben); Instrumente mit Eigenschaften
            [
                'ALTER TABLE records ADD COLUMN royalty_carry REAL NOT NULL DEFAULT 0',
                'ALTER TABLE gear ADD COLUMN perks TEXT NULL',
                [Migrations::class, 'gearPerks'],
            ],
            // 10: Charts in Stunden – Schub aus Gigs und Promo, Plätze vorher und bester Platz
            [
                'ALTER TABLE records ADD COLUMN boost REAL NOT NULL DEFAULT 0',
                'ALTER TABLE records ADD COLUMN boost_at INTEGER NOT NULL DEFAULT 0',
                'ALTER TABLE records ADD COLUMN rank_last INTEGER NULL',
                'ALTER TABLE records ADD COLUMN rank_prev INTEGER NULL',
                'ALTER TABLE records ADD COLUMN rank_hour INTEGER NOT NULL DEFAULT 0',
                'ALTER TABLE records ADD COLUMN rank_best INTEGER NULL',
            ],
            // 11: Platten bestehen aus gemischten Minispielen (Einspielen, Mischpult, Master-Mix)
            [
                'ALTER TABLE records ADD COLUMN plan TEXT NULL',
            ],
            // 12: Welche Platte die Band auf Gigs spielt (und Gigs und Promo damit anschieben)
            [
                'ALTER TABLE bands ADD COLUMN promo_record_id INTEGER NULL',
            ],
            // 13: Katalog – jede Platte verkauft sich dauerhaft ein wenig, auch ohne Chartplatz
            [
                'ALTER TABLE records ADD COLUMN catalog_until INTEGER NOT NULL DEFAULT 0',
            ],
            // 14: Tageskurse – wer welchen Kurs schon besucht hat (pro Musiker und Tag einmal)
            [
                'CREATE TABLE course_visits (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    musician_id INTEGER NOT NULL,
                    day VARCHAR(10) NOT NULL,
                    price INTEGER NOT NULL,
                    activity_id INTEGER NULL,
                    created_at INTEGER NOT NULL
                ){OPTS}',
                'CREATE UNIQUE INDEX course_visits_once ON course_visits (musician_id, day)',
            ],
            // 15: Keine widersprüchlichen Eigenschaften mehr (z. B. Teamplayer und Diva)
            [
                [Migrations::class, 'personalityContradictions'],
            ],
            // 16: Instrumentwechsel merken – wer zurückwechselt, ist wieder eingespielt
            [
                'ALTER TABLE bands ADD COLUMN slot_return TEXT NULL',
            ],
            // 17: Jam-Café nur einmal – wer darüber schon eine Band gegründet hat, ist markiert
            [
                "UPDATE musicians SET jam_at = created_at WHERE jam_at = 0 AND (origin = 'jam' OR id IN (SELECT musician_id FROM jam_offers WHERE status = 'done'))",
            ],
            // 18: Plattencover aus dem Cover-Automaten (Bänder, gewählte Felder, fertiges Cover)
            [
                'ALTER TABLE records ADD COLUMN cover TEXT NULL',
            ],
            // 19: Eine Band darf wieder mehrere Platten in den Charts haben – Plätze sofort neu vergeben
            [
                [Migrations::class, 'chartsRerank'],
            ],
            // 20: Album-Zyklus – live gespielte Lieder und Band-Promos seit der letzten Platte
            [
                'ALTER TABLE bands ADD COLUMN live_songs INTEGER NOT NULL DEFAULT 0',
                'ALTER TABLE bands ADD COLUMN band_promos INTEGER NOT NULL DEFAULT 0',
                [Migrations::class, 'recordCycle'],
            ],
            // 21: Chart-Verlauf ohne eigenes Steigen (Einstieg mit vollen Punkten) – Plätze sofort neu vergeben
            [
                [Migrations::class, 'chartsRerank'],
            ],
            // 22: Eigene Reihenfolge der Band- und Favoritenknöpfe (JSON-Liste wie ["b12", "m5"])
            [
                'ALTER TABLE labels ADD COLUMN nav_order TEXT NULL',
            ],
            // 23: Label-Umbau – Beziehungen zwischen Musikern, Wohlbefinden, Songs der Band, Lebenszeichen des Labels
            [
                'CREATE TABLE relations (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    a_id INTEGER NOT NULL,
                    b_id INTEGER NOT NULL,
                    value REAL NOT NULL DEFAULT 0,
                    at INTEGER NOT NULL DEFAULT 0
                ){OPTS}',
                'CREATE UNIQUE INDEX relations_pair ON relations (a_id, b_id)',
                'CREATE INDEX relations_label ON relations (label_id)',
                'ALTER TABLE musicians ADD COLUMN wellbeing REAL NOT NULL DEFAULT 60',
                'ALTER TABLE bands ADD COLUMN songs_written REAL NOT NULL DEFAULT 0',
                'ALTER TABLE bands ADD COLUMN mood_at INTEGER NOT NULL DEFAULT 0',
                'ALTER TABLE bands ADD COLUMN patience REAL NOT NULL DEFAULT 1800',
                'ALTER TABLE labels ADD COLUMN online_at INTEGER NOT NULL DEFAULT 0',
            ],
            // 24: Label-Umbau – Einsendungen (Bewerbungen von Musikern, je Label eigene)
            [
                'CREATE TABLE submissions (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    card TEXT NOT NULL,
                    created_at INTEGER NOT NULL,
                    expires_at INTEGER NOT NULL
                ){OPTS}',
                'CREATE INDEX submissions_label ON submissions (label_id)',
                'ALTER TABLE labels ADD COLUMN submit_at INTEGER NOT NULL DEFAULT 0',
            ],
            // 25: Einsendungen kommen ungehört an; Anhören dauert ein paar Sekunden (opened_at = Zeitpunkt, ab dem man sieht, was drauf ist)
            [
                'ALTER TABLE submissions ADD COLUMN opened_at INTEGER NULL',
            ],
            // 26: Eigene Reihenfolge der Einsendungen (Kassetten und Visitenkarten frei sortierbar)
            [
                'ALTER TABLE labels ADD COLUMN sub_order TEXT NULL',
            ],
            // 27: Ehemalige Bands: Wer seinen Namen aufgibt (umbenannt, aufgelöst, aufgegangen), bleibt als Eintrag mit Platten und Ruhm von damals erhalten
            [
                'ALTER TABLE bands ADD COLUMN retired_at INTEGER NULL',
                'ALTER TABLE bands ADD COLUMN retired_reason VARCHAR(10) NULL',
                'ALTER TABLE bands ADD COLUMN retired_note VARCHAR(200) NULL',
                'ALTER TABLE bands ADD COLUMN fame_final REAL NOT NULL DEFAULT 0',
            ],
            // 28: WikiSignia – wer dem Label einmal angehörte (entlassen oder verkauft), bleibt dort mit seinem Artikel erhalten
            [
                'CREATE TABLE alumni (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    musician_id INTEGER NOT NULL,
                    left_at INTEGER NOT NULL,
                    reason VARCHAR(10) NOT NULL
                ){OPTS}',
                'CREATE INDEX alumni_label ON alumni (label_id, musician_id)',
                "INSERT INTO alumni (label_id, musician_id, left_at, reason) SELECT label_id, musician_id, created_at, 'released' FROM history WHERE musician_id IS NOT NULL AND text LIKE '%verlässt das Label.'",
            ],
            // 29: Music Store – zurückgelegtes Instrument (eines je Label, bleibt über den Wechsel des Angebots) und schon Gekauftes
            [
                'CREATE TABLE store_holds (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    slot INTEGER NOT NULL,
                    idx INTEGER NOT NULL,
                    created_at INTEGER NOT NULL,
                    expires_at INTEGER NOT NULL
                ){OPTS}',
                'CREATE INDEX store_holds_label ON store_holds (label_id)',
                'CREATE TABLE store_buys (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    slot INTEGER NOT NULL,
                    idx INTEGER NOT NULL,
                    created_at INTEGER NOT NULL
                ){OPTS}',
                'CREATE UNIQUE INDEX store_buys_once ON store_buys (label_id, slot, idx)',
            ],
            // 30: Proberaum – jede Band (ab zwei Mitgliedern) hat ihren eigenen, Solokünstler teilen sich die Übungsecke des Labels
            [
                'ALTER TABLE bands ADD COLUMN room INTEGER NOT NULL DEFAULT 0',
                'ALTER TABLE labels ADD COLUMN solo_room INTEGER NOT NULL DEFAULT 0',
            ],
            // 31: Künstlername – ein Solokünstler kann einen haben und behält ihn, wenn er in eine Band geht
            [
                'ALTER TABLE musicians ADD COLUMN stage_name VARCHAR(60) NULL',
            ],
            // 32: Music Store – eigene Lieferung eines Labels (Nummer des Regals, solange das Zeitfenster läuft)
            [
                'ALTER TABLE labels ADD COLUMN store_shelf INTEGER NULL',
            ],
            // 33: Jeder Solokünstler bekommt einen eigenen Proberaum; er übernimmt die Stufe der bisherigen gemeinsamen Übungsecke
            [
                'UPDATE bands SET room = (SELECT l.solo_room FROM labels l WHERE l.id = bands.label_id)
                    WHERE (SELECT COUNT(*) FROM musicians m WHERE m.band_id = bands.id) = 1 AND room < (SELECT l.solo_room FROM labels l WHERE l.id = bands.label_id)',
            ],
            // 34: Instrumente in Güteklassen – farbige Boni (grün, blau, gold) je Instrument; leer = aus den alten Werten abgeleitet (Gear::legacy)
            [
                'ALTER TABLE gear ADD COLUMN bonuses TEXT NULL',
            ],
            // 35: Verborgene Charakterzüge – welche das Label schon kennt; leer = alle (wer schon da war, ist bekannt)
            [
                'ALTER TABLE musicians ADD COLUMN known_traits TEXT NULL',
            ],
            // 36: Music Store mit Tagesangebot statt Regal alle vier Stunden – alte Zurücklegungen gelten nicht mehr (labels.store_shelf bleibt unbenutzt)
            [
                'DELETE FROM store_holds',
            ],
            // 37: Aufnehmen ohne Minispiele – Studio, Produzent und Qualität je Platte; alte, offene Produktionen werden erstattet
            [
                'ALTER TABLE records ADD COLUMN studio VARCHAR(12) NULL',
                'ALTER TABLE records ADD COLUMN producer VARCHAR(12) NULL',
                'ALTER TABLE records ADD COLUMN quality REAL NULL',
                [Migrations::class, 'dropOpenRecords'],
            ],
            // 38: Einsendungen merken: gespeichert (statt im Eingang); ablaufen tun sie trotzdem
            [
                'ALTER TABLE submissions ADD COLUMN saved INTEGER NOT NULL DEFAULT 0',
            ],
            // 39: Posteingang – Interview-Anfragen und Bewerbungen (Demos bleiben in submissions); eingestellte Leute (Assistenz, später Scout, Lehrer)
            [
                'CREATE TABLE mail (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    kind VARCHAR(12) NOT NULL,
                    data TEXT NOT NULL,
                    created_at INTEGER NOT NULL,
                    expires_at INTEGER NOT NULL,
                    saved INTEGER NOT NULL DEFAULT 0
                ){OPTS}',
                'CREATE INDEX mail_label ON mail (label_id)',
                'CREATE TABLE staff (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    role VARCHAR(12) NOT NULL,
                    tier INTEGER NOT NULL,
                    data TEXT NOT NULL,
                    hired_at INTEGER NOT NULL,
                    busy_until INTEGER NOT NULL DEFAULT 0
                ){OPTS}',
                'CREATE INDEX staff_label ON staff (label_id)',
                'ALTER TABLE labels ADD COLUMN interview_at INTEGER NOT NULL DEFAULT 0',
                'ALTER TABLE labels ADD COLUMN job_at INTEGER NOT NULL DEFAULT 0',
                'ALTER TABLE labels ADD COLUMN assist_at INTEGER NOT NULL DEFAULT 0',
                'ALTER TABLE labels ADD COLUMN assist_rules TEXT NULL',
                'ALTER TABLE labels ADD COLUMN assist_stats TEXT NULL',
            ],
            // 40: Songwriting-Coach – Songs aus der gecoachten Zeit und ihr Qualitäts-Zuschlag für die nächste Platte
            [
                'ALTER TABLE bands ADD COLUMN coached_songs REAL NOT NULL DEFAULT 0',
                'ALTER TABLE bands ADD COLUMN coach_bonus REAL NOT NULL DEFAULT 0',
            ],
            // 41: Manager aus dem Posteingang – Schwerpunkt-Genres und Stil (Charakterzug); alte Manager ohne beides passen überall mittel
            [
                'ALTER TABLE managers ADD COLUMN genres TEXT NULL',
                'ALTER TABLE managers ADD COLUMN style VARCHAR(16) NULL',
            ],
            // 42: Bandlogo (Schrift und Farbe), bei der ersten Platte gewählt und auf allen weiteren Covern
            [
                'ALTER TABLE bands ADD COLUMN logo TEXT NULL',
            ],
            // 43: Post kommt in Umschlägen (erst auspacken), liegt im Eingang, bis jemand sie in die Fächer sortiert; wer gerade daran arbeitet (Praktikum);
            // was seit dem letzten Blick in den Posteingang neu ist. Was schon da war, gilt als ausgepackt.
            [
                'ALTER TABLE submissions ADD COLUMN unpacked_at INTEGER NULL',
                'ALTER TABLE submissions ADD COLUMN sorted_at INTEGER NULL',
                'ALTER TABLE submissions ADD COLUMN worker VARCHAR(10) NULL',
                'ALTER TABLE mail ADD COLUMN unpacked_at INTEGER NULL',
                'ALTER TABLE mail ADD COLUMN sorted_at INTEGER NULL',
                'ALTER TABLE mail ADD COLUMN worker VARCHAR(10) NULL',
                'ALTER TABLE labels ADD COLUMN inbox_seen_at INTEGER NOT NULL DEFAULT 0',
                'UPDATE submissions SET unpacked_at = created_at',
                'UPDATE mail SET unpacked_at = created_at',
            ],
            // 44: Von Hand in ein Fach gezogen (überstimmt die Sortierung)
            [
                'ALTER TABLE submissions ADD COLUMN box VARCHAR(10) NULL',
                'ALTER TABLE mail ADD COLUMN box VARCHAR(10) NULL',
            ],
            // 45: Seit wann der Manager die Band betreut (nach einiger Zeit kennt er alle Charakterzüge)
            [
                'ALTER TABLE bands ADD COLUMN manager_since INTEGER NULL',
            ],
            // 46: Eigene Aufstellung der Mitglieder im Raum (Musiker-IDs als JSON; wer fehlt, steht hinten an)
            [
                'ALTER TABLE bands ADD COLUMN stage_order TEXT NULL',
            ],
            // 47: Assistenz in Arbeitsgebieten: Kassetten in die Genre-Unterreiter gelegt (filed_at), vom Suchauftrag gefunden (found),
            // Briefe vom Büro gelesen (read_at); der Suchauftrag des Labels
            [
                'ALTER TABLE submissions ADD COLUMN filed_at INTEGER NULL',
                'ALTER TABLE submissions ADD COLUMN found INTEGER NOT NULL DEFAULT 0',
                'ALTER TABLE mail ADD COLUMN read_at INTEGER NULL',
                'ALTER TABLE labels ADD COLUMN assist_search TEXT NULL',
            ],
            // 48: Unerfüllte Bedürfnisse wachsen: wie viele Stunden ein Musiker schon auf seinen Wunsch und auf ein (besseres) Instrument wartet
            // (NULL = nichts offen). Gezählt wird nur Zeit, die gerechnet wird (eingefroren offline im Roten, siehe Mood::run).
            [
                'ALTER TABLE musicians ADD COLUMN wish_wait REAL NULL',
                'ALTER TABLE musicians ADD COLUMN gear_wait REAL NULL',
            ],
            // 49: Veranstaltungen für alle Labels (Battle of the Bands, Festival): je Art und Woche eine, mit Anmeldungen und Pokalen
            [
                'CREATE TABLE events (
                    id {PK},
                    kind VARCHAR(16) NOT NULL,
                    week VARCHAR(10) NOT NULL,
                    starts_at INTEGER NOT NULL,
                    ends_at INTEGER NOT NULL DEFAULT 0,
                    state VARCHAR(10) NOT NULL DEFAULT \'open\',
                    data TEXT NULL
                ){OPTS}',
                'CREATE UNIQUE INDEX events_week ON events (kind, week)',
                'CREATE INDEX events_state ON events (state, starts_at)',
                'CREATE TABLE event_entries (
                    id {PK},
                    event_id INTEGER NOT NULL,
                    label_id INTEGER NOT NULL,
                    band_id INTEGER NOT NULL,
                    status VARCHAR(10) NOT NULL DEFAULT \'in\',
                    place INTEGER NULL,
                    created_at INTEGER NOT NULL
                ){OPTS}',
                'CREATE UNIQUE INDEX event_entries_band ON event_entries (event_id, band_id)',
                'CREATE INDEX event_entries_label ON event_entries (label_id)',
                'CREATE TABLE awards (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    band_id INTEGER NOT NULL,
                    kind VARCHAR(16) NOT NULL,
                    place INTEGER NOT NULL,
                    title VARCHAR(120) NOT NULL,
                    created_at INTEGER NOT NULL
                ){OPTS}',
                'CREATE INDEX awards_band ON awards (band_id)',
            ],
            // 50: Festival: Anmeldeschluss vor dem Beginn; gesammelte Bekanntheit je Band und Tag (wer kommt auf die Hauptbühne)
            [
                'ALTER TABLE events ADD COLUMN closes_at INTEGER NOT NULL DEFAULT 0',
                'CREATE TABLE fame_days (
                    id {PK},
                    band_id INTEGER NOT NULL,
                    day VARCHAR(10) NOT NULL,
                    gain REAL NOT NULL DEFAULT 0
                ){OPTS}',
                'CREATE INDEX fame_days_band ON fame_days (band_id, day)',
            ],
            // 51: Tourneen: je Band höchstens eine laufende; Stationen, Strecken und Ergebnisse als JSON
            [
                'CREATE TABLE tours (
                    id {PK},
                    label_id INTEGER NOT NULL,
                    band_id INTEGER NOT NULL,
                    state VARCHAR(10) NOT NULL,
                    stations INTEGER NOT NULL,
                    country VARCHAR(4) NULL,
                    deadline INTEGER NOT NULL DEFAULT 0,
                    started_at INTEGER NOT NULL,
                    ended_at INTEGER NULL,
                    data TEXT NOT NULL
                ){OPTS}',
                'CREATE INDEX tours_band ON tours (band_id, state)',
                'CREATE INDEX tours_label ON tours (label_id, state)',
            ],
            // 52: Auszeichnungen: verkaufte Stück je Platte (Gold, Platin, Diamant), Auszeichnung je Platte, noch nicht gefeiert;
            // bisherige Platten bekommen geschätzte Verkäufe und ihre Auszeichnungen (ohne Feier)
            [
                'ALTER TABLE records ADD COLUMN sold REAL NOT NULL DEFAULT 0',
                'ALTER TABLE awards ADD COLUMN record_id INTEGER NULL',
                'ALTER TABLE awards ADD COLUMN seen INTEGER NOT NULL DEFAULT 1',
                static fn (Db $db) => \Signed\Game\Awards::backfill($db),
            ],
            // 53: Übergang alter Labels: Wechselbörse zu, Karten und Gutscheine in Geld, Beziehungen aus der Bandzeit, Kartei beiseite;
            // was passiert ist, zeigt der Browser einmal als „Was ist neu?“ (labels.transition, danach leer)
            [
                'ALTER TABLE labels ADD COLUMN transition TEXT NULL',
                static fn (Db $db) => \Signed\Game\Transition::prepare($db, self::$from),
            ],
        ];
    }
}
