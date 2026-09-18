<?php

/**
 * Database
 *
 * Thin PDO wrapper providing prepared-statement query helpers.
 * Uses a singleton connection so the whole request shares one PDO instance.
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
class Database
{
    private static ?PDO $instance = null;
    private PDO $pdo;
    private $stmt;

    public function __construct()
    {
        $this->pdo = self::connection();
    }

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $dbPath = DB_PATH;
            $dsn = 'sqlite:' . $dbPath;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, null, null, $options);
                // Enable foreign key support (off by default in SQLite)
                self::$instance->exec('PRAGMA foreign_keys = ON');
                // Enable WAL mode for better concurrent read performance
                self::$instance->exec('PRAGMA journal_mode = WAL');
            } catch (PDOException $e) {
                if (APP_DEBUG) {
                    die('Database connection failed: ' . $e->getMessage());
                }
                die('Database connection failed. Please try again later.');
            }
        }

        return self::$instance;
    }

    /**
     * 结构是否就绪：users 表存在，且 migrations/ 里没有未登记的增量文件。
     * 每次请求都要过这一关，所以只做两条快查询 + 一个目录扫描，不做任何写入。
     */
    public static function schemaReady(): bool
    {
        try {
            $pdo = self::connection();
            $hasUsers = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn() > 0;
            if (!$hasUsers) {
                return false;
            }
            $applied = array_flip($pdo->query('SELECT name FROM _migrations')->fetchAll(PDO::FETCH_COLUMN));
            foreach (glob(BASE_PATH . '/database/migrations/*.sql') ?: [] as $file) {
                if (!isset($applied[basename((string) $file)])) {
                    return false;   // 有新代码带来的增量还没补
                }
            }
            return true;
        } catch (Throwable $e) {
            return false;   // 表不存在/不是合法库等，都视为“需要初始化”
        }
    }

    /**
     * 首次使用免 CLI：库没建/缺增量时，自动执行与 php database/migrate.php 完全相同的
     * 幂等迁移（核心在 database/migrator.php 的 DbMigrator）。已就绪时零成本返回。
     *
     * 并发防护：flock + 双重检查 —— 多个 FPM worker 同时撞上空库时，只有拿到锁的
     * 那个真正建库，其余在锁上等完、重查发现已就绪后直接走人。
     */
    public static function ensureSchema(): void
    {
        if (self::schemaReady()) {
            return;
        }
        require_once BASE_PATH . '/database/migrator.php';

        // 默认带演示数据，口径与 migrate.php 一致：CRM_DEMO_DATA=0 跳过
        $demo = getenv('CRM_DEMO_DATA');
        if ($demo === false || trim((string) $demo) === '') {
            $demo = $_ENV['CRM_DEMO_DATA'] ?? $_SERVER['CRM_DEMO_DATA'] ?? '';
        }
        $demo = trim((string) $demo);
        $demoData = $demo === '' ? true : in_array(strtolower($demo), ['1', 'true', 'yes', 'on'], true);

        $fh = @fopen(sys_get_temp_dir() . '/crm-migrate-' . md5(DB_PATH) . '.lock', 'c');
        $locked = $fh !== false && flock($fh, LOCK_EX);
        try {
            if (self::schemaReady()) {
                return;   // 另一个 worker 已经把库建好了
            }
            $pdo = self::connection();
            DbMigrator::apply($pdo, DbMigrator::schemaFile(), DbMigrator::migrationsDir(), $demoData);
            $missing = DbMigrator::missingExpectedTables($pdo);
            if ($missing) {
                die('数据库初始化不完整（缺少表：' . implode(', ', $missing) . '）。'
                    . '请检查 database/schema.sql 与 database/migrations/，'
                    . '或手动执行 php database/migrate.php 查看详细报错。');
            }
        } catch (Throwable $e) {
            die('数据库初始化失败：' . (APP_DEBUG ? $e->getMessage()
                : '请确认 database/ 目录可写，或手动执行 php database/migrate.php 后重试。'));
        } finally {
            if ($locked) {
                flock($fh, LOCK_UN);
                fclose($fh);
            }
        }
    }

    /** Prepare a statement. */
    public function query(string $sql): self
    {
        $this->stmt = $this->pdo->prepare($sql);
        return $this;
    }

    /** Bind a single value to the prepared statement. */
    public function bind($param, $value, $type = null): self
    {
        if ($type === null) {
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default          => PDO::PARAM_STR,
            };
        }
        $this->stmt->bindValue($param, $value, $type);
        return $this;
    }

    /** Bind an associative array of params at once. */
    public function bindAll(array $params): self
    {
        foreach ($params as $key => $value) {
            $this->bind(is_int($key) ? $key + 1 : ':' . ltrim($key, ':'), $value);
        }
        return $this;
    }

    public function execute(): bool
    {
        return $this->stmt->execute();
    }

    /** Return all rows. */
    public function resultSet(): array
    {
        $this->execute();
        return $this->stmt->fetchAll();
    }

    /** Return a single row (or false). */
    public function single()
    {
        $this->execute();
        return $this->stmt->fetch();
    }

    public function rowCount(): int
    {
        return $this->stmt->rowCount();
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
}
