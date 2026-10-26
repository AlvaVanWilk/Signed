<?php
declare(strict_types=1);

namespace Signed\Game;

/**
 * Verborgene Charakterzüge: Wer neu ins Label kommt, zeigt seine Züge erst nach und nach – jeweils, sobald ein Zug zum ersten Mal
 * etwas bewirkt, und spätestens nach einiger gemeinsamer Zeit (Balance::TRAIT_REVEAL). Gewirkt wird immer, auch solange er unbekannt ist.
 */
final class Traits
{
    /** Die Züge, die das Label schon kennt (in der Reihenfolge von personality). */
    public static function known(array $m): array
    {
        $known = $m['known_traits'] ?? [];
        return array_values(array_filter($m['personality'] ?? [], static fn ($t) => in_array($t, $known, true)));
    }

    /** Die Züge, die sich noch nicht gezeigt haben. */
    public static function hidden(array $m): array
    {
        return array_values(array_diff($m['personality'] ?? [], self::known($m)));
    }

    /**
     * Züge aufdecken, die gerade gewirkt haben, mit einer kurzen Nachricht für das Label.
     * @param string[] $traits
     * @param string $why Schlüssel aus Catalog::TRAIT_REVEAL
     */
    public static function reveal(Repo $repo, array $m, array $traits, string $why, string $order = ''): void
    {
        if (($m['id'] ?? 0) <= 0) {
            return;
        }
        $row = $repo->db->one('SELECT personality, known_traits FROM musicians WHERE id = ?', [$m['id']]);
        if ($row === null || $row['known_traits'] === null) {
            return;
        }
        $known = json_decode((string) $row['known_traits'], true) ?: [];
        $new = array_values(array_diff(array_intersect($m['personality'], $traits), $known));
        if (!$new) {
            return;
        }
        $known = array_values(array_intersect($m['personality'], array_merge($known, $new)));
        $repo->db->update('musicians', ['known_traits' => json_encode($known)], ['id' => $m['id']]);
        $words = array_map(static fn ($t) => mb_strtolower(Catalog::PERSONALITIES[$t]), $new);
        $reason = sprintf(Catalog::TRAIT_REVEAL[$why], $m['first_name'], $order);
        $repo->log('trait', sprintf('%s Jetzt weißt du: %s ist %s.', $reason, $m['first_name'], implode(' und ', $words)), $m['band_id'] ?? null, $m['id']);
    }

    /** Nach einer Anordnung: Wer sie mochte oder nicht mochte, zeigt den Zug, der dafür sorgt. */
    public static function revealLiking(Repo $repo, array $members, string $type): void
    {
        if (!isset(Catalog::TRAIT_REVEAL_ORDERS[$type])) {
            return;
        }
        foreach ($members as $m) {
            $score = Mood::liking($m['personality'], $type);
            if ($score === 0) {
                continue;
            }
            $traits = array_filter($m['personality'], static fn ($t) => (Catalog::LIKES[$t][$type] ?? 0) * $score > 0);
            self::reveal($repo, $m, $traits, $score > 0 ? 'like' : 'dislike', Catalog::TRAIT_REVEAL_ORDERS[$type]);
        }
    }

    /**
     * Beim Fortschreiben der Bands: Unruhe, Gelassenheit beim Songstau, neue Songs, Künstlernamen-Wunsch, Unzufriedenheit –
     * und das Sicherheitsnetz nach einiger Zeit im Label.
     * @param array{stageIdle: float, recordIdle: float} $band
     */
    public static function settleBand(Repo $repo, array $band, array $members, float $songsBefore, float $songsAfter, int $now): void
    {
        $t = Balance::TRAITS;
        foreach ($members as $m) {
            if (!self::hidden($m)) {
                continue;
            }
            if (($band['stageIdle'] ?? 0) >= $t['restless']['hours']) {
                self::reveal($repo, $m, array_keys($t['restless']['traits']), 'restless');
            }
            if (($band['recordIdle'] ?? 0) >= $t['stagnant']['hours']) {
                self::reveal($repo, $m, array_keys($t['stagnant']['traits']), 'stagnant');
            }
            if ($songsAfter >= Balance::MOOD['stau']['from']) {
                self::reveal($repo, $m, [$t['calm']['trait']], 'calm');
            }
            if (floor($songsAfter) > floor($songsBefore)) {
                self::reveal($repo, $m, ['kreativ'], 'songs');
                if (count($members) > 1) {
                    self::reveal($repo, $m, ['technisch'], 'noSongs');
                }
            }
            $want = Balance::STAGE_NAME['want'];
            if (($m['stage_name'] ?? null) === null) {
                self::reveal($repo, $m, array_keys(array_filter($want, static fn ($from) => $m['fame'] >= $from)), 'stageName');
            }
            $w = $repo->db->value('SELECT wellbeing FROM musicians WHERE id = ?', [$m['id']]);
            if ($w !== null && Mood::level((float) $w) !== 'green' && $m['personality']) {
                self::reveal($repo, $m, [$m['personality'][0]], 'unhappy');
            }
        }
    }

    /** Sicherheitsnetz: Wer schon eine Weile im Label ist, hat alle Züge gezeigt (auch ohne Band). */
    public static function settleTime(Repo $repo, int $now): void
    {
        $rows = $repo->db->all('SELECT id FROM musicians WHERE label_id = ? AND known_traits IS NOT NULL AND created_at <= ?', [$repo->labelId, $now - Balance::TRAIT_REVEAL['after']]);
        foreach ($rows as $row) {
            $m = $repo->musician((int) $row['id']);
            if (self::hidden($m)) {
                self::reveal($repo, $m, $m['personality'], 'time');
            }
        }
    }

    /**
     * Nach einer Probe: Wer zuerst keine Lust mehr hatte, zeigt den Zug dafür; sonst zeigt sich ab und zu ein Zug einfach so
     * (fest aus Probe und Musiker gelost, damit es beim erneuten Laden gleich bleibt).
     */
    public static function afterRehearsal(Repo $repo, array $members, int $activityId, array $limit, int $seconds): void
    {
        if ($seconds < Balance::TRAIT_REVEAL['minRehearsal']) {
            return;
        }
        foreach ($members as $m) {
            if ($limit['why'] === 'lust' && $m['id'] === (int) $limit['id']) {
                self::reveal($repo, $m, array_filter($m['personality'], static fn ($t) => (Catalog::LUST[$t] ?? 1.0) < 1.0), 'lust');
                $m = $repo->musician($m['id']);
            }
            $hidden = self::hidden($m);
            $roll = crc32('trait|' . $activityId . '|' . $m['id']);
            if ($hidden && ($roll % 1000) / 1000 < Balance::TRAIT_REVEAL['rehearsal']) {
                self::reveal($repo, $m, [$hidden[intdiv($roll, 1000) % count($hidden)]], 'rehearsal');
            }
        }
    }
}
