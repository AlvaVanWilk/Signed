<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;
use Signed\Db;

/** Datenzugriff für ein angemeldetes Label. Prüft bei jedem Zugriff, dass die Karte dem Label gehört. */
final class Repo
{
    public function __construct(public readonly Db $db, public readonly int $labelId)
    {
    }

    public function label(): array
    {
        $row = $this->db->one('SELECT id, username, name, money, nav_order, sub_order FROM labels WHERE id = ?', [$this->labelId]);
        if ($row === null) {
            throw new ApiError('Bitte melde dich an.', 401);
        }
        return [
            'id' => (int) $row['id'], 'username' => $row['username'], 'name' => $row['name'], 'money' => (int) $row['money'],
            'navOrder' => json_decode((string) $row['nav_order'], true) ?: [],
            'subOrder' => json_decode((string) $row['sub_order'], true) ?: [],
        ];
    }

    public function spend(int $amount, string $reason): void
    {
        if ($amount <= 0) {
            return;
        }
        $changed = $this->db->exec('UPDATE labels SET money = money - ? WHERE id = ? AND money >= ?', [$amount, $this->labelId, $amount]);
        if ($changed === 0) {
            throw new ApiError('Dafür reicht das Geld nicht.');
        }
        $this->book(-$amount, $reason);
    }

    public function earn(int $amount, string $reason): void
    {
        if ($amount > 0) {
            $this->db->exec('UPDATE labels SET money = money + ? WHERE id = ?', [$amount, $this->labelId]);
            $this->book($amount, $reason);
        }
    }

    /** Laufende Einnahmen (Tantiemen): eine Zeile pro Grund und Tag im Kontoauszug statt vieler kleiner. */
    public function earnDaily(int $amount, string $reason): void
    {
        if ($amount <= 0) {
            return;
        }
        $this->db->exec('UPDATE labels SET money = money + ? WHERE id = ?', [$amount, $this->labelId]);
        $now = Clock::now();
        $text = mb_substr($reason, 0, 120);
        $updated = $this->db->exec(
            'UPDATE ledger SET amount = amount + ?, created_at = ? WHERE label_id = ? AND text = ? AND created_at >= ?',
            [$amount, $now, $this->labelId, $text, $now - $now % 86400]
        );
        if ($updated === 0) {
            $this->book($amount, $reason);
        }
    }

    /** Kontoauszug: jede Einnahme und Ausgabe mit Grund. */
    private function book(int $amount, string $reason): void
    {
        $this->db->insert('ledger', [
            'label_id' => $this->labelId, 'amount' => $amount, 'text' => mb_substr($reason, 0, 120), 'created_at' => Clock::now(),
        ]);
    }

    public function log(string $kind, string $text, ?int $bandId = null, ?int $musicianId = null): void
    {
        $this->db->insert('history', [
            'label_id' => $this->labelId, 'band_id' => $bandId, 'musician_id' => $musicianId,
            'kind' => $kind, 'text' => mb_substr($text, 0, 255), 'created_at' => Clock::now(),
        ]);
    }

    /**
     * Ruhm für eine Band: Ein Teil bleibt am Bandnamen hängen, der Rest wird Bekanntheit der Mitglieder.
     * @param array<int, float> $gains Musiker-ID => Gewinn
     * @return float der ganze Gewinn
     */
    public function addBandFame(int $bandId, array $gains): float
    {
        $total = array_sum($gains);
        if ($total <= 0) {
            return 0.0;
        }
        $own = $total * Balance::BAND_FAME_SHARE;
        $this->db->exec('UPDATE bands SET fame_own = fame_own + ? WHERE id = ? AND label_id = ?', [$own, $bandId, $this->labelId]);
        foreach ($gains as $musicianId => $gain) {
            $own += $this->addMusicianFame((int) $musicianId, $gain * (1 - Balance::BAND_FAME_SHARE));
        }
        return $own;
    }

    /** Bekanntheit für einen Musiker – über Stufe 1 hinaus nur noch zum Teil (Balance::LEVEL_PACE). Liefert den echten Gewinn. */
    public function addMusicianFame(int $musicianId, float $gain): float
    {
        $fame = $this->db->value('SELECT fame FROM musicians WHERE id = ? AND label_id = ?', [$musicianId, $this->labelId]);
        if ($fame === null || $gain <= 0) {
            return 0.0;
        }
        $before = Levels::forFame((float) $fame);
        $gain *= Levels::pace($before);
        $this->db->exec('UPDATE musicians SET fame = fame + ? WHERE id = ? AND label_id = ?', [$gain, $musicianId, $this->labelId]);
        $after = Levels::forFame((float) $fame + $gain);
        if ($after > $before) {
            $m = $this->db->one('SELECT first_name, last_name, band_id FROM musicians WHERE id = ?', [$musicianId]);
            $this->log('level', sprintf('%s %s ist jetzt %s.', $m['first_name'], $m['last_name'], Catalog::FAME_NAMES[$after]), $m['band_id'] === null ? null : (int) $m['band_id'], $musicianId);
        }
        return $gain;
    }

    /** Label-Ruhm: Ruhm aller eigenen Bands (Bandnamen und Mitglieder). */
    public function labelFame(): float
    {
        return (float) $this->db->value('SELECT COALESCE(SUM(fame_own), 0) FROM bands WHERE label_id = ?', [$this->labelId])
            + (float) $this->db->value('SELECT COALESCE(SUM(fame), 0) FROM musicians WHERE label_id = ? AND band_id IS NOT NULL', [$this->labelId]);
    }

    // ---------- Musiker ----------

    /** @return array<int, array> */
    public function musicians(): array
    {
        $out = [];
        foreach ($this->db->all('SELECT * FROM musicians WHERE label_id = ? ORDER BY id', [$this->labelId]) as $row) {
            $m = self::hydrateMusician($row);
            $out[$m['id']] = $m;
        }
        return $out;
    }

    public function musician(int $id): array
    {
        $row = $this->db->one('SELECT * FROM musicians WHERE id = ? AND label_id = ?', [$id, $this->labelId]);
        if ($row === null) {
            throw new ApiError('Diesen Musiker gibt es in deinem Katalog nicht.', 404);
        }
        return self::hydrateMusician($row);
    }

    /** @return list<array> */
    public function members(int $bandId): array
    {
        $rows = $this->db->all('SELECT * FROM musicians WHERE band_id = ? AND label_id = ? ORDER BY id', [$bandId, $this->labelId]);
        return array_map([self::class, 'hydrateMusician'], $rows);
    }

    /** Alle Vor- und Nachnamen von Musikern und Managern des Labels. */
    public function names(): array
    {
        $rows = $this->db->all(
            'SELECT first_name, last_name FROM musicians WHERE label_id = ? UNION ALL SELECT first_name, last_name FROM managers WHERE label_id = ?',
            [$this->labelId, $this->labelId]
        );
        return array_merge(array_column($rows, 'first_name'), array_column($rows, 'last_name'));
    }

    public function insertMusician(array $m, string $origin): int
    {
        return $this->db->insert('musicians', [
            'label_id' => $this->labelId,
            'first_name' => $m['first_name'],
            'last_name' => $m['last_name'],
            'rarity' => $m['rarity'],
            'instr1' => $m['instr1'],
            'skill1' => $m['skill1'],
            'instr2' => $m['instr2'],
            'skill2' => $m['skill2'],
            'presence' => $m['presence'],
            'fame' => $m['fame'],
            'genres' => json_encode($m['genres']),
            'traits' => '[]',
            'techniques' => '[]',
            'level' => $m['level'],
            'abilities' => json_encode($m['abilities']),
            'personality' => json_encode($m['personality']),
            'unusual' => $m['unusual'] ? 1 : 0,
            'look' => json_encode($m['look']),
            'band_id' => null,
            'band_slot' => 1,
            'busy_until' => 0,
            'origin' => $origin,
            'created_at' => Clock::now(),
        ]);
    }

    public static function hydrateMusician(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'label_id' => $r['label_id'] === null ? null : (int) $r['label_id'],
            'first_name' => $r['first_name'],
            'last_name' => $r['last_name'],
            'rarity' => $r['rarity'],
            'instr1' => $r['instr1'],
            'skill1' => (float) $r['skill1'],
            'instr2' => $r['instr2'] ?: null,
            'skill2' => $r['skill2'] === null ? null : (float) $r['skill2'],
            'presence' => (float) $r['presence'],
            'fame' => (float) $r['fame'],
            'genres' => json_decode($r['genres'], true) ?: [],
            'level' => (int) $r['level'],
            'abilities' => json_decode($r['abilities'], true) ?: [],
            'ability_choice' => $r['ability_choice'] ? json_decode($r['ability_choice'], true) : null,
            'favorite' => (bool) $r['favorite'],
            'jam_at' => (int) $r['jam_at'],
            'personality' => json_decode($r['personality'], true) ?: [],
            'unusual' => (bool) $r['unusual'],
            'look' => json_decode($r['look'], true) ?: [],
            'band_id' => $r['band_id'] === null ? null : (int) $r['band_id'],
            'band_slot' => (int) $r['band_slot'],
            'busy_until' => (int) $r['busy_until'],
            'wellbeing' => (float) ($r['wellbeing'] ?? 60),
            'origin' => $r['origin'],
            'created_at' => (int) $r['created_at'],
        ];
    }

    // ---------- Instrumente ----------

    /** @return array<int, array{id: int, model: string, musician_id: ?int}> */
    public function gear(): array
    {
        $out = [];
        foreach ($this->db->all('SELECT id, model, musician_id, klang, spielbarkeit, design, perks FROM gear WHERE label_id = ? ORDER BY id', [$this->labelId]) as $row) {
            $out[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'model' => $row['model'],
                'musician_id' => $row['musician_id'] === null ? null : (int) $row['musician_id'],
                'klang' => (int) $row['klang'],
                'spielbarkeit' => (int) $row['spielbarkeit'],
                'design' => (int) $row['design'],
                'perks' => json_decode((string) $row['perks'], true) ?: [],
            ];
        }
        return $out;
    }

    /** @return array<int, array<string, array>> Musiker-ID => [Typ => Modell] */
    public function gearByMusician(?array $gear = null): array
    {
        $map = [];
        foreach ($gear ?? $this->gear() as $item) {
            $model = Catalog::GEAR[$item['model']] ?? null;
            if ($item['musician_id'] !== null && $model !== null) {
                // Die Werte des einzelnen Instruments gehen vor die des Modells.
                $stats = ['klang' => $item['klang'], 'spielbarkeit' => $item['spielbarkeit'], 'design' => $item['design'], 'perks' => $item['perks']];
                $map[$item['musician_id']][$model['type']] = $stats + $model + ['key' => $item['model'], 'gearId' => $item['id']];
            }
        }
        return $map;
    }

    public function insertGear(string $model, ?array $stats = null): int
    {
        $stats ??= Generator::gearStats($model);
        return $this->db->insert('gear', [
            'label_id' => $this->labelId,
            'model' => $model,
            'musician_id' => null,
            'klang' => $stats['klang'],
            'spielbarkeit' => $stats['spielbarkeit'],
            'design' => $stats['design'],
            'perks' => json_encode($stats['perks'] ?? []),
            'created_at' => Clock::now(),
        ]);
    }

    // ---------- Manager ----------

    /** @return array<int, array> */
    public function managers(): array
    {
        $out = [];
        foreach ($this->db->all('SELECT * FROM managers WHERE label_id = ? ORDER BY id', [$this->labelId]) as $row) {
            $m = self::hydrateManager($row);
            $out[$m['id']] = $m;
        }
        return $out;
    }

    public function manager(int $id): array
    {
        $row = $this->db->one('SELECT * FROM managers WHERE id = ? AND label_id = ?', [$id, $this->labelId]);
        if ($row === null) {
            throw new ApiError('Diesen Manager gibt es nicht.', 404);
        }
        return self::hydrateManager($row);
    }

    public function managerBandCount(int $managerId): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM bands WHERE manager_id = ? AND label_id = ?', [$managerId, $this->labelId]);
    }

    public function insertManager(array $m): int
    {
        return $this->db->insert('managers', [
            'label_id' => $this->labelId,
            'first_name' => $m['first_name'],
            'last_name' => $m['last_name'],
            'rarity' => $m['rarity'],
            'level' => $m['level'],
            'skills' => json_encode($m['skills']),
            'look' => json_encode($m['look']),
            'created_at' => Clock::now(),
        ]);
    }

    public static function hydrateManager(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'first_name' => $r['first_name'],
            'last_name' => $r['last_name'],
            'rarity' => $r['rarity'],
            'level' => (int) $r['level'],
            'skills' => json_decode($r['skills'], true) ?: [],
            'look' => json_decode($r['look'], true) ?: [],
        ];
    }

    // ---------- Bands ----------

    /** @return array<int, array> */
    public function bands(): array
    {
        $out = [];
        foreach ($this->db->all('SELECT * FROM bands WHERE label_id = ? AND retired_at IS NULL ORDER BY id', [$this->labelId]) as $row) {
            $b = self::hydrateBand($row);
            $out[$b['id']] = $b;
        }
        return $out;
    }

    public function band(int $id): array
    {
        $row = $this->db->one('SELECT * FROM bands WHERE id = ? AND label_id = ?', [$id, $this->labelId]);
        if ($row === null) {
            throw new ApiError('Diese Band gibt es nicht.', 404);
        }
        return self::hydrateBand($row);
    }

    public static function hydrateBand(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'genre' => (string) $r['genre'],
            'genre2' => $r['genre2'] ?? null,
            'manager_id' => $r['manager_id'] === null ? null : (int) $r['manager_id'],
            'cohesion' => (float) $r['cohesion'],
            'sound' => (float) $r['sound'],
            'songs_recorded' => (int) $r['songs_recorded'],
            'records' => (int) $r['records'],
            'perfect_records' => (int) $r['perfect_records'],
            'gigs_played' => (int) $r['gigs_played'],
            'gigs_since_record' => (int) $r['gigs_since_record'],
            'live_songs_needed' => (int) $r['live_songs_needed'],
            'live_songs' => (int) ($r['live_songs'] ?? 0),
            'band_promos' => (int) ($r['band_promos'] ?? 0),
            'promo_record_id' => isset($r['promo_record_id']) ? (int) $r['promo_record_id'] : null,
            // Musiker-ID => [Instrument von vorher, Zusammenspiel-Abzug], siehe Bands::setSlot()
            'slot_return' => json_decode((string) ($r['slot_return'] ?? ''), true) ?: [],
            'released' => (bool) $r['released'],
            'retired' => ($r['retired_at'] ?? null) !== null,
            'room' => (int) ($r['room'] ?? 0),
            'fame_own' => (float) $r['fame_own'],
            'songs_written' => (float) ($r['songs_written'] ?? 0),
            'mood_at' => (int) ($r['mood_at'] ?? 0),
            'patience' => (float) ($r['patience'] ?? 1800),
            'busy_until' => (int) $r['busy_until'],
            'created_at' => (int) $r['created_at'],
        ];
    }

    /** Manager-Stufe einer Band nach Aufteilung auf alle Bands des Managers. */
    public function bandManager(array $band): array
    {
        if ($band['manager_id'] === null) {
            return [null, 0.0];
        }
        $manager = $this->manager($band['manager_id']);
        return [$manager, BandCalc::managerLevel($manager, $this->managerBandCount($manager['id']))];
    }

    // ---------- Platten ----------

    public function openRecord(int $bandId): ?array
    {
        $row = $this->db->one("SELECT * FROM records WHERE band_id = ? AND label_id = ? AND status = 'open'", [$bandId, $this->labelId]);
        return $row === null ? null : self::hydrateRecord($row);
    }

    public static function hydrateRecord(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'band_id' => (int) $r['band_id'],
            'title' => $r['title'],
            'songs_total' => (int) $r['songs_total'],
            'songs_done' => (int) $r['songs_done'],
            'song_results' => json_decode($r['song_results'], true) ?: [],
            'plan' => json_decode((string) ($r['plan'] ?? ''), true) ?: null,
            'cover' => json_decode((string) ($r['cover'] ?? ''), true) ?: null,
            'status' => $r['status'],
        ];
    }
}
