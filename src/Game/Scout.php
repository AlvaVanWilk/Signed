<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;

/**
 * Talent-Scout: fünf gute Musiker zur Auswahl, einer davon unterschreibt. Die anderen verschwinden.
 * Mit Gutschein (Label-Start, Meilensteine) oder teuer gegen Geld, dann höchstens einmal pro Woche.
 */
final class Scout
{
    public function __construct(private Repo $repo)
    {
    }

    /** Stand für den Spielstand: Gutscheine, nächster Kauf, offene Auswahl. */
    public function status(): array
    {
        $row = $this->repo->db->one('SELECT scout_vouchers, scout_bought_at FROM labels WHERE id = ?', [$this->repo->labelId]);
        $offer = $this->openOffer();
        return [
            'vouchers' => (int) ($row['scout_vouchers'] ?? 0),
            'nextPurchase' => (int) ($row['scout_bought_at'] ?? 0) + Balance::SCOUT_COOLDOWN,
            'price' => Balance::SCOUT_PRICE,
            'offer' => $offer === null ? null : self::present($offer),
        ];
    }

    public function open(bool $useVoucher): array
    {
        return $this->repo->db->transaction(function () use ($useVoucher): array {
            $existing = $this->openOffer();
            if ($existing !== null) {
                return self::present($existing);
            }
            $db = $this->repo->db;
            $now = Clock::now();
            if ($useVoucher) {
                $used = $db->exec('UPDATE labels SET scout_vouchers = scout_vouchers - 1 WHERE id = ? AND scout_vouchers > 0', [$this->repo->labelId]);
                if ($used === 0) {
                    throw new ApiError('Du hast keinen Scout-Gutschein.');
                }
            } else {
                $last = (int) $db->value('SELECT scout_bought_at FROM labels WHERE id = ?', [$this->repo->labelId]);
                if ($now < $last + Balance::SCOUT_COOLDOWN) {
                    throw new ApiError(sprintf('Der Talent-Scout ist erst am %s wieder für dich unterwegs.', date('d.m.', $last + Balance::SCOUT_COOLDOWN)));
                }
                $this->repo->spend(Balance::SCOUT_PRICE);
                $db->update('labels', ['scout_bought_at' => $now], ['id' => $this->repo->labelId]);
            }

            $cards = [];
            for ($i = 0; $i < Balance::SCOUT_CARDS; $i++) {
                $cards[] = Generator::musician(Generator::rarity(Balance::SCOUT_ODDS));
            }
            $id = $db->insert('scout_offers', [
                'label_id' => $this->repo->labelId,
                'cards' => json_encode($cards, JSON_UNESCAPED_UNICODE),
                'status' => 'open',
                'created_at' => $now,
            ]);
            return self::present(['id' => $id, 'cards' => json_encode($cards)]);
        });
    }

    /** Ein Musiker aus der Auswahl unterschreibt; die übrigen sind danach weg. */
    public function pick(int $offerId, int $index): array
    {
        return $this->repo->db->transaction(function () use ($offerId, $index): array {
            $offer = $this->openOffer();
            if ($offer === null || (int) $offer['id'] !== $offerId) {
                throw new ApiError('Diese Auswahl gibt es nicht mehr.');
            }
            $cards = json_decode($offer['cards'], true);
            if (!isset($cards[$index])) {
                throw new ApiError('Diesen Musiker gibt es in der Auswahl nicht.');
            }
            $id = $this->repo->insertMusician($cards[$index], 'scout');
            $this->repo->db->update('scout_offers', ['status' => 'done'], ['id' => $offerId]);
            $m = $this->repo->musician($id);
            $this->repo->log('scout', sprintf('Der Talent-Scout hat %s entdeckt: %s unterschreibt bei deinem Label.', Bands::fullName($m), $m['first_name']), null, $id);
            return Present::musician($m);
        });
    }

    /** Ein Gutschein als Belohnung für einen Meilenstein. */
    public static function grant(Repo $repo, string $reason): void
    {
        $repo->db->exec('UPDATE labels SET scout_vouchers = scout_vouchers + 1 WHERE id = ?', [$repo->labelId]);
        $repo->log('scout', sprintf('Scout-Gutschein erhalten: %s', $reason));
    }

    private function openOffer(): ?array
    {
        return $this->repo->db->one(
            "SELECT id, cards FROM scout_offers WHERE label_id = ? AND status = 'open' ORDER BY id DESC",
            [$this->repo->labelId]
        );
    }

    private static function present(array $offer): array
    {
        $cards = [];
        foreach (json_decode($offer['cards'], true) as $index => $m) {
            // Noch nicht unterschrieben: Die Karte hat keine echte ID, nur ihren Platz in der Auswahl.
            $cards[] = Present::musician($m + ['id' => $index, 'band_id' => null, 'band_slot' => 1, 'busy_until' => 0]);
        }
        return ['id' => (int) $offer['id'], 'cards' => $cards];
    }
}
