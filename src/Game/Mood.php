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
    public static function bond(Repo $repo, array $members, string $kind, ?int $now = null): void
    {
        $now ??= Clock::now();
        $strength = Balance::RELATION['bond'][$kind] ?? 0.0;
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
    }

    // ---------- Wohlbefinden und Stimmung ----------

    /** Ampel zu einem Wohlbefinden-Wert oder einer Bandstimmung. */
    public static function level(float $value): string
    {
        return $value >= Balance::MOOD['green'] ? 'green' : ($value >= Balance::MOOD['yellow'] ? 'yellow' : 'red');
    }

    /** Stimmung der Band: Ampel nach dem unzufriedensten Mitglied, fließender Wert als Mischung aus ihm und dem Durchschnitt. */
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
            return $value;
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
