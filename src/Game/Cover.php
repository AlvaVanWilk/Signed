<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Rng;

/**
 * Cover-Automat: Sind alle Lieder einer Platte eingespielt, fehlt nur noch das Cover. Der Server mischt die vier Bänder
 * (Motiv, Farben, Stil, Anordnung); jedes angehaltene Band wird sofort festgeschrieben – Neuladen bringt also
 * keinen zweiten Versuch. Nach dem vierten ist die Platte fertig (Studio::complete). Werte in Balance::COVER.
 */
final class Cover
{
    public function __construct(private Repo $repo)
    {
    }

    /** Bänder und schon gewählte Felder der Platte in Produktion; legt die Bänder beim ersten Aufruf an. */
    public function start(int $bandId): array
    {
        return $this->repo->db->transaction(function () use ($bandId): array {
            $record = $this->pending($bandId);
            $cover = $record['cover'];
            if (!$cover) {
                $cover = ['reels' => self::reels(), 'picks' => []];
                $this->save($record['id'], $cover);
            }
            return self::present($record, $cover);
        });
    }

    /** Ein Band ist angehalten. Beim letzten wird die Platte fertig; dann kommt deren Ergebnis zurück. */
    public function pick(int $bandId, int $reel, int $index): array
    {
        return $this->repo->db->transaction(function () use ($bandId, $reel, $index): array {
            $record = $this->pending($bandId);
            $cover = $record['cover'] ?: throw new ApiError('Der Cover-Automat läuft noch nicht.');
            if ($reel !== count($cover['picks'])) {
                throw new ApiError('Dieses Band ist schon angehalten.');
            }
            if (!isset($cover['reels'][$reel]['items'][$index])) {
                throw new ApiError('Dieses Feld gibt es nicht.');
            }
            $cover['picks'][] = $index;
            $completed = null;
            if (count($cover['picks']) === count($cover['reels'])) {
                $cover['spec'] = self::spec($cover);
                $this->save($record['id'], $cover);
                $band = $this->repo->band($bandId);
                $members = $this->repo->members($bandId);
                $record['cover'] = $cover;
                $completed = (new Studio($this->repo))->complete($record, $band, $members, $this->repo->gearByMusician());
            } else {
                $this->save($record['id'], $cover);
            }
            return ['picks' => $cover['picks'], 'completed' => $completed];
        });
    }

    /** Güte 0–1 aus den Wertigkeiten der gewählten Felder. */
    public static function quality(array $rarity): float
    {
        $points = Balance::COVER['points'];
        $sum = array_sum(array_map(static fn ($r) => $points[$r], $rarity));
        return $rarity ? $sum / (max($points) * count($rarity)) : 0.0;
    }

    /** Fertiges Cover: je Zutat der gewählte Schlüssel und seine Wertigkeit. */
    public static function spec(array $cover): array
    {
        $spec = ['rarity' => []];
        foreach ($cover['reels'] as $i => $reel) {
            $item = $reel['items'][$cover['picks'][$i]];
            $spec[$reel['key']] = $item['key'];
            $spec['rarity'][] = $item['rarity'];
        }
        $spec['quality'] = round(self::quality($spec['rarity']), 3);
        return $spec;
    }

    private static function reels(): array
    {
        $reels = [];
        foreach (Catalog::COVER_PARTS as $key => $options) {
            $rarity = [];
            foreach (Balance::COVER['rarity'] as $r => $count) {
                array_push($rarity, ...array_fill(0, $count, $r));
            }
            $rarity = Rng::sample($rarity, count($rarity));
            $pool = [];
            $items = [];
            foreach ($rarity as $r) {
                if (!$pool) {
                    $pool = Rng::sample($options, count($options));
                }
                $items[] = ['key' => array_pop($pool), 'rarity' => $r];
            }
            $reels[] = ['key' => $key, 'items' => $items];
        }
        return $reels;
    }

    private function pending(int $bandId): array
    {
        $band = $this->repo->band($bandId);
        $record = $this->repo->openRecord($band['id']);
        if ($record === null) {
            throw new ApiError('Diese Band hat keine Platte in Produktion.');
        }
        if ($record['songs_done'] < $record['songs_total']) {
            throw new ApiError('Erst alle Lieder aufnehmen, dann kommt das Cover.');
        }
        return $record;
    }

    private function save(int $recordId, array $cover): void
    {
        $this->repo->db->update('records', ['cover' => json_encode($cover)], ['id' => $recordId]);
    }

    private static function present(array $record, array $cover): array
    {
        return [
            'recordId' => $record['id'],
            'title' => $record['title'],
            'reels' => $cover['reels'],
            'picks' => $cover['picks'],
            'speeds' => Balance::COVER['speeds'],
        ];
    }
}
