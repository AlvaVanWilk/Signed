<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;
use Signed\Rng;

/**
 * Packs sind Orte, an denen man stöbert: Man bezahlt, sieht mehrere verdeckte Karten und darf genau eine
 * mitnehmen. Die übrigen bleiben dort. Solange eine Auswahl offen ist, gibt es kein neues Pack.
 */
final class Packs
{
    public function __construct(private Repo $repo)
    {
    }

    /** Pack kaufen: Die Karten werden gezogen und als offene Auswahl gespeichert. */
    public function buy(string $key, ?string $type = null): array
    {
        $pack = Balance::PACKS[$key] ?? null;
        if ($pack === null) {
            throw new ApiError('Dieses Pack gibt es nicht.');
        }
        if (!empty($pack['chooseType']) && !isset(Catalog::INSTRUMENTS[(string) $type])) {
            throw new ApiError('Für welches Instrument soll angefertigt werden?');
        }
        return $this->repo->db->transaction(function () use ($pack, $key, $type): array {
            if ($this->openOffer() !== null) {
                throw new ApiError('Nimm dir erst eine Karte aus deinem letzten Pack.');
            }
            $this->repo->spend($pack['price'], $pack['name']);
            $odds = $this->withScout($pack['odds']);
            $kinds = $pack['slots'] ?? array_map(static fn () => (string) Rng::weighted($pack['kinds']), range(1, $pack['choices']));
            $avoid = $this->repo->lastNames();
            $cards = [];
            foreach ($kinds as $kind) {
                $card = $this->draw($kind, Generator::rarity($odds), $key, $avoid, empty($pack['chooseType']) ? null : [$type => 1]);
                if (isset($card['data']['last_name'])) {
                    $avoid[] = $card['data']['last_name'];
                }
                $cards[] = $card;
            }
            $tip = empty($pack['tip']) || !Rng::chance(Balance::STORE_TIP_CHANCE) ? null : $this->tipFor();
            if ($tip !== null) {
                $index = Rng::int(0, count($cards) - 1);
                $cards[$index] = $this->draw('gear', Generator::rarity($odds), $key, $avoid, [$tip['type'] => 1]);
                $cards[$index]['tip'] = $tip['name'];
            }
            $id = $this->repo->db->insert('pack_offers', [
                'label_id' => $this->repo->labelId,
                'pack' => $key,
                'cards' => json_encode($cards, JSON_UNESCAPED_UNICODE),
                'status' => 'open',
                'created_at' => Clock::now(),
            ]);
            return self::present(['id' => $id, 'pack' => $key, 'cards' => json_encode($cards)]);
        });
    }

    /** Die offene Auswahl, falls es eine gibt. */
    public function open(): ?array
    {
        $offer = $this->openOffer();
        return $offer === null ? null : self::present($offer);
    }

    /** Eine Karte mitnehmen; die übrigen sind weg. */
    public function pick(int $offerId, int $index): array
    {
        return $this->repo->db->transaction(function () use ($offerId, $index): array {
            $offer = $this->openOffer();
            if ($offer === null || (int) $offer['id'] !== $offerId) {
                throw new ApiError('Diese Auswahl gibt es nicht mehr.');
            }
            $card = json_decode($offer['cards'], true)[$index] ?? null;
            if ($card === null) {
                throw new ApiError('Diese Karte gibt es in der Auswahl nicht.');
            }
            $this->repo->db->update('pack_offers', ['status' => 'done'], ['id' => $offerId]);
            $data = $card['data'];
            if ($card['kind'] === 'gear') {
                $id = $this->repo->insertGear($data['model'], $data['stats']);
                return ['kind' => 'gear', 'gear' => Present::gear($this->repo->gear()[$id])];
            }
            if ($card['kind'] === 'manager') {
                $id = $this->repo->insertManager($data);
                return ['kind' => 'manager', 'manager' => Present::manager($this->repo->manager($id))];
            }
            $id = $this->repo->insertMusician($data, $offer['pack']);
            return ['kind' => 'musician', 'musician' => Present::musician($this->repo->musician($id))];
        });
    }

    /** @param array<string, int>|null $types gewünschte Instrumentenarten mit Gewicht */
    private function draw(string $kind, string $rarity, string $origin, array $avoid, ?array $types): array
    {
        if ($kind === 'gear') {
            $model = Generator::gear($rarity, $types === null ? null : (string) Rng::weighted($types));
            return ['kind' => 'gear', 'data' => ['model' => $model, 'stats' => Generator::gearStats($model)]];
        }
        if ($kind === 'manager') {
            return ['kind' => 'manager', 'data' => Generator::manager($rarity, $avoid)];
        }
        return ['kind' => 'musician', 'data' => Generator::musician($rarity, ['avoid' => $avoid])];
    }

    /** Wem fehlt ein Instrument? Bandmitglieder (für ihr Instrument in der Band) vor Musikern ohne Band. */
    private function tipFor(): ?array
    {
        $gear = $this->repo->gearByMusician();
        $inBand = [];
        $free = [];
        foreach ($this->repo->musicians() as $m) {
            $type = $m['band_id'] !== null && (int) $m['band_slot'] === 2 && $m['instr2'] ? $m['instr2'] : $m['instr1'];
            if (!isset($gear[$m['id']][$type])) {
                $entry = ['type' => $type, 'name' => $m['first_name']];
                if ($m['band_id'] !== null) {
                    $inBand[] = $entry;
                } else {
                    $free[] = $entry;
                }
            }
        }
        $pool = $inBand ?: $free;
        return $pool ? Rng::pick($pool) : null;
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

    private function openOffer(): ?array
    {
        return $this->repo->db->one(
            "SELECT id, pack, cards FROM pack_offers WHERE label_id = ? AND status = 'open' ORDER BY id DESC",
            [$this->repo->labelId]
        );
    }

    private static function present(array $offer): array
    {
        $cards = [];
        foreach (json_decode($offer['cards'], true) as $index => $card) {
            // Noch nicht mitgenommen: Die Karte hat keine echte ID, nur ihren Platz in der Auswahl.
            $data = $card['data'];
            $cards[] = match ($card['kind']) {
                'gear' => ['kind' => 'gear', 'gear' => Present::gear(['id' => $index, 'model' => $data['model'], 'musician_id' => null] + $data['stats']), 'tip' => $card['tip'] ?? null],
                'manager' => ['kind' => 'manager', 'manager' => Present::manager($data + ['id' => $index])],
                default => ['kind' => 'musician', 'musician' => Present::musician($data + ['id' => $index, 'band_id' => null, 'band_slot' => 1, 'busy_until' => 0, 'ability_choice' => null, 'favorite' => false, 'jam_at' => 0])],
            };
        }
        return ['id' => (int) $offer['id'], 'pack' => $offer['pack'], 'cards' => $cards];
    }
}
