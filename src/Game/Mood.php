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
    }

    /**
     * Wie lange die Band probt: bis das erste Mitglied keine Lust mehr hat (Charakter) oder wer die Probe nicht mag zu unzufrieden wird.
     * @return array{0: int, 1: array{id: int, why: string}} Sekunden und wer die Probe beendet
     */
    public static function rehearsalLimits(array $members): array
    {
        $c = Balance::REHEARSAL;
        $best = null;
        foreach ($members as $m) {
            $factors = array_map(static fn ($p) => Catalog::LUST[$p] ?? 1.0, $m['personality']);
            $lust = $c['lust'] * ($factors ? array_sum($factors) / count($factors) : 1.0);
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
                'like' => array_values(array_map(static fn ($m) => $m['id'], array_filter($members, static fn ($m) => self::liking($m['personality'], $type) > 0))),
                'dislike' => array_values(array_map(static fn ($m) => $m['id'], array_filter($members, static fn ($m) => self::liking($m['personality'], $type) < 0))),
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
                [$text, $action] = Catalog::WISHES[$m['personality'][0] ?? 'gelassen'] ?? Catalog::WISHES['gelassen'];
                $char[] = ['musicianId' => $m['id'], 'text' => $text, 'action' => $action, 'level' => $level];
            }
        }
        // Die Unzufriedensten zuerst
        usort($char, static fn ($a, $b) => ($a['level'] === 'red' ? 0 : 1) <=> ($b['level'] === 'red' ? 0 : 1));
        $out = $char;
        if ($noGear !== null) {
            $out[] = $noGear;
        }
        if ($songs >= Balance::MOOD['stau']['from'] && $members) {
            $out[] = ['musicianId' => $members[0]['id'], 'text' => 'Wir wollen aufnehmen!', 'action' => 'record', 'level' => 'yellow'];
        }
        return $out;
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
    public static function target(array $m, array $band, array $members, array $others, array $relations, float $songs): float
    {
        $c = Balance::MOOD;
        $value = $c['start'];
        $mates = array_values(array_filter($members, static fn ($x) => $x['id'] !== $m['id']));
        if (!$mates) {
            // Solokünstler: keine Beziehungen, kein Genre der Band; es zählen Charakter (Band-Wunsch oder Rampenlicht) und Songstau.
            foreach ($m['personality'] as $trait) {
                $value += $c['solo'][$trait] ?? 0.0;
            }
            $value -= min($c['stau']['max'], max(0.0, $songs - $c['stau']['from']) * $c['stau']['per']);
            return max(0.0, min(100.0, $value));
        }
        // Beziehungen
        $mine = [];
        foreach ($mates as $x) {
            [$a, $b] = self::key($m['id'], $x['id']);
            $mine[$x['id']] = $relations["$a-$b"] ?? 0.0;
        }
        $value += $c['relationWeight'] * array_sum($mine) / count($mine);
        // Können-Unterschied zum Durchschnitt der anderen
        $skill = BandCalc::skill($m);
        $avg = array_sum(array_map(static fn ($x) => BandCalc::skill($x), $mates)) / count($mates);
        $gap = $skill - $avg;
        $g = $c['gap'];
        if ($gap < 0) {
            $weak = -$gap;
            $value += $weak >= $g['good'][0] && $weak <= $g['good'][1] ? $g['good'][2] : -min($g['max'], max(0.0, $weak - $g['weakFrom']) * $g['weakPer']);
        } else {
            $value -= min($g['max'], max(0.0, $gap - $g['strongFrom']) * $g['strongPer']);
        }
        // Genre der Band
        if ($band['genre'] === '') {
            $value += $c['genre']['mixed'];
        } else {
            $value += in_array($band['genre'], $m['genres'], true) ? $c['genre']['fits'] : $c['genre']['misfit'];
        }
        // Songstau
        $value -= min($c['stau']['max'], max(0.0, $songs - $c['stau']['from']) * $c['stau']['per']);
        // Miesepeter: wer rot ist, zieht andere mit runter – Reibung mehr, aber auch Freunde leiden mit
        $pull = 0.0;
        foreach ($mates as $x) {
            $w = $others[$x['id']] ?? $c['start'];
            if ($w < $c['yellow']) {
                $rel = $mine[$x['id']];
                $factor = 1 + ($rel < 0 ? -$rel / Balance::RELATION['max'] : $c['pull']['friend'] * $rel / Balance::RELATION['max']);
                $pull += ($c['yellow'] - $w) * $c['pull']['per'] * $factor;
            }
        }
        $value -= min($c['pull']['max'], $pull);
        return max(0.0, min(100.0, $value));
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
            $state = ['w' => array_column($members, 'wellbeing', 'id'), 'songs' => $band['songs_written']];
            $onlineSpan = min($online, $span);
            $state = self::run($state, $members, $band, $relations, $span - $onlineSpan, true);
            $state = self::run($state, $members, $band, $relations, $onlineSpan, false);
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
        }
    }

    /**
     * Rechnet $seconds in Schritten. $freezeAtRed: Offline-Zeit – rot am Anfang überspringt alles, rot unterwegs hält an.
     * @return array{w: array<int, float>, songs: float}
     */
    private static function run(array $state, array $members, array $band, array $relations, int $seconds, bool $freezeAtRed): array
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
            $level = self::bandMood($state['w'])['level'];
            $state['songs'] = min((float) $c['songCap'], $state['songs'] + $c['songsPerHour'][$level] * $dt / 3600);
            $next = [];
            foreach ($state['w'] as $id => $w) {
                $target = self::target($byId[$id], $band, $members, $state['w'], $relations, $state['songs']);
                $next[$id] = $target + ($w - $target) * exp(-$dt / $c['tau']);
            }
            $state['w'] = $next;
        }
        return $state;
    }
}
