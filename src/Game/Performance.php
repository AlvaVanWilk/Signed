<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Rng;

/**
 * Simuliert einen automatischen Auftritt Lied für Lied: Verspieler, Glanzmomente und Publikumsreaktionen
 * bewegen die Publikumsleiste. Das Ergebnis steht beim Start fest; der Live-Ticker spielt es nur ab.
 */
final class Performance
{
    /**
     * @param list<array{name: string, instrument: string, skill: float, presence: float, traits: list<string>, techniques: list<string>}> $players
     * @return array{timeline: list<array>, meter: float, perf: float}
     */
    public static function simulate(array $players, float $sound, float $expect, int $songs): array
    {
        $length = Balance::SONG_SECONDS;
        $drift = max(-10.0, min(10.0, ($sound - $expect) / max($expect, 10) * 8));
        $meter = 50.0;
        $timeline = [];
        $songEnds = [];

        for ($s = 0; $s < $songs; $s++) {
            $start = $s * $length;
            $events = [['t' => $start + 0.5, 'kind' => 'song', 'text' => sprintf('Lied %d: „%s“', $s + 1, Generator::songTitle()), 'delta' => 0.0]];
            foreach ($players as $p) {
                $mistake = max(0.02, min(0.25, 0.25 - $p['skill'] * 0.0022));
                if (in_array('nervenstark', $p['traits'], true)) {
                    $mistake *= 0.6;
                }
                $highlight = max(0.03, min(0.35, 0.05 + $p['skill'] * 0.002 + $p['presence'] * 0.0008 + ($p['techniques'] ? 0.03 : 0)));
                $roll = Rng::float();
                if ($roll < $mistake) {
                    $events[] = ['t' => $start + Rng::float(3, 26), 'kind' => 'mistake', 'text' => self::mistakeText($p), 'delta' => -Rng::float(5, 9)];
                } elseif ($roll < $mistake + $highlight) {
                    $events[] = ['t' => $start + Rng::float(3, 26), 'kind' => 'highlight', 'text' => self::highlightText($p), 'delta' => Rng::float(5, 9)];
                }
            }
            $events[] = ['t' => $start + $length - 1.5, 'kind' => 'crowd', 'text' => '', 'delta' => Balance::LIVE_MOOD + $drift + Rng::float(-4, 4)];
            usort($events, static fn ($a, $b) => $a['t'] <=> $b['t']);

            foreach ($events as $event) {
                $meter = max(0.0, min(100.0, $meter + $event['delta']));
                $text = $event['kind'] === 'crowd' ? self::crowdText($meter) : $event['text'];
                $timeline[] = ['t' => round($event['t'], 1), 'kind' => $event['kind'], 'text' => $text, 'meter' => round($meter, 1)];
            }
            $songEnds[] = $meter;
        }

        $average = $songEnds ? array_sum($songEnds) / count($songEnds) : 50.0;
        return [
            'timeline' => $timeline,
            'meter' => round($average, 1),
            'perf' => round(0.4 + $average / 100 * 1.2, 3),
        ];
    }

    private static function mistakeText(array $p): string
    {
        $options = array_merge(Catalog::MISTAKES['any'], Catalog::MISTAKES[$p['instrument']] ?? []);
        return sprintf(Rng::pick($options), $p['name']);
    }

    private static function highlightText(array $p): string
    {
        if ($p['techniques'] && Rng::chance(0.4)) {
            return sprintf('%s: perfektes %s!', $p['name'], Catalog::TECHNIQUES[Rng::pick($p['techniques'])]);
        }
        return sprintf(Rng::pick(Catalog::HIGHLIGHTS[$p['instrument']]), $p['name']);
    }

    private static function crowdText(float $meter): string
    {
        $mood = $meter >= 65 ? 'high' : ($meter <= 35 ? 'low' : 'mid');
        return Rng::pick(Catalog::CROWD[$mood]);
    }
}
