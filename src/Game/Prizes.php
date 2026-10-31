<?php
declare(strict_types=1);

namespace Signed\Game;

use DateTimeImmutable;
use DateTimeZone;
use Signed\ApiError;
use Signed\Clock;

/**
 * Neuer Kern: Preise vom Battle of the Bands (beschlossen, Sandra). Der Sieger wählt das Preisgeld oder den Wochenpreis, der schon vorher
 * auf dem Plakat steht und jede Woche wechselt (für alle gleich): ein blaues Instrument nach Wahl (wartet als Gutschein), eine Aufnahme
 * umsonst im Studio, das zur Ruhmstufe der Band passt (Gutschein der Band), der Proberaum eine Stufe besser oder ein Songwriting-Coach
 * für drei Tage. Bis gewählt ist, wartet die Wahl (prizes.kind = 'choice').
 */
final class Prizes
{
    public function __construct(private Repo $repo)
    {
    }

    /** Wochenpreis des Battles, das zu dieser Zeit beginnt: jede Woche ein anderer, aus der Woche ausgewürfelt. */
    public static function weekly(int $startsAt): string
    {
        $keys = array_keys(Balance::CORE_BATTLE_PRIZES['weekly']);
        $week = (new DateTimeImmutable('@' . $startsAt))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('o-W');
        return $keys[crc32('battle-' . $week) % count($keys)];
    }

    /** Der Sieger bekommt die Wahl (beim Gutschreiben des Battles). @return int ID der Wahl */
    public static function offer(Repo $repo, int $bandId, string $weekly): int
    {
        $repo->log('event', sprintf('Der Preis vom Battle wartet: %s ₲ oder %s.', number_format(Balance::CORE_BATTLE_PRIZES['winner'], 0, ',', '.'), Catalog::BATTLE_PRIZES[$weekly]['name']), $bandId);
        return $repo->db->insert('prizes', ['label_id' => $repo->labelId, 'band_id' => $bandId, 'kind' => 'choice', 'prize' => $weekly, 'created_at' => Clock::now()]);
    }

    /** Für den Spielstand: offene Wahlen und Gutscheine des Labels. */
    public static function present(Repo $repo): array
    {
        $out = [];
        foreach ($repo->db->all('SELECT * FROM prizes WHERE label_id = ? ORDER BY id', [$repo->labelId]) as $row) {
            $out[] = [
                'id' => (int) $row['id'], 'kind' => $row['kind'], 'prize' => $row['prize'],
                'bandId' => $row['band_id'] === null ? null : (int) $row['band_id'], 'money' => Balance::CORE_BATTLE_PRIZES['winner'],
            ];
        }
        return $out;
    }

    /** Wählen: 'money' (das Preisgeld) oder 'weekly' (der Wochenpreis). */
    public function choose(int $id, string $pick): void
    {
        $db = $this->repo->db;
        $db->transaction(function () use ($db, $id, $pick): void {
            $row = $db->one("SELECT * FROM prizes WHERE id = ? AND label_id = ? AND kind = 'choice'", [$id, $this->repo->labelId])
                ?? throw new ApiError('Diesen Preis gibt es nicht mehr.', 404);
            $bandId = $row['band_id'] === null ? null : (int) $row['band_id'];
            if ($pick === 'money') {
                $db->exec('DELETE FROM prizes WHERE id = ?', [$id]);
                $this->repo->earn(Balance::CORE_BATTLE_PRIZES['winner'], 'Battle of the Bands: Preisgeld');
                return;
            }
            if ($pick !== 'weekly') {
                throw new ApiError('Diesen Preis gibt es nicht.');
            }
            $prize = (string) $row['prize'];
            if ($prize === 'room_upgrade' || $prize === 'coach_days') {
                $band = $bandId === null ? null : $db->one('SELECT id FROM bands WHERE id = ? AND label_id = ?', [$bandId, $this->repo->labelId]);
                if ($band === null) {
                    throw new ApiError('Die Band gibt es nicht mehr. Nimm lieber das Preisgeld.');
                }
                $prize === 'room_upgrade' ? $this->room($bandId) : $this->coach($bandId);
                $db->exec('DELETE FROM prizes WHERE id = ?', [$id]);
            } else {
                // Gutschein: das blaue Instrument fürs ganze Label, die Aufnahme für die Band
                $db->update('prizes', ['kind' => 'voucher', 'band_id' => $prize === 'blue_instrument' ? null : $bandId], ['id' => $id]);
            }
            $this->repo->log('event', sprintf('Preis vom Battle: %s.', Catalog::BATTLE_PRIZES[$prize]['name']), $bandId);
        });
    }

    /** Blauer Gutschein: Instrument dieser Art aussuchen, es kommt ins Lager. @return int ID des Instruments */
    public function redeem(int $id, string $type): int
    {
        if (!isset(Catalog::INSTRUMENTS[$type])) {
            throw new ApiError('Dieses Instrument gibt es nicht.');
        }
        $db = $this->repo->db;
        return $db->transaction(function () use ($db, $id, $type): int {
            $row = $db->one("SELECT * FROM prizes WHERE id = ? AND label_id = ? AND kind = 'voucher' AND prize = 'blue_instrument'", [$id, $this->repo->labelId])
                ?? throw new ApiError('Diesen Gutschein gibt es nicht mehr.', 404);
            $gearId = $this->repo->insertGear(CoreTransition::model($type, Balance::CORE_BATTLE_PRIZES['weekly']['blue_instrument']['tier']));
            $db->exec('DELETE FROM prizes WHERE id = ?', [(int) $row['id']]);
            $model = (string) $db->value('SELECT model FROM gear WHERE id = ?', [$gearId]);
            $this->repo->log('card', sprintf('Gutschein vom Battle eingelöst: %s liegt im Lager.', Catalog::GEAR[$model]['name'] ?? 'ein blaues Instrument'));
            return $gearId;
        });
    }

    /** Gutschein „Aufnahme umsonst“ der Band (ID) oder null. */
    public static function production(Repo $repo, int $bandId): ?int
    {
        $id = $repo->db->value("SELECT id FROM prizes WHERE label_id = ? AND band_id = ? AND kind = 'voucher' AND prize = 'free_production' ORDER BY id LIMIT 1", [$repo->labelId, $bandId]);
        return $id === null ? null : (int) $id;
    }

    private function room(int $bandId): void
    {
        $band = $this->repo->band($bandId);
        $next = Rooms::present($this->repo, $band, count($this->repo->members($bandId)))['next'];
        if ($next === null) {
            throw new ApiError('Der Proberaum ist schon voll ausgebaut. Nimm lieber das Preisgeld.');
        }
        $this->repo->db->exec('UPDATE bands SET room = room + 1 WHERE id = ?', [$bandId]);
        $this->repo->log('band', sprintf('Der Proberaum von %s ist jetzt: %s.', $band['name'], $next['name']), $bandId);
    }

    private function coach(int $bandId): void
    {
        $c = Balance::CORE_BATTLE_PRIZES['weekly']['coach_days'];
        (new Tutors($this->repo))->book(Inbox::applicant('coach', 1.0, $c['tier']), $bandId, $c['days'] * 86400);
    }
}
