<?php

/**
 * DbMigrator — 数据库迁移的执行核心，两个入口共用：
 *   1. database/migrate.php（CLI：php database/migrate.php）
 *   2. Web 端首次访问时的自动初始化（app/core/Database.php 的 ensureSchema()）
 *
 * 语义与原 migrate.php 完全一致：
 *   - 基线 schema.sql 幂等重放（CREATE ... IF NOT EXISTS / INSERT OR IGNORE）→ 缺表缺列自愈；
 *   - 增量 migrations/*.sql 按 _migrations 台账只执行一次；
 *   - "只加列"的增量在基线已含该列（全新库）时跳过执行、仅登记。
 * 本文件不依赖 app/ 的任何东西，CLI 场景可单独 include。
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
final class DbMigrator
{
    /** 结构自检的期望表清单（与 migrate.php 原来的 Verify 段一致）。 */
    public const EXPECTED_TABLES = ['users', 'app_settings', 'customers', 'products', 'leads',
        'deals', 'orders', 'order_items', 'follow_ups', 'activities', 'attachments'];

    public static function schemaFile(): string
    {
        return __DIR__ . '/schema.sql';
    }

    public static function migrationsDir(): string
    {
        return __DIR__ . '/migrations';
    }

    public static function ensureMigrationsTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS _migrations (
    name       TEXT PRIMARY KEY,
    applied_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
)');
    }

    /** @return string[] 已登记的增量迁移文件名 */
    public static function appliedNames(PDO $pdo): array
    {
        return array_column($pdo->query('SELECT name FROM _migrations')->fetchAll(), 'name');
    }

    private static function markApplied(PDO $pdo, string $name): void
    {
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO _migrations (name) VALUES (:n)');
        $stmt->bindValue(':n', $name);
        $stmt->execute();
    }

    /** 标识符是否已是数据库中真实存在的列（表不存在时返回 false） */
    public static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        // pragma_table_info 的表名可以参数化，无需拼接标识符
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM pragma_table_info(:t) WHERE lower(name) = lower(:c)');
        $stmt->execute([':t' => $table, ':c' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * 解析"纯加列"增量文件。
     *
     * 返回 [[表名, 列名], ...]；若文件除注释/空白/分号外还含其它语句，返回 []，
     * 表示不做跳过判定，交由正常执行流程处理。
     *
     * @return array<int, array{0:string,1:string}>
     */
    public static function addedColumnsOfPureAddColumnFile(string $sql): array
    {
        // SQLite 的 ADD COLUMN 子句内不会出现分号，用 [^;]* 截到语句末尾即可
        $re = '/ALTER\s+TABLE\s+[`"\[]?([A-Za-z_][A-Za-z0-9_$]*)[`"\]]?\s+ADD\s+(?:COLUMN\s+)?[`"\[]?([A-Za-z_][A-Za-z0-9_$]*)[`"\]]?[^;]*/i';
        if (!preg_match_all($re, $sql, $m, PREG_SET_ORDER)) {
            return [];
        }
        $rest = preg_replace('/--[^\r\n]*/', ' ', $sql); // 去行注释
        $rest = preg_replace($re, ' ', $rest);           // 移除加列语句本身
        $rest = trim(preg_replace('/\s+/', ' ', str_replace(';', ' ', (string) $rest)));
        if ($rest !== '') {
            return []; // 含其它变更（建表、改 CHECK 等）——不可跳过
        }
        $cols = [];
        foreach ($m as $row) {
            $cols[] = [$row[1], $row[2]];
        }
        return $cols;
    }

    /** 执行一段 SQL；尝试包事务；执行成功才算数（抛错前已执行部分的风险由"成功才记录"兜底）。 */
    public static function execSql(PDO $pdo, string $sql, string $label, ?callable $log = null): void
    {
        // PRAGMA journal_mode / foreign_keys 不能在事务内执行
        $canTx = stripos($sql, 'journal_mode') === false && stripos($sql, 'PRAGMA ') === false;
        if ($canTx) {
            $pdo->exec('BEGIN');
        }
        try {
            $pdo->exec($sql);
            if ($canTx) {
                $pdo->exec('COMMIT');
            }
        } catch (Throwable $e) {
            if ($canTx) {
                try { $pdo->exec('ROLLBACK'); } catch (Throwable $ignored) {}
            }
            throw $e;
        }
        if ($log !== null) {
            $log('  applied: ' . $label);
        }
    }

    public static function execFile(PDO $pdo, string $file, string $label, ?callable $log = null): void
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("Cannot read {$file}");
        }
        self::execSql($pdo, $sql, $label, $log);
    }

    /** 摘掉 schema.sql 里的演示数据段（--no-demo / CRM_DEMO_DATA=0 时用）。 */
    public static function stripDemoData(string $schemaSql): string
    {
        return (string) preg_replace('/-- >>> DEMO_DATA_BEGIN >>>.*?-- >>> DEMO_DATA_END >>>/s', '', $schemaSql);
    }

    /**
     * 应用基线 + 未执行的增量。$log(string $line) 输出进度：CLI 传 print，Web 传 null（静默）。
     */
    public static function apply(PDO $pdo, string $schemaFile, string $migDir, bool $demoData, ?callable $log = null): void
    {
        // ---------- 1) 基线 schema.sql（幂等，每次都跑 => 自愈） ----------
        if ($log !== null) {
            $log('== Baseline ==');
        }
        $pdo->exec('PRAGMA foreign_keys = ON');
        $schemaSql = (string) file_get_contents($schemaFile);
        if (!$demoData) {
            $schemaSql = self::stripDemoData($schemaSql);
            if ($log !== null) {
                $log('  note: demo sample data skipped (CRM_DEMO_DATA=0 / --no-demo)');
            }
        }
        self::execSql($pdo, $schemaSql, 'schema.sql (baseline)', $log);

        // ---------- 2) 未执行的增量迁移 ----------
        if ($log !== null) {
            $log('== Incremental migrations ==');
        }
        self::ensureMigrationsTable($pdo);
        $applied = self::appliedNames($pdo);
        $files = glob($migDir . '/*.sql') ?: [];
        sort($files);
        $ran = 0;
        $skipped = 0;
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue; // 已执行过
            }
            // 基线 schema.sql 是结构的唯一权威来源，且每次运行都会自愈式重放；
            // 因此"只加列"的增量文件在基线已含该列的数据库上是多余的，跳过即可。
            $added = self::addedColumnsOfPureAddColumnFile((string) file_get_contents($file));
            if ($added) {
                $missing = [];
                foreach ($added as [$table, $column]) {
                    if (!self::columnExists($pdo, $table, $column)) {
                        $missing[] = $table . '.' . $column;
                    }
                }
                if (!$missing) {
                    if ($log !== null) {
                        $log('  skipped: ' . $name . ' (column(s) already present in baseline)');
                    }
                    self::markApplied($pdo, $name); // 效果已具备，登记以免每次重判
                    $skipped++;
                    continue;
                }
            }
            self::execFile($pdo, $file, $name, $log);
            self::markApplied($pdo, $name);
            $ran++;
        }
        if ($ran === 0 && $skipped === 0 && $log !== null) {
            $log('  nothing pending (migrations/ has no unapplied files)');
        }
    }

    /** @return string[] 期望表里还缺哪几张（空数组 = 结构齐全） */
    public static function missingExpectedTables(PDO $pdo): array
    {
        $actual = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name <> '_migrations'")->fetchAll();
        return array_diff(self::EXPECTED_TABLES, array_column($actual, 'name'));
    }
}
