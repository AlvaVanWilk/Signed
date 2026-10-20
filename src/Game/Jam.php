<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;
use Signed\Rng;

/**
 * Jam-Café: Ein Musiker ohne Band lernt drei Musiker kennen, die zu ihm passen (gemeinsames Genre,
 * anderes Instrument, keine Reibung im Charakter). Einer davon gründet mit ihm eine Band – oder man lehnt
 * alle ab und versucht es noch einmal. Aus dem Jam-Café kommen nur Musiker mit Können-Stufe 0.
 */
final class Jam
{
    public function __construct(private Repo $repo)
    {
    }

    /** Nach der Session: drei passende Musiker zur Auswahl. */
    public static function createOffer(Repo $repo, int $musicianId): ?array
    {
        $row = $repo->db->one('SELECT * FROM musicians WHERE id = ? AND label_id = ?', [$musicianId, $repo->labelId]);
        if ($row === null || $row['band_id'] !== null) {
            return null;
        }
        $host = Repo::hydrateMusician($row);
        $cards = self::matches($host, $repo->lastNames());
        $repo->db->exec("UPDATE jam_offers SET status = 'declined' WHERE musician_id = ? AND status = 'open'", [$musicianId]);
        $id = $repo->db->insert('jam_offers', [
            'label_id' => $repo->labelId,
            'musician_id' => $musicianId,
            'cards' => json_encode($cards, JSON_UNESCAPED_UNICODE),
            'status' => 'open',
            'created_at' => Clock::now(),
        ]);
        $repo->log('jam', sprintf('Im Jam-Café lernt %1$s %2$d Musiker kennen, die gern mit %1$s eine Band gründen würden.', $host['first_name'], count($cards)), null, $musicianId);
        return self::present(['id' => $id, 'musician_id' => $musicianId, 'cards' => json_encode($cards)]);
    }

    /** Offene Angebote des Labels für den Spielstand (nur für Musiker, die noch keine Band haben). */
    public function open(): array
    {
        $rows = $this->repo->db->all(
            "SELECT o.id, o.musician_id, o.cards FROM jam_offers o JOIN musicians m ON m.id = o.musician_id
             WHERE o.label_id = ? AND o.status = 'open' AND m.band_id IS NULL ORDER BY o.id",
            [$this->repo->labelId]
        );
        return array_map([self::class, 'present'], $rows);
    }

    /** Der gewählte Musiker unterschreibt und gründet mit dem Gastgeber eine Band. */
    public function pick(int $offerId, int $index, string $bandName, string $genre): int
    {
        return $this->repo->db->transaction(function () use ($offerId, $index, $bandName, $genre): int {
            $offer = $this->offer($offerId);
            $cards = json_decode($offer['cards'], true);
            if (!isset($cards[$index])) {
                throw new ApiError('Diesen Musiker gibt es in der Auswahl nicht.');
            }
            $card = $cards[$index];
            $host = $this->repo->musician((int) $offer['musician_id']);
            if (!in_array($genre, $host['genres'], true) || !in_array($genre, $card['genres'], true)) {
                throw new ApiError('Die Band braucht ein Genre, das beide spielen.');
            }
            $id = $this->repo->insertMusician($card, 'jam');
            $this->repo->db->update('jam_offers', ['status' => 'done'], ['id' => $offerId]);
            return (new Bands($this->repo))->create($bandName, $genre, [
                ['id' => $host['id'], 'slot' => 1],
                ['id' => $id, 'slot' => 1],
            ]);
        });
    }

    /** Alle ablehnen: Die Auswahl verfällt, der Musiker kann wieder ins Jam-Café. */
    public function decline(int $offerId): void
    {
        $offer = $this->offer($offerId);
        $this->repo->db->update('jam_offers', ['status' => 'declined'], ['id' => (int) $offer['id']]);
    }

    private function offer(int $offerId): array
    {
        $offer = $this->repo->db->one(
            "SELECT id, musician_id, cards FROM jam_offers WHERE id = ? AND label_id = ? AND status = 'open'",
            [$offerId, $this->repo->labelId]
        );
        if ($offer === null) {
            throw new ApiError('Diese Auswahl gibt es nicht mehr.');
        }
        return $offer;
    }

    /**
     * Drei verschiedene Instrumente, die der Gastgeber nicht spielt; jeder teilt ein Genre mit ihm
     * und verträgt sich mit seinem Charakter.
     */
    private static function matches(array $host, array $avoid): array
    {
        $cfg = Balance::JAM;
        $own = array_filter([$host['instr1'], $host['instr2']]);
        $cards = [];
        $used = [];
        for ($i = 0; $i < $cfg['cards']; $i++) {
            $weights = [];
            foreach (Catalog::INSTRUMENTS as $type => $instrument) {
                if (!in_array($type, $own, true) && !in_array($type, $used, true) && array_intersect($instrument['genres'], $host['genres'])) {
                    $weights[$type] = $instrument['weight'];
                }
            }
            if (!$weights) {
                break;
            }
            $type = (string) Rng::weighted($weights);
            $used[] = $type;
            $shared = array_values(array_intersect($host['genres'], Catalog::INSTRUMENTS[$type]['genres']));
            $card = Generator::musician(Generator::rarity($cfg['odds']), [
                'instrument' => $type,
                'genre' => Rng::pick($shared),
                'single' => true,
                'maxSkill' => Balance::SKILL_LEVELS[0] - 1,
                'avoid' => $avoid,
                'getsAlongWith' => $host['personality'],
            ]);
            $avoid[] = $card['last_name'];
            $cards[] = $card;
        }
        return $cards;
    }

    private static function present(array $offer): array
    {
        $cards = [];
        foreach (json_decode($offer['cards'], true) as $index => $m) {
            // Noch nicht unterschrieben: Die Karte hat keine echte ID, nur ihren Platz in der Auswahl.
            $cards[] = Present::musician($m + ['id' => $index, 'band_id' => null, 'band_slot' => 1, 'busy_until' => 0, 'ability_choice' => null, 'favorite' => false, 'jam_at' => 0]);
        }
        return ['id' => (int) $offer['id'], 'musicianId' => (int) $offer['musician_id'], 'cards' => $cards];
    }
}
