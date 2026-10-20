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
}
