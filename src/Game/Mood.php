<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Clock;

/**
 * Das Eigenleben der Bands (siehe docs/label-konzept.md): Beziehungen, Wohlbefinden der Musiker, Stimmung der Band, Songs.
 * Alles läuft in Echtzeit weiter, auch offline; eine rote Band friert offline ein (kein Weiterrechnen, keine Songs, kein Weggang).
 */
final class Mood
{
    // ---------- Beziehung ----------

    /** Wie gut zwei Musiker zusammenpassen (−1 bis +1): Charakter, gemeinsame Genres, Können-Abstand. Wird nicht angezeigt. */
    public static function fit(array $a, array $b): float
    {
        $c = Balance::RELATION;
        $score = 0.0;
        foreach ($a['personality'] as $x) {
            foreach ($b['personality'] as $y) {
                if (BandCalc::clashes($x, $y)) {
                    $score += $c['clash'];
                }
                if ($x === 'partytier' && $y === 'partytier') {
                    $score += $c['party'];
                }
            }
        }
        if (in_array('teamplayer', $a['personality'], true) || in_array('teamplayer', $b['personality'], true)) {
            $score += $c['teamplayer'];
        }
        $shared = count(array_intersect($a['genres'], $b['genres']));
        $score += $c['genres'][min(3, $shared)];
        $gap = abs(BandCalc::skill($a) - BandCalc::skill($b));
        if ($gap > $c['gapBig']) {
            $score += $c['gapPenalty'][1];
        } elseif ($gap > $c['gapFrom']) {
            $score += $c['gapPenalty'][0];
        }
        return max(-1.0, min(1.0, $score));
    }

    /** Zielwert einer Beziehung bei dieser Passung. */
    public static function relationTarget(float $fit): float
    {
        $c = Balance::RELATION;
        return max(-$c['max'], min($c['max'], $c['scale'] * $fit + $c['base']));
    }

    private static function key(int $a, int $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }

    /**
     * Beziehungen unter den angegebenen Musikern: "a-b" => Wert. Wer nicht zusammen in einer Band ist, driftet seit der letzten Änderung auf 0.
     * @param list<array> $people
     * @param array<int, ?int> $bands Musiker-ID => Band-ID (um zu wissen, wer zusammen ist)
     */
    public static function relations(Repo $repo, array $people, ?int $now = null): array
    {
        $now ??= Clock::now();
        $ids = array_column($people, 'id');
        if (count($ids) < 2) {
            return [];
        }
        $in = implode(',', array_map('intval', $ids));
        $rows = $repo->db->all("SELECT a_id, b_id, value, at FROM relations WHERE label_id = ? AND a_id IN ($in) AND b_id IN ($in)", [$repo->labelId]);
        $byPair = [];
        foreach ($rows as $r) {
            $byPair[$r['a_id'] . '-' . $r['b_id']] = ['value' => (float) $r['value'], 'at' => (int) $r['at']];
        }
        $band = array_column($people, 'band_id', 'id');
        $out = [];
        foreach ($ids as $i => $x) {
            foreach (array_slice($ids, $i + 1) as $y) {
                [$a, $b] = self::key((int) $x, (int) $y);
                $row = $byPair["$a-$b"] ?? null;
                $value = $row['value'] ?? 0.0;
                $together = $band[$a] !== null && $band[$a] === $band[$b];
                if ($row !== null && !$together && $row['at'] > 0) {
                    $drift = Balance::RELATION['drift'] * max(0, $now - $row['at']) / 3600;
                    $value = $value > 0 ? max(0.0, $value - $drift) : min(0.0, $value + $drift);
                }
                $out["$a-$b"] = $value;
            }
        }
        return $out;
    }

    /** Gemeinsames Erlebnis aller Mitglieder (Probe, Auftritt, Aufnahme): jede Beziehung rückt ein Stück auf ihren Zielwert zu. */
    public static function bond(Repo $repo, array $members, string $kind, ?int $now = null, float $factor = 1.0): void
    {
        $now ??= Clock::now();
        $strength = min(1.0, (Balance::RELATION['bond'][$kind] ?? 0.0) * $factor);
        if ($strength <= 0 || count($members) < 2) {
            return;
        }
        $current = self::relations($repo, $members, $now);
        $members = array_values($members);
        foreach ($members as $i => $x) {
            foreach (array_slice($members, $i + 1) as $y) {
                [$a, $b] = self::key($x['id'], $y['id']);
                $value = $current["$a-$b"] ?? 0.0;
                $value += (self::relationTarget(self::fit($x, $y)) - $value) * $strength;
                $value = max(-Balance::RELATION['max'], min(Balance::RELATION['max'], $value));
                $repo->db->exec('DELETE FROM relations WHERE a_id = ? AND b_id = ?', [$a, $b]);
                $repo->db->insert('relations', ['label_id' => $repo->labelId, 'a_id' => $a, 'b_id' => $b, 'value' => round($value, 4), 'at' => $now]);
            }
        }
        if ($members[0]['band_id'] !== null) {
            self::syncCohesion($repo, (int) $members[0]['band_id']);
        }
    }

    /**
     * Zusammenhalt der Band aus ihren Beziehungen (−10…+10): je zur Hälfte Durchschnitt und schwächste Beziehung.
     * @param array<string, float> $relations "a-b" => Wert; leer bei einem Solokünstler
     * @return array{avg: float, min: float, value: float}|null
     */
    public static function togetherness(array $relations): ?array
    {
        if (!$relations) {
            return null;
        }
        $avg = array_sum($relations) / count($relations);
        $min = min($relations);
        return ['avg' => round($avg, 2), 'min' => round($min, 2), 'value' => round(0.5 * $avg + 0.5 * $min, 2)];
    }

    /** Zusammenhalt als Wert 0–100 für den Sound (0 = −10, 50 = 0, 100 = +10); ein Solokünstler hat keinen Streit, also 100. */
    public static function cohesionValue(array $relations): float
    {
        $t = self::togetherness($relations);
        return $t === null ? 100.0 : max(0.0, min(100.0, 50 + 5 * $t['value']));
    }

    /** Hält den gespeicherten Zusammenhalt der Band (nur Zwischenspeicher für den Sound) aktuell. */
    public static function syncCohesion(Repo $repo, int $bandId): void
    {
        $members = $repo->members($bandId);
        $value = round(self::cohesionValue($members ? self::relations($repo, $members) : []), 2);
        $repo->db->exec('UPDATE bands SET cohesion = ? WHERE id = ?', [$value, $bandId]);
    }

    // ---------- Wohlbefinden und Stimmung ----------

    /** Ampel zu einem Wohlbefinden-Wert oder einer Bandstimmung. */
    public static function level(float $value): string
    {
        return $value >= Balance::MOOD['green'] ? 'green' : ($value >= Balance::MOOD['yellow'] ? 'yellow' : 'red');
    }

    /** Stimmung der Band: Ampel nach dem unzufriedensten Mitglied, fließender Wert als Mischung aus ihm und dem Durchschnitt. */
    /** Wie sehr ein Charakter (bis zu zwei Eigenschaften) eine Anordnung mag: positiv, 0 (egal) oder negativ. */
    public static function liking(array $personality, string $type): int
    {
        $sum = 0;
        foreach ($personality as $p) {
            $sum += Catalog::LIKES[$p][$type] ?? 0;
        }
        return $sum;
    }

    /** Wer in der Band die Anordnung ablehnt, obwohl er schon rot ist: Dann ist sie gesperrt (man kann ihr nichts abverlangen). */
    public static function refuser(array $members, string $type): ?array
    {
        foreach ($members as $m) {
            if (self::level($m['wellbeing']) === 'red' && self::liking($m['personality'], $type) < 0) {
                return $m;
            }
        }
        return null;
    }

    /** Eine erlebte Anordnung hebt oder drückt das Wohlbefinden je nach Charakter sofort (wirkt danach langsam ab). */
    /** Ein schöner Proberaum tut allen gut: Wohlbefinden je Bezugszeit der Probe. */
    public static function roomMood(Repo $repo, array $members, float $perUnit, float $factor): void
    {
        if ($perUnit == 0.0) {
            return;
        }
        foreach ($members as $m) {
            $value = max(0.0, min(100.0, $m['wellbeing'] + $perUnit * $factor));
            $repo->db->update('musicians', ['wellbeing' => round($value, 3)], ['id' => $m['id']]);
        }
    }

    public static function react(Repo $repo, array $members, string $type, float $factor = 1.0): void
    {
        $step = Balance::MOOD['reactStep'];
        foreach ($members as $m) {
            $score = self::liking($m['personality'], $type);
            if ($score !== 0) {
                $value = max(0.0, min(100.0, $m['wellbeing'] + $step * max(-1, min(1, $score)) * $factor));
                $repo->db->update('musicians', ['wellbeing' => round($value, 3)], ['id' => $m['id']]);
            }
        }
        Traits::revealLiking($repo, $members, $type);
    }

    /**
     * Wie lange die Band probt: bis das erste Mitglied keine Lust mehr hat (Charakter) oder wer die Probe nicht mag zu unzufrieden wird.
     * @return array{0: int, 1: array{id: int, why: string}} Sekunden und wer die Probe beendet
     */
    public static function rehearsalLimits(array $members, float $lustFactor = 1.0, array $gear = []): array
    {
        $c = Balance::REHEARSAL;
        $best = null;
        foreach ($members as $m) {
            $factors = array_map(static fn ($p) => Catalog::LUST[$p] ?? 1.0, $m['personality']);
            $lust = $c['lust'] * $lustFactor * ($factors ? array_sum($factors) / count($factors) : 1.0) * (1 + Gear::value(BandCalc::gearOf($m, $gear), 'lust'));
            $why = 'lust';
            $score = max(-1, min(1, self::liking($m['personality'], 'rehearsal')));
            if ($score < 0) {
                $toRed = max(0.0, $m['wellbeing'] - Balance::MOOD['yellow']) / (Balance::MOOD['reactStep'] * -$score / $c['seconds']);
                if ($toRed < $lust) {
                    $lust = $toRed;
                    $why = 'red';
                }
            }
            if ($best === null || $lust < $best[0]) {
                $best = [$lust, $m['id'], $why];
            }
        }
        return [max($c['minLust'], (int) round($best[0] ?? $c['lust'])), ['id' => $best[1] ?? 0, 'why' => $best[2] ?? 'lust']];
    }

    /**
     * Reaktion der Band auf die drei Anordnungen für die Anzeige: wer mag sie, wer nicht, ist sie gesperrt.
     * @return array<string, array{like: list<int>, dislike: list<int>, locked: bool}>
     */
    public static function reactions(array $members): array
    {
        $out = [];
        foreach (['rehearsal' => 'rehearsal', 'gig' => 'gig', 'record' => 'record'] as $key => $type) {
            $out[$key] = [
                // Nur nach Zügen, die das Label schon kennt
                'like' => array_values(array_map(static fn ($m) => $m['id'], array_filter($members, static fn ($m) => self::liking(Traits::known($m), $type) > 0))),
                'dislike' => array_values(array_map(static fn ($m) => $m['id'], array_filter($members, static fn ($m) => self::liking(Traits::known($m), $type) < 0))),
                'locked' => self::refuser($members, $type) !== null,
            ];
        }
        return $out;
    }

    /**
     * Die Wünsche der Band (Anzeige): unzufriedene Musiker nennen ihren Charakter-Wunsch, wer kein Instrument hat, wünscht sich eins,
     * und bei Songstau wollen sie aufnehmen.
     * @param array<int, array<string, array>> $gear Instrumente je Musiker und Art
     * @return list<array{musicianId: int, text: string, action: string, level: string}>
     */
    public static function wishes(array $members, array $gear, int $songs): array
    {
        $char = [];
        $noGear = null;
        foreach ($members as $m) {
            $level = self::level($m['wellbeing']);
            $played = $m['band_slot'] === 2 && $m['instr2'] !== null ? $m['instr2'] : $m['instr1'];
            if ($noGear === null && !isset($gear[$m['id']][$played])) {
                $noGear = ['musicianId' => $m['id'], 'text' => 'Ich brauche ein Instrument.', 'action' => 'gear', 'level' => $level];
            }
            if ($level !== 'green') {
                $table = count($members) === 1 ? Catalog::WISHES_SOLO : Catalog::WISHES;
                [$text, $action] = $table[$m['personality'][0] ?? 'gelassen'] ?? $table['gelassen'];
                $char[] = ['musicianId' => $m['id'], 'text' => $text, 'action' => $action, 'level' => $level];
            }
        }
        // Die Unzufriedensten zuerst
        usort($char, static fn ($a, $b) => ($a['level'] === 'red' ? 0 : 1) <=> ($b['level'] === 'red' ? 0 : 1));
        $out = $char;
        if ($noGear !== null) {
            $out[] = $noGear;
        }
        // Noch ohne Künstlernamen: Diven, Rampensäue und Ehrgeizige wünschen sich einen, sobald sie bekannt genug sind (je nach Charakter).
        $want = Balance::STAGE_NAME['want'];
        foreach ($members as $m) {
            $from = min(array_map(static fn ($trait) => $want[$trait] ?? INF, $m['personality'] ?: ['']));
            if (($m['stage_name'] ?? null) === null && $m['fame'] >= $from) {
                $out[] = ['musicianId' => $m['id'], 'text' => 'Ich hätte gern einen Künstlernamen.', 'action' => 'stageName', 'level' => self::level($m['wellbeing'])];
                break;
            }
        }
        if ($songs >= Balance::MOOD['stau']['from'] && $members) {
            $out[] = ['musicianId' => $members[0]['id'], 'text' => count($members) === 1 ? 'Ich will aufnehmen!' : 'Wir wollen aufnehmen!', 'action' => 'record', 'level' => 'yellow'];
        }
        return $out;
    }

    /** Songs je Stunde bei dieser Stimmung (Ampel und Wert): grün schnell, gelb halb so schnell, rot gar nicht, ganz oben mit Schub. */
    public static function songRate(array $mood): float
    {
        $c = Balance::MOOD;
        $rate = $c['songsPerHour'][$mood['level']];
        return $mood['level'] === 'green' && $mood['value'] >= $c['songsBoost']['from'] ? $rate * $c['songsBoost']['factor'] : $rate;
    }

    /** Wie viel die Mitglieder zusammen zum Songschreiben beitragen (1 = Vierer-Band ohne besondere Züge, allein viel weniger; siehe Balance::SONGWRITING). */
    public static function crewFactor(array $members): float
    {
        $c = Balance::SONGWRITING;
        $sum = 0.0;
        foreach ($members as $m) {
            $share = $c['share'];
            foreach ($m['personality'] ?? [] as $trait) {
                $share *= Balance::TRAITS['write'][$trait] ?? 1.0;
            }
            $sum += $share;
        }
        return ($sum / $c['norm']) ** $c['exp'];
    }

    /** Abzug durch liegende Songs (Gemütliche nehmen es gelassener). */
    private static function stau(array $m, float $songs): float
    {
        $c = Balance::MOOD['stau'];
        $calm = in_array(Balance::TRAITS['calm']['trait'], $m['personality'], true) ? Balance::TRAITS['calm']['stau'] : 1.0;
        return min($c['max'], max(0.0, $songs - $c['from']) * $c['per']) * $calm;
    }

    /**
     * Abzug, wenn die Band lange nichts erlebt hat, das jemandem wichtig ist: Extrovertierte und Gesellige wollen auf die Bühne,
     * Ehrgeizige wollen neue Platten. Die Stunden seit dem letzten Auftritt und der letzten Platte stehen in $band (stageIdle, recordIdle).
     */
    private static function idle(array $m, array $band): float
    {
        $t = Balance::TRAITS;
        $off = 0.0;
        if (($band['stageIdle'] ?? 0) >= $t['restless']['hours']) {
            $off += self::traitMax($m, $t['restless']['traits'], 0.0);
        }
        if (($band['recordIdle'] ?? 0) >= $t['stagnant']['hours']) {
            $off += self::traitMax($m, $t['stagnant']['traits'], 0.0);
        }
        return $off;
    }

    /** Größter Wert aus einer Tabelle nach Charakterzug (oder $none, wenn keiner zutrifft). */
    public static function traitMax(array $m, array $table, float $none): float
    {
        $values = array_values(array_intersect_key($table, array_flip($m['personality'] ?? [])));
        return $values ? max($values) : $none;
    }

    /** Faktor aufs Songschreiben je nach dem, was die Band gerade tut: Auftritt und Promo 0, Probe etwas langsamer, sonst 1. */
    public static function activityFactor(?string $type): float
    {
        $c = Balance::MOOD['songsActivity'];
        return $type === null ? 1.0 : (in_array($type, $c['stop'], true) ? 0.0 : ($type === 'rehearsal' ? $c['rehearsal'] : 1.0));
    }

    /** Mittlerer Faktor aufs Songschreiben zwischen zwei Zeitpunkten (nach den Aktivitäten der Band in dieser Zeit). */
    public static function writeFactor(Repo $repo, int $bandId, int $from, int $to): float
    {
        if ($to <= $from) {
            return 1.0;
        }
        $lost = 0.0;
        foreach ($repo->db->all('SELECT type, started_at, ends_at FROM activities WHERE band_id = ? AND ends_at > ? AND started_at < ?', [$bandId, $from, $to]) as $a) {
            $overlap = max(0, min($to, (int) $a['ends_at']) - max($from, (int) $a['started_at']));
            $lost += $overlap * (1 - self::activityFactor($a['type']));
        }
        return max(0.0, 1 - $lost / ($to - $from));
    }

    public static function bandMood(array $wellbeing): array
    {
        if (!$wellbeing) {
            return ['value' => Balance::MOOD['start'], 'level' => 'green'];
        }
        $min = min($wellbeing);
        $w = Balance::MOOD['minWeight'];
        return ['value' => round($w * $min + (1 - $w) * array_sum($wellbeing) / count($wellbeing), 2), 'level' => self::level($min)];
    }

    /**
     * Wohin sich das Wohlbefinden eines Musikers bewegt: Beziehungen, Können-Unterschied, Genre der Band, Songstau und die Laune der anderen.
     * @param array<int, float> $others Wohlbefinden der anderen Mitglieder (ID => Wert)
     * @param array<string, float> $relations Beziehungen der Band ("a-b" => Wert)
     */
    /**
     * @param array|null $why wenn übergeben: sammelt die Gründe als [Text, Wert] (für die Anzeige „Warum?“; ohne Namen versteckter Züge)
     */
    public static function target(array $m, array $band, array $members, array $others, array $relations, float $songs, ?array &$why = null): float
    {
        $c = Balance::MOOD;
        $value = $c['start'];
        $add = static function (string $text, float $delta) use (&$value, &$why): void {
            $value += $delta;
            if ($why !== null && abs($delta) >= 0.05) {
                $why[] = [$text, round($delta, 1)];
            }
        };
        $why = $why === null ? null : [];
        $mates = array_values(array_filter($members, static fn ($x) => $x['id'] !== $m['id']));
        if (!$mates) {
            // Solokünstler: keine Beziehungen, kein Genre der Band; es zählen Einsamkeit, Charakter (Band-Wunsch oder Rampenlicht) und Songstau.
            $add('Allein ist es manchmal einsam', $c['soloBase']);
            $solo = 0.0;
            foreach ($m['personality'] as $trait) {
                $solo += $c['solo'][$trait] ?? 0.0;
            }
            $add($solo < 0 ? 'Wäre lieber in einer Band' : 'Steht gern allein im Rampenlicht', $solo);
            $add('Zu viele Songs warten auf eine Aufnahme', -self::stau($m, $songs));
            self::idleWhy($m, $band, $add);
            return max(0.0, min(100.0, $value));
        }
        // Beziehungen
        $mine = [];
        foreach ($mates as $x) {
            [$a, $b] = self::key($m['id'], $x['id']);
            $mine[$x['id']] = $relations["$a-$b"] ?? 0.0;
        }
        $rel = $c['relationWeight'] * array_sum($mine) / count($mine);
        $add($rel >= 0 ? 'Versteht sich gut mit den anderen' : 'Versteht sich nicht gut mit den anderen', $rel);
        // Können-Unterschied zum Durchschnitt der anderen
        $skill = BandCalc::skill($m);
        $avg = array_sum(array_map(static fn ($x) => BandCalc::skill($x), $mates)) / count($mates);
        $gap = $skill - $avg;
        $g = $c['gap'];
        if ($gap < 0) {
            $weak = -$gap;
            if ($weak >= $g['good'][0] && $weak <= $g['good'][1]) {
                $add('Lernt von den Besseren in der Band', $g['good'][2]);
            } else {
                $add('Kommt nicht mit – die anderen spielen viel besser', -min($g['max'], max(0.0, $weak - $g['weakFrom']) * $g['weakPer']));
            }
        } else {
            $add('Langweilt sich – die anderen spielen viel schwächer', -min($g['max'], max(0.0, $gap - $g['strongFrom']) * $g['strongPer']));
        }
        // Genre der Band
        if ($band['genre'] === '') {
            $add('Die Band hat kein gemeinsames Genre', $c['genre']['mixed']);
        } elseif (in_array($band['genre'], $m['genres'], true)) {
            $add('Mag das Genre der Band', $c['genre']['fits']);
        } else {
            $add('Mag das Genre der Band nicht', $c['genre']['misfit']);
        }
        // Songstau, und wer lange nicht auf der Bühne war oder lange keine Platte hatte (je nach Charakter)
        $add('Zu viele Songs warten auf eine Aufnahme', -self::stau($m, $songs));
        self::idleWhy($m, $band, $add);
        // Miesepeter: wer rot ist, zieht andere mit runter – Reibung mehr, aber auch Freunde leiden mit
        $pull = 0.0;
        foreach ($mates as $x) {
            $w = $others[$x['id']] ?? $c['start'];
            if ($w < $c['yellow']) {
                $r = $mine[$x['id']];
                $factor = 1 + ($r < 0 ? -$r / Balance::RELATION['max'] : $c['pull']['friend'] * $r / Balance::RELATION['max']);
                $pull += ($c['yellow'] - $w) * $c['pull']['per'] * $factor * self::traitMax($m, Balance::TRAITS['pulled'], 1.0);
            }
        }
        $add('Die schlechte Laune der anderen färbt ab', -min($c['pull']['max'], $pull));
        return max(0.0, min(100.0, $value));
    }

    /** Unruhe ohne Auftritt oder neue Platte, als einzelne Gründe (gleiche Rechnung wie idle()). */
    private static function idleWhy(array $m, array $band, callable $add): void
    {
        $t = Balance::TRAITS;
        if (($band['stageIdle'] ?? 0) >= $t['restless']['hours']) {
            $add('Will endlich wieder auf die Bühne', -self::traitMax($m, $t['restless']['traits'], 0.0));
        }
        if (($band['recordIdle'] ?? 0) >= $t['stagnant']['hours']) {
            $add('Will endlich eine neue Platte machen', -self::traitMax($m, $t['stagnant']['traits'], 0.0));
        }
    }

    /**
     * Alle Bands des Labels fortschreiben: Offline-Zeit rechnet bis zum ersten Rot, danach ist die Band eingefroren; die Zeit, in der das Label
     * online war (Lebenszeichen), rechnet immer. Songs entstehen im Tempo der Stimmung. Wird beim Aufbau des Spielstands aufgerufen.
     */
    public static function settle(Repo $repo, ?int $now = null): void
    {
        $now ??= Clock::now();
        $c = Balance::MOOD;
        $onlineAt = (int) $repo->db->value('SELECT online_at FROM labels WHERE id = ?', [$repo->labelId]);
        // Online zählt nur, wenn die Lebenszeichen dicht aufeinander kommen; nach einer Pause ist die Lücke offline.
        $gap = $now - $onlineAt;
        $online = $onlineAt > 0 && $gap >= 0 && $gap <= $c['onlineCredit'] ? $gap : 0;
        $repo->db->update('labels', ['online_at' => $now], ['id' => $repo->labelId]);

        foreach ($repo->bands() as $band) {
            self::syncCohesion($repo, $band['id']);
            if ($band['mood_at'] <= 0) {
                $repo->db->update('bands', ['mood_at' => $now], ['id' => $band['id']]);
                continue;
            }
            $span = min($c['maxCatchUp'], max(0, $now - $band['mood_at']));
            if ($span === 0) {
                continue;
            }
            $members = $repo->members($band['id']);
            if (count($members) < 1) {
                $repo->db->update('bands', ['mood_at' => $now], ['id' => $band['id']]);
                continue;
            }
            $relations = self::relations($repo, $members, $now);
            [$band['stageIdle'], $band['recordIdle']] = self::idleHours($repo, $band, $now);
            $state = ['w' => array_column($members, 'wellbeing', 'id'), 'songs' => $band['songs_written']];
            $onlineSpan = min($online, $span);
            $factor = self::writeFactor($repo, $band['id'], $now - $span, $now) * self::crewFactor($members);
            $state = self::run($state, $members, $band, $relations, $span - $onlineSpan, true, $factor);
            $state = self::run($state, $members, $band, $relations, $onlineSpan, false, $factor);
            $after = self::bandMood($state['w']);
            foreach ($state['w'] as $id => $w) {
                $repo->db->update('musicians', ['wellbeing' => round($w, 3)], ['id' => $id]);
            }
            // Geduld: nur online im Roten; sonst erholt sie sich
            $patience = $band['patience'];
            if ($after['level'] === 'red') {
                $patience = max(0.0, $patience - $onlineSpan);
            } else {
                $patience = min($c['patience'], $patience + $onlineSpan * $c['patienceRecover']);
            }
            $repo->db->update('bands', ['songs_written' => round($state['songs'], 4), 'mood_at' => $now, 'patience' => $patience], ['id' => $band['id']]);
            Traits::settleBand($repo, $band, $members, (float) $band['songs_written'], $state['songs'], $now);
        }
        Traits::settleTime($repo, $now);
    }

    /** Stunden seit dem letzten Auftritt (Gig, Straße, offene Bühne) und seit der letzten fertigen Platte (ohne: seit Gründung). */
    public static function idleHours(Repo $repo, array $band, int $now): array
    {
        $stage = (int) $repo->db->value("SELECT MAX(ends_at) FROM activities WHERE band_id = ? AND type IN ('gig', 'busking', 'openstage') AND ends_at <= ?", [$band['id'], $now]);
        $record = (int) $repo->db->value("SELECT MAX(finished_at) FROM records WHERE band_id = ? AND status = 'done'", [$band['id']]);
        $since = static fn (int $at) => max(0.0, ($now - max($at, (int) $band['created_at'])) / 3600);
        return [$since($stage), $since($record)];
    }

    /**
     * Rechnet $seconds in Schritten. $freezeAtRed: Offline-Zeit – rot am Anfang überspringt alles, rot unterwegs hält an.
     * @return array{w: array<int, float>, songs: float}
     */
    private static function run(array $state, array $members, array $band, array $relations, int $seconds, bool $freezeAtRed, float $writeFactor = 1.0): array
    {
        $c = Balance::MOOD;
        $byId = [];
        foreach ($members as $m) {
            $byId[$m['id']] = $m;
        }
        while ($seconds > 0) {
            if ($freezeAtRed && self::bandMood($state['w'])['level'] === 'red') {
                break;
            }
            $dt = min($c['step'], $seconds);
            $seconds -= $dt;
            $state['songs'] = min((float) $c['songCap'], $state['songs'] + self::songRate(self::bandMood($state['w'])) * $writeFactor * $dt / 3600);
            $next = [];
            foreach ($state['w'] as $id => $w) {
                $target = self::target($byId[$id], $band, $members, $state['w'], $relations, $state['songs']);
                // Gemütliche rutschen langsamer nach unten
                $calm = $target < $w && in_array(Balance::TRAITS['calm']['trait'], $byId[$id]['personality'], true) ? Balance::TRAITS['calm']['tau'] : 1.0;
                $next[$id] = $target + ($w - $target) * exp(-$dt / ($c['tau'] * $calm));
            }
            $state['w'] = $next;
        }
        return $state;
    }
}
