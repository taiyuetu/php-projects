<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Database
 *
 * Thin PDO wrapper exposed as a singleton so every Model shares one
 * connection per request. Kept deliberately small: query building
 * belongs in Model/QueryBuilder, not here.
 */
final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct(array $config)
    {
        $driver = $config['driver'] ?? 'sqlite';

        if ($driver === 'sqlite') {
            $database = $config['database'] ?? (dirname(__DIR__, 2) . '/database/database.sqlite');
            if ($database !== ':memory:' && !preg_match('#^([a-zA-Z]:|[\\\\/])#', $database)) {
                $database = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ltrim($database, '/\\');
            }

            if ($database !== ':memory:') {
                $dir = dirname($database);
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
            }

            $dsn = "sqlite:{$database}";
            $username = null;
            $password = null;
        } else {
            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s;charset=%s',
                $driver,
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? '3306',
                $config['database'] ?? '',
                $config['charset'] ?? 'utf8mb4'
            );
            $username = $config['username'] ?? '';
            $password = $config['password'] ?? '';
        }

        try {
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
            ]);

            if ($driver === 'sqlite') {
                $this->pdo->exec('PRAGMA foreign_keys = ON;');
            }
        } catch (PDOException $e) {
            // Never leak credentials/DSN details to the user in production.
            Logger::error('Database connection failed: ' . $e->getMessage());
            throw new \RuntimeException('Could not connect to the database.');
        }
    }

    public static function getInstance(?array $config = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config ?? Config::get('database'));
        }

        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Run $callback inside a transaction; rolls back automatically on
     * any exception and rethrows. Use this for anything touching more
     * than one table (e.g. posting a journal entry + updating balances).
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($this->inTransaction()) {
                $this->rollBack();
            }
            throw $e;
        }
    }

    // Prevent clone/unserialize of the singleton.
    private function __clone(): void
    {
    }

    public function __wakeup(): void
    {
        throw new \RuntimeException('Cannot unserialize a singleton.');
    }
}
