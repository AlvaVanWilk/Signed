<?php
declare(strict_types=1);

use Signed\Api;
use Signed\Clock;
use Signed\Db;
use Signed\Schema;
use Signed\Session;

/** Minimaler Test-Runner ohne Abhängigkeiten. */
final class Tests
{
    private static array $tests = [];

    public static function add(string $name, callable $fn): void
    {
        self::$tests[$name] = $fn;
    }

    public static function run(): bool
    {
        $failed = 0;
        foreach (self::$tests as $name => $fn) {
            Clock::set(1_800_000_000);
            mt_srand(crc32($name));
            try {
                $fn();
                echo "  ✓ $name\n";
            } catch (Throwable $e) {
                $failed++;
                echo "  ✗ $name\n      " . get_class($e) . ': ' . $e->getMessage() . "\n      " . $e->getFile() . ':' . $e->getLine() . "\n";
            }
        }
        $total = count(self::$tests);
        echo $failed === 0 ? "\nAlle $total Tests bestanden.\n" : "\n$failed von $total Tests fehlgeschlagen.\n";
        return $failed === 0;
    }
}

function test(string $name, callable $fn): void
{
    Tests::add($name, static function () use ($fn): void {
        Signed\Game\Bands::$autoSolo = true;
        $fn();
    });
}

function check(bool $condition, string $message = 'Bedingung nicht erfüllt'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function checkEquals(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected != $actual) {
        throw new RuntimeException(trim($message . ' – erwartet ' . var_export($expected, true) . ', erhalten ' . var_export($actual, true)));
    }
}

/** Erwartet, dass $fn eine ApiError wirft, deren Text $fragment enthält. */
function checkApiError(callable $fn, string $fragment = ''): void
{
    try {
        $fn();
    } catch (Signed\ApiError $e) {
        if ($fragment !== '' && !str_contains($e->getMessage(), $fragment)) {
            throw new RuntimeException("Falscher Fehlertext: {$e->getMessage()}");
        }
        return;
    }
    throw new RuntimeException('ApiError erwartet');
}

final class ArraySession implements Session
{
    private ?int $id = null;

    public function labelId(): ?int
    {
        return $this->id;
    }

    public function setLabelId(?int $id): void
    {
        $this->id = $id;
    }
}

/** Ein Spieler mit eigener Session gegen eine gemeinsame Datenbank. */
final class Player
{
    public function __construct(public Api $api)
    {
    }

    public function get(string $action): array
    {
        return $this->api->handle('GET', $action, []);
    }

    public function post(string $action, array $in = []): array
    {
        return $this->api->handle('POST', $action, $in);
    }
}

function freshDb(): Db
{
    $db = new Db('sqlite::memory:');
    Schema::migrate($db);
    return $db;
}

function player(Db $db): Player
{
    return new Player(new Api($db, new ArraySession()));
}

/** Testmusiker im hydrierten Format. */
function musician(array $overrides = []): array
{
    static $id = 1000;
    return $overrides + [
        'id' => ++$id,
        'label_id' => 1,
        'first_name' => 'Test',
        'last_name' => 'Person',
        'rarity' => 'common',
        'instr1' => 'guitar',
        'skill1' => 40.0,
        'instr2' => null,
        'skill2' => null,
        'presence' => 20.0,
        'fame' => 0.0,
        'genres' => ['rock'],
        'level' => 0,
        'abilities' => [],
        'ability_choice' => null,
        'favorite' => false,
        'jam_at' => 0,
        'personality' => ['gelassen'],
        'unusual' => false,
        'look' => [],
        'band_id' => null,
        'band_slot' => 1,
        'busy_until' => 0,
        'origin' => 'test',
        'created_at' => 0,
    ];
}
