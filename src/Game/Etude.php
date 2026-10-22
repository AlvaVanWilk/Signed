<?php
declare(strict_types=1);

namespace Signed\Game;

/**
 * Etüde: eine Übung, die das Spiel aus typischen Genre-Bausteinen (Styles) erzeugt – Tonart, Tempo, Akkordfolge,
 * Rhythmus-Muster. Wie schwer sie ist, hängt nur vom Können ab: Die Muster tragen Wichtigkeiten (9 = Grundgerüst,
 * 1 = Verzierung); je besser der Musiker, desto mehr davon spielt er. Alles Zufällige hängt nur am seed –
 * dieselbe Etüde (gemerkt im Notizbuch) wird mit mehr Können also dichter, bleibt aber dieselbe.
 */
final class Etude
{
    private const LANE_NAMES = [
        'drums' => ['Bassdrum', 'Snare', 'Hi-Hat', 'Tom'],
        'bass' => ['Grundton', 'Terz', 'Quinte', 'Oktave'],
        'comp' => ['Grundton', 'Terz', 'Quinte', 'Oktave'],
        'lead' => ['tief', 'mittel', 'hoch', 'ganz hoch'],
    ];
    private const DRUM_LANES = ['k', 's', 'h', 't'];

    /**
     * @return array{recipe: array, genre: string, bpm: int, meter: int, mode: string, notes: list<array>,
     *   laneNames?: list<string>, tonality?: array, backing: list<array>, chords: list<string>, key: int}
     */
    public static function build(string $instrument, string $genre, float $skill, int $seed, int $duration, bool $rap = false): array
    {
        $style = Styles::GENRES[$genre] ?? Styles::GENRES['pop'];
        $genre = isset(Styles::GENRES[$genre]) ? $genre : 'pop';
        $rand = self::random($seed);
        $role = Styles::ROLES[$instrument] ?? 'lead';
        $pitchGame = in_array($instrument, Catalog::PITCH_INSTRUMENTS, true) && !$rap;

        // Alles, was die Etüde ausmacht, hängt nur am seed (nicht am Können).
        $bpm = (int) round($style['bpm'][0] + $rand() * ($style['bpm'][1] - $style['bpm'][0]));
        $key = (int) floor($rand() * 12) - 5;                       // -5 … +6 Halbtöne um C
        $progression = $style['progressions'][(int) floor($rand() * count($style['progressions']))];
        $set = static fn (array $list) => $list[(int) floor($rand() * count($list))];
        $drums = $set($style['drums']);
        $bass = $set($style['bass']);
        $comp = $set($style['comp']);
        $lead = $set($style['lead']);

        $d = max(0.0, min(1.0, $skill / 100));
        $steps = $style['meter'] * 4;
        $stepSec = 60 / $bpm / 4;
        $barSec = $steps * $stepSec;
        $bars = (int) ceil($duration / $barSec);
        // Aufbau wie in echter Musik: drei gleiche Takte, dann die Variante (Fill). Hängt nicht am Können,
        // damit eine gemerkte Etüde mit mehr Können dieselbe bleibt.
        $form = ['A', 'A', 'A', 'B'];
        $time = static function (int $bar, int $step) use ($barSec, $stepSec, $style): float {
            $swing = $style['swing'] && $step % 4 === 2 ? $stepSec * 0.66 : 0.0;
            return $bar * $barSec + $step * $stepSec + $swing;
        };
        $chords = [];
        for ($b = 0; $b < $bars; $b++) {
            $chords[] = self::chord($progression[$b % count($progression)], $style['mode']);
        }

        $register = Styles::REGISTER[$instrument] ?? 60;
        $notes = [];
        $walk = 3;
        for ($b = 0; $b < $bars; $b++) {
            $part = $form[$b % 4];
            $chord = $chords[$b];
            $pattern = match ($role) {
                'drums' => null,
                'bass' => $bass[$part],
                'comp' => $comp[$part],
                default => $lead[$part],
            };
            if ($role === 'drums') {
                $shift = 9 - max(array_map(static fn ($p) => self::top($p, $style['meter']), $drums[$part]));
                foreach (self::DRUM_LANES as $lane => $voice) {
                    foreach (self::hits($drums[$part][$voice], $style['meter']) as [$step, $weight]) {
                        $notes[] = ['t' => $time($b, $step), 'l' => $lane, 'd' => 0.0, 'w' => $weight + $shift + $rand() * 0.999];
                    }
                }
                continue;
            }
            $count = 0;
            // Jedes Muster an seiner wichtigsten Note ausrichten (Offbeat-Bass im Elektro hat sonst für Anfänger nichts).
            $shift = 9 - self::top($pattern, $style['meter']);
            foreach (self::hits($pattern, $style['meter']) as [$step, $weight]) {
                $weight += $shift;
                $fraction = $rand() * 0.999;
                $tones = $chord['tones'];
                if ($role === 'bass') {
                    // Grundton auf der Eins und bei wichtigen Noten, sonst Quinte, Oktave, Terz im Wechsel.
                    $lane = $step === 0 || $count === 0 ? 0 : [2, 3, 1, 2][$count % 4];
                    $midi = $register + $key + $chord['root'] + [0, $tones[1], $tones[2], 12][$lane];
                    $notes[] = ['t' => $time($b, $step), 'l' => $lane, 'd' => 0.0, 'w' => $weight + $fraction, 'm' => $midi];
                } elseif ($role === 'comp') {
                    // Erster Anschlag im Takt auf dem Grundton, danach durch die Akkordtöne.
                    $lane = $count === 0 ? 0 : [2, 1, 3, 2, 1][$count % 5];
                    $midi = $register + $key + $chord['root'] + [0, $tones[1], $tones[2], 12][$lane];
                    $notes[] = ['t' => $time($b, $step), 'l' => $lane, 'd' => 0.0, 'w' => $weight + $fraction, 'm' => $midi, 'chord' => $weight >= 8];
                } else {
                    // Melodie: wichtige Noten auf Akkordtönen, dazwischen kleine Schritte durch die Tonleiter.
                    $scale = Styles::SCALES[$style['scale']];
                    if ($weight >= 8) {
                        $walk = self::nearestChordDegree($walk, $chord, $key, $scale, $rand);
                    } else {
                        $walk = max(0, min(7, $walk + [-2, -1, -1, 1, 1, 2][(int) floor($rand() * 6)]));
                    }
                    $midi = $register + $key + $scale[$walk];
                    $lane = min(3, intdiv($walk, 2));
                    $notes[] = ['t' => $time($b, $step), 'l' => $lane, 'd' => 0.0, 'w' => $weight + $fraction, 'm' => $midi, 'p' => $walk];
                }
                $count++;
            }
        }

        // Jetzt erst nach Können ausdünnen – die Entscheidungen oben sind davon unabhängig. Das Können legt fest, wie viele
        // Noten pro Sekunde kommen (gleich für jedes Muster, Tempo und Genre); davon die wichtigsten. Mit mehr Können
        // kommen also nur Noten dazu. Singen und Geige: nie die feinsten Verzierungen.
        [$low, $high] = Balance::ETUDE_RATE[$role];
        $count = (int) round(($low + ($high - $low) * $d ** Balance::ETUDE_CURVE) * $duration);
        $floor = $pitchGame ? 6 - 2 * $d : 0;
        $notes = array_values(array_filter($notes, static fn ($n) => $n['w'] >= $floor && $n['t'] < $duration - 0.3));
        usort($notes, static fn ($a, $b) => $b['w'] <=> $a['w']);
        $notes = array_slice($notes, 0, max(1, $count));
        usort($notes, static fn ($a, $b) => $a['t'] <=> $b['t'] ?: $a['l'] <=> $b['l']);

        $out = [
            'recipe' => ['genre' => $genre, 'seed' => $seed],
            'genre' => $genre, 'bpm' => $bpm, 'meter' => $style['meter'], 'key' => $key,
            'chords' => array_map(static fn ($c) => $c['name'], array_slice($chords, 0, count($progression))),
            'backing' => self::backing($role, $drums, $bass, $form, $chords, $bars, $time, $duration, $key, $style['meter']),
        ];
        if ($pitchGame) {
            $scale = Styles::SCALES[$style['scale']];
            $melody = [];
            // Spielbar, aber mit dem Können anspruchsvoller: Sprünge von 2 Stufen (Anfänger) bis 5 (Virtuose),
            // gleiten von 2,5 bis 5,5 Stufen pro Sekunde (die Schonzeit im Spiel deckt den halben Ton), Töne mit 0,4 bis 0,32 s Abstand.
            $maxJump = 2 + (int) floor($d * 3.5);
            $glide = 2.5 + 3 * $d;
            $spacing = 0.4 - 0.08 * $d;
            // Töne mit Mindestabstand auswählen (schnelle Genres singen sonst nur Fetzen) …
            $starts = [];
            foreach ($notes as $n) {
                if (!$starts || $n['t'] - end($starts)['t'] >= $spacing) {
                    $starts[] = $n;
                }
            }
            $rawPrev = 0;
            // … und jeder klingt bis kurz vor den nächsten, höchstens zwei Schläge.
            foreach ($starts as $i => $n) {
                $next = $starts[$i + 1]['t'] ?? $duration - 0.3;
                $len = min($next - $n['t'], 4 * $stepSec * 2) * 0.92;
                if ($len < $spacing * 0.75) {
                    continue;
                }
                $p = $n['p'];
                if ($melody) {
                    $prev = end($melody);
                    if ($n['w'] >= 8) {
                        // Gerüsttöne (auf Akkorden): derselbe Verlauf, mit dem Können weiter auseinandergezogen.
                        $p = $prev['p'] + (int) round(($n['p'] - $rawPrev) * (1 + 1.6 * $d));
                    } else {
                        // Zusätzliche Töne (je besser, desto mehr): echte Sprünge, fest an Nummer und Zeit gebunden.
                        $h = crc32($seed . ':' . round($n['t'], 3));
                        $p = $prev['p'] + (($h & 1) ? 1 : -1) * (1 + ($h >> 1) % max(1, $maxJump));
                    }
                    if ($p < 0 || $p > 7) {
                        $p = $prev['p'] - ($p - $prev['p']);  // an der Grenze umkehren statt kleben bleiben
                    }
                    $reach = max(1, min($maxJump, (int) floor(($n['t'] - $prev['t'] + $len / 2) * $glide)));
                    $p = max(0, min(7, max($prev['p'] - $reach, min($prev['p'] + $reach, $p))));
                }
                $rawPrev = $n['p'];
                $melody[] = ['t' => round($n['t'], 3), 'd' => round($len, 3), 'p' => $p];
            }
            // Großer Sprung (ab 3 Stufen): den Ton davor hinten kürzen, damit Zeit zum Hinübergleiten bleibt.
            for ($i = 0; $i < count($melody) - 1; $i++) {
                $jump = abs($melody[$i + 1]['p'] - $melody[$i]['p']);
                if ($jump >= 3) {
                    $end = $melody[$i + 1]['t'] - 0.07 * $jump;
                    $melody[$i]['d'] = round(max(0.15, min($melody[$i]['d'], $end - $melody[$i]['t'])), 3);
                }
            }
            return $out + [
                'mode' => 'pitch',
                'notes' => $melody,
                'tonality' => ['root' => $register + $key, 'scale' => $scale],
            ];
        }

        $rhythm = [];
        foreach ($notes as $i => $n) {
            // Gehaltene Töne (ab Fortgeschritten): langer Abstand bis zur nächsten Note derselben Rolle.
            $hold = 0.0;
            if ($role !== 'drums' && $d >= 0.3 && $n['w'] >= 8) {
                $next = $notes[$i + 1]['t'] ?? $duration;
                if ($next - $n['t'] >= 4 * $stepSec - 0.001) {
                    $hold = round(($next - $n['t']) * 0.7, 3);
                }
            }
            $rhythm[] = ['t' => round($n['t'], 3), 'l' => $n['l'], 'd' => $hold] + (isset($n['m']) ? ['m' => $n['m']] : []);
            // Zweiklang (ab Fortgeschritten): Begleitung schlägt auf wichtigen Noten zwei Saiten an.
            if (!empty($n['chord']) && $d >= 0.45) {
                $lane = $n['l'] === 0 ? 2 : 0;
                $rhythm[] = ['t' => round($n['t'], 3), 'l' => $lane, 'd' => $hold, 'm' => $n['m'] + ($lane === 2 ? 7 : -7)];
            }
        }
        $names = self::LANE_NAMES[$role];
        if ($role === 'drums' && $genre === 'jazz') {
            $names[2] = 'Ride';
        }
        if ($role === 'drums' && $genre === 'elektro') {
            $names[3] = 'Clap';
        }
        return $out + ['mode' => 'rhythm', 'notes' => $rhythm, 'laneNames' => $names];
    }

    /**
     * Fertiges Minispiel aus einer Etüde (gleiches Format wie die Solo-Probe): Rhythmus- oder Tonhöhen-Spiel,
     * Trefferfenster nach Können, Begleitung, Tick im richtigen Takt.
     */
    public static function game(string $instrument, string $genre, float $skill, int $seed, bool $perfectPitch = false, bool $rap = false): array
    {
        $duration = Balance::SONG_SECONDS;
        $e = self::build($instrument, $genre, $skill, $seed, $duration, $rap);
        $game = [
            'mode' => $e['mode'], 'notes' => $e['notes'], 'bpm' => $e['bpm'], 'meter' => $e['meter'], 'duration' => $duration,
            'instrument' => $instrument, 'backing' => $e['backing'], 'count' => count($e['notes']),
            'etude' => ['recipe' => $e['recipe'], 'genre' => $e['genre'], 'chords' => $e['chords'], 'key' => $e['key'], 'bpm' => $e['bpm'], 'meter' => $e['meter']],
        ];
        if ($e['mode'] === 'pitch') {
            // Die Etüde wird mit dem Können schwerer – das Trefferfeld darf dann nicht breiter werden, sondern enger.
            $d = min(100.0, $skill) / 100;
            $tolerance = round(0.1 - 0.025 * $d + ($perfectPitch ? 0.02 : 0), 3);
            // Schonzeit nach Sprüngen: Anfänger ganz, Profis kaum noch – wer gut ist, trifft den neuen Ton sofort.
            return $game + ['tonality' => $e['tonality'], 'tolerance' => $tolerance, 'grace' => round(1 - 0.45 * $d, 2)];
        }
        return $game + [
            'windows' => Chart::windows($skill, $perfectPitch),
            'lanes' => array_fill(0, Chart::LANES, $instrument),
            'laneNames' => $e['laneNames'],
        ];
    }

    /** Akkord aus Stufe: „bVII“ → Grundton 10, Dur; „ii7“ → 2, Moll mit kleiner Septime. */
    public static function chord(string $numeral, string $mode): array
    {
        preg_match('/^(b?)([ivIV]+)(maj7|7)?$/', $numeral, $m);
        $roman = strtoupper($m[2]);
        $base = ['I' => 0, 'II' => 2, 'III' => 4, 'IV' => 5, 'V' => 7, 'VI' => 9, 'VII' => 11][$roman];
        // In Moll stehen III, VI, VII ohnehin tiefer – geschrieben wird trotzdem mit b (bVI), wie üblich.
        $root = ($base - ($m[1] === 'b' ? 1 : 0) + 12) % 12;
        $minor = $m[2] === strtolower($m[2]);
        $third = $minor ? 3 : 4;
        $seventh = ($m[3] ?? '') === 'maj7' ? 11 : (($m[3] ?? '') === '7' ? 10 : null);
        return [
            'name' => $numeral, 'root' => $root, 'minor' => $minor,
            'tones' => array_values(array_filter([0, $third, 7, $seventh], static fn ($x) => $x !== null)),
        ];
    }

    /** Höchste Wichtigkeit in einem Muster (nach Deckelung durch die Taktposition). */
    private static function top(string $pattern, int $meter): int
    {
        return max([0, ...array_column(self::hits($pattern, $meter), 1)]);
    }

    /**
     * [Sechzehntel, Wichtigkeit] je Note eines Musters. Die Taktposition deckelt die Wichtigkeit: Eins und Backbeat
     * bis 9, übrige Viertel bis 8, Achtel bis 6, Sechzehntel bis 4 – so kommen erst die Schläge, dann Achtel, dann
     * Sechzehntel dazu, auch wenn ein Muster viele gleich wichtige Noten hat (z. B. Hi-Hat).
     */
    private static function hits(string $pattern, int $meter): array
    {
        $strong = $meter === 3 ? [0] : [0, 4, 12];
        $out = [];
        foreach (str_split($pattern) as $step => $char) {
            if (ctype_digit($char) && $char !== '0') {
                $cap = in_array($step, $strong, true) ? 9 : ($step % 4 === 0 ? 8 : ($step % 2 === 0 ? 6 : 4));
                $out[] = [$step, min((int) $char, $cap)];
            }
        }
        return $out;
    }

    /** Stufe der Tonleiter, die einem Akkordton am nächsten liegt (bei Gleichstand zufällig). */
    private static function nearestChordDegree(int $from, array $chord, int $key, array $scale, callable $rand): int
    {
        $pcs = array_map(static fn ($t) => ($chord['root'] + $t) % 12, $chord['tones']);
        $best = [];
        $bestDist = 99;
        foreach ($scale as $degree => $semi) {
            if (!in_array($semi % 12, $pcs, true)) {
                continue;
            }
            $dist = abs($degree - $from);
            if ($dist < $bestDist) {
                [$best, $bestDist] = [[$degree], $dist];
            } elseif ($dist === $bestDist) {
                $best[] = $degree;
            }
        }
        return $best ? $best[(int) floor($rand() * count($best))] : $from;
    }

    /**
     * Leise Begleitung aus dem Synthesizer: Schlagzeug, Bass, Akkorde – ohne die Rolle, die man selbst spielt.
     * @return list<array{0: float, 1: string, 2?: int, 3?: float}> [Zeit, Art (k/s/h/t/bass/pad), MIDI, Länge]
     */
    private static function backing(string $role, array $drums, array $bass, array $form, array $chords, int $bars, callable $time, int $duration, int $key, int $meter): array
    {
        $events = [];
        for ($b = 0; $b < $bars; $b++) {
            $part = $form[$b % 4];
            $chord = $chords[$b];
            if ($role !== 'drums') {
                foreach (self::DRUM_LANES as $voice) {
                    foreach (self::hits($drums[$part][$voice], $meter) as [$step, $weight]) {
                        if ($weight >= 4) {
                            $events[] = [round($time($b, $step), 3), $voice];
                        }
                    }
                }
            }
            if ($role !== 'bass') {
                foreach (self::hits($bass[$part], $meter) as [$step, $weight]) {
                    if ($weight >= 6) {
                        // Eine Oktave über dem echten Bass: Tablet-Lautsprecher spielen so tiefe Töne kaum.
                        $events[] = [round($time($b, $step), 3), 'bass', 48 + $key + $chord['root'], 0.3];
                    }
                }
            }
            // Akkorde unter jeder Übung, auch unter der eigenen Begleitung: Erst sie machen die Begleitband hörbar.
            foreach ($chord['tones'] as $tone) {
                $events[] = [round($time($b, 0), 3), 'pad', 60 + $key + $chord['root'] + $tone, round($time($b + 1, 0) - $time($b, 0), 3)];
            }
        }
        return array_values(array_filter($events, static fn ($e) => $e[0] < $duration));
    }

    /** Kleiner eigener Zufallsgenerator (mulberry32): dieselbe Zahl → dieselbe Etüde, ohne den Spiel-Zufall zu stören. */
    private static function random(int $seed): callable
    {
        $state = $seed & 0xFFFFFFFF;
        return static function () use (&$state): float {
            $state = ($state + 0x6D2B79F5) & 0xFFFFFFFF;
            $t = $state;
            $t = self::mul($t ^ ($t >> 15), $t | 1);
            $t ^= ($t + self::mul($t ^ ($t >> 7), $t | 61)) & 0xFFFFFFFF;
            return (($t ^ ($t >> 14)) & 0xFFFFFFFF) / 4294967296;
        };
    }

    private static function mul(int $a, int $b): int
    {
        // 32-Bit-Multiplikation ohne Überlauf in float.
        return ((($a & 0xFFFF) * $b) + (((($a >> 16) * $b) & 0xFFFF) << 16)) & 0xFFFFFFFF;
    }
}
