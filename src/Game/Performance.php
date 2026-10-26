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
     * @param list<array{name: string, instrument: string, skill: float, abilities: list<string>}> $players
     * @return array{timeline: list<array>, meter: float, perf: float}
     */
    public static function simulate(array $players, float $sound, float $expect, int $songs): array
    {
        $length = Balance::SONG_SECONDS;
        $phases = Balance::LIVE_PHASES;
        $phaseLength = $length / $phases;
        // Klingt die Band besser, als der Ort erwartet, steigt die Stimmung von selbst – sonst sinkt sie.
        $drift = max(-4.0, min(4.0, ($sound - $expect) / max($expect, 10) * 4));
        $meter = Balance::LIVE_START;
        // Im ersten Lied wärmt sich das Publikum ein Stück Richtung Gleichgewicht aus Grundstimmung und Sound auf.
        $warm = max(0.0, min(100.0, 50 + Balance::LIVE_WARMUP_REACH * (Balance::LIVE_MOOD + $drift) / Balance::LIVE_SETTLE));
        $timeline = [];
        $songEnds = [];

        for ($s = 0; $s < $songs; $s++) {
            $start = $s * $length;
            $timeline[] = ['t' => round($start + 0.5, 1), 'kind' => 'song', 'text' => sprintf('Lied %d: „%s“', $s + 1, Generator::songTitle()), 'meter' => round($meter, 1)];
            for ($phase = 0; $phase < $phases; $phase++) {
                $from = $start + $phase * $phaseLength;
                $events = [];
                foreach ($players as $p) {
                    // Wie oft jemand patzt oder glänzt, hängt vom Können ab.
                    $mistake = max(0.02, min(0.24, 0.24 - $p['skill'] * 0.0026));
                    // Charakter: Impulsive glänzen und patzen öfter, Ausgeglichene glänzen seltener, Technische patzen seltener
                    foreach ($p['personality'] ?? [] as $trait) {
                        $mistake += Balance::TRAITS['live'][$trait]['mistake'] ?? 0.0;
                    }
                    $mistake = max(0.01, $mistake);
                    $highlight = 0.04 + $p['skill'] * 0.0028
                        + (Levels::techniques($p) ? 0.03 : 0);
                    foreach ($p['personality'] ?? [] as $trait) {
                        $highlight += Balance::TRAITS['live'][$trait]['highlight'] ?? 0.0;
                    }
                    $highlight += $p['show'] ?? 0.0;
                    $highlight = max(0.02, min(0.36, $highlight));
                    $roll = Rng::float();
                    $at = $from + Rng::float(0.5, $phaseLength - 0.5);
                    if ($roll < $mistake) {
                        $events[] = ['t' => $at, 'kind' => 'mistake', 'text' => self::mistakeText($p), 'delta' => -Rng::float(...Balance::LIVE_SWING)];
                    } elseif ($roll < $mistake + $highlight) {
                        $events[] = ['t' => $at, 'kind' => 'highlight', 'text' => self::highlightText($p), 'delta' => Rng::float(...Balance::LIVE_SWING)];
                    }
                }
                usort($events, static fn ($a, $b) => $a['t'] <=> $b['t']);
                foreach ($events as $event) {
                    $meter = max(0.0, min(100.0, $meter + $event['delta']));
                    $timeline[] = ['t' => round($event['t'], 1), 'kind' => $event['kind'], 'text' => $event['text'], 'meter' => round($meter, 1)];
                }
                if ($s === 0) {
                    // Erstes Lied: Das Publikum lässt sich erst überzeugen und zieht schnell Richtung der Stimmung,
                    // die diese Band auf Dauer erzeugt.
                    $meter += Rng::float(-2, 2) + ($warm - $meter) * Balance::LIVE_WARMUP;
                } else {
                    // Grundstimmung und Sound wirken laufend; das Publikum beruhigt sich etwas Richtung Mitte.
                    $meter += Balance::LIVE_MOOD + $drift + Rng::float(-2, 2);
                    $meter += (50 - $meter) * Balance::LIVE_SETTLE;
                }
                $meter = max(0.0, min(100.0, $meter));
                $isLast = $phase === $phases - 1;
                $timeline[] = [
                    't' => round($from + $phaseLength - ($isLast ? 1.0 : 0.2), 1),
                    'kind' => $isLast ? 'crowd' : 'mood',
                    'text' => $isLast ? self::crowdText($meter) : '',
                    'meter' => round($meter, 1),
                ];
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
        $techniques = Levels::techniques($p);
        if ($techniques && Rng::chance(0.4)) {
            return sprintf('%s: perfektes %s!', $p['name'], Catalog::ABILITIES[Rng::pick($techniques)]['name']);
        }
        return sprintf(Rng::pick(Catalog::HIGHLIGHTS[$p['instrument']]), $p['name']);
    }

    private static function crowdText(float $meter): string
    {
        $mood = $meter >= 65 ? 'high' : ($meter <= 35 ? 'low' : 'mid');
        return Rng::pick(Catalog::CROWD[$mood]);
    }
}
