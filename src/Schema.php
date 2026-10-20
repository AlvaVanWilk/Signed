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

    private static function apply(Db $db): void
    {
        $current = self::version($db);
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
        ];
    }
}
