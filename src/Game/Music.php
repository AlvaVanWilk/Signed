<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Rng;

/**
 * Die Aufnahmen aus Logic: je Genre und Können-Stufe ein Ordner public/assets/music/<genre>-<stufe>/ mit einer MP3
 * pro Spur (z. B. gitarre-elektrisch.mp3) und den Noten in noten.json. Fehlende Spuren ersetzt der Browser
 * durch die nächstbeste (andere Stufe, dann Pop).
 */
final class Music
{
    /** Instrument im Spiel → Spurname der Aufnahme; true = akustisch und elektrisch. */
    public const STEMS = [
        'vocals' => ['gesang', false], 'guitar' => ['gitarre', true], 'bass' => ['bass', true], 'drums' => ['schlagzeug', true],
        'keys' => ['keyboard', true], 'turntables' => ['turntables', false], 'sax' => ['saxophon', false],
        'trumpet' => ['trompete', false], 'violin' => ['geige', false], 'flute' => ['floete', false],
    ];

    /** Welche Aufnahmen es gibt: Ordner => Spurnamen. */
    public static function manifest(): array
    {
        $out = [];
        foreach (glob(dirname(__DIR__, 2) . '/public/assets/music/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $out[basename($dir)] = array_map(static fn ($f) => basename($f, '.mp3'), glob("$dir/*.mp3") ?: []);
        }
        return $out;
    }

    /**
     * Welche Spur ein Musiker spielt: sein Instrument, seine Können-Stufe, das Genre und – bei Gitarre,
     * Bass, Schlagzeug, Keyboard – ob sein Instrument elektrisch ist. Ohne eigenes Instrument entscheidet seine ID.
     * Folgt die Band einem Genre, spielen alle darin (Hybrid: jeder eins der beiden); sonst jeder eins seiner Lieblingsgenres.
     * @param list<string> $bandGenres
     */
    public static function stem(array $m, array $gear, array $bandGenres = []): array
    {
        $type = BandCalc::instrument($m);
        $model = $gear[$m['id']][$type] ?? null;
        return [
            'type' => $type,
            'level' => $m['level'],
            'genre' => (string) Rng::pick($bandGenres ?: $m['genres']),
            'electric' => $model === null ? $m['id'] % 2 === 1 : (bool) ($model['electric'] ?? false),
        ];
    }

    private const DIR = '/public/assets/music';
    /** C-Dur-Stufen (Halbtöne ab C); alle Aufnahmen stehen in C-Dur / a-Moll. */
    private const MAJOR = [0, 2, 4, 5, 7, 9, 11];
    private const KICK = [35, 36];
    private const SNARE = [37, 38, 40];
    /** Töne ab dieser Länge (in Schlägen) werden im Rhythmus-Spiel gehalten. */
    private const HOLD_BEATS = 1.5;

    /**
     * Ordner und Spurname der Aufnahme – dieselbe Reihenfolge wie stemUrl() im Browser:
     * Genre vor Pop, gewünschte Variante vor der anderen, nächste Stufe (bei Gleichstand die höhere).
     * @return array{folder: string, file: string}|null
     */
    public static function resolve(array $stem): ?array
    {
        [$name, $variants] = self::STEMS[$stem['type']] ?? [null, false];
        if ($name === null) {
            return null;
        }
        $electric = $stem['electric'] ? 'elektrisch' : 'akustisch';
        $other = $stem['electric'] ? 'akustisch' : 'elektrisch';
        $names = $variants ? ["$name-$electric", "$name-$other"] : [$name];
        $levels = [0, 1, 2, 3];
        usort($levels, static fn ($a, $b) => abs($a - $stem['level']) <=> abs($b - $stem['level']) ?: $b <=> $a);
        $manifest = self::manifest();
        foreach ($stem['genre'] === 'pop' ? ['pop'] : [$stem['genre'], 'pop'] as $genre) {
            foreach ($names as $file) {
                foreach ($levels as $level) {
                    if (in_array($file, $manifest["$genre-$level"] ?? [], true)) {
                        return ['folder' => "$genre-$level", 'file' => $file];
                    }
                }
            }
        }
        return null;
    }

    /** Noten einer Spur aus noten.json: [[Schlag, Dauer in Schlägen, MIDI-Ton, Anschlag], …] oder null. */
    public static function notes(array $resolved): ?array
    {
        $path = dirname(__DIR__, 2) . self::DIR . '/' . $resolved['folder'] . '/noten.json';
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $notes = $data['tracks'][$resolved['file']] ?? null;
        return $notes ? ['bpm' => (int) $data['bpm'], 'notes' => $notes] : null;
    }

    /**
     * Rhythmus-Noten aus der Aufnahme, damit jede Spur ähnlich viel zu tun gibt. Schlagzeug: nur Bassdrum
     * (Spur 1–2) und Snare (Spur 3–4), die Hi-Hat bleibt nur im Klang. Andere Instrumente: Spur nach Tonhöhe
     * (tief links), Akkorde als mehrere Noten zugleich (höchstens drei), lange Töne werden gehalten (d > 0).
     * @return array{notes: list<array{t: float, l: int, d: float}>, laneNames: list<string>}
     */
    public static function rhythmChart(array $source, string $type, int $duration): array
    {
        $beat = 60 / $source['bpm'];
        $notes = $source['notes'];
        usort($notes, static fn ($a, $b) => $a[0] <=> $b[0] ?: $a[2] <=> $b[2]);
        $out = [];
        if ($type === 'drums') {
            $count = ['kick' => 0, 'snare' => 0];
            foreach ($notes as [$at, , $pitch]) {
                $kind = in_array($pitch, self::KICK, true) ? 'kick' : (in_array($pitch, self::SNARE, true) ? 'snare' : null);
                if ($kind === null) {
                    continue;
                }
                $lane = ($kind === 'kick' ? 0 : 2) + $count[$kind]++ % 2;
                $out[] = ['t' => round($at * $beat, 3), 'l' => $lane, 'd' => 0.0];
            }
            $names = ['Bassdrum', 'Bassdrum', 'Snare', 'Snare'];
        } else {
            // Gleichzeitige Töne zu Akkorden zusammenfassen.
            $chords = [];
            foreach ($notes as $note) {
                $last = count($chords) - 1;
                if ($last >= 0 && $note[0] - $chords[$last][0][0] < 0.1) {
                    $chords[$last][] = $note;
                } else {
                    $chords[] = [$note];
                }
            }
            $pitches = array_values(array_unique(array_column($notes, 2)));
            sort($pitches);
            $lanes = Chart::LANES;
            foreach ($chords as $c => $chord) {
                $chord = self::chordNotes($chord);
                // Gehalten wird höchstens bis kurz vor dem nächsten Anschlag.
                $next = isset($chords[$c + 1]) ? $chords[$c + 1][0][0] - 0.25 : INF;
                $k = count($chord);
                $prev = -1;
                foreach ($chord as $i => [$at, $length, $pitch]) {
                    $rank = array_search($pitch, $pitches, true);
                    $lane = count($pitches) <= 1 ? 1 : intdiv($rank * $lanes, count($pitches));
                    $lane = min(max($lane, $prev + 1), $lanes - $k + $i);
                    $prev = $lane;
                    $t = $at * $beat;
                    $length = min($length, $next - $at);
                    $hold = $length >= self::HOLD_BEATS ? min($length * $beat, $duration - 0.3 - $t) : 0.0;
                    $out[] = ['t' => round($t, 3), 'l' => $lane, 'd' => round(max(0.0, $hold), 3)];
                }
            }
            $names = ['tief', '', '', 'hoch'];
        }
        $out = array_values(array_filter($out, static fn ($n) => $n['t'] < $duration - 0.3));
        usort($out, static fn ($a, $b) => $a['t'] <=> $b['t'] ?: $a['l'] <=> $b['l']);
        return ['notes' => $out, 'laneNames' => $names];
    }

    /** Höchstens drei verschiedene Töne eines Akkords: der tiefste und die beiden höchsten, aufsteigend. */
    private static function chordNotes(array $chord): array
    {
        $byPitch = [];
        foreach ($chord as $note) {
            $byPitch[$note[2]] ??= $note;
        }
        ksort($byPitch);
        $list = array_values($byPitch);
        return count($list) <= 3 ? $list : [$list[0], ...array_slice($list, -2)];
    }

    /**
     * Melodie für das Tonhöhen-Spiel: p = Stufe 0–7 der Tonleiter ab dem tiefsten Ton der Melodie,
     * dazu Grundton und Tonleiter in Halbtönen, damit der eigene Ton zur Aufnahme passt.
     * @return array{notes: list<array{t: float, d: float, p: int}>, tonality: array{root: int, scale: list<int>}}
     */
    public static function melodyChart(array $source, int $duration): array
    {
        $beat = 60 / $source['bpm'];
        $notes = $source['notes'];
        usort($notes, static fn ($a, $b) => $a[0] <=> $b[0]);
        $steps = array_map(static fn ($n) => self::diatonic($n[2]), $notes);
        $low = $steps ? min($steps) : 0;
        $out = [];
        $end = -1.0;
        foreach ($notes as $i => [$at, $length]) {
            if ($at < $end - 0.05) {
                continue;
            }
            $degree = $steps[$i] - $low;
            while ($degree > 7) {
                $degree -= 7;
            }
            $t = $at * $beat;
            $d = min($length * $beat, $duration - 0.3 - $t);
            if ($d < 0.2) {
                break;
            }
            $out[] = ['t' => round($t, 3), 'd' => round($d, 3), 'p' => $degree];
            $end = $at + $length;
        }
        $root = self::midiOf($low);
        return [
            'notes' => $out,
            'tonality' => ['root' => $root, 'scale' => array_map(static fn ($k) => self::midiOf($low + $k) - $root, range(0, 7))],
        ];
    }

    /** Stammton-Index (C-Dur): C4 = 28, D4 = 29 …; Halbtöne dazwischen zählen zum Ton darunter. */
    private static function diatonic(int $midi): int
    {
        $pc = $midi % 12;
        $step = 0;
        foreach (self::MAJOR as $i => $semi) {
            if ($semi <= $pc) {
                $step = $i;
            }
        }
        return intdiv($midi, 12) * 7 + $step;
    }

    private static function midiOf(int $diatonic): int
    {
        return intdiv($diatonic, 7) * 12 + self::MAJOR[$diatonic % 7];
    }
}
