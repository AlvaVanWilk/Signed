<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Clock;
use Signed\Db;
use Signed\Game\Core\Quality;
use Signed\Game\Core\Songs;
use Signed\Rng;

/**
 * Songvorrat im neuen Kern: Jeder Song hat seine eigene Qualität, festgelegt beim Schreiben (Trefferquote des Songschreibers, Regler, Coach,
 * Zufall; Balance::CORE_SONGS), und merkt sich, wer ihn geschrieben hat (`musician_id`). Beim Aufnehmen wählt das Label 10 bis 16 aus;
 * ins Album geht ihr Durchschnitt. Der Vorrat hat keine Grenze; kein Song fliegt von selbst raus (Sandra, 1.11.).
 * Neue Musiker bringen eigene Songs mit. Solange sie in keiner Band sind, liegen die Songs in ihrer Tasche (`band_id` 0).
 * Der Zähler bands.songs_written ist im neuen Kern die Zahl der Songs im Vorrat plus der angefangene Song (sync).
 */
final class SongStock
{
    /**
     * So viele neue Songs schreiben ($coachQuality: Zuschlag des Coaches aus Tutors::coaching, 0 ohne). Jeden Song schreibt einer der
     * Songschreiber (nach seinem Gewicht, Core\Songs::weight) mit seiner Trefferquote. $times: wann jeder fertig wurde (aus Mood::run,
     * auch offline); ohne Angabe jetzt.
     */
    public static function write(Repo $repo, array $band, array $members, int $count, float $coachQuality = 0.0, array $times = []): void
    {
        if ($count <= 0) {
            return;
        }
        $writers = array_values(array_filter($members, static fn ($m) => Songs::weight($m) > 0));
        if (!$writers) {
            return;
        }
        $coach = self::coachTier($coachQuality);
        $mode = isset(Balance::CORE_SONGS['slider'][$band['write_mode'] ?? '']) ? $band['write_mode'] : 'normal';
        $weights = array_map([Songs::class, 'weight'], $writers);
        $rates = array_map(static fn ($m) => Quality::hitRate($m, $mode, $coach), $writers);
        for ($i = 0; $i < $count; $i++) {
            $k = (int) Rng::weighted($weights);
            $repo->db->insert('songs', [
                'label_id' => $repo->labelId, 'band_id' => $band['id'], 'musician_id' => $writers[$k]['id'] ?? null, 'record_id' => null,
                'created_at' => (int) ($times[$i] ?? Clock::now()), 'quality' => round(Quality::song($rates[$k], Rng::float()), 2),
            ]);
        }
        self::sync($repo->db, (int) $band['id']);
    }

    /**
     * Songs, die ein neuer Musiker mitbringt (für seine Karte, Generator::musician): so viele wie CORE_SONGS['bring'] nach Können-Stufe,
     * mal sein Gewicht beim Schreiben (Kreative mehr, Perfektionisten weniger, Technische keine), mal Zufall; jeder mit seiner Trefferquote.
     * @return list<float> Qualitäten
     */
    public static function roll(array $musician): array
    {
        $c = Balance::CORE_SONGS;
        $level = Levels::forSkill(max((float) ($musician['skill1'] ?? 0), (float) ($musician['skill2'] ?? 0)));
        $count = min($c['bringMax'], (int) round($c['bring'][$level] * Songs::weight($musician) * Rng::float(...$c['bringSpread'])));
        $rate = Quality::hitRate($musician);
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = round(Quality::song($rate, Rng::float()), 2);
        }
        rsort($out);
        return $out;
    }

    /** Die mitgebrachten Songs eines frisch unterschriebenen Musikers in seine Tasche legen (bis er in einer Band ist). */
    public static function bring(Repo $repo, int $musicianId, array $qualities): void
    {
        foreach ($qualities as $q) {
            $repo->db->insert('songs', [
                'label_id' => $repo->labelId, 'band_id' => 0, 'musician_id' => $musicianId, 'record_id' => null, 'created_at' => Clock::now(),
                'quality' => round(max(0.0, min(100.0, (float) $q)), 2),
            ]);
        }
    }

    /**
     * Songs aus einer Band in die Tasche eines Musikers: $all für seine Solo-Band, die gleich verschwindet (alle Songs gehören dann ihm),
     * sonst nur die, die er selbst geschrieben hat (er verlässt die Band). Liefert, wie viele es waren.
     */
    public static function toBag(Db $db, int $bandId, int $musicianId, bool $all = false): int
    {
        $moved = $all
            ? $db->exec('UPDATE songs SET band_id = 0, musician_id = ? WHERE band_id = ? AND record_id IS NULL', [$musicianId, $bandId])
            : $db->exec('UPDATE songs SET band_id = 0 WHERE band_id = ? AND musician_id = ? AND record_id IS NULL', [$bandId, $musicianId]);
        if ($moved > 0 && !$all) {
            self::sync($db, $bandId);
        }
        return $moved;
    }

    /** Ein Musiker kommt in eine Band: Die Songs aus seiner Tasche kommen in ihren Vorrat. */
    public static function join(Db $db, int $musicianId, int $bandId): void
    {
        if ($db->exec('UPDATE songs SET band_id = ? WHERE band_id = 0 AND musician_id = ? AND record_id IS NULL', [$bandId, $musicianId]) > 0) {
            self::sync($db, $bandId);
        }
    }

    /** Zwei Bands tun sich zusammen: Der Vorrat der einen kommt zur anderen. */
    public static function moveBand(Db $db, int $fromBandId, int $toBandId): void
    {
        if ($db->exec('UPDATE songs SET band_id = ? WHERE band_id = ? AND record_id IS NULL', [$toBandId, $fromBandId]) > 0) {
            self::sync($db, $fromBandId);
            self::sync($db, $toBandId);
        }
    }

    /** Zähler der Band an den Vorrat anpassen: Zahl der Songs plus der angefangene Song. */
    public static function sync(Db $db, int $bandId): void
    {
        $row = $db->one('SELECT songs_written, coached_songs FROM bands WHERE id = ?', [$bandId]);
        if ($row === null) {
            return;
        }
        $written = (float) $row['songs_written'];
        $count = (int) $db->value('SELECT COUNT(*) FROM songs WHERE band_id = ? AND record_id IS NULL', [$bandId]);
        $new = round($count + ($written - floor($written)), 4);
        $db->update('bands', ['songs_written' => $new, 'coached_songs' => min((float) $row['coached_songs'], $new)], ['id' => $bandId]);
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
        [$min, $max] = Balance::CORE_ALBUM_RANGE;
        if (count($ids) < $min || count($ids) > $max) {
            throw new \Signed\ApiError(sprintf('Ein Album hat %d bis %d Songs.', $min, $max));
        }
        foreach ($ids as $id) {
            if (!isset($byId[$id])) {
                throw new \Signed\ApiError('Diesen Song gibt es im Vorrat nicht.');
            }
        }
        return array_map(static fn ($id) => $byId[$id], $ids);
    }

    /**
     * Das Label lehnt einen Song ab: Er fliegt aus dem Vorrat. Der Zähler bands.songs_written sinkt mit.
     */
    public static function reject(Repo $repo, int $bandId, int $songId): void
    {
        $repo->band($bandId);
        $db = $repo->db;
        $db->transaction(static function () use ($db, $bandId, $songId): void {
            if ($db->exec('DELETE FROM songs WHERE id = ? AND band_id = ? AND record_id IS NULL', [$songId, $bandId]) === 0) {
                throw new \Signed\ApiError('Diesen Song gibt es im Vorrat nicht mehr.');
            }
            $db->exec('UPDATE bands SET songs_written = CASE WHEN songs_written >= 1 THEN songs_written - 1 ELSE 0 END WHERE id = ?', [$bandId]);
            $db->exec('UPDATE bands SET coached_songs = songs_written WHERE id = ? AND coached_songs > songs_written', [$bandId]);
        });
    }

    /** Songs gehören jetzt zur Platte. */
    public static function assign(Db $db, int $bandId, array $ids, int $recordId): void
    {
        foreach ($ids as $id) {
            $db->exec('UPDATE songs SET record_id = ? WHERE id = ? AND band_id = ? AND record_id IS NULL', [$recordId, (int) $id, $bandId]);
        }
    }

    /** Coach-Stufe 1–3 aus dem Zuschlag, den Tutors::coaching meldet (0 = kein Coach). */
    public static function coachTier(float $quality): int
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
