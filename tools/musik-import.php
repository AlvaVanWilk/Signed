<?php
declare(strict_types=1);

/**
 * Musik einlesen (läuft lokal, nicht auf dem Webspace; braucht ffmpeg):
 *
 *   php tools/musik-import.php pop-0 Pop_0.mid Pop_Guitar_1.wav Pop_Bass_E_1.wav …
 *
 * - MIDI (Logic-Export „Auswahl als MIDI“): Noten je Spur → public/assets/music/<ordner>/noten.json.
 *   Spuren mit gleichem Namen werden zusammengefasst, alles nach 12 Takten wird abgeschnitten,
 *   das Tempo in der Datei zählt nicht (wir rechnen mit 120 BPM).
 * - WAV: auf die Lautheit seiner Rolle gebracht (LUFS, siehe TARGETS), auf 24 Sekunden geschnitten,
 *   als MP3 → public/assets/music/<ordner>/<spur>.mp3.
 *
 * Spurnamen aus Logic wie „Pop Guitar E“ werden zu „gitarre-elektrisch“, „Pop Guitar“ zu „gitarre-akustisch“.
 */

const BARS = 12;
const SECONDS = 24;

/** Englischer bzw. Logic-Name → Spurname im Spiel; true = gibt es akustisch und elektrisch. */
const NAMES = [
    'drummer' => ['schlagzeug', true], 'drums' => ['schlagzeug', true],
    'bass' => ['bass', true],
    'guitar' => ['gitarre', true],
    'piano' => ['keyboard', true], 'keys' => ['keyboard', true], 'keyboard' => ['keyboard', true],
    'vocal' => ['gesang', false], 'vocals' => ['gesang', false],
    'flute' => ['floete', false], 'violine' => ['geige', false], 'violin' => ['geige', false],
    'trumpet' => ['trompete', false], 'sax' => ['saxophon', false], 'turntables' => ['turntables', false],
];

/** Ziel-Lautheit je Rolle in LUFS: Gesang vorn, Bass und Schlagzeug tragen, der Rest begleitet. */
const TARGETS = [
    'gesang' => -17, 'bass' => -19, 'schlagzeug' => -19, 'keyboard' => -21,
    'gitarre' => -23, 'floete' => -22, 'geige' => -22, 'trompete' => -22, 'saxophon' => -22, 'turntables' => -21,
];

[$script, $folder, $midi] = $argv + [null, null, null];
$wavs = array_slice($argv, 3);
if ($folder === null || $midi === null || !preg_match('/^[a-z]+-[0-3]$/', $folder)) {
    fwrite(STDERR, "Aufruf: php tools/musik-import.php <genre-stufe, z. B. pop-0> <datei.mid> [spur.wav …]\n");
    exit(1);
}
$out = dirname(__DIR__) . "/public/assets/music/$folder";
@mkdir($out, 0775, true);

/** „Pop Guitar E“ / „Pop_Guitar_E_1.wav“ → „gitarre-elektrisch“; null, wenn unbekannt. */
function trackName(string $label): ?string
{
    $words = preg_split('/[\s_]+/', strtolower(trim(preg_replace('/\.[a-z0-9]+$/i', '', $label))));
    $words = array_values(array_filter($words, static fn ($w) => $w !== '' && !ctype_digit($w)));
    $electric = end($words) === 'e';
    foreach ($words as $word) {
        if (isset(NAMES[$word])) {
            [$name, $variants] = NAMES[$word];
            return $variants ? $name . ($electric ? '-elektrisch' : '-akustisch') : $name;
        }
    }
    return null;
}

// ---------- MIDI ----------

$data = file_get_contents($midi);
if ($data === false || substr($data, 0, 4) !== 'MThd') {
    fwrite(STDERR, "Keine MIDI-Datei: $midi\n");
    exit(1);
}
$division = unpack('n', substr($data, 12, 2))[1];
$count = unpack('n', substr($data, 10, 2))[1];
$pos = 14;
$limit = BARS * 4 * $division;
$tracks = [];
$readVlq = static function (string $d, int &$i): int {
    $v = 0;
    do {
        $b = ord($d[$i++]);
        $v = ($v << 7) | ($b & 0x7f);
    } while ($b & 0x80);
    return $v;
};
for ($t = 0; $t < $count; $t++) {
    $length = unpack('N', substr($data, $pos + 4, 4))[1];
    $d = substr($data, $pos + 8, $length);
    $pos += 8 + $length;
    $i = 0;
    $tick = 0;
    $status = 0;
    $labels = [];
    $open = [];
    $notes = [];
    while ($i < strlen($d)) {
        $tick += $readVlq($d, $i);
        $b = ord($d[$i]);
        if ($b === 0xff) {
            $type = ord($d[$i + 1]);
            $i += 2;
            $len = $readVlq($d, $i);
            if ($type === 3 || $type === 4) {
                $labels[] = substr($d, $i, $len); // Logic schreibt Regionsname und Spurname; der Spurname kommt zuletzt
            }
            $i += $len;
            continue;
        }
        if ($b === 0xf0 || $b === 0xf7) {
            $i++;
            $i += $readVlq($d, $i);
            continue;
        }
        if ($b & 0x80) {
            $status = $b;
            $i++;
        }
        $kind = $status & 0xf0;
        $bytes = in_array($kind, [0xc0, 0xd0], true) ? 1 : 2;
        $pitch = ord($d[$i]);
        $velocity = $bytes === 2 ? ord($d[$i + 1]) : 0;
        $i += $bytes;
        if ($kind === 0x90 && $velocity > 0) {
            $open[$pitch][] = [$tick, $velocity];
        } elseif ($kind === 0x80 || ($kind === 0x90 && $velocity === 0)) {
            if (!empty($open[$pitch])) {
                [$start, $vel] = array_shift($open[$pitch]);
                if ($start < $limit) {
                    $notes[] = [round($start / $division, 3), round((min($tick, $limit) - $start) / $division, 3), $pitch, $vel];
                }
            }
        }
    }
    $name = null;
    foreach (array_reverse($labels) as $label) {
        $name ??= trackName($label);
    }
    if ($name === null) {
        if ($notes) {
            echo '  ? Spur „' . implode(' / ', $labels) . "“ nicht zugeordnet, übersprungen\n";
        }
        continue;
    }
    $tracks[$name] = array_merge($tracks[$name] ?? [], $notes);
}
foreach ($tracks as $name => &$notes) {
    usort($notes, static fn ($a, $b) => $a[0] <=> $b[0] ?: $a[2] <=> $b[2]);
    echo sprintf("  MIDI %-22s %3d Noten\n", $name, count($notes));
}
unset($notes);
file_put_contents("$out/noten.json", json_encode(['bpm' => 120, 'bars' => BARS, 'tracks' => $tracks], JSON_UNESCAPED_UNICODE));

// ---------- Audio ----------

foreach ($wavs as $wav) {
    $name = trackName(basename($wav));
    if ($name === null) {
        echo "  ? Datei „" . basename($wav) . "“ nicht zugeordnet, übersprungen\n";
        continue;
    }
    $role = explode('-', $name)[0];
    $measure = shell_exec(sprintf('ffmpeg -hide_banner -nostats -i %s -af ebur128 -f null - 2>&1', escapeshellarg($wav)));
    preg_match_all('/I:\s+(-?[0-9.]+) LUFS/', (string) $measure, $m);
    $loudness = (float) end($m[1]);
    $gain = round(TARGETS[$role] - $loudness, 1);
    exec(sprintf(
        'ffmpeg -hide_banner -loglevel error -y -i %s -t %d -af %s -ar 44100 -codec:a libmp3lame -b:a 128k %s',
        escapeshellarg($wav),
        SECONDS,
        escapeshellarg("volume={$gain}dB,alimiter=limit=0.95:level=disabled"),
        escapeshellarg("$out/$name.mp3")
    ), $output, $code);
    echo sprintf("  MP3  %-22s %6.1f LUFS → %d (%+.1f dB)%s\n", $name, $loudness, TARGETS[$role], $gain, $code === 0 ? '' : '  FEHLER');
}
