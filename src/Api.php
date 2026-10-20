<?php
declare(strict_types=1);

namespace Signed;

use Signed\Game\Activities;
use Signed\Game\Balance;
use Signed\Game\Bands;
use Signed\Game\Charts;
use Signed\Game\Jam;
use Signed\Game\Levels;
use Signed\Game\Minigames;
use Signed\Game\Names;
use Signed\Game\Packs;
use Signed\Game\Present;
use Signed\Game\Repo;
use Signed\Game\Scout;
use Signed\Game\State;
use Signed\Game\Studio;
use Signed\Game\Transfers;

/**
 * Nimmt Aktionen aus dem Browser entgegen. Jede verändernde Aktion liefert den neuen Spielstand mit,
 * damit der Browser nur eine Anfrage braucht.
 */
final class Api
{
    /** Aktion => [HTTP-Methode, Anmeldung nötig] */
    private const ROUTES = [
        'session' => ['GET', false],
        'register' => ['POST', false],
        'login' => ['POST', false],
        'logout' => ['POST', false],
        'state' => ['GET', true],
        'charts' => ['GET', true],
        'buyPack' => ['POST', true],
        'packPick' => ['POST', true],
        'scoutOpen' => ['POST', true],
        'scoutPick' => ['POST', true],
        'nameSuggest' => ['POST', true],
        'bandCreate' => ['POST', true],
        'bandRename' => ['POST', true],
        'bandAddMember' => ['POST', true],
        'bandRemoveMember' => ['POST', true],
        'bandSetSlot' => ['POST', true],
        'bandSetManager' => ['POST', true],
        'bandDisband' => ['POST', true],
        'gearAssign' => ['POST', true],
        'itemUse' => ['POST', true],
        'activityStart' => ['POST', true],
        'activityAck' => ['POST', true],
        'promoStart' => ['POST', true],
        'bandPromo' => ['POST', true],
        'soloStart' => ['POST', true],
        'jamPick' => ['POST', true],
        'transferList' => ['POST', true],
        'transferWithdraw' => ['POST', true],
        'transferBid' => ['POST', true],
        'transferAccept' => ['POST', true],
        'jamDecline' => ['POST', true],
        'musicianFavorite' => ['POST', true],
        'abilityChoose' => ['POST', true],
        'recordStart' => ['POST', true],
        'minigameStart' => ['POST', true],
        'minigameFinish' => ['POST', true],
        'minigameAbort' => ['POST', true],
    ];

    public function __construct(private Db $db, private Session $session)
    {
    }

    public function handle(string $method, string $action, array $in): array
    {
        $route = self::ROUTES[$action] ?? null;
        if ($route === null) {
            throw new ApiError('Unbekannte Aktion.', 404);
        }
        if ($method !== $route[0]) {
            throw new ApiError('Falsche Anfragemethode.', 405);
        }
        if (!$route[1]) {
            return $this->{$action}($in);
        }

        $labelId = $this->session->labelId();
        if ($labelId === null) {
            throw new ApiError('Bitte melde dich an.', 401);
        }
        $repo = new Repo($this->db, $labelId);
        try {
            $repo->label();
        } catch (ApiError $e) {
            $this->session->setLabelId(null);
            throw $e;
        }
        Activities::settleDue($this->db, $labelId);
        Charts::payRoyalties($this->db, $labelId);
        Transfers::settleDue($this->db);
        return $this->{$action}($in, $repo);
    }

    // ---------- Konto ----------

    private function session(array $in): array
    {
        $label = null;
        $id = $this->session->labelId();
        if ($id !== null) {
            try {
                $label = (new Repo($this->db, $id))->label();
            } catch (ApiError) {
                $this->session->setLabelId(null);
            }
        }
        return ['label' => $label, 'catalog' => Present::catalog()];
    }

    private function register(array $in): array
    {
        $username = mb_strtolower(trim(self::str($in, 'username')));
        $password = self::str($in, 'password');
        if (!preg_match('/^[a-z0-9_.-]{3,20}$/', $username)) {
            throw new ApiError('Der Benutzername braucht 3–20 Zeichen: Buchstaben, Ziffern, Punkt, Bindestrich oder Unterstrich.');
        }
        if (mb_strlen($password) < 8) {
            throw new ApiError('Das Passwort braucht mindestens 8 Zeichen.');
        }
        $labelName = Bands::cleanName(self::str($in, 'labelName'), 'Der Labelname');
        $nameKey = mb_strtolower($labelName);

        $id = $this->db->transaction(function () use ($username, $password, $labelName, $nameKey): int {
            if ($this->db->value('SELECT id FROM labels WHERE username = ?', [$username]) !== null) {
                throw new ApiError('Dieser Benutzername ist schon vergeben.');
            }
            if ($this->db->value('SELECT id FROM labels WHERE name_key = ?', [$nameKey]) !== null) {
                throw new ApiError('Ein Label mit diesem Namen gibt es schon.');
            }
            $id = $this->db->insert('labels', [
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'name' => $labelName,
                'name_key' => $nameKey,
                'money' => Balance::START_MONEY,
                'scout_vouchers' => Balance::SCOUT_START_VOUCHERS,
                'created_at' => Clock::now(),
                'last_seen' => Clock::now(),
            ]);
            (new Repo($this->db, $id))->log('label', sprintf('Das Label „%s“ ist gegründet. Der Talent-Scout wartet auf deinen Auftrag.', $labelName));
            return $id;
        });

        $this->session->setLabelId($id);
        return ['label' => (new Repo($this->db, $id))->label()];
    }

    private function login(array $in): array
    {
        $username = mb_strtolower(trim(self::str($in, 'username')));
        $password = self::str($in, 'password');
        $row = $this->db->one('SELECT id, password_hash FROM labels WHERE username = ?', [$username]);
        if ($row === null || !password_verify($password, $row['password_hash'])) {
            throw new ApiError('Benutzername oder Passwort stimmt nicht.', 401);
        }
        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->update('labels', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], ['id' => (int) $row['id']]);
        }
        $this->session->setLabelId((int) $row['id']);
        return ['label' => (new Repo($this->db, (int) $row['id']))->label()];
    }

    private function logout(array $in): array
    {
        $this->session->setLabelId(null);
        return [];
    }

    // ---------- Spielstand ----------

    private function state(array $in, Repo $repo): array
    {
        return State::build($repo);
    }

    private function charts(array $in, Repo $repo): array
    {
        Activities::settleDue($this->db);
        return Charts::build($this->db, $repo->labelId);
    }

    // ---------- Karten ----------

    private function buyPack(array $in, Repo $repo): array
    {
        $type = isset($in['type']) && is_string($in['type']) ? $in['type'] : null;
        $offer = (new Packs($repo))->buy(self::str($in, 'pack'), $type);
        return ['offer' => $offer, 'state' => State::build($repo)];
    }

    private function packPick(array $in, Repo $repo): array
    {
        $card = (new Packs($repo))->pick(self::int($in, 'offerId'), self::int($in, 'index'));
        return ['card' => $card, 'state' => State::build($repo)];
    }

    private function scoutOpen(array $in, Repo $repo): array
    {
        $offer = (new Scout($repo))->open(!empty($in['voucher']));
        return ['offer' => $offer, 'state' => State::build($repo)];
    }

    private function scoutPick(array $in, Repo $repo): array
    {
        $musician = (new Scout($repo))->pick(self::int($in, 'offerId'), self::int($in, 'index'));
        return ['musician' => $musician, 'state' => State::build($repo)];
    }

    private function nameSuggest(array $in, Repo $repo): array
    {
        return ['name' => ($in['kind'] ?? '') === 'band' ? Names::band($this->db) : Names::record()];
    }

    private function gearAssign(array $in, Repo $repo): array
    {
        (new Bands($repo))->assignGear(self::int($in, 'gearId'), self::optInt($in, 'musicianId'));
        return ['state' => State::build($repo)];
    }

    private function itemUse(array $in, Repo $repo): array
    {
        $value = (new Bands($repo))->useItem(self::int($in, 'itemId'), self::int($in, 'bandId'));
        return ['value' => $value, 'state' => State::build($repo)];
    }

    // ---------- Bands ----------

    private function bandCreate(array $in, Repo $repo): array
    {
        $members = is_array($in['members'] ?? null) ? array_values($in['members']) : [];
        $id = (new Bands($repo))->create(self::str($in, 'name'), $members);
        return ['bandId' => $id, 'state' => State::build($repo)];
    }

    private function bandRename(array $in, Repo $repo): array
    {
        (new Bands($repo))->rename(self::int($in, 'bandId'), self::str($in, 'name'));
        return ['state' => State::build($repo)];
    }

    private function bandAddMember(array $in, Repo $repo): array
    {
        (new Bands($repo))->addMember(self::int($in, 'bandId'), self::int($in, 'musicianId'), (int) ($in['slot'] ?? 1));
        return ['state' => State::build($repo)];
    }

    private function bandRemoveMember(array $in, Repo $repo): array
    {
        (new Bands($repo))->removeMember(self::int($in, 'bandId'), self::int($in, 'musicianId'));
        return ['state' => State::build($repo)];
    }

    private function bandSetSlot(array $in, Repo $repo): array
    {
        (new Bands($repo))->setSlot(self::int($in, 'bandId'), self::int($in, 'musicianId'), self::int($in, 'slot'));
        return ['state' => State::build($repo)];
    }

    private function bandSetManager(array $in, Repo $repo): array
    {
        (new Bands($repo))->setManager(self::int($in, 'bandId'), self::optInt($in, 'managerId'));
        return ['state' => State::build($repo)];
    }

    private function bandDisband(array $in, Repo $repo): array
    {
        (new Bands($repo))->disband(self::int($in, 'bandId'));
        return ['state' => State::build($repo)];
    }

    // ---------- Aktivitäten ----------

    private function activityStart(array $in, Repo $repo): array
    {
        $activity = (new Activities($repo))->start(self::int($in, 'bandId'), self::str($in, 'type'), (int) ($in['songs'] ?? 0));
        return ['activity' => $activity, 'state' => State::build($repo)];
    }

    private function activityAck(array $in, Repo $repo): array
    {
        (new Activities($repo))->ack(self::int($in, 'activityId'));
        return [];
    }

    private function promoStart(array $in, Repo $repo): array
    {
        $activity = (new Activities($repo))->promo(self::int($in, 'musicianId'));
        return ['activity' => $activity, 'state' => State::build($repo)];
    }

    private function bandPromo(array $in, Repo $repo): array
    {
        $activity = (new Activities($repo))->bandPromo(self::int($in, 'bandId'));
        return ['activity' => $activity, 'state' => State::build($repo)];
    }

    private function soloStart(array $in, Repo $repo): array
    {
        $activity = (new Activities($repo))->startSolo(self::int($in, 'musicianId'), self::str($in, 'type'));
        return ['activity' => $activity, 'state' => State::build($repo)];
    }

    private function jamPick(array $in, Repo $repo): array
    {
        $id = (new Jam($repo))->pick(self::int($in, 'offerId'), self::int($in, 'index'), self::str($in, 'name'));
        return ['bandId' => $id, 'state' => State::build($repo)];
    }

    private function transferList(array $in, Repo $repo): array
    {
        (new Transfers($repo))->list(self::int($in, 'musicianId'));
        return ['state' => State::build($repo)];
    }

    private function transferWithdraw(array $in, Repo $repo): array
    {
        (new Transfers($repo))->withdraw(self::int($in, 'transferId'));
        return ['state' => State::build($repo)];
    }

    private function transferBid(array $in, Repo $repo): array
    {
        (new Transfers($repo))->bid(self::int($in, 'transferId'), self::int($in, 'amount'));
        return ['state' => State::build($repo)];
    }

    private function transferAccept(array $in, Repo $repo): array
    {
        (new Transfers($repo))->accept(self::int($in, 'transferId'));
        return ['state' => State::build($repo)];
    }

    private function jamDecline(array $in, Repo $repo): array
    {
        (new Jam($repo))->decline(self::int($in, 'offerId'));
        return ['state' => State::build($repo)];
    }

    private function musicianFavorite(array $in, Repo $repo): array
    {
        $m = $repo->musician(self::int($in, 'musicianId'));
        $this->db->update('musicians', ['favorite' => empty($in['favorite']) ? 0 : 1], ['id' => $m['id']]);
        return ['state' => State::build($repo)];
    }

    private function abilityChoose(array $in, Repo $repo): array
    {
        Levels::choose($repo, self::int($in, 'musicianId'), self::str($in, 'ability'));
        return ['state' => State::build($repo)];
    }

    private function recordStart(array $in, Repo $repo): array
    {
        (new Studio($repo))->start(self::int($in, 'bandId'), self::str($in, 'title'), self::int($in, 'songs'));
        return ['state' => State::build($repo)];
    }

    private function minigameStart(array $in, Repo $repo): array
    {
        $game = (new Minigames($repo))->start(self::str($in, 'kind'), $in);
        return ['game' => $game, 'state' => State::build($repo)];
    }

    private function minigameFinish(array $in, Repo $repo): array
    {
        $result = (new Minigames($repo))->finish(
            self::int($in, 'sessionId'),
            self::int($in, 'perfect'),
            self::int($in, 'good'),
            self::int($in, 'miss')
        );
        return ['result' => $result, 'state' => State::build($repo)];
    }

    private function minigameAbort(array $in, Repo $repo): array
    {
        (new Minigames($repo))->abort(self::int($in, 'sessionId'));
        return ['state' => State::build($repo)];
    }

    // ---------- Eingaben ----------

    private static function str(array $in, string $key): string
    {
        if (!isset($in[$key]) || !is_string($in[$key])) {
            throw new ApiError('Fehlende Angabe: ' . $key);
        }
        return $in[$key];
    }

    private static function int(array $in, string $key): int
    {
        if (!isset($in[$key]) || !is_numeric($in[$key])) {
            throw new ApiError('Fehlende Angabe: ' . $key);
        }
        return (int) $in[$key];
    }

    private static function optInt(array $in, string $key): ?int
    {
        return ($in[$key] ?? null) === null || $in[$key] === '' ? null : self::int($in, $key);
    }
}
