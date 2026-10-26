<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;
use Signed\Rng;

/**
 * Scouts des Labels (eingestellt über eine Bewerbung im Posteingang): mit Suchauftrag losschicken, nach ein paar Stunden liegt ein Bericht
 * mit Talenten im Posteingang. Gefunden werden Unbekannte, gut, aber keine Profis. Man darf beliebig viele unterschreiben, jeder kostet seine Prämie.
 */
final class Scouting
{
    public function __construct(private Repo $repo)
    {
    }

    /** @return list<array> eingestellte Scouts */
    public function staff(): array
    {
        return $this->repo->db->all("SELECT * FROM staff WHERE label_id = ? AND role = 'scout' ORDER BY tier DESC, id", [$this->repo->labelId]);
    }

    private function scout(int $id): array
    {
        $row = $this->repo->db->one("SELECT * FROM staff WHERE id = ? AND label_id = ? AND role = 'scout'", [$id, $this->repo->labelId]);
        if ($row === null) {
            throw new ApiError('Diesen Scout gibt es nicht.');
        }
        return $row;
    }

    /** Losschicken: Genre und Instrument (oder null = egal). */
    public function send(int $staffId, ?string $genre, ?string $instrument): void
    {
        $db = $this->repo->db;
        $db->transaction(function () use ($db, $staffId, $genre, $instrument): void {
            $row = $this->scout($staffId);
            $now = Clock::now();
            if ((int) $row['busy_until'] > $now || isset(json_decode($row['data'], true)['trip'])) {
                throw new ApiError('Der Scout ist schon unterwegs.');
            }
            if ($genre !== null && !isset(Catalog::GENRES[$genre])) {
                throw new ApiError('Unbekanntes Genre.');
            }
            if ($instrument !== null && !isset(Catalog::INSTRUMENTS[$instrument])) {
                throw new ApiError('Unbekanntes Instrument.');
            }
            $tier = Balance::SCOUT_STAFF['tiers'][(int) $row['tier']];
            $data = json_decode($row['data'], true);
            $data['trip'] = ['genre' => $genre, 'instrument' => $instrument, 'startedAt' => $now];
            $db->exec('UPDATE staff SET busy_until = ?, data = ? WHERE id = ? AND busy_until <= ?', [$now + $tier['seconds'], json_encode($data, JSON_UNESCAPED_UNICODE), $staffId, $now]);
        });
    }

    /** Zurückgekehrte Scouts legen ihren Bericht in den Posteingang (auch, wenn man offline war). */
    public function settle(int $now): void
    {
        $db = $this->repo->db;
        foreach ($this->staff() as $row) {
            $data = json_decode($row['data'], true);
            if (!isset($data['trip']) || (int) $row['busy_until'] > $now) {
                continue;
            }
            $trip = $data['trip'];
            unset($data['trip']);
            if ($db->exec('UPDATE staff SET data = ? WHERE id = ? AND data = ?', [json_encode($data, JSON_UNESCAPED_UNICODE), (int) $row['id'], $row['data']]) === 0) {
                continue;
            }
            $back = (int) $row['busy_until'];
            $tier = (int) $row['tier'];
            $cards = self::find($tier, $trip['genre'], $trip['instrument'], $this->repo->names());
            (new Inbox($this->repo))->store('report', [
                'staffId' => (int) $row['id'], 'tier' => $tier, 'name' => $data['first_name'], 'look' => $data['look'],
                'genre' => $trip['genre'], 'instrument' => $trip['instrument'], 'cards' => $cards, 'signed' => [],
            ], $back, Balance::SCOUT_STAFF['ttl']);
            $this->repo->log('scout', sprintf('%s ist zurück und stellt %d Talente vor.', $data['first_name'], count($cards)));
        }
    }

    /** Talente nach Auftrag: unbekannt, Können im Bereich der Stufe des Scouts. */
    public static function find(int $tier, ?string $genre, ?string $instrument, array $avoid = []): array
    {
        $c = Balance::SCOUT_STAFF['tiers'][$tier];
        $cards = [];
        for ($i = 0; $i < $c['candidates']; $i++) {
            $opts = ['avoid' => $avoid] + ($genre !== null ? ['genre' => $genre] : []) + ($instrument !== null ? ['instrument' => $instrument] : []);
            $card = Submissions::make(0, 0.0, $opts);
            $best = round(Rng::float(...$c['skill']), 1);
            $card['skill1'] = $best;
            $card['skill2'] = $card['instr2'] === null ? null : round($best * Rng::float(...Balance::MULTI_SECOND_FACTOR), 1);
            $card['level'] = Levels::forSkill($best);
            $card['abilities'] = Levels::randomTechnique(array_filter([$card['instr1'], $card['instr2']]), $card['genres']);
            array_push($avoid, $card['first_name'], $card['last_name']);
            $cards[] = $card;
        }
        return $cards;
    }

    /** Bericht für den Browser: Scout, Auftrag und die Talente (wer schon unterschrieben hat, ist markiert). */
    public static function presentReport(array $d, int $mailId): array
    {
        $signed = $d['signed'] ?? [];
        return [
            'scoutName' => $d['name'], 'look' => $d['look'], 'tier' => (int) $d['tier'], 'genre' => $d['genre'], 'instrument' => $d['instrument'],
            'cards' => array_map(static fn ($c, $i) => Submissions::presentCard($c, -($mailId * 10 + $i + 1)) + ['index' => $i, 'signed' => in_array($i, $signed, true)], $d['cards'], array_keys($d['cards'])),
        ];
    }

    /** Ein Talent aus dem Bericht unterschreiben. */
    public function sign(int $mailId, int $index): int
    {
        $db = $this->repo->db;
        return $db->transaction(function () use ($db, $mailId, $index): int {
            $row = $db->one("SELECT * FROM mail WHERE id = ? AND label_id = ? AND kind = 'report' AND expires_at > ?", [$mailId, $this->repo->labelId, Clock::now()]);
            if ($row === null) {
                throw new ApiError('Diesen Bericht gibt es nicht mehr.');
            }
            $d = json_decode($row['data'], true);
            $card = $d['cards'][$index] ?? null;
            if ($card === null || in_array($index, $d['signed'] ?? [], true)) {
                throw new ApiError('Dieses Talent ist nicht mehr zu haben.');
            }
            $this->repo->spend(Submissions::price($card), sprintf('Unterschrift: %s %s', $card['first_name'], $card['last_name']));
            $id = $this->repo->insertMusician($card, 'scout');
            $d['signed'][] = $index;
            $db->update('mail', ['data' => json_encode($d, JSON_UNESCAPED_UNICODE)], ['id' => $mailId]);
            $this->repo->log('submission', sprintf('%s %s hat bei uns unterschrieben (gefunden von %s).', $card['first_name'], $card['last_name'], $d['name']), null, $id);
            return $id;
        });
    }

    public function present(int $now): array
    {
        return array_map(static function ($s) use ($now) {
            $d = json_decode($s['data'], true);
            $tier = Balance::SCOUT_STAFF['tiers'][(int) $s['tier']];
            return [
                'id' => (int) $s['id'], 'tier' => (int) $s['tier'], 'firstName' => $d['first_name'], 'lastName' => $d['last_name'], 'look' => $d['look'],
                'seconds' => $tier['seconds'], 'candidates' => $tier['candidates'], 'maxSkill' => $tier['skill'][1],
                'away' => isset($d['trip']) && (int) $s['busy_until'] > $now ? ['until' => (int) $s['busy_until'], 'genre' => $d['trip']['genre'], 'instrument' => $d['trip']['instrument']] : null,
            ];
        }, $this->staff());
    }
}
