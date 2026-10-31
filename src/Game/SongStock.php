<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Clock;
use Signed\Db;
use Signed\Game\Core\Quality;
use Signed\Rng;

/**
 * Songvorrat im neuen Kern: Jeder Song hat seine eigene Qualität, festgelegt beim Schreiben (kreative Kraft der Band, Regler, Coach, Zufall).
 * Beim Aufnehmen wählt das Label 12 aus; ins Album geht ihr Durchschnitt. Der Zähler bands.songs_written läuft weiter mit (Fortschritt).
 */
final class SongStock
{
    /** So viele neue Songs schreiben ($coachQuality: Zuschlag des Coaches aus Tutors::coaching, 0 ohne). */
    public static function write(Repo $repo, array $band, array $members, int $count, float $coachQuality = 0.0): void
    {
        if ($count <= 0) {
            return;
        }
        $creative = Quality::creativeShare($members);
        $coach = self::coachTier($coachQuality);
        $mode = isset(Balance::CORE_QUALITY['song']['slider'][$band['write_mode'] ?? '']) ? $band['write_mode'] : 'normal';
        for ($i = 0; $i < $count; $i++) {
            $repo->db->insert('songs', [
                'label_id' => $repo->labelId, 'band_id' => $band['id'], 'record_id' => null, 'created_at' => Clock::now(),
                'quality' => round(Quality::song($creative, $mode, $coach, Rng::float(-1.0, 1.0)), 2),
            ]);
        }
    }

    /** Songs im Vorrat, beste zuerst: [id, quality]. */
    public static function stock(Db $db, int $bandId): array
    {
        return array_map(
            static fn ($r) => ['id' => (int) $r['id'], 'quality' => (float) $r['quality']],
            $db->all('SELECT id, quality FROM songs WHERE band_id = ? AND record_id IS NULL ORDER BY quality DESC, id', [$bandId])
        );
    }

    /** Die besten Songs für ein Album (Vorauswahl). */
    public static function best(Db $db, int $bandId, int $count = Balance::CORE_ALBUM_SONGS): array
    {
        return array_slice(self::stock($db, $bandId), 0, $count);
    }

    /**
     * Ausgewählte Songs prüfen: genau ein Album voll, alle aus dem Vorrat der Band. Liefert ihre Qualitäten.
     * @param list<int> $ids
     */
    public static function pick(Db $db, int $bandId, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $byId = array_column(self::stock($db, $bandId), 'quality', 'id');
        if (count($ids) !== Balance::CORE_ALBUM_SONGS) {
            throw new \Signed\ApiError(sprintf('Ein Album hat %d Songs.', Balance::CORE_ALBUM_SONGS));
        }
        foreach ($ids as $id) {
            if (!isset($byId[$id])) {
                throw new \Signed\ApiError('Diesen Song gibt es im Vorrat nicht.');
            }
        }
        return array_map(static fn ($id) => $byId[$id], $ids);
    }

    /** Songs gehören jetzt zur Platte. */
    public static function assign(Db $db, int $bandId, array $ids, int $recordId): void
    {
        foreach ($ids as $id) {
            $db->exec('UPDATE songs SET record_id = ? WHERE id = ? AND band_id = ? AND record_id IS NULL', [$recordId, (int) $id, $bandId]);
        }
    }

    /** Coach-Stufe 1–3 aus dem Zuschlag, den Tutors::coaching meldet (0 = kein Coach). */
    private static function coachTier(float $quality): int
    {
        $tier = 0;
        foreach (Balance::COACH['tiers'] as $level => $t) {
            if ($quality >= $t['quality'] - 1e-9) {
                $tier = $level;
            }
        }
        return $tier;
    }
}
