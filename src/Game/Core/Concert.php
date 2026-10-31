<?php
declare(strict_types=1);

namespace Signed\Game\Core;

use Signed\Game\Balance;

/**
 * Ein Auftritt, für sich gerechnet (Sandras Formel): Wie viele kommen, hängt von den Fans, dem Hype der neuesten Platte und der Frische ab.
 * Den Ort wählt das Spiel, Kosten gibt es keine; das Label verdient je Zuschauer nach Ruhmstufe. Noch nicht ins Spiel eingebaut (neuer Kern).
 */
final class Concert
{
    /**
     * Wer bei voller Frische kommen will: Fans × (b + c × A) und Neugierige (Pool − Fans) × (n0 + n × A × (1 + w × F0 / Pool)).
     * @return array{0: float, 1: float} [Fans, Neugierige]
     */
    public static function demand(float $fans, float $hype, float $f0, float $pool): array
    {
        $c = Balance::CORE_CONCERT;
        $a = $hype / 100;
        return [
            $fans * ($c['b'] + $c['c'] * $a),
            max(0.0, $pool - $fans) * ($c['n0'] + $c['n'] * $a * (1 + $c['w'] * $f0 / $pool)),
        ];
    }

    /** Frische jetzt: Sie erholt sich gleichmäßig, in `freshHours` Stunden von nichts auf ganz. Vor dem ersten Auftritt ist sie ganz. */
    public static function freshness(float $fresh, ?int $lastGig, int $now): float
    {
        if ($lastGig === null) {
            return 1.0;
        }
        return min(1.0, $fresh + max(0, $now - $lastGig) / (Balance::CORE_CONCERT['freshHours'] * 3600));
    }

    /** Frische direkt nach einem Auftritt. */
    public static function afterGig(float $fresh): float
    {
        return $fresh * Balance::CORE_CONCERT['freshAfter'];
    }

    /**
     * Ort: die kleinste Größe, in die alle passen; reicht keine, die größte, die die Ruhmstufe erlaubt. Halle und Arena nur auf Tournee.
     * @return array{0: string, 1: int} [Ort, Plätze]
     */
    public static function venue(int $stage, float $demand, bool $tour = false): array
    {
        $options = [];
        foreach (Balance::CORE_VENUES as $key => $venue) {
            if ($stage >= $venue['stage'] && ($tour || !($venue['tour'] ?? false))) {
                foreach ($venue['sizes'] as $size) {
                    $options[] = [$key, $size];
                }
            }
        }
        usort($options, static fn ($a, $b) => $a[1] <=> $b[1]);
        foreach ($options as $option) {
            if ($option[1] >= $demand) {
                return $option;
            }
        }
        return $options[count($options) - 1];
    }

    public static function earningsPerVisitor(int $stage): int
    {
        return Balance::CORE_EARNINGS[$stage];
    }

    /**
     * Ein Auftritt. $hype, $f0 und $pool gehören zur neuesten Platte (ohne Platte: Hype 0, F0 = Fans, Pool aus den Fans).
     * Ist der Ort voll, werden nur die Neugierigen Fans, die hineinpassen. $live = Live-Qualität 0–100.
     * @return array{venue: string, size: int, demand: float, tickets: float, earnings: float, newFans: float}
     */
    public static function play(float $fans, float $hype, float $f0, float $pool, float $fresh, float $live, bool $tour = false): array
    {
        [$fanDemand, $curious] = self::demand($fans, $hype, $f0, $pool);
        $demand = ($fanDemand + $curious) * $fresh;
        $stage = Fans::stage($fans);
        [$venue, $size] = self::venue($stage, $demand, $tour);
        $tickets = min($demand, (float) $size);
        $newFans = $demand > 0 ? $curious * $fresh * ($tickets / $demand) * Balance::CORE_CONCERT['u'] * $live / 100 : 0.0;
        return [
            'venue' => $venue,
            'size' => $size,
            'demand' => $demand,
            'tickets' => $tickets,
            'earnings' => $tickets * self::earningsPerVisitor($stage),
            'newFans' => $newFans,
        ];
    }
}
