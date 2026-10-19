<?php
declare(strict_types=1);

namespace Signed;

use PDO;
use PDOStatement;

/** Dünne PDO-Hülle, die mit SQLite und MySQL/MariaDB funktioniert. */
final class Db
{
    private PDO $pdo;
    private string $driver;
    private int $depth = 0;

    public function __construct(string $dsn, ?string $user = null, ?string $pass = null)
    {
        if (str_starts_with($dsn, 'sqlite:')) {
            $file = substr($dsn, 7);
            if ($file !== ':memory:' && !is_dir(dirname($file))) {
                mkdir(dirname($file), 0775, true);
            }
        }
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($this->driver === 'sqlite') {
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
        }
    }

    public static function fromConfig(): self
    {
        $c = Config::get('db');
        return new self($c['dsn'], $c['user'] ?? null, $c['pass'] ?? null);
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $st = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
            $type = match (true) {
                $value === null => PDO::PARAM_NULL,
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_INT,
                default => PDO::PARAM_STR,
            };
            $st->bindValue($name, is_bool($value) ? (int) $value : $value, $type);
        }
        $st->execute();
        return $st;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function value(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** Führt eine Anweisung aus und liefert die Zahl der betroffenen Zeilen. */
    public function exec(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    public function raw(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    public function insert(string $table, array $row): int
    {
        $cols = array_keys($row);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', array_map(static fn ($c) => ':' . $c, $cols))
        );
        $this->run($sql, $row);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $row, array $where): int
    {
        $set = [];
        $params = [];
        foreach ($row as $col => $value) {
            $set[] = "$col = :s_$col";
            $params["s_$col"] = $value;
        }
        $cond = [];
        foreach ($where as $col => $value) {
            $cond[] = "$col = :w_$col";
            $params["w_$col"] = $value;
        }
        return $this->exec(sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $set), implode(' AND ', $cond)), $params);
    }

    /** Führt $fn in einer Transaktion aus. Verschachtelte Aufrufe laufen in der äußeren mit. */
    public function transaction(callable $fn): mixed
    {
        if ($this->depth > 0) {
            $this->depth++;
            try {
                return $fn();
            } finally {
                $this->depth--;
            }
        }
        // SQLite: IMMEDIATE holt die Schreibsperre sofort und vermeidet Deadlocks bei parallelen Anfragen.
        $this->driver === 'sqlite' ? $this->pdo->exec('BEGIN IMMEDIATE') : $this->pdo->beginTransaction();
        $this->depth = 1;
        try {
            $result = $fn();
            $this->driver === 'sqlite' ? $this->pdo->exec('COMMIT') : $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->driver === 'sqlite' ? $this->pdo->exec('ROLLBACK') : $this->pdo->rollBack();
            throw $e;
        } finally {
            $this->depth = 0;
        }
    }
}
