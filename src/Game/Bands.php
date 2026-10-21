<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;

/** Bands gründen und umbesetzen, Manager und Instrumente zuweisen, Karten einsetzen. */
final class Bands
{
    public function __construct(private Repo $repo)
    {
    }

    /** @param list<array{id: int, slot?: int}> $entries */
    public function create(string $name, array $entries): int
    {
        $name = self::cleanName($name, 'Der Bandname');
        $db = $this->repo->db;
        return $db->transaction(function () use ($db, $name, $entries): int {
            if (Names::bandTaken($db, $name)) {
                throw new ApiError(sprintf('Den Bandnamen „%s“ gibt es schon. Wie wäre es mit dem Würfel?', $name));
            }
            $this->assertSlotFree();
            $members = [];
            foreach ($entries as $entry) {
                $id = (int) ($entry['id'] ?? 0);
                if (isset($members[$id])) {
                    continue;
                }
                $m = $this->repo->musician($id);
                if ($m['band_id'] !== null) {
                    throw new ApiError(sprintf('%s spielt schon in einer Band.', $m['first_name']));
                }
                self::assertNotListed($db, $m);
                $m['band_slot'] = self::slot($m, (int) ($entry['slot'] ?? 1));
                $members[$id] = $m;
            }
            self::assertLineup($members);

            $bandId = $db->insert('bands', [
                'label_id' => $this->repo->labelId,
                'name' => $name,
                'name_key' => Names::key($name),
                'genre' => '',
                'created_at' => Clock::now(),
            ]);
            foreach ($members as $m) {
                $db->update('musicians', ['band_id' => $bandId, 'band_slot' => $m['band_slot']], ['id' => $m['id']]);
            }
            $this->repo->log('band', sprintf('Die Band „%s“ ist gegründet.', $name), $bandId);
            $this->syncIdentity($this->repo->band($bandId));
            return $bandId;
        });
    }

    public function rename(int $bandId, string $name): void
    {
        $band = $this->repo->band($bandId);
        $name = self::cleanName($name, 'Der Bandname');
        if (Names::bandTaken($this->repo->db, $name, $band['id'])) {
            throw new ApiError(sprintf('Den Bandnamen „%s“ gibt es schon.', $name));
        }
        $this->repo->db->transaction(function () use ($band, $name): void {
            if ($band['released']) {
                // Nach dem ersten Auftritt kennt man die Band unter ihrem Namen: Der Ruhm am Namen geht verloren.
                $this->repo->spend(Balance::RENAME_COST, sprintf('Namensänderung %s', $band['name']));
                $this->repo->db->update('bands', ['fame_own' => 0], ['id' => $band['id']]);
                $this->repo->log('band', sprintf('%s heißt jetzt %s. Der Ruhm des alten Namens (%s) ist weg.',
                    $band['name'], $name, number_format($band['fame_own'], 1, ',', '.')), $band['id']);
            }
            $this->repo->db->update('bands', ['name' => $name, 'name_key' => Names::key($name)], ['id' => $band['id']]);
        });
    }

    public function addMember(int $bandId, int $musicianId, int $slot): void
    {
        $this->repo->db->transaction(function () use ($bandId, $musicianId, $slot): void {
            $band = $this->idleBand($bandId);
            $m = $this->repo->musician($musicianId);
            if ($m['band_id'] !== null) {
                throw new ApiError(sprintf('%s spielt schon in einer Band.', $m['first_name']));
            }
            self::assertNotListed($this->repo->db, $m);
            $m['band_slot'] = self::slot($m, $slot);
            $members = $this->repo->members($bandId);
            $members[] = $m;
            self::assertLineup($members);

            $this->repo->db->update('musicians', ['band_id' => $bandId, 'band_slot' => $m['band_slot']], ['id' => $m['id']]);
            if ($band['released']) {
                $this->lowerCohesion($band, Balance::COHESION_ADD_PENALTY);
                $this->repo->log('lineup', sprintf('%s steigt bei %s ein.', self::fullName($m), $band['name']), $bandId, $m['id']);
            }
            $this->syncIdentity($band);
        });
    }

    public function removeMember(int $bandId, int $musicianId): void
    {
        $this->repo->db->transaction(function () use ($bandId, $musicianId): void {
            $band = $this->idleBand($bandId);
            $m = $this->repo->musician($musicianId);
            if ($m['band_id'] !== $bandId) {
                throw new ApiError(sprintf('%s spielt nicht in dieser Band.', $m['first_name']));
            }
            if (count($this->repo->members($bandId)) - 1 < Balance::MIN_MEMBERS) {
                throw new ApiError('Eine Band braucht mindestens 2 Musiker. Löse die Band stattdessen auf.');
            }
            $this->repo->db->update('musicians', ['band_id' => null, 'band_slot' => 1], ['id' => $m['id']]);
            if ($band['released']) {
                $this->lowerCohesion($band, Balance::COHESION_REMOVE_PENALTY);
                $this->repo->log('lineup', sprintf(
                    '%s verlässt %s und nimmt %s Bekanntheit mit.',
                    self::fullName($m),
                    $band['name'],
                    number_format($m['fame'], 1, ',', '.')
                ), $bandId, $m['id']);
            }
            $this->syncIdentity($band);
        });
    }

    /** Ein Multi-Instrumentalist wechselt das Instrument, das er in der Band spielt. */
    public function setSlot(int $bandId, int $musicianId, int $slot): void
    {
        $this->repo->db->transaction(function () use ($bandId, $musicianId, $slot): void {
            $band = $this->idleBand($bandId);
            $members = $this->repo->members($bandId);
            $index = array_search($musicianId, array_column($members, 'id'), true);
            if ($index === false) {
                throw new ApiError('Dieser Musiker spielt nicht in der Band.');
            }
            $slot = self::slot($members[$index], $slot);
            if ($members[$index]['band_slot'] === $slot) {
                return;
            }
            $members[$index]['band_slot'] = $slot;
            self::assertLineup($members);
            $this->repo->db->update('musicians', ['band_slot' => $slot], ['id' => $musicianId]);
            if ($band['released']) {
                $this->lowerCohesion($band, Balance::COHESION_SWITCH_PENALTY);
            }
            $this->syncIdentity($band);
        });
    }

    /**
     * Genre der Band neu bestimmen und speichern; ein Wechsel kommt in die Chronik.
     * @return array siehe BandCalc::identity()
     */
    public function syncIdentity(array $band, ?array $members = null): array
    {
        $identity = BandCalc::identity($members ?? $this->repo->members($band['id']), $band['genre'], $band['genre2']);
        $primary = $identity['primary'] ?? '';
        if ($primary !== $band['genre'] || $identity['second'] !== $band['genre2']) {
            $this->repo->db->update('bands', ['genre' => $primary, 'genre2' => $identity['second']], ['id' => $band['id']]);
            $this->repo->log('band', $identity['title'] !== null
                ? sprintf('%s ist jetzt eine %s.', $band['name'], $identity['title'])
                : sprintf('%s hat keine gemeinsame Genre-Linie mehr.', $band['name']), $band['id']);
        }
        return $identity;
    }

    /** Ist noch ein Band-Platz frei? Sonst Fehler mit dem Ruhm, ab dem der nächste dazukommt. */
    public function assertSlotFree(): void
    {
        $slots = self::slots($this->repo->labelFame());
        if (count($this->repo->bands()) >= $slots['open']) {
            throw new ApiError($slots['next'] === null
                ? 'Mehr Bands kann dein Label nicht führen.'
                : sprintf('Deine nächste Band kannst du ab %s Label-Ruhm gründen.', number_format($slots['next'], 0, ',', '.')));
        }
    }

    /** Wie viele Bands das Label führen darf und ab welchem Label-Ruhm die nächste dazukommt. */
    public static function slots(float $labelFame): array
    {
        $open = count(array_filter(Balance::BAND_SLOTS, static fn ($min) => $labelFame >= $min));
        return ['open' => $open, 'max' => count(Balance::BAND_SLOTS), 'next' => Balance::BAND_SLOTS[$open] ?? null];
    }

    public function setManager(int $bandId, ?int $managerId): void
    {
        $band = $this->repo->band($bandId);
        if ($managerId !== null) {
            $manager = $this->repo->manager($managerId);
            $others = (int) $this->repo->db->value(
                'SELECT COUNT(*) FROM bands WHERE manager_id = ? AND id <> ? AND label_id = ?',
                [$managerId, $bandId, $this->repo->labelId]
            );
            if ($others >= Balance::MANAGER_MAX_BANDS) {
                throw new ApiError(sprintf('%s managt schon %d Bands.', $manager['first_name'], Balance::MANAGER_MAX_BANDS));
            }
        }
        $this->repo->db->update('bands', ['manager_id' => $managerId], ['id' => $band['id']]);
    }

    public function disband(int $bandId): void
    {
        $this->repo->db->transaction(function () use ($bandId): void {
            $band = $this->idleBand($bandId);
            $db = $this->repo->db;
            $db->exec('UPDATE musicians SET band_id = NULL, band_slot = 1 WHERE band_id = ? AND label_id = ?', [$bandId, $this->repo->labelId]);
            $db->exec("DELETE FROM records WHERE band_id = ? AND status = 'open'", [$bandId]);
            $db->exec('DELETE FROM bands WHERE id = ?', [$bandId]);
            $this->repo->log('band', sprintf('Die Band „%s“ hat sich aufgelöst.', $band['name']), $bandId);
        });
    }

    public function assignGear(int $gearId, ?int $musicianId): void
    {
        $this->repo->db->transaction(function () use ($gearId, $musicianId): void {
            $db = $this->repo->db;
            $item = $this->repo->gear()[$gearId] ?? throw new ApiError('Dieses Instrument gibt es nicht.', 404);
            if ($musicianId === null) {
                $db->update('gear', ['musician_id' => null], ['id' => $gearId]);
                return;
            }
            $m = $this->repo->musician($musicianId);
            $type = Catalog::GEAR[$item['model']]['type'];
            if ($type !== $m['instr1'] && $type !== $m['instr2']) {
                throw new ApiError(sprintf('%s spielt kein Instrument der Art %s.', $m['first_name'], Catalog::INSTRUMENTS[$type]['name']));
            }
            // Pro Instrumentenart hat ein Musiker ein Instrument; das bisherige kommt zurück ins Lager.
            $sameType = array_keys(array_filter(Catalog::GEAR, static fn ($g) => $g['type'] === $type));
            $placeholders = implode(', ', array_fill(0, count($sameType), '?'));
            $db->exec(
                "UPDATE gear SET musician_id = NULL WHERE label_id = ? AND musician_id = ? AND model IN ($placeholders)",
                array_merge([$this->repo->labelId, $musicianId], $sameType)
            );
            $db->update('gear', ['musician_id' => $musicianId], ['id' => $gearId]);
        });
    }

    /** Fan-Karte: kleiner Ruhm-Bonus, verteilt auf alle Mitglieder der Band. */
    public function useItem(int $itemId, int $bandId): float
    {
        return $this->repo->db->transaction(function () use ($itemId, $bandId): float {
            $db = $this->repo->db;
            $item = $db->one('SELECT * FROM items WHERE id = ? AND label_id = ?', [$itemId, $this->repo->labelId]);
            if ($item === null) {
                throw new ApiError('Diese Karte gibt es nicht.', 404);
            }
            $band = $this->repo->band($bandId);
            $members = $this->repo->members($bandId);
            if (!$members) {
                throw new ApiError('Die Band hat keine Mitglieder.');
            }
            $value = (float) $item['value'];
            $this->repo->addBandFame($bandId, array_fill_keys(array_column($members, 'id'), $value / count($members)));
            $db->exec('DELETE FROM items WHERE id = ?', [$itemId]);
            $this->repo->log('card', sprintf('Fan-Karte für %s eingesetzt: +%s Ruhm.', $band['name'], number_format($value, 0, ',', '.')), $bandId);
            return $value;
        });
    }

    public static function cleanName(string $value, string $what, int $max = 40): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        $length = mb_strlen($value);
        if ($length < 1 || $length > $max) {
            throw new ApiError(sprintf('%s braucht 1 bis %d Zeichen.', $what, $max));
        }
        return $value;
    }

    private static function assertNotListed(\Signed\Db $db, array $m): void
    {
        if (Transfers::listed($db, $m['id'])) {
            throw new ApiError(sprintf('%s steht auf der Wechselbörse.', $m['first_name']));
        }
    }

    public static function fullName(array $m): string
    {
        return $m['first_name'] . ' ' . $m['last_name'];
    }

    private function idleBand(int $bandId): array
    {
        $band = $this->repo->band($bandId);
        if ($band['busy_until'] > Clock::now()) {
            throw new ApiError('Die Band ist gerade beschäftigt. Warte, bis sie fertig ist.');
        }
        return $band;
    }

    private function lowerCohesion(array $band, float $amount): void
    {
        $this->repo->db->update('bands', ['cohesion' => max(0.0, $band['cohesion'] - $amount)], ['id' => $band['id']]);
    }

    private static function slot(array $m, int $slot): int
    {
        if ($slot === 2) {
            if ($m['instr2'] === null) {
                throw new ApiError(sprintf('%s spielt nur ein Instrument.', $m['first_name']));
            }
            return 2;
        }
        return 1;
    }

    private static function assertLineup(array $members): void
    {
        $problems = BandCalc::lineupProblems(array_values($members));
        if ($problems) {
            throw new ApiError($problems[0]);
        }
    }
}
