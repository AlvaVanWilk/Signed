<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Db;

/** Charts über alle Spieler: eine Liste pro Genre (Bandruhm) und eine Label-Gesamtliste. */
final class Charts
{
    private const TOP = 50;

    public static function build(Db $db, int $labelId): array
    {
        $rows = $db->all(
            'SELECT b.id, b.name, b.genre, b.sound, b.label_id, l.name AS label_name,
                    COALESCE(SUM(m.fame), 0) AS fame, COUNT(m.id) AS members
             FROM bands b
             JOIN labels l ON l.id = b.label_id
             LEFT JOIN musicians m ON m.band_id = b.id
             GROUP BY b.id, b.name, b.genre, b.sound, b.label_id, l.name'
        );

        $genres = array_fill_keys(array_keys(Catalog::GENRES), []);
        $labels = [];
        foreach ($rows as $row) {
            $own = (int) $row['label_id'] === $labelId;
            $fame = round((float) $row['fame'], 1);
            if (isset($genres[$row['genre']])) {
                $genres[$row['genre']][] = [
                    'bandId' => (int) $row['id'],
                    'band' => $row['name'],
                    'label' => $row['label_name'],
                    'fame' => $fame,
                    'sound' => round((float) $row['sound'], 1),
                    'members' => (int) $row['members'],
                    'own' => $own,
                ];
            }
            // Label-Gesamtliste: Summe des Ruhms aller eigenen Bands.
            $id = (int) $row['label_id'];
            $labels[$id] ??= ['label' => $row['label_name'], 'fame' => 0.0, 'bands' => 0, 'own' => $own];
            $labels[$id]['fame'] = round($labels[$id]['fame'] + $fame, 1);
            $labels[$id]['bands']++;
        }

        foreach ($genres as $genre => $list) {
            $genres[$genre] = self::rank($list);
        }
        return ['genres' => $genres, 'labels' => self::rank(array_values($labels))];
    }

    /** Sortiert nach Ruhm, vergibt Plätze und kürzt auf die Top-Liste (eigene Einträge bleiben immer). */
    private static function rank(array $list): array
    {
        usort($list, static fn ($a, $b) => $b['fame'] <=> $a['fame']);
        $out = [];
        foreach ($list as $index => $entry) {
            if ($index < self::TOP || $entry['own']) {
                $out[] = $entry + ['rank' => $index + 1];
            }
        }
        return $out;
    }
}
