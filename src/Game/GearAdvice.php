<?php
declare(strict_types=1);

namespace Signed\Game;

/**
 * Wer könnte ein freies Instrument gebrauchen? Für jeden Musiker, der diese Art spielt, der Sound vorher und
 * nachher: in der Band der Bandsound, ohne Band der eigene. Nur Verbesserungen werden gemeldet.
 */
final class GearAdvice
{
    /**
     * @param array<int, array> $musicians ID => hydrierter Musiker
     * @param array<int, array> $bands ID => Band
     * @param array<int, list<array>> $members Band-ID => Mitglieder
     * @param array<int, array<string, array>> $gearMap aus Repo::gearByMusician
     * @return list<array{musicianId: int, bandId: ?int, before: float, after: float}>
     */
    public static function upgrades(string $modelKey, array $stats, array $musicians, array $bands, array $members, array $gearMap): array
    {
        $model = Catalog::GEAR[$modelKey] ?? null;
        if ($model === null) {
            return [];
        }
        $type = $model['type'];
        $item = $stats + $model + ['key' => $modelKey];
        $out = [];
        foreach ($musicians as $m) {
            $bandId = $m['band_id'];
            if ($bandId !== null) {
                if (BandCalc::instrument($m) !== $type || !isset($bands[$bandId])) {
                    continue;
                }
                $list = $members[$bandId] ?? [];
                $sound = static fn (array $map) => BandCalc::bandSound($bands[$bandId], $list, $map);
            } else {
                $slot = $m['instr1'] === $type ? 1 : ($m['instr2'] === $type ? 2 : null);
                if ($slot === null) {
                    continue;
                }
                $solo = ['band_slot' => $slot] + $m;
                $sound = static fn (array $map) => BandCalc::sound([$solo], 0, $map);
            }
            $with = $gearMap;
            $with[$m['id']][$type] = $item;
            $before = $sound($gearMap);
            $after = $sound($with);
            if ($after - $before >= 0.1) {
                $out[] = ['musicianId' => $m['id'], 'bandId' => $bandId, 'before' => $before, 'after' => $after];
            }
        }
        usort($out, static fn ($a, $b) => ($b['bandId'] !== null) <=> ($a['bandId'] !== null) ?: ($b['after'] - $b['before']) <=> ($a['after'] - $a['before']));
        return $out;
    }
}
