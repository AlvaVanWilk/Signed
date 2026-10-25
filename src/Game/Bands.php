<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Clock;
use Signed\Db;

/** Bands gründen und umbesetzen, Manager und Instrumente zuweisen, Karten einsetzen. */
final class Bands
{
    public function __construct(private Repo $repo)
    {
    }

    /**
     * Öffentliche Ansicht einer Band, auch von anderen Labels (z. B. aus den Charts): Mitglieder als Karten,
     * Genre, Ruhm, Platten – und die Spuren, um reinzuhören. Nichts, was man zum Spielen gegen sie bräuchte.
     */
    public static function view(Db $db, int $bandId, int $viewerLabelId): array
    {
        $row = $db->one('SELECT b.*, l.name AS label_name FROM bands b JOIN labels l ON l.id = b.label_id WHERE b.id = ?', [$bandId]);
        if ($row === null) {
            throw new ApiError('Diese Band gibt es nicht mehr.', 404);
        }
        $repo = new Repo($db, (int) $row['label_id']);
        $band = Repo::hydrateBand($row);
        $members = $repo->members($bandId);
        $gear = $repo->gearByMusician();
        $identity = BandCalc::identity($members, $band['genre'], $band['genre2']);
        $genres = array_values(array_filter([$identity['primary'], $identity['second'], $identity['third']]));
        $stems = Music::bandStems($members, $gear, $genres);
        $records = $db->all(
            "SELECT title, cover, songs_total, perfect, rank_best, finished_at FROM records WHERE band_id = ? AND status = 'done' ORDER BY finished_at DESC LIMIT 5",
            [$bandId]
        );
        return [
            'id' => $bandId,
            'name' => $band['name'],
            'label' => $row['label_name'],
            'own' => (int) $row['label_id'] === $viewerLabelId,
            'genres' => $genres,
            'title' => $identity['title'],
            'fame' => round(BandCalc::bandFame($band, $members), 1),
            'records' => array_map(static fn ($r) => [
                'title' => $r['title'],
                'cover' => (json_decode((string) ($r['cover'] ?? ''), true) ?: [])['spec'] ?? null,
                'songs' => (int) $r['songs_total'],
                'perfect' => (bool) $r['perfect'],
                'best' => $r['rank_best'] === null ? null : (int) $r['rank_best'],
            ], $records),
            'members' => array_map(static function ($m) use ($stems) {
                $out = Present::musician($m);
                unset($out['busyUntil'], $out['value'], $out['favorite'], $out['abilityChoice']);
                $out['bandId'] = null;
                $out['stem'] = $stems[$m['id']];
                return $out;
            }, $members),
        ];
    }

    /** @param list<array{id: int, slot?: int}> $entries */
    /**
     * Jeder Künstler unter Vertrag ist in einer Band oder Solokünstler (eine Band mit einem Mitglied, benannt nach ihm).
     * Wer ohne Band dasteht (frisch unterschrieben, herausgenommen, Band aufgelöst), bekommt hier seine Solo-Band.
     */
    /** Für Tests der alten Funktionen (Jam-Café, Börse, Einzelauftritte), die Musiker ohne Band voraussetzen. */
    public static bool $autoSolo = true;

    public static function ensureSoloBands(Repo $repo): void
    {
        if (!self::$autoSolo) {
            return;
        }
        $db = $repo->db;
        foreach ($db->all('SELECT * FROM musicians WHERE label_id = ? AND band_id IS NULL', [$repo->labelId]) as $row) {
            $m = Repo::hydrateMusician($row);
            if (Transfers::listed($db, $m['id'])) {
                continue;
            }
            $name = self::fullName($m);
            for ($n = 2; Names::bandTaken($db, $name); $n++) {
                $name = sprintf('%s (%d)', self::fullName($m), $n);
            }
            $bandId = $db->insert('bands', ['label_id' => $repo->labelId, 'name' => $name, 'name_key' => Names::key($name), 'genre' => '', 'created_at' => Clock::now()]);
            $db->update('musicians', ['band_id' => $bandId, 'band_slot' => 1], ['id' => $m['id']]);
        }
    }

    /**
     * Wer allein in einer noch unveröffentlichten Solo-Band steht, darf in eine andere Band wechseln; die leere Solo-Band verschwindet.
     * @return array der Musiker ohne Band
     */
    private function freeFromSolo(array $m, ?int $targetBandId = null): array
    {
        if ($m['band_id'] === null) {
            return $m;
        }
        $db = $this->repo->db;
        $solo = $this->repo->band($m['band_id']);
        $others = (int) $db->value('SELECT COUNT(*) FROM musicians WHERE band_id = ? AND id <> ?', [$solo['id'], $m['id']]);
        if ($others > 0 || $m['band_id'] === $targetBandId) {
            throw new ApiError(sprintf('%s spielt schon in einer Band.', $m['first_name']));
        }
        if ($solo['released'] || $solo['records'] > 0) {
            throw new ApiError(sprintf('%s hat mit der eigenen Band schon etwas aufgebaut. Löse sie zuerst auf, wenn %s wechseln soll.', $m['first_name'], 'die Person'));
        }
        $db->exec('UPDATE musicians SET band_id = NULL, band_slot = 1 WHERE id = ?', [$m['id']]);
        $db->exec("DELETE FROM records WHERE band_id = ? AND status = 'open'", [$solo['id']]);
        $db->exec('DELETE FROM bands WHERE id = ?', [$solo['id']]);
        $m['band_id'] = null;
        return $m;
    }

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
                $m = $this->freeFromSolo($this->repo->musician($id));
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
            $this->repo->log('band', mb_substr(sprintf('Die Band „%s“ ist gegründet von %s.', $name, implode(', ', array_map(static fn ($m) => self::fullName($m), $members))), 0, 250), $bandId);
            $this->syncIdentity($this->repo->band($bandId));
            return $bandId;
        });
    }

    /**
     * Was das Zusammentun für diese Band bedeutet: Meldungen (nur echte Pluspunkte und Verschlechterungen) und ein Maß für das Ausmaß.
     * @param array $band Band mit ihren Vorlieben (Genre)
     * @param list<array> $members ihre Mitglieder, $group die dazukommenden
     * @return array{effects: list<array>, score: float, avg: float}
     */
    private function evaluate(array $band, array $members, array $group): array
    {
        $after = array_merge($members, $group);
        $idNow = BandCalc::identity($members, $band['genre'], $band['genre2']);
        $idAfter = BandCalc::identity($after, $band['genre'], $band['genre2']);
        $effects = [];
        // Gemeinsames Genre aller (nach dem Zusammentun)
        $shared = [];
        foreach ($idAfter['checks'] as $genre => $check) {
            if ($check['fans'] === count($after)) {
                $shared[] = $genre;
            }
        }
        if ($shared) {
            $effects[] = ['good' => true, 'text' => 'Gemeinsames Genre: ' . implode(' und ', array_map(static fn ($g) => Catalog::GENRES[$g]['name'], $shared))];
            // Besetzungs-Schablone des Genres: erfüllt der Neue ein Stück davon, oder gar das letzte?
            $genre = $idAfter['primary'] ?? $shared[0];
            $bandName = BandCalc::title($genre, null);
            if ($idNow['primary'] !== $genre && $idAfter['primary'] === $genre) {
                $effects[] = ['good' => true, 'text' => sprintf('Vervollständigt die %s', $bandName)];
            } elseif ($idAfter['primary'] === null || $idNow['primary'] !== $genre) {
                $nowReq = $idNow['checks'][$genre]['requirements'];
                foreach ($idAfter['checks'][$genre]['requirements'] as $i => $req) {
                    if (!$nowReq[$i]['met'] && $req['have'] > $nowReq[$i]['have']) {
                        $given = array_values(array_unique(array_filter(array_map([BandCalc::class, 'instrument'], $group), static fn ($t) => in_array($t, $req['instruments'], true))));
                        $effects[] = ['good' => true, 'text' => sprintf('Bringt %s für die %s', implode(' oder ', array_map(static fn ($t) => Catalog::INSTRUMENTS[$t]['name'], $given)), $bandName)];
                    }
                }
            }
        } elseif ($idAfter['bonus'] < $idNow['bonus'] - 1e-9) {
            $effects[] = ['good' => false, 'text' => sprintf('Kein gemeinsames Genre: Sound %d %%', (int) round(($idAfter['bonus'] / max(0.01, $idNow['bonus']) - 1) * 100))];
        }
        // Charakter: verstehen sie sich über das Genre hinaus gut oder reiben sie sich?
        $pairs = [];
        foreach ($group as $g) {
            foreach ($members as $m) {
                $fit = Mood::fit($g, $m);
                $genreTerm = Balance::RELATION['genres'][min(3, count(array_intersect($g['genres'], $m['genres'])))];
                $pairs[] = ['g' => $g, 'm' => $m, 'v' => Mood::relationTarget($fit), 'own' => $fit - $genreTerm];
            }
        }
        $avg = $pairs ? array_sum(array_column($pairs, 'v')) / count($pairs) : 0.0;
        $teamplayer = array_values(array_filter($group, static fn ($g) => in_array('teamplayer', $g['personality'], true)));
        if ($teamplayer) {
            $effects[] = ['good' => true, 'text' => sprintf('%s ist Teamplayer und hilft allen', $teamplayer[0]['first_name'])];
        } else {
            usort($pairs, static fn ($a, $b) => $b['own'] <=> $a['own']);
            if ($pairs && $pairs[0]['own'] >= 0.2) {
                $effects[] = ['good' => true, 'text' => sprintf('%s und %s verstehen sich gut', $pairs[0]['g']['first_name'], $pairs[0]['m']['first_name'])];
            }
        }
        usort($pairs, static fn ($a, $b) => $a['v'] <=> $b['v']);
        if ($pairs && $pairs[0]['v'] <= -3) {
            $effects[] = ['good' => false, 'text' => sprintf('%s und %s würden sich reiben', $pairs[0]['g']['first_name'], $pairs[0]['m']['first_name'])];
        }
        // Ausmaß der Veränderung, nur was am Zusammentun selbst hängt: Genre-Bonus (in %) und Verständnis (neutral = 3, der Startwert für Fremde).
        // Das Können des Neuen zählt nicht, das ist eine Frage der Person und nicht der Passung.
        $score = round(($idAfter['bonus'] / max(0.01, $idNow['bonus']) - 1) * 100 + 2 * ($avg - 3.0), 1);
        return ['effects' => $effects, 'score' => $score, 'avg' => $avg];
    }

    /**
     * Wer passt zu dieser Band? Alle Solokünstler und anderen Bands, die sich zusammentun ließen. Bewertet wird für beide Seiten
     * (die andere Band hat auch etwas davon oder davon zu befürchten); sortiert vom größten Gewinn über neutral bis zum größten Verlust.
     * @return list<array<string, mixed>>
     */
    public function joinCandidates(int $bandId): array
    {
        $band = $this->repo->band($bandId);
        $members = $this->repo->members($bandId);
        $now = Clock::now();
        $out = [];
        foreach ($this->repo->bands() as $other) {
            if ($other['id'] === $bandId) {
                continue;
            }
            $group = $this->repo->members($other['id']);
            if (!$group) {
                continue;
            }
            $solo = count($group) === 1;
            $blocked = null;
            if ($other['busy_until'] > $now || $band['busy_until'] > $now) {
                $blocked = 'Gerade beschäftigt.';
            } elseif ($problems = BandCalc::lineupProblems(array_merge($members, $group))) {
                $blocked = $problems[0];
            }
            $mine = $this->evaluate($band, $members, $group);
            // Aus Sicht der anderen Band (ein Einzelmusiker hat keine Band-Vorteile oder -Nachteile zu verlieren)
            $theirs = $solo ? null : $this->evaluate($other, $group, $members);
            $score = $theirs === null ? $mine['score'] : round(($mine['score'] + $theirs['score']) / 2, 1);
            $out[] = [
                'kind' => $solo ? 'musician' : 'band',
                'bandId' => $other['id'],
                'musicianId' => $solo ? $group[0]['id'] : null,
                'name' => $solo ? self::fullName($group[0]) : $other['name'],
                'memberIds' => array_column($group, 'id'),
                'blocked' => $blocked,
                'score' => $score,
                'rating' => $score > 1.0 ? 'better' : ($score < -1.0 ? 'worse' : 'neutral'),
                'compat' => round($mine['avg'], 1),
                'effects' => $mine['effects'],
                'effectsOther' => $theirs['effects'] ?? [],
                'nameOptions' => self::nameOptions($band, count($members), $other, count($group)),
            ];
        }
        // Unmögliches zuletzt, sonst vom größten Gewinn über neutral bis zum größten Verlust.
        usort($out, static fn ($a, $b) => [$a['blocked'] === null ? 0 : 1, -$a['score']] <=> [$b['blocked'] === null ? 0 : 1, -$b['score']]);
        return array_slice($out, 0, 80);
    }

    /** Eigene Laufbahn: Auftritte oder Platten, also ein Name, der etwas wert ist. */
    private static function hasCareer(array $band): bool
    {
        return $band['released'] || $band['records'] > 0;
    }

    /** Bandnamen, die ein Zusammentun fortführen kann (ein Einzelmusiker hat keinen Namen, den man behalten könnte). */
    private static function nameOptions(array $a, int $countA, array $b, int $countB): array
    {
        $out = [];
        if ($countA > 1) {
            $out[] = 'a';
        }
        if ($countB > 1) {
            $out[] = 'b';
        }
        $out[] = 'new';
        $out[] = 'combo';
        return $out;
    }

    /**
     * Archiviert eine Band, die ihren Namen aufgibt, als „ehemalige Band“: Name, Platten und Verlauf bleiben unter dem alten Namen,
     * der Ruhm am Namen ist weg. Ohne eigene Laufbahn gibt es nichts zu bewahren.
     * $continue = die Band spielt unter neuem Namen weiter (dann bleibt ihre Zeile bestehen, die Platten ziehen in die ehemalige Band um).
     */
    private function giveUpName(array $band, string $reason, string $note, bool $continue): void
    {
        $db = $this->repo->db;
        $members = $this->repo->members($band['id']);
        $db->exec("DELETE FROM records WHERE band_id = ? AND status = 'open'", [$band['id']]);
        if (!self::hasCareer($band)) {
            if (!$continue) {
                $db->exec('DELETE FROM bands WHERE id = ?', [$band['id']]);
            }
            return;
        }
        if (!$continue) {
            $db->update('bands', [
                'retired_at' => Clock::now(), 'retired_reason' => $reason, 'retired_note' => mb_substr($note, 0, 200),
                'fame_final' => $band['fame_own'], 'fame_own' => 0, 'manager_id' => null, 'busy_until' => 0, 'promo_record_id' => null,
            ], ['id' => $band['id']]);
            $this->logLastMembers($members, $band['name'], $band['id']);
            return;
        }
        $shell = $db->one('SELECT * FROM bands WHERE id = ?', [$band['id']]);
        unset($shell['id']);
        $shell['retired_at'] = Clock::now();
        $shell['retired_reason'] = $reason;
        $shell['retired_note'] = mb_substr($note, 0, 200);
        $shell['fame_final'] = $band['fame_own'];
        $shell['fame_own'] = 0;
        $shell['manager_id'] = null;
        $shell['busy_until'] = 0;
        $shell['promo_record_id'] = null;
        $shellId = $db->insert('bands', $shell);
        $db->exec('UPDATE records SET band_id = ? WHERE band_id = ?', [$shellId, $band['id']]);
        $db->exec('UPDATE history SET band_id = ? WHERE band_id = ?', [$shellId, $band['id']]);
        $db->update('bands', [
            'fame_own' => 0, 'records' => 0, 'perfect_records' => 0, 'songs_recorded' => 0, 'gigs_since_record' => 0,
            'gigs_played' => 0, 'promo_record_id' => null, 'released' => 0, 'live_songs' => 0, 'live_songs_needed' => 0, 'band_promos' => 0,
        ], ['id' => $band['id']]);
        $this->logLastMembers($members, $band['name'], $shellId);
    }

    /** Wer zuletzt dabei war, steht in der Geschichte der ehemaligen Band und im Artikel des Künstlers. */
    private function logLastMembers(array $members, string $bandName, int $bandId): void
    {
        foreach ($members as $m) {
            $this->repo->log('lineup', mb_substr(sprintf('%s war Mitglied von „%s“, als der Name aufgegeben wurde.', self::fullName($m), $bandName), 0, 250), $bandId, $m['id']);
        }
    }

    /**
     * Zwei Bands (oder Solokünstler) tun sich zusammen. Welcher Name weitermacht, entscheiden die Spielerin und der Spieler:
     * 'a' (Name dieser Band), 'b' (Name der anderen) oder 'new' (neuer Name). Ein Einzelmusiker hat keinen Namen, der bleiben könnte.
     * Wer seinen Namen aufgibt, verliert den Ruhm daran; seine Platten bleiben bei der ehemaligen Band (Tantiemen laufen weiter).
     * @return int die Band, die weiterbesteht
     */
    public function merge(int $bandId, int $otherId, string $keepName = 'a', string $newName = '', int $slot = 1): int
    {
        return $this->repo->db->transaction(function () use ($bandId, $otherId, $keepName, $newName): int {
            if ($bandId === $otherId) {
                throw new ApiError('Das ist dieselbe Band.');
            }
            $a = $this->idleBand($bandId);
            $b = $this->idleBand($otherId);
            $groupA = $this->repo->members($bandId);
            $groupB = $this->repo->members($otherId);
            if (!in_array($keepName, self::nameOptions($a, count($groupA), $b, count($groupB)), true)) {
                throw new ApiError('Diesen Namen kann die Band nicht weiterführen.');
            }
            self::assertLineup(array_merge($groupA, $groupB));
            $db = $this->repo->db;
            [$keep, $gone, $joining] = $keepName === 'b' ? [$b, $a, $groupA] : [$a, $b, $groupB];
            $finalName = $keep['name'];
            $renamed = in_array($keepName, ['new', 'combo'], true);
            if ($renamed) {
                $finalName = self::cleanName($newName, 'Der Bandname');
                if (Names::bandTaken($db, $finalName, null)) {
                    throw new ApiError(sprintf('Den Bandnamen „%s“ gibt es schon.', $finalName));
                }
            }
            // Wer den Namen aufgibt: ehemalige Band mit allem, was daran hängt.
            $this->giveUpName($gone, 'merged', $renamed ? sprintf('ging mit „%s“ in „%s“ auf', $keep['name'], $finalName) : sprintf('ging in „%s“ auf', $keep['name']), false);
            if ($renamed) {
                $this->giveUpName($keep, 'renamed', sprintf('heißt jetzt „%s“', $finalName), true);
                // Ein verbundener Name („Solo & Band“) behält einen Teil des Ruhms beider Namen, ein ganz neuer nichts.
                $carry = $keepName === 'combo' ? round(Balance::NAME_COMBO_KEEP * ($a['fame_own'] + $b['fame_own']), 1) : 0.0;
                $db->update('bands', ['name' => $finalName, 'name_key' => Names::key($finalName), 'fame_own' => $carry], ['id' => $keep['id']]);
            }
            $db->exec('UPDATE musicians SET band_id = ?, band_slot = band_slot WHERE band_id = ?', [$keep['id'], $gone['id']]);
            // Ein Einzelmusiker mit zwei Instrumenten sagt, welches er in der neuen Band spielt.
            if (count($joining) === 1 && $joining[0]['instr2'] !== null) {
                $db->update('musicians', ['band_slot' => self::slot($joining[0], $slot)], ['id' => $joining[0]['id']]);
            }
            foreach ($joining as $m) {
                $this->repo->log('lineup', mb_substr(sprintf('%s kommt von „%s“ zu „%s“.', self::fullName($m), $gone['name'], $finalName), 0, 250), $keep['id'], $m['id']);
            }
            $this->syncIdentity($this->repo->band($keep['id']));
            return $keep['id'];
        });
    }

    /** Umbenennen kostet kein Geld, aber den Ruhm am Namen; die Platten bleiben beim alten Namen (ehemalige Band, Tantiemen laufen weiter). */
    public function rename(int $bandId, string $name): void
    {
        $band = $this->repo->band($bandId);
        $name = self::cleanName($name, 'Der Bandname');
        if (Names::bandTaken($this->repo->db, $name, $band['id'])) {
            throw new ApiError(sprintf('Den Bandnamen „%s“ gibt es schon.', $name));
        }
        $this->repo->db->transaction(function () use ($band, $name): void {
            if (self::hasCareer($band)) {
                $this->giveUpName($band, 'renamed', sprintf('heißt jetzt „%s“', $name), true);
                $this->repo->log('band', sprintf('%s heißt jetzt %s. Der Ruhm des alten Namens (%s) ist weg, die Platten bleiben unter „%s“.',
                    $band['name'], $name, number_format($band['fame_own'], 1, ',', '.'), $band['name']), $band['id']);
            }
            $this->repo->db->update('bands', ['name' => $name, 'name_key' => Names::key($name)], ['id' => $band['id']]);
        });
    }

    public function addMember(int $bandId, int $musicianId, int $slot): void
    {
        $this->repo->db->transaction(function () use ($bandId, $musicianId, $slot): void {
            $band = $this->idleBand($bandId);
            $m = $this->freeFromSolo($this->repo->musician($musicianId), $bandId);
            self::assertNotListed($this->repo->db, $m);
            $m['band_slot'] = self::slot($m, $slot);
            $members = $this->repo->members($bandId);
            $members[] = $m;
            self::assertLineup($members);

            $this->repo->db->update('musicians', ['band_id' => $bandId, 'band_slot' => $m['band_slot']], ['id' => $m['id']]);
            $this->repo->log('lineup', sprintf('%s steigt bei %s ein.', self::fullName($m), $band['name']), $bandId, $m['id']);
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
                throw new ApiError(sprintf('Eine Band braucht mindestens %d Musiker. Löse die Band stattdessen auf.', Balance::MIN_MEMBERS));
            }
            $this->repo->db->update('musicians', ['band_id' => null, 'band_slot' => 1], ['id' => $m['id']]);
            $return = $band['slot_return'];
            unset($return[$m['id']]);
            $this->saveSlotReturn($bandId, $return);
            $this->repo->log('lineup', sprintf(
                '%s verlässt %s und nimmt %s Bekanntheit mit.',
                self::fullName($m),
                $band['name'],
                number_format($m['fame'], 1, ',', '.')
            ), $bandId, $m['id']);
            $this->syncIdentity($band);
        });
    }

    /**
     * Ein Multi-Instrumentalist wechselt das Instrument, das er in der Band spielt. Die neue Rolle kostet Zusammenspiel;
     * wechselt er zurück, ist die Band mit ihm wieder eingespielt und bekommt den Abzug zurück.
     */
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
        // Wer dazukommt oder geht, ändert den Zusammenhalt (Beziehungen der neuen Besetzung).
        Mood::syncCohesion($this->repo, (int) $band['id']);
        return $identity;
    }

    /** Bands lassen sich ohne Obergrenze führen (kein Platz-Freischalten mehr). Bleibt für die alten Aufrufer. */
    public function assertSlotFree(): void
    {
    }

    /** Für den Browser: Es gibt keine Bandplätze mehr. */
    public static function slots(float $labelFame): array
    {
        return ['open' => 9999, 'max' => 9999, 'next' => null];
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

    /** $reason: 'disbanded' (löst sich auf) oder 'left' (ein Solokünstler verlässt das Label). Eine Band mit Laufbahn bleibt als ehemalige Band erhalten. */
    public function disband(int $bandId, string $reason = 'disbanded'): void
    {
        $this->repo->db->transaction(function () use ($bandId, $reason): void {
            $band = $this->idleBand($bandId);
            $db = $this->repo->db;
            $this->giveUpName($band, $reason, $reason === 'left' ? 'hat das Label verlassen' : 'hat sich aufgelöst', false);
            $db->exec('UPDATE musicians SET band_id = NULL, band_slot = 1 WHERE band_id = ? AND label_id = ?', [$bandId, $this->repo->labelId]);
            if ($reason !== 'left') {
                $this->repo->log('band', sprintf('Die Band „%s“ hat sich aufgelöst.', $band['name']), $bandId);
            }
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

    /** Was der Second-Hand-Laden für ein Instrument zahlt. */
    public static function sellPrice(array $item): int
    {
        $rarity = Catalog::GEAR[$item['model']]['rarity'] ?? 'common';
        return (int) round(Balance::GEAR_SELL[$rarity] * (1 + Balance::GEAR_SELL_PERK * count($item['perks'] ?? [])));
    }

    /** Instrument an den Second-Hand-Laden verkaufen; wer es spielt, steht danach ohne da. */
    public function sellGear(int $gearId): int
    {
        return $this->repo->db->transaction(function () use ($gearId): int {
            $item = $this->repo->gear()[$gearId] ?? throw new ApiError('Dieses Instrument gibt es nicht.', 404);
            if ($this->repo->db->exec('DELETE FROM gear WHERE id = ? AND label_id = ?', [$gearId, $this->repo->labelId]) === 0) {
                throw new ApiError('Dieses Instrument gibt es nicht mehr.');
            }
            $price = self::sellPrice($item);
            $this->repo->earn($price, sprintf('Verkauf: %s', Catalog::GEAR[$item['model']]['name']));
            return $price;
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
        if ($band['retired']) {
            throw new ApiError('Diese Band gibt es nicht mehr.', 404);
        }
        if ($band['busy_until'] > Clock::now()) {
            throw new ApiError('Die Band ist gerade beschäftigt. Warte, bis sie fertig ist.');
        }
        return $band;
    }

    private function saveSlotReturn(int $bandId, array $return): void
    {
        $this->repo->db->update('bands', ['slot_return' => $return ? json_encode($return) : null], ['id' => $bandId]);
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
