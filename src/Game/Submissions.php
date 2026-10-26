<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;
use Signed\Rng;

/**
 * Einsendungen: Musiker bewerben sich von selbst beim Label (docs/label-konzept.md). Jedes Label hat seine eigene Auswahl,
 * sie trudelt über die Zeit ein (auch offline) und verfällt nach ein paar Tagen. Aufnehmen kostet eine einmalige Unterschriftsprämie.
 */
final class Submissions
{
    public function __construct(private Repo $repo)
    {
    }

    /** Einsendungen pro Tag bei diesem Erfolg des Labels. */
    public static function perDay(float $erfolg): float
    {
        return self::curve(Balance::SUBMISSIONS['perDay'], $erfolg);
    }

    /** Gleitend zwischen Punkten [x, y]; vor dem ersten und nach dem letzten bleibt es flach. */
    public static function curve(array $points, float $erfolg): float
    {
        foreach ($points as $i => [$x, $y]) {
            if ($erfolg <= $x) {
                if ($i === 0) {
                    return (float) $y;
                }
                [$x0, $y0] = $points[$i - 1];
                return $y0 + ($y - $y0) * ($erfolg - $x0) / ($x - $x0);
            }
        }
        return (float) end($points)[1];
    }

    public static function goodShare(float $erfolg): float
    {
        $g = Balance::SUBMISSIONS['good'];
        return $g['from'] + ($g['to'] - $g['from']) * min(1.0, $erfolg / $g['at']);
    }

    public static function price(array $card): int
    {
        $p = Balance::SUBMISSIONS['price'];
        $skill = max($card['skill1'], (float) ($card['skill2'] ?? 0));
        $v = Balance::VALUE;
        $worth = max($v['min'], min($v['max'], 1 + $v['price'] * Levels::value($card)));
        return (int) round(($p['base'] + $p['skill'] * $skill ** 2 + $p['fame'] * $card['fame']) * $worth);
    }

    /** Eine Bewerbung der gewünschten Können-Stufe (0–3) mit dem Rest eines gewöhnlichen Musikers. */
    public static function make(int $level, float $fame = 0.0, array $opts = []): array
    {
        $card = Generator::musician('common', $opts + ['minSkill' => 0]);
        [$lo, $hi] = Balance::SUBMISSIONS['skill'][$level];
        $best = round(Rng::float($lo, $hi), 1);
        $card['skill1'] = $best;
        $card['skill2'] = $card['instr2'] === null ? null : round($best * Rng::float(...Balance::MULTI_SECOND_FACTOR), 1);
        $card['fame'] = round($fame, 1);
        $card['level'] = Levels::forSkill($best);
        $card['abilities'] = Levels::randomTechnique(array_filter([$card['instr1'], $card['instr2']]), $card['genres']);
        $card['ability_choice'] = null;
        return $card;
    }

    /**
     * Eine Band als Einsendung: zwei bis vier Musiker mit gemeinsamem Genre und verschiedenen Instrumenten, rund um die gewünschte Stufe.
     * Sie kennen sich schon (Beziehung beim Unterschreiben `bandBond`).
     */
    public static function makeBand(int $level, ?\Signed\Db $db = null): array
    {
        $genre = (string) Rng::pick(array_keys(Catalog::GENRES));
        $instruments = Rng::sample(['vocals', 'guitar', 'drums', 'bass', 'keys'], Rng::int(2, 4));
        $members = [];
        $avoid = [];
        foreach ($instruments as $instrument) {
            $own = max(0, min(3, $level - (Rng::chance(0.4) ? 1 : 0)));
            $card = self::make($own, 0.0, ['instrument' => $instrument, 'genre' => $genre, 'single' => true, 'avoid' => $avoid]);
            $card['instr2'] = null;
            $card['skill2'] = null;
            $avoid = array_merge($avoid, [$card['first_name'], $card['last_name']]);
            $members[] = $card;
        }
        return ['kind' => 'band', 'name' => $db !== null ? Names::band($db) : 'Die Unbekannten', 'members' => $members];
    }

    /** Eine Kassette kommt an: normalerweise im Umschlag; die Test-Knöpfe legen sie gleich ausgepackt hin. */
    private function store(array $card, int $at, ?int $openedAt = null, bool $unpacked = false): void
    {
        $this->repo->db->insert('submissions', [
            'label_id' => $this->repo->labelId,
            'card' => json_encode($card, JSON_UNESCAPED_UNICODE),
            'created_at' => $at,
            'expires_at' => $at + Balance::POST['ttl'],
            'opened_at' => $openedAt,
            'unpacked_at' => $unpacked ? $at : null,
        ]);
    }

    /** Erfolg des Labels: Summe des Ruhms aller Bands. */
    public function success(): float
    {
        $total = 0.0;
        foreach ($this->repo->bands() as $band) {
            $total += BandCalc::bandFame($band, $this->repo->members($band['id']));
        }
        return $total;
    }

    /** Neue Einsendungen für die vergangene Zeit; zu Beginn der Willkommens-Stapel (drei brauchbare, zwei Mist). */
    public function topUp(int $now): void
    {
        $db = $this->repo->db;
        $at = (int) $db->value('SELECT submit_at FROM labels WHERE id = ?', [$this->repo->labelId]);
        if ($at === 0) {
            foreach ([['vocals', 15, 34], ['guitar', 15, 34], ['drums', 15, 34], [null, 5, 12], [null, 5, 12]] as $i => [$instrument, $lo, $hi]) {
                $card = self::make(0, 0.0, $instrument === null ? [] : ['instrument' => $instrument, 'genre' => 'rock', 'single' => true]);
                $card['skill1'] = round(Rng::float($lo, $hi), 1);
                $card['skill2'] = null;
                $card['instr2'] = null;
                $card['level'] = Levels::forSkill($card['skill1']);
                $card['abilities'] = Levels::randomTechnique([$card['instr1']], $card['genres']);
                $this->store($card, $now - $i * 60);
            }
            $db->update('labels', ['submit_at' => $now + Post::gap(86400 / self::perDay(0))], ['id' => $this->repo->labelId]);
            return;
        }
        $db->exec('DELETE FROM submissions WHERE label_id = ? AND expires_at <= ?', [$this->repo->labelId, $now]);
        $erfolg = $this->success();
        $mean = 86400 / self::perDay($erfolg);
        $good = self::goodShare($erfolg);
        // submit_at ist der Zeitpunkt der nächsten Kassette; die Abstände sind zufällig (man weiß nie genau, wann etwas kommt).
        $next = max($at, $now - Balance::SUBMISSIONS['maxCatchUp']);
        for ($n = 0; $next <= $now && $n < Balance::SUBMISSIONS['maxPerCall']; $n++, $next += Post::gap($mean)) {
            $level = Rng::chance($good) ? (int) Rng::weighted(Balance::SUBMISSIONS['goodLevels']) : 0;
            $fame = $level >= 1 && Rng::chance(Balance::SUBMISSIONS['fameChance']) ? Rng::float(...Balance::SUBMISSIONS['fame']) : 0.0;
            $card = Rng::chance(Balance::SUBMISSIONS['bandChance']) ? self::makeBand($level, $this->repo->db) : self::make($level, $fame);
            $this->store($card, $next);
        }
        $db->update('labels', ['submit_at' => max($next, $now + 1)], ['id' => $this->repo->labelId]);
    }

    /** Zum Ausprobieren: sechs neue, ungehörte Kassetten (Stufen gemischt, wie sie im Alltag kämen). */
    public function testClosed(): void
    {
        $now = Clock::now();
        foreach ([0, 0, 1, 2, 0, 3] as $i => $level) {
            $this->store(self::make($level, $level >= 1 && $i % 2 === 1 ? 40.0 : 0.0), $now - $i * 20, null, true);
        }
        $this->store(self::makeBand(1, $this->repo->db), $now - 130, null, true);
    }

    /** Zum Ausprobieren: acht gesichtete Bewerbungen, zwei je Können-Stufe, darunter bekannte. */
    public function testFill(): void
    {
        $now = Clock::now();
        foreach ([0, 0, 1, 1, 2, 2, 3, 3] as $i => $level) {
            $fame = match ($i) {
                3 => 30.0, 5 => 150.0, 7 => 650.0,
                default => 0.0,
            };
            $this->store(self::make($level, $fame), $now - $i * 30, $now, true);
        }
        $this->store(self::makeBand(1, $this->repo->db), $now - 300, $now, true);
        $this->store(self::makeBand(2, $this->repo->db), $now - 330, $now, true);
    }

    /**
     * Die Einsendungen im Briefkasten, neueste zuerst. Ungehörte verraten nichts (nur Verfall); wer gerade anhört, bekommt es ab opensAt zu sehen.
     * @return list<array>
     */
    public function list(int $now): array
    {
        $rows = $this->repo->db->all('SELECT * FROM submissions WHERE label_id = ? AND expires_at > ? ORDER BY created_at DESC, id DESC', [$this->repo->labelId, $now]);
        return array_map(static function (array $r) use ($now): array {
            $base = ['submissionId' => (int) $r['id'], 'createdAt' => (int) $r['created_at'], 'expiresAt' => (int) $r['expires_at'], 'saved' => (bool) ($r['saved'] ?? 0), 'sorted' => Post::sorted($r, $now), 'box' => $r['box'] ?? null];
            // Im Umschlag: Man weiß noch nicht einmal, dass eine Kassette drin ist.
            $closed = Post::envelope($r, $now);
            if ($closed !== null) {
                return $base + ['state' => $closed, 'unpacksAt' => $r['unpacked_at'] === null ? null : (int) $r['unpacked_at'], 'byIntern' => $r['worker'] === 'intern'];
            }
            if ($r['opened_at'] === null) {
                return $base + ['state' => 'closed'];
            }
            if ((int) $r['opened_at'] > $now) {
                return $base + ['state' => 'listening', 'opensAt' => (int) $r['opened_at'], 'byIntern' => $r['worker'] === 'intern'];
            }
            $card = json_decode($r['card'], true);
            $present = [self::class, 'presentCard'];
            if (($card['kind'] ?? '') === 'band') {
                $members = [];
                foreach ($card['members'] as $i => $c) {
                    $members[] = $present($c, -((int) $r['id'] * 10 + $i + 1)) + ['index' => $i];
                }
                return $base + ['state' => 'open', 'kind' => 'band', 'bandName' => $card['name'], 'members' => $members, 'price' => array_sum(array_column($members, 'price'))];
            }
            return $present($card, -(int) $r['id']) + $base + ['state' => 'open', 'kind' => 'solo'];
        }, $rows);
    }

    /** Ein Bewerber (Einsendung oder Scout-Bericht) wie ein Musiker, mit Prämie; Charakterzüge noch verborgen. */
    public static function presentCard(array $c, int $id): array
    {
        $m = $c + ['id' => $id, 'favorite' => false, 'jam_at' => 0, 'band_id' => null, 'band_slot' => 1, 'busy_until' => 0];
        return Present::musician($m) + ['price' => self::price($c)];
    }

    /** Sichten starten: Nach ein paar Sekunden Arbeit sieht man, was drauf ist. */
    public function open(int $id): void
    {
        $row = $this->row($id);
        if (Post::envelope($row, Clock::now()) !== null) {
            throw new ApiError('Den Umschlag musst du erst auspacken.');
        }
        if ($row['opened_at'] === null) {
            if ((int) $this->repo->db->value('SELECT COUNT(*) FROM submissions WHERE label_id = ? AND opened_at > ? AND worker IS NULL', [$this->repo->labelId, Clock::now()]) > 0) {
                throw new ApiError('Du hörst gerade schon eine andere Kassette an.');
            }
            $this->repo->db->update('submissions', ['opened_at' => Clock::now() + Balance::SUBMISSIONS['listen'], 'worker' => null], ['id' => $id]);
        }
    }

    private function row(int $id): array
    {
        $row = $this->repo->db->one('SELECT * FROM submissions WHERE id = ? AND label_id = ? AND expires_at > ?', [$id, $this->repo->labelId, Clock::now()]);
        if ($row === null) {
            throw new ApiError('Diese Einsendung gibt es nicht mehr.');
        }
        return $row;
    }

    /**
     * Unterschreiben: Prämie zahlen, der Musiker ist im Label (zunächst ohne Band). Bei einer Band lassen sich alle oder einzelne Mitglieder
     * aufnehmen; die anderen bleiben auf der Einsendung. Mitglieder einer Band kennen sich schon.
     * @param list<int>|null $picks Nummern der Mitglieder (nur bei Bands); null = alle
     * @return int ID des (ersten) neuen Musikers
     */
/** „Anna, Ben und Cem“ */
    private static function names(array $names): string
    {
        return count($names) > 1 ? implode(', ', array_slice($names, 0, -1)) . ' und ' . end($names) : (string) ($names[0] ?? '');
    }

        public function sign(int $id, ?array $picks = null): int
    {
        return $this->repo->db->transaction(function () use ($id, $picks): int {
            $row = $this->row($id);
            if ($row['opened_at'] === null || (int) $row['opened_at'] > Clock::now()) {
                throw new ApiError('Die Einsendung musst du erst sichten.');
            }
            $card = json_decode($row['card'], true);
            $isBand = ($card['kind'] ?? '') === 'band';
            $cards = $isBand ? $card['members'] : [$card];
            $chosen = $isBand ? array_values(array_unique($picks ?? array_keys($cards))) : [0];
            $chosen = array_values(array_filter($chosen, static fn ($i) => isset($cards[$i])));
            if (!$chosen) {
                throw new ApiError('Wähle mindestens einen Musiker.');
            }
            $this->repo->spend((int) array_sum(array_map(static fn ($i) => self::price($cards[$i]), $chosen)), $isBand && count($chosen) > 1 ? sprintf('Unterschrift: %s', $card['name']) : sprintf('Unterschrift: %s %s', $cards[$chosen[0]]['first_name'], $cards[$chosen[0]]['last_name']));
            $ids = [];
            foreach ($chosen as $i) {
                $c = $cards[$i];
                $musicianId = $this->repo->insertMusician($c, 'submission');
                if (!empty($c['ability_choice'])) {
                    $this->repo->db->update('musicians', ['ability_choice' => json_encode($c['ability_choice'])], ['id' => $musicianId]);
                }
                $this->repo->log('submission', sprintf('%s %s hat bei uns unterschrieben.', $c['first_name'], $c['last_name']), null, $musicianId);
                $ids[] = $musicianId;
            }
            // Wer als Band kam, kennt sich schon.
            foreach ($ids as $k => $a) {
                foreach (array_slice($ids, $k + 1) as $b) {
                    $this->repo->db->insert('relations', ['label_id' => $this->repo->labelId, 'a_id' => min($a, $b), 'b_id' => max($a, $b), 'value' => Balance::SUBMISSIONS['bandBond'], 'at' => Clock::now()]);
                }
            }
            // Als Band unterschrieben: Die Mitglieder bilden gleich ihre Band (sonst steht jeder als Solokünstler da).
            if ($isBand && count($ids) > 1) {
                try {
                    (new Bands($this->repo))->create($card['name'], array_map(static fn ($musicianId) => ['id' => $musicianId, 'slot' => 1], $ids));
                } catch (ApiError) {
                    // Besetzung oder Name passen nicht: Dann bleiben sie Solokünstler und lassen sich im Label zusammenstellen.
                }
            }
            // Nicht alle genommen: Die anderen ziehen enttäuscht weiter, die Aufgenommenen sind gekränkt.
            $left = $isBand ? array_values(array_diff_key($cards, array_flip($chosen))) : [];
            if ($left) {
                $c = Balance::SUBMISSIONS;
                foreach ($chosen as $k => $i) {
                    $factor = 1.0;
                    foreach ($cards[$i]['personality'] ?? [] as $trait) {
                        $factor *= $c['splitTrait'][$trait] ?? 1.0;
                    }
                    $this->repo->db->update('musicians', ['wellbeing' => round(max(0.0, Balance::MOOD['start'] - $c['splitHurt'] * $factor), 3)], ['id' => $ids[$k]]);
                }
                $this->repo->log('submission', sprintf('%s %s ohne die anderen von %s gekommen. %s enttäuscht weitergezogen.',
                    self::names(array_map(static fn ($i) => $cards[$i]['first_name'], $chosen)), count($chosen) > 1 ? 'sind' : 'ist', $card['name'],
                    self::names(array_map(static fn ($x) => $x['first_name'], $left)) . (count($left) > 1 ? ' sind' : ' ist')), null, $ids[0]);
            }
            $this->repo->db->exec('DELETE FROM submissions WHERE id = ?', [$id]);
            return $ids[0];
        });
    }

    /**
     * Mit wem würde dieser Bewerber (bei Bands: das Mitglied mit der Nummer $index) gut auskommen, mit wem nicht? Die Beziehung, auf die es zuläuft
     * (−10…+10), für jeden Musiker des Labels. Erst nach dem Sichten.
     * @return list<array{id: int, name: string, bandId: ?int, target: float}> beste zuerst
     */
    public function compat(int $id, int $index = 0): array
    {
        $row = $this->row($id);
        if ($row['opened_at'] === null || (int) $row['opened_at'] > Clock::now()) {
            throw new ApiError('Die Einsendung musst du erst sichten.');
        }
        $card = json_decode($row['card'], true);
        $c = ($card['kind'] ?? '') === 'band' ? ($card['members'][$index] ?? null) : $card;
        if ($c === null) {
            throw new ApiError('Diesen Musiker gibt es auf der Einsendung nicht.');
        }
        $candidate = $c + ['id' => 0, 'band_slot' => 1];
        $out = [];
        foreach ($this->repo->musicians() as $m) {
            $out[] = ['id' => $m['id'], 'name' => $m['first_name'] . ' ' . $m['last_name'], 'bandId' => $m['band_id'], 'target' => round(Mood::relationTarget(Mood::fit($candidate, $m)), 1)];
        }
        usort($out, static fn ($a, $b) => $b['target'] <=> $a['target']);
        return $out;
    }

    /** Merken (oder wieder in den Eingang legen). Gemerkte laufen trotzdem ab. */
    public function save(int $id, bool $saved): void
    {
        $this->row($id);
        $this->repo->db->update('submissions', ['saved' => $saved ? 1 : 0], ['id' => $id]);
    }

    public function drop(int $id): void
    {
        $this->row($id);
        $this->repo->db->exec('DELETE FROM submissions WHERE id = ?', [$id]);
    }
}
