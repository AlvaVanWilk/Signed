<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;
use Signed\Db;

/**
 * Wechselbörse: Ein Label bietet einen Musiker ohne Band für eine feste Zeit an. Der Mindestpreis ergibt sich
 * aus seinen Werten. Andere Labels bieten verbindlich – das Geld wird dabei zurückgelegt, wer überboten wird,
 * bekommt es zurück. Am Ende geht der Musiker an das höchste Gebot; der Verkäufer kann auch vorher zuschlagen.
 */
final class Transfers
{
    public function __construct(private Repo $repo)
    {
    }

    /** Marktwert = Mindestpreis an der Börse. */
    public static function value(array $m): int
    {
        $v = Balance::TRANSFER_VALUE;
        $skill = max($m['skill1'], (float) ($m['skill2'] ?? 0));
        $value = $v['base'] + $v['skill'] * $skill ** 2 + $v['fame'] * $m['fame']
            + $v['level'] * ($m['level'] + Levels::forFame($m['fame'])) + $v['ability'] * count($m['abilities']);
        return (int) (round($value / 10) * 10);
    }

    public static function listed(Db $db, int $musicianId): bool
    {
        return (int) $db->value("SELECT COUNT(*) FROM transfers WHERE musician_id = ? AND status = 'open'", [$musicianId]) > 0;
    }

    /** Mindestgebot: Mindestpreis, sonst das aktuelle Gebot plus Schritt. */
    public static function minBid(array $t): int
    {
        if ($t['best_label_id'] === null) {
            return (int) $t['min_price'];
        }
        $best = (int) $t['best_amount'];
        return $best + max(Balance::TRANSFER['minStep'], (int) (ceil($best * Balance::TRANSFER['step'] / 10) * 10));
    }

    public function list(int $musicianId): void
    {
        $db = $this->repo->db;
        $db->transaction(function () use ($db, $musicianId): void {
            $now = Clock::now();
            $m = $this->repo->musician($musicianId);
            if ($m['band_id'] !== null) {
                throw new ApiError(sprintf('%s spielt in einer Band. An die Börse kann nur, wer keine Band hat.', $m['first_name']));
            }
            $endsAt = $now + Balance::TRANSFER['seconds'];
            if ($db->exec('UPDATE musicians SET busy_until = ?, favorite = 0 WHERE id = ? AND busy_until <= ?', [$endsAt, $m['id'], $now]) === 0) {
                throw new ApiError(sprintf('%s ist gerade beschäftigt.', $m['first_name']));
            }
            $db->exec('UPDATE gear SET musician_id = NULL WHERE musician_id = ?', [$m['id']]);
            $db->insert('transfers', [
                'label_id' => $this->repo->labelId,
                'musician_id' => $m['id'],
                'min_price' => self::value($m),
                'best_amount' => 0,
                'best_label_id' => null,
                'bids' => 0,
                'status' => 'open',
                'created_at' => $now,
                'ends_at' => $endsAt,
            ]);
            $this->repo->log('transfer', sprintf('%s steht auf der Wechselbörse.', Bands::fullName($m)), null, $m['id']);
        });
    }

    /** Zurückziehen geht nur, solange niemand geboten hat. */
    public function withdraw(int $transferId): void
    {
        $db = $this->repo->db;
        $db->transaction(function () use ($db, $transferId): void {
            $t = $this->own($transferId);
            if ($t['best_label_id'] !== null) {
                throw new ApiError('Es gibt schon ein Gebot. Jetzt kannst du nur noch zuschlagen oder warten.');
            }
            if ($db->exec("UPDATE transfers SET status = 'withdrawn', closed_at = ? WHERE id = ? AND status = 'open' AND best_label_id IS NULL", [Clock::now(), $t['id']]) === 0) {
                throw new ApiError('Gerade ist ein Gebot eingegangen.');
            }
            $db->update('musicians', ['busy_until' => 0], ['id' => (int) $t['musician_id']]);
        });
    }

    public function bid(int $transferId, int $amount): void
    {
        $db = $this->repo->db;
        $db->transaction(function () use ($db, $transferId, $amount): void {
            $now = Clock::now();
            $t = $db->one("SELECT * FROM transfers WHERE id = ? AND status = 'open'", [$transferId]);
            if ($t === null || (int) $t['ends_at'] <= $now) {
                throw new ApiError('Dieses Angebot ist schon vorbei.');
            }
            if ((int) $t['label_id'] === $this->repo->labelId) {
                throw new ApiError('Auf deine eigenen Musiker kannst du nicht bieten.');
            }
            $min = self::minBid(self::typed($t));
            if ($amount < $min) {
                throw new ApiError(sprintf('Das Gebot muss mindestens %s ₲ betragen.', number_format($min, 0, ',', '.')));
            }
            $raising = $t['best_label_id'] !== null && (int) $t['best_label_id'] === $this->repo->labelId;
            // Wer schon vorn liegt und erhöht, legt nur die Differenz dazu.
            $name = self::musicianName($db, (int) $t['musician_id']);
            $this->repo->spend($raising ? $amount - (int) $t['best_amount'] : $amount, sprintf('Gebot für %s (zurückgelegt)', $name));
            // Kurz vor Schluss verlängert jedes Gebot die Frist, damit niemand in letzter Sekunde zuschnappt.
            $endsAt = max((int) $t['ends_at'], $now + Balance::TRANSFER['snipe']);
            $claimed = $db->exec(
                "UPDATE transfers SET best_amount = ?, best_label_id = ?, bids = bids + 1, ends_at = ?
                 WHERE id = ? AND status = 'open' AND best_amount = ?",
                [$amount, $this->repo->labelId, $endsAt, $t['id'], $t['best_amount']]
            );
            if ($claimed === 0) {
                throw new ApiError('Gerade hat jemand anderes geboten. Schau dir das neue Gebot an.');
            }
            $db->update('musicians', ['busy_until' => $endsAt], ['id' => (int) $t['musician_id']]);
            if ($t['best_label_id'] !== null && !$raising) {
                $previous = new Repo($db, (int) $t['best_label_id']);
                $previous->earn((int) $t['best_amount'], sprintf('Gebot für %s zurück', $name));
                $previous->log('transfer', sprintf('Du wurdest bei %s überboten. Dein Gebot ist zurück auf dem Konto.', $name));
            }
            $this->repo->log('transfer', sprintf('Du bietest %s ₲ für %s.', number_format($amount, 0, ',', '.'), $name));
        });
    }

    /** Der Verkäufer nimmt das aktuelle Gebot an. */
    public function accept(int $transferId): void
    {
        $db = $this->repo->db;
        $db->transaction(function () use ($db, $transferId): void {
            $t = $this->own($transferId);
            if ($t['best_label_id'] === null) {
                throw new ApiError('Es gibt noch kein Gebot.');
            }
            self::close($db, $t);
        });
    }

    /** Abgelaufene Angebote aller Labels abschließen: verkaufen oder zurückgeben. */
    public static function settleDue(Db $db): void
    {
        foreach ($db->all("SELECT * FROM transfers WHERE status = 'open' AND ends_at <= ?", [Clock::now()]) as $row) {
            $db->transaction(static fn () => self::close($db, self::typed($row)));
        }
    }

    /** Für den Spielstand: Angebote anderer Labels und die eigenen. */
    public function market(): array
    {
        $rows = $this->repo->db->all(
            "SELECT t.*, l.name AS seller_name, b.name AS bidder_name, m.*, t.id AS transfer_id, t.label_id AS seller_id
             FROM transfers t
             JOIN musicians m ON m.id = t.musician_id
             JOIN labels l ON l.id = t.label_id
             LEFT JOIN labels b ON b.id = t.best_label_id
             WHERE t.status = 'open' AND t.ends_at > ?
             ORDER BY t.ends_at
             LIMIT 100",
            [Clock::now()]
        );
        $offers = [];
        $mine = [];
        foreach ($rows as $row) {
            $t = self::typed(array_merge($row, ['id' => $row['transfer_id'], 'label_id' => $row['seller_id']]));
            $musician = Repo::hydrateMusician($row);
            $entry = [
                'id' => (int) $row['transfer_id'],
                'musician' => Present::musician($musician),
                'seller' => $row['seller_name'],
                'minPrice' => $t['min_price'],
                'bestAmount' => $t['best_label_id'] === null ? null : $t['best_amount'],
                'bidder' => $row['bidder_name'],
                'leading' => $t['best_label_id'] === $this->repo->labelId,
                'bids' => (int) $row['bids'],
                'minBid' => self::minBid($t),
                'endsAt' => (int) $row['ends_at'],
            ];
            if ((int) $row['seller_id'] === $this->repo->labelId) {
                $mine[] = $entry;
            } else {
                $offers[] = $entry;
            }
        }
        return ['offers' => $offers, 'mine' => $mine];
    }

    private static function close(Db $db, array $t): void
    {
        $now = Clock::now();
        $sold = $t['best_label_id'] !== null;
        if ($db->exec("UPDATE transfers SET status = ?, closed_at = ? WHERE id = ? AND status = 'open'", [$sold ? 'sold' : 'expired', $now, $t['id']]) === 0) {
            return;
        }
        $musicianId = (int) $t['musician_id'];
        $name = self::musicianName($db, $musicianId);
        $seller = new Repo($db, (int) $t['label_id']);
        if (!$sold) {
            $db->update('musicians', ['busy_until' => 0], ['id' => $musicianId]);
            $seller->log('transfer', sprintf('%s bleibt bei dir – an der Börse hat niemand geboten.', $name), null, $musicianId);
            return;
        }
        $buyer = new Repo($db, (int) $t['best_label_id']);
        $db->update('musicians', ['label_id' => $buyer->labelId, 'band_id' => null, 'busy_until' => 0, 'favorite' => 0], ['id' => $musicianId]);
        $db->exec("UPDATE jam_offers SET status = 'declined' WHERE musician_id = ? AND status = 'open'", [$musicianId]);
        $seller->earn((int) $t['best_amount'], sprintf('Verkauf: %s', $name));
        $price = number_format((int) $t['best_amount'], 0, ',', '.');
        $seller->log('transfer', sprintf('%s wechselt für %s ₲ zu %s.', $name, $price, $buyer->label()['name']));
        $buyer->log('transfer', sprintf('%s unterschreibt für %s ₲ bei deinem Label.', $name, $price), null, $musicianId);
    }

    private function own(int $transferId): array
    {
        $t = $this->repo->db->one("SELECT * FROM transfers WHERE id = ? AND label_id = ? AND status = 'open'", [$transferId, $this->repo->labelId]);
        if ($t === null) {
            throw new ApiError('Dieses Angebot gibt es nicht mehr.');
        }
        return self::typed($t);
    }

    private static function typed(array $t): array
    {
        return [
            'id' => (int) $t['id'],
            'label_id' => (int) $t['label_id'],
            'musician_id' => (int) $t['musician_id'],
            'min_price' => (int) $t['min_price'],
            'best_amount' => (int) $t['best_amount'],
            'best_label_id' => $t['best_label_id'] === null ? null : (int) $t['best_label_id'],
            'ends_at' => (int) $t['ends_at'],
        ];
    }

    private static function musicianName(Db $db, int $id): string
    {
        $m = $db->one('SELECT first_name, last_name FROM musicians WHERE id = ?', [$id]);
        return $m === null ? 'Der Musiker' : $m['first_name'] . ' ' . $m['last_name'];
    }
}
