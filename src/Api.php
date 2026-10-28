<?php
declare(strict_types=1);

namespace Signed;

use Signed\Clock;
use Signed\Game\Activities;
use Signed\Game\Assistant;
use Signed\Game\Inbox;
use Signed\Game\Balance;
use Signed\Game\Bands;
use Signed\Game\Battle;
use Signed\Game\Events;
use Signed\Game\Festival;
use Signed\Game\Catalog;
use Signed\Game\Charts;
use Signed\Game\Courses;
use Signed\Game\Etude;
use Signed\Game\Cover;
use Signed\Game\Jam;
use Signed\Game\Levels;
use Signed\Game\Minigames;
use Signed\Game\Names;
use Signed\Game\Packs;
use Signed\Game\Post;
use Signed\Game\Present;
use Signed\Game\Repo;
use Signed\Game\Scout;
use Signed\Game\Scouting;
use Signed\Game\Submissions;
use Signed\Game\State;
use Signed\Game\Store;
use Signed\Game\Rooms;
use Signed\Game\Gigs;
use Signed\Game\Wiki;
use Signed\Game\Styles;
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
        'etudePreview' => ['POST', false],
        'register' => ['POST', false],
        'login' => ['POST', false],
        'logout' => ['POST', false],
        'state' => ['GET', true],
        'charts' => ['GET', true],
        'bandView' => ['POST', true],
        'storeBuy' => ['POST', true],
        'roomUpgrade' => ['POST', true],
        'gigOptions' => ['POST', true],
        'storeBuyBasic' => ['POST', true],
        'wikiIndex' => ['POST', true],
        'wikiLabel' => ['POST', true],
        'wikiSearch' => ['POST', true],
        'wikiBand' => ['POST', true],
        'wikiSignie' => ['POST', true],
        'buyPack' => ['POST', true],
        'packPick' => ['POST', true],
        'scoutOpen' => ['POST', true],
        'scoutPick' => ['POST', true],
        'nameSuggest' => ['POST', true],
        'stageNameOptions' => ['POST', true],
        'stageNameGive' => ['POST', true],
        'bandCreate' => ['POST', true],
        'bandRename' => ['POST', true],
        'bandStageOrder' => ['POST', true],
        'bandAddMember' => ['POST', true],
        'bandRemoveMember' => ['POST', true],
        'bandSetSlot' => ['POST', true],
        'bandSetManager' => ['POST', true],
        'bandDisband' => ['POST', true],
        'gearAssign' => ['POST', true],
        'gearSell' => ['POST', true],
        'itemUse' => ['POST', true],
        'activityStart' => ['POST', true],
        'activityAck' => ['POST', true],
        'bandPromo' => ['POST', true],
        'bandRetreat' => ['POST', true],
        'promoCancel' => ['POST', true],
        'rehearsalStop' => ['POST', true],
        'bandJoinCandidates' => ['POST', true],
        'bandMerge' => ['POST', true],
        'submissionSign' => ['POST', true],
        'submissionDrop' => ['POST', true],
        'submissionOpen' => ['POST', true],
        'submissionOrder' => ['POST', true],
        'submissionSave' => ['POST', true],
        'submissionCompat' => ['POST', true],
        'submissionsTest' => ['POST', true],
        'mailAccept' => ['POST', true],
        'mailHire' => ['POST', true],
        'mailSave' => ['POST', true],
        'mailDrop' => ['POST', true],
        'mailTest' => ['POST', true],
        'assistRules' => ['POST', true],
        'assistSearch' => ['POST', true],
        'assistTask' => ['POST', true],
        'assistAdopt' => ['POST', true],
        'assistDismiss' => ['POST', true],
        'postUnpack' => ['POST', true],
        'internOrder' => ['POST', true],
        'internCancel' => ['POST', true],
        'inboxSeen' => ['POST', true],
        'postMove' => ['POST', true],
        'scoutSend' => ['POST', true],
        'scoutReportSign' => ['POST', true],
        'practiceAuto' => ['POST', true],
        'courseAttend' => ['POST', true],
        'battleEnter' => ['POST', true],
        'battleWithdraw' => ['POST', true],
        'festivalEnter' => ['POST', true],
        'festivalWithdraw' => ['POST', true],
        'soloStart' => ['POST', true],
        'jamPick' => ['POST', true],
        'coverRedraw' => ['POST', true],
        'recordOptions' => ['POST', true],
        'transferList' => ['POST', true],
        'transferWithdraw' => ['POST', true],
        'transferBid' => ['POST', true],
        'transferAccept' => ['POST', true],
        'jamDecline' => ['POST', true],
        'musicianFavorite' => ['POST', true],
        'musicianRelease' => ['POST', true],
        'navOrder' => ['POST', true],
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
        Backup::daily($this->db);
        // Veranstaltungen für alle (Battle of the Bands, Festival): beginnen und abschließen, bevor Aktionen gutgeschrieben werden.
        Events::tick($this->db, Clock::now());
        Activities::settleDue($this->db, $labelId);
        Activities::advanceRunning($this->db, $labelId);
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
            (new Repo($this->db, $id))->log('label', sprintf('Das Label „%s“ ist gegründet. Im Posteingang liegen die ersten Kassetten.', $labelName));
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

    private function bandView(array $in, Repo $repo): array
    {
        return Bands::view($this->db, self::int($in, 'bandId'), $repo->labelId);
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

    private function gigOptions(array $in, Repo $repo): array
    {
        $band = $repo->band(self::int($in, 'bandId'));
        return Gigs::options($repo, $band, $repo->members($band['id']));
    }

    private function roomUpgrade(array $in, Repo $repo): array
    {
        Rooms::upgrade($repo, self::int($in, 'bandId'));
        return ['state' => State::build($repo)];
    }

    private function storeBuy(array $in, Repo $repo): array
    {
        $res = (new Store($repo))->buy(self::int($in, 'slot'), self::int($in, 'idx'));
        return $res + ['state' => State::build($repo)];
    }

    private function storeBuyBasic(array $in, Repo $repo): array
    {
        $res = (new Store($repo))->buyBasic((string) ($in['model'] ?? ''));
        return $res + ['state' => State::build($repo)];
    }

    private function wikiIndex(array $in, Repo $repo): array
    {
        return Wiki::index($repo);
    }

    private function wikiLabel(array $in, Repo $repo): array
    {
        return Wiki::label($repo, self::int($in, 'id'));
    }

    private function wikiSearch(array $in, Repo $repo): array
    {
        return Wiki::search($repo, (string) ($in['q'] ?? ''));
    }

    private function wikiBand(array $in, Repo $repo): array
    {
        return Wiki::band($repo, self::int($in, 'id'));
    }

    private function wikiSignie(array $in, Repo $repo): array
    {
        return Wiki::signie($repo, self::int($in, 'id'));
    }

    private function stageNameOptions(array $in, Repo $repo): array
    {
        return ['options' => Names::stageOptions($repo->db, $repo->musician(self::int($in, 'musicianId')))];
    }

    private function stageNameGive(array $in, Repo $repo): array
    {
        (new Bands($repo))->giveStageName(self::int($in, 'musicianId'), trim((string) ($in['name'] ?? '')));
        return ['state' => State::build($repo)];
    }

    private function nameSuggest(array $in, Repo $repo): array
    {
        return ['name' => ($in['kind'] ?? '') === 'band' ? Names::band($this->db) : Names::record()];
    }

    private function gearSell(array $in, Repo $repo): array
    {
        $price = (new Bands($repo))->sellGear(self::int($in, 'gearId'));
        return ['price' => $price, 'state' => State::build($repo)];
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

    private function bandStageOrder(array $in, Repo $repo): array
    {
        (new Bands($repo))->setStageOrder(self::int($in, 'bandId'), is_array($in['order'] ?? null) ? $in['order'] : []);
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
        $activity = (new Activities($repo))->start(self::int($in, 'bandId'), self::str($in, 'type'), (int) ($in['songs'] ?? 0), isset($in['venue']) ? (string) $in['venue'] : null);
        return ['activity' => $activity, 'state' => State::build($repo)];
    }

    private function submissionSign(array $in, Repo $repo): array
    {
        $picks = is_array($in['members'] ?? null) ? array_map('intval', $in['members']) : null;
        $id = (new Submissions($repo))->sign(self::int($in, 'submissionId'), $picks);
        return ['musicianId' => $id, 'state' => State::build($repo)];
    }

    /** Eigene Reihenfolge der Kassetten und Visitenkarten merken. */
    private function submissionOrder(array $in, Repo $repo): array
    {
        $order = array_values(array_unique(array_map('intval', array_filter(is_array($in['order'] ?? null) ? $in['order'] : [], 'is_numeric'))));
        $this->db->update('labels', ['sub_order' => json_encode(array_slice($order, 0, 500))], ['id' => $repo->labelId]);
        return [];
    }

    private function submissionCompat(array $in, Repo $repo): array
    {
        return ['compat' => (new Submissions($repo))->compat(self::int($in, 'submissionId'), (int) ($in['index'] ?? 0))];
    }

    private function submissionOpen(array $in, Repo $repo): array
    {
        (new Submissions($repo))->open(self::int($in, 'submissionId'));
        return ['state' => State::build($repo)];
    }

    private function submissionSave(array $in, Repo $repo): array
    {
        (new Submissions($repo))->save(self::int($in, 'submissionId'), !empty($in['saved']));
        return ['state' => State::build($repo)];
    }

    private function submissionDrop(array $in, Repo $repo): array
    {
        (new Submissions($repo))->drop(self::int($in, 'submissionId'));
        return ['state' => State::build($repo)];
    }

    /** Zum Ausprobieren (nur in der Testversion im Menü): Bewerbungen aller Können-Stufen. */
    private function submissionsTest(array $in, Repo $repo): array
    {
        $tests = new Submissions($repo);
        empty($in['closed']) ? $tests->testFill() : $tests->testClosed();
        return ['state' => State::build($repo)];
    }

    private function mailAccept(array $in, Repo $repo): array
    {
        $activity = (new Inbox($repo))->accept(self::int($in, 'mailId'));
        return ['activity' => $activity, 'state' => State::build($repo)];
    }

    private function mailHire(array $in, Repo $repo): array
    {
        $target = isset($in['target']) && is_numeric($in['target']) ? (int) $in['target'] : null;
        return ['staffId' => (new Inbox($repo))->hire(self::int($in, 'mailId'), $target), 'state' => State::build($repo)];
    }

    private function mailSave(array $in, Repo $repo): array
    {
        (new Inbox($repo))->save(self::int($in, 'mailId'), !empty($in['saved']));
        return ['state' => State::build($repo)];
    }

    private function mailDrop(array $in, Repo $repo): array
    {
        (new Inbox($repo))->drop(self::int($in, 'mailId'));
        return ['state' => State::build($repo)];
    }

    /** Zum Ausprobieren: Interview-Anfragen für alle und Bewerbungen jeder Assistenz-Stufe. */
    private function mailTest(array $in, Repo $repo): array
    {
        (new Inbox($repo))->testFill();
        return ['state' => State::build($repo)];
    }

    private function scoutSend(array $in, Repo $repo): array
    {
        $pick = static fn ($v) => is_string($v) && $v !== '' ? $v : null;
        (new Scouting($repo))->send(self::int($in, 'staffId'), $pick($in['genre'] ?? null), $pick($in['instrument'] ?? null));
        return ['state' => State::build($repo)];
    }

    private function scoutReportSign(array $in, Repo $repo): array
    {
        $id = (new Scouting($repo))->sign(self::int($in, 'mailId'), (int) ($in['index'] ?? -1));
        return ['musicianId' => $id, 'state' => State::build($repo)];
    }

    private function assistTask(array $in, Repo $repo): array
    {
        (new Assistant($repo))->setTask(self::int($in, 'staffId'), self::str($in, 'task'));
        return ['state' => State::build($repo)];
    }

    private function postUnpack(array $in, Repo $repo): array
    {
        (new Post($repo))->unpack(self::str($in, 'table'), self::int($in, 'id'));
        return ['state' => State::build($repo)];
    }

    private function internOrder(array $in, Repo $repo): array
    {
        (new Assistant($repo))->internOrder(self::int($in, 'staffId'), self::str($in, 'job'));
        return ['state' => State::build($repo)];
    }

    private function internCancel(array $in, Repo $repo): array
    {
        (new Assistant($repo))->internCancel(self::int($in, 'staffId'));
        return ['state' => State::build($repo)];
    }

    /** Von Hand in ein anderes Fach gezogen. */
    private function postMove(array $in, Repo $repo): array
    {
        (new Post($repo))->move(self::str($in, 'table'), self::int($in, 'id'), self::str($in, 'box'));
        return ['state' => State::build($repo)];
    }

    /** Posteingang angesehen: Die Zahl am Menüpunkt fängt wieder bei null an. */
    private function inboxSeen(array $in, Repo $repo): array
    {
        $this->db->update('labels', ['inbox_seen_at' => Clock::now()], ['id' => $repo->labelId]);
        return [];
    }

    private function assistAdopt(array $in, Repo $repo): array
    {
        (new Assistant($repo))->adopt(self::int($in, 'staffId'));
        return ['state' => State::build($repo)];
    }

    private function assistDismiss(array $in, Repo $repo): array
    {
        (new Assistant($repo))->dismiss(self::int($in, 'staffId'));
        return ['state' => State::build($repo)];
    }

    private function assistRules(array $in, Repo $repo): array
    {
        (new Assistant($repo))->setRules(is_array($in['rules'] ?? null) ? $in['rules'] : []);
        return ['state' => State::build($repo)];
    }

    /** Suchauftrag für die Kassetten-Assistenz (search: null = nichts mehr suchen). */
    private function assistSearch(array $in, Repo $repo): array
    {
        (new Assistant($repo))->setSearch(is_array($in['search'] ?? null) ? $in['search'] : null);
        return ['state' => State::build($repo)];
    }

    private function bandJoinCandidates(array $in, Repo $repo): array
    {
        return ['candidates' => (new Bands($repo))->joinCandidates(self::int($in, 'bandId'))];
    }

    private function bandMerge(array $in, Repo $repo): array
    {
        $kept = (new Bands($repo))->merge(self::int($in, 'bandId'), self::int($in, 'otherId'), (string) ($in['keepName'] ?? 'a'), (string) ($in['newName'] ?? ''), (int) ($in['slot'] ?? 1));
        return ['bandId' => $kept, 'state' => State::build($repo)];
    }

    private function rehearsalStop(array $in, Repo $repo): array
    {
        (new Activities($repo))->stopRehearsal(self::int($in, 'activityId'));
        return ['state' => State::build($repo)];
    }

    private function activityAck(array $in, Repo $repo): array
    {
        (new Activities($repo))->ack(self::int($in, 'activityId'));
        return [];
    }

    private function courseAttend(array $in, Repo $repo): array
    {
        $activity = (new Courses($repo))->attend(self::int($in, 'musicianId'), isset($in['course']) && is_numeric($in['course']) ? (int) $in['course'] : null);
        return ['activity' => $activity, 'state' => State::build($repo)];
    }

    private function battleEnter(array $in, Repo $repo): array
    {
        Battle::enter($repo, self::int($in, 'bandId'));
        return ['state' => State::build($repo)];
    }

    private function battleWithdraw(array $in, Repo $repo): array
    {
        Battle::withdraw($repo, self::int($in, 'bandId'));
        return ['state' => State::build($repo)];
    }

    private function festivalEnter(array $in, Repo $repo): array
    {
        Festival::enter($repo, self::int($in, 'bandId'));
        return ['state' => State::build($repo)];
    }

    private function festivalWithdraw(array $in, Repo $repo): array
    {
        Festival::withdraw($repo, self::int($in, 'bandId'));
        return ['state' => State::build($repo)];
    }

    private function practiceAuto(array $in, Repo $repo): array
    {
        $activity = (new Activities($repo))->autoPractice(self::int($in, 'musicianId'), self::int($in, 'slot'));
        return ['activity' => $activity, 'state' => State::build($repo)];
    }

    private function promoCancel(array $in, Repo $repo): array
    {
        (new Activities($repo))->cancelPromo(self::int($in, 'activityId'));
        return ['state' => State::build($repo)];
    }

    private function bandPromo(array $in, Repo $repo): array
    {
        $activity = (new Activities($repo))->bandPromo(self::int($in, 'bandId'));
        return ['activity' => $activity, 'state' => State::build($repo)];
    }

    private function bandRetreat(array $in, Repo $repo): array
    {
        $activity = (new Activities($repo))->retreat(self::int($in, 'bandId'), self::str($in, 'option'));
        return ['activity' => $activity, 'state' => State::build($repo)];
    }

    private function soloStart(array $in, Repo $repo): array
    {
        $activity = (new Activities($repo))->startSolo(self::int($in, 'musicianId'), self::str($in, 'type'));
        return ['activity' => $activity, 'state' => State::build($repo)];
    }

    /** Probeseite: eine Etüde ansehen und spielen, ohne Anmeldung und ohne etwas zu speichern. */
    private function etudePreview(array $in): array
    {
        $instrument = (string) ($in['instrument'] ?? 'guitar');
        $genre = (string) ($in['genre'] ?? 'pop');
        if (!isset(Catalog::INSTRUMENTS[$instrument]) || !isset(Styles::GENRES[$genre])) {
            throw new ApiError('Unbekanntes Instrument oder Genre.');
        }
        $skill = max(0.0, min(100.0, (float) ($in['skill'] ?? 10)));
        $seed = (int) ($in['seed'] ?? random_int(1, 999999));
        $game = Etude::game($instrument, $genre, $skill, $seed, false, $instrument === 'vocals' && in_array($genre, Catalog::RAP_GENRES, true));
        return ['game' => $game + ['title' => 'Etüde', 'subtitle' => sprintf('%s · %s', Catalog::GENRES[$genre]['name'], Catalog::INSTRUMENTS[$instrument]['name']), 'seed' => $seed]];
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

    /**
     * Musiker entlassen: verlässt das Label für immer. Nur ohne Band, wenn er frei ist und nicht an der Börse steht.
     * Seine Instrumente bleiben im Lager; offene Jam-Angebote verfallen.
     */
    private function musicianRelease(array $in, Repo $repo): array
    {
        $m = $repo->musician(self::int($in, 'musicianId'));
        if ($m['band_id'] !== null) {
            // Ein Solokünstler darf gehen (seine Solo-Band löst sich auf); wer in einer Band mit anderen spielt, muss erst herausgenommen werden.
            if ((int) $this->db->value('SELECT COUNT(*) FROM musicians WHERE band_id = ?', [$m['band_id']]) > 1) {
                throw new ApiError('Erst aus der Band nehmen, dann entlassen.');
            }
            (new Bands($repo))->disband($m['band_id'], 'left');
            $m['band_id'] = null;
        }
        if ((int) $m['busy_until'] > \Signed\Clock::now()) {
            throw new ApiError(sprintf('%s ist gerade unterwegs.', $m['first_name']));
        }
        if ($this->db->value("SELECT COUNT(*) FROM transfers WHERE musician_id = ? AND status = 'open'", [$m['id']])) {
            throw new ApiError(sprintf('%s steht an der Börse. Erst zurückziehen.', $m['first_name']));
        }
        $this->db->transaction(function () use ($m, $repo): void {
            $this->db->exec('UPDATE gear SET musician_id = NULL WHERE musician_id = ?', [$m['id']]);
            $this->db->exec("UPDATE jam_offers SET status = 'declined' WHERE musician_id = ? AND status = 'open'", [$m['id']]);
            // Label 0 gibt es nicht: Der Musiker verschwindet aus allem, alte Einträge (Chronik, Platten) bleiben gültig.
            $this->db->update('musicians', ['label_id' => 0, 'favorite' => 0], ['id' => $m['id']]);
            $this->db->insert('alumni', ['label_id' => $repo->labelId, 'musician_id' => $m['id'], 'left_at' => \Signed\Clock::now(), 'reason' => 'released']);
            $repo->log('lineup', sprintf('%s %s verlässt das Label.', $m['first_name'], $m['last_name']), null, $m['id']);
        });
        return ['state' => State::build($repo)];
    }

    private function musicianFavorite(array $in, Repo $repo): array
    {
        $m = $repo->musician(self::int($in, 'musicianId'));
        $this->db->update('musicians', ['favorite' => empty($in['favorite']) ? 0 : 1], ['id' => $m['id']]);
        return ['state' => State::build($repo)];
    }

    /** Reihenfolge der Band- und Favoritenknöpfe: Liste wie ["b12", "m5"]; Unbekanntes wird verworfen. */
    private function navOrder(array $in, Repo $repo): array
    {
        $order = array_values(array_unique(array_filter(
            is_array($in['order'] ?? null) ? $in['order'] : [],
            static fn ($key) => is_string($key) && preg_match('/^[bm]\d{1,9}$/', $key) === 1
        )));
        $this->db->update('labels', ['nav_order' => json_encode(array_slice($order, 0, 200))], ['id' => $repo->labelId]);
        return ['state' => State::build($repo)];
    }

    private function recordStart(array $in, Repo $repo): array
    {
        $activity = (new Studio($repo))->start(
            self::int($in, 'bandId'),
            self::str($in, 'title'),
            self::int($in, 'songs'),
            self::int($in, 'studio'),
            (string) ($in['producer'] ?? 'none'),
            is_array($in['cover'] ?? null) ? $in['cover'] : [],
            (string) ($in['designer'] ?? 'student'),
            $in['logo'] ?? null
        );
        return ['activity' => $activity, 'state' => State::build($repo)];
    }

    /** Für den Aufnahme-Dialog: alle Studios und Produzenten mit Kosten und Schätzung, dazu drei Cover-Entwürfe. */
    private function recordOptions(array $in, Repo $repo): array
    {
        return (new Studio($repo))->options(self::int($in, 'bandId'), self::int($in, 'songs')) + (empty($in['noCovers']) ? ['covers' => Cover::drafts()] : []);
    }

    private function coverRedraw(array $in, Repo $repo): array
    {
        return ['covers' => (new Cover($repo))->redraw(), 'state' => State::build($repo)];
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
