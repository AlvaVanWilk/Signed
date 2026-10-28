<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Clock;
use Signed\Db;

/**
 * Auszeichnungen (Balance::AWARDS): echte Pokale statt Kleinkram. Goldene, Platin- und Diamant-Schallplatte nach verkauften Stück
 * je Platte, Platz 1 der Charts je Platte, der erste Auftritt in einer Halle und in einer Arena je Band. Dazu kommen Battle-Pokale,
 * Festival-Plakat und Weltenbummler-Globus (Battle, Festival, Tours). Alles steht im Proberaum der Band.
 * Neue Auszeichnungen (seen = 0) feiert der Browser einmal mit einem Fenster.
 */
final class Awards
{
    public const RECORD_KINDS = ['gold' => 'Goldene Schallplatte', 'platinum' => 'Platin-Schallplatte', 'diamond' => 'Diamant-Schallplatte'];

    /** Einmal je Band und Art (mit Platte: einmal je Platte und Art). Liefert true, wenn sie neu dazukam. */
    public static function give(Repo $repo, int $bandId, string $kind, string $title, string $news, ?int $recordId = null): bool
    {
        $exists = $recordId === null
            ? $repo->db->value('SELECT id FROM awards WHERE band_id = ? AND kind = ?', [$bandId, $kind])
            : $repo->db->value('SELECT id FROM awards WHERE record_id = ? AND kind = ?', [$recordId, $kind]);
        if ($exists !== null) {
            return false;
        }
        $repo->db->insert('awards', [
            'label_id' => $repo->labelId, 'band_id' => $bandId, 'kind' => $kind, 'place' => 1, 'title' => mb_substr($title, 0, 120),
            'record_id' => $recordId, 'seen' => 0, 'created_at' => Clock::now(),
        ]);
        $repo->log('award', $news, $bandId);
        return true;
    }

    /** Verkäufe einer Platte sind gestiegen: Gold, Platin, Diamant, sobald die Schwelle erreicht ist. */
    public static function sales(Repo $repo, int $bandId, int $recordId, string $title, float $before, float $after): void
    {
        foreach (Balance::AWARDS['records'] as $kind => $copies) {
            if ($before < $copies && $after >= $copies) {
                $name = self::RECORD_KINDS[$kind];
                $band = $repo->db->value('SELECT name FROM bands WHERE id = ?', [$bandId]) ?? '';
                self::give($repo, $bandId, $kind, sprintf('%s: „%s“', $name, $title),
                    sprintf('„%s“ von %s hat sich %s Mal verkauft: %s!', $title, $band, number_format($copies, 0, ',', '.'), $name), $recordId);
            }
        }
    }

    /** Die Platte steht auf Platz 1 (Charts::markTop). */
    public static function numberOne(Repo $repo, int $bandId, int $recordId, string $title): void
    {
        $band = $repo->db->value('SELECT name FROM bands WHERE id = ?', [$bandId]) ?? '';
        self::give($repo, $bandId, 'number1', sprintf('Platz 1 der Charts: „%s“', $title), sprintf('„%s“ von %s steht auf Platz 1 der Charts!', $title, $band), $recordId);
    }

    /** Erster Auftritt in einer Halle oder Arena (Gig oder auf Tournee). */
    public static function bigStage(Repo $repo, int $bandId, string $venue): void
    {
        $names = ['halle' => ['hall', 'Halle', 'einer großen Halle'], 'arena' => ['arena', 'Arena', 'einer Arena']];
        if (!isset($names[$venue])) {
            return;
        }
        [$kind, $short, $where] = $names[$venue];
        $band = $repo->db->value('SELECT name FROM bands WHERE id = ?', [$bandId]) ?? '';
        self::give($repo, $bandId, $kind, sprintf('Erster Auftritt in %s', $where), sprintf('%s spielt zum ersten Mal in %s!', $band, $where));
    }

    /** Auszeichnungen je Band (für die Bandseite und den Proberaum). */
    public static function byBand(Db $db, int $labelId): array
    {
        $out = [];
        foreach ($db->all('SELECT id, band_id, kind, place, title, record_id, created_at FROM awards WHERE label_id = ? ORDER BY id', [$labelId]) as $row) {
            $out[(int) $row['band_id']][] = self::present($row);
        }
        return $out;
    }

    /** Auszeichnungen einer Band (auch fremder Labels, für WikiSignia). */
    public static function forBand(Db $db, int $bandId): array
    {
        return array_map([self::class, 'present'], $db->all('SELECT * FROM awards WHERE band_id = ? ORDER BY id', [$bandId]));
    }

    /** Neue Auszeichnungen, die noch nicht gefeiert wurden. */
    public static function fresh(Db $db, int $labelId): array
    {
        return array_map([self::class, 'present'], $db->all('SELECT * FROM awards WHERE label_id = ? AND seen = 0 ORDER BY id', [$labelId]));
    }

    public static function seen(Repo $repo, int $id): void
    {
        $repo->db->exec('UPDATE awards SET seen = 1 WHERE id = ? AND label_id = ?', [$id, $repo->labelId]);
    }

    private static function present(array $row): array
    {
        return [
            'id' => (int) $row['id'], 'bandId' => (int) $row['band_id'], 'kind' => $row['kind'], 'place' => (int) $row['place'], 'title' => $row['title'],
            'recordId' => $row['record_id'] === null ? null : (int) $row['record_id'], 'at' => (int) $row['created_at'],
        ];
    }

    /**
     * Für den Umbau: bisherige Verkäufe der Platten schätzen (ohne Schub, bis die Tantiemen abgerechnet sind) und die Auszeichnungen
     * dafür vergeben, ohne Feier (seen = 1).
     */
    public static function backfill(Db $db): void
    {
        $decay = -log(1 - Balance::CHART_DECAY);
        foreach ($db->all("SELECT id, label_id, band_id, title, chart_points, finished_at, royalties_until, topped FROM records WHERE status = 'done' AND chart_points > 0") as $r) {
            $until = min((int) $r['royalties_until'], (int) $r['finished_at'] + Balance::CHART_MAX_AGE);
            $hours = max(0, $until - (int) $r['finished_at']) / 3600;
            $sold = (float) $r['chart_points'] * (1 - exp(-$decay * $hours)) / $decay * Balance::AWARDS['copies'];
            $db->update('records', ['sold' => round($sold)], ['id' => (int) $r['id']]);
            $kinds = array_keys(array_filter(Balance::AWARDS['records'], static fn ($copies) => $sold >= $copies));
            if ((int) $r['topped'] === 1) {
                $kinds[] = 'number1';
            }
            foreach ($kinds as $kind) {
                $name = $kind === 'number1' ? 'Platz 1 der Charts' : self::RECORD_KINDS[$kind];
                $db->insert('awards', [
                    'label_id' => (int) $r['label_id'], 'band_id' => (int) $r['band_id'], 'kind' => $kind, 'place' => 1,
                    'title' => mb_substr(sprintf('%s: „%s“', $name, $r['title']), 0, 120), 'record_id' => (int) $r['id'], 'seen' => 1, 'created_at' => Clock::now(),
                ]);
            }
        }
    }
}
