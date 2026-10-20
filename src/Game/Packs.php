<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Rng;

final class Packs
{
    private const RANK = ['common' => 0, 'rare' => 1, 'epic' => 2, 'legendary' => 3];

    public function __construct(private Repo $repo)
    {
    }

    /** @return list<array> die gezogenen Karten in Aufdeck-Reihenfolge */
    public function buy(string $key): array
    {
        $pack = Balance::PACKS[$key] ?? null;
        if ($pack === null) {
            throw new ApiError('Dieses Pack gibt es nicht.');
        }
        return $this->repo->db->transaction(function () use ($pack, $key): array {
            $this->repo->spend($pack['price']);
            $odds = $this->withScout($pack['odds']);
            $slots = [];
            foreach ($pack['slots'] as $slot) {
                $slots[] = [
                    'kind' => $slot === 'any' ? (string) Rng::weighted(Balance::ANY_SLOT) : $slot,
                    'rarity' => Generator::rarity($odds),
                ];
            }
            if (isset($pack['guarantee'])) {
                $slots = self::guarantee($slots, $pack['guarantee']);
            }
            return array_map(fn ($s) => $this->create($s['kind'], $s['rarity'], $key), $slots);
        });
    }

    private function create(string $kind, string $rarity, string $origin): array
    {
        if ($kind === 'gear') {
            $id = $this->repo->insertGear(Generator::gear($rarity));
            return ['kind' => 'gear', 'gear' => Present::gear($this->repo->gear()[$id])];
        }
        if ($kind === 'manager') {
            $id = $this->repo->insertManager(Generator::manager($rarity));
            return ['kind' => 'manager', 'manager' => Present::manager($this->repo->manager($id))];
        }
        $id = $this->repo->insertMusician(Generator::musician($rarity), $origin);
        return ['kind' => 'musician', 'musician' => Present::musician($this->repo->musician($id))];
    }

    /** Ein Scout unter den Managern des Labels senkt die Chance auf gewöhnliche Karten. */
    private function withScout(array $odds): array
    {
        $level = 0;
        foreach ($this->repo->managers() as $manager) {
            if (in_array('scout', $manager['skills'], true)) {
                $level = max($level, $manager['level']);
            }
        }
        if ($level > 0) {
            $odds['common'] *= max(0.4, 1 - Balance::SCOUT_SHIFT * $level);
        }
        return $odds;
    }

    private static function guarantee(array $slots, string $minimum): array
    {
        foreach ($slots as $slot) {
            if (self::RANK[$slot['rarity']] >= self::RANK[$minimum]) {
                return $slots;
            }
        }
        foreach ($slots as $i => $slot) {
            if ($slot['kind'] === 'musician') {
                $slots[$i]['rarity'] = $minimum;
                return $slots;
            }
        }
        $slots[0]['rarity'] = $minimum;
        return $slots;
    }
}
