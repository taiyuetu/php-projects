<?php

/**
 * 叁程 CRM (Triphase CRM) 统一数据库迁移入口 (single source of truth for DB setup)
 *
 * 用法 (运行一次即可，可重复执行，幂等)：
 *   php database/migrate.php             # 创建/升级数据库
 *   php database/migrate.php --status    # 只查看当前迁移状态
 *   php database/migrate.php --db=/path/to/other.sqlite
 *
 * 工作机制：
 *   1. 基线 (schema.sql)：
 *      schema.sql 是"结构 + 索引 + 触发器 + 种子数据"的唯一权威文件，
 *      并且完全幂等 (全部使用 CREATE ... IF NOT EXISTS / INSERT OR IGNORE)。
 *      因此每次运行本脚本都会重新执行它 —— 旧数据库缺任何表/列/索引都会被自动补齐 (自愈)。
 *   2. 增量迁移 (database/migrations/*.sql)：
 *      对现有表做无法幂等表达的变更 (如 ALTER TABLE ... ADD COLUMN) 时，
 *      在 database/migrations/ 目录新增一个 NNN_名称.sql 文件。
 *      本脚本按文件名顺序执行一次并记录到 _migrations 表，之后不会再重复执行。
 *      若某个增量文件通篇只有 ADD COLUMN 语句，而其中的列在基线里已经存在
 *      （全新数据库就是这种情况），则自动跳过执行、仅登记，避免 duplicate column name。
 *
 * 约定：
 *   - 新增"整张新表/索引/触发器"→ 直接写进 schema.sql 即可（不需要增量文件）。
 *   - 修改"已有表的结构"(加列等) → 新建增量文件，并同步更新 schema.sql，
 *     这样全新数据库与旧数据库最终结构一致（增量文件会因基线已含该列而自动跳过）。
 *
 * 执行核心在 database/migrator.php（DbMigrator）：Web 端首次访问时的自动建库
 * 走同一份代码，保证"手动跑脚本"和"网页自动初始化"永远一个口径。
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

require __DIR__ . '/migrator.php';

// ---------- 参数解析 ----------
$opts = getopt('', ['db::', 'status', 'help', 'no-demo']);
$statusOnly = isset($opts['status']);
$dbPath = $opts['db'] ?? null;
if ($dbPath !== null) {
    $dbPath = str_replace('\\', '/', $dbPath);
}

if (isset($opts['help'])) {
    echo <<<TXT
Triphase CRM database migrate tool

Usage:
  php database/migrate.php                 create or upgrade the database
  php database/migrate.php --status        show applied migrations and tables
  php database/migrate.php --db=PATH       use a specific sqlite file
  php database/migrate.php --no-demo       skip demo sample data (products/customers/leads/deals/orders/follow-ups)
TXT;
    exit(0);
}

$baseDir = dirname(__DIR__);          // project root
$schemaFile = DbMigrator::schemaFile();
$migDir = DbMigrator::migrationsDir();

// ---------- 定位数据库文件 ----------
if ($dbPath === null) {
    // 读 .env 里的 DB_PATH（与 app/config/config.php 逻辑一致，但本脚本不依赖 bootstrap）
    $dbPath = getenv('DB_PATH') ?: '';
    $envFile = $baseDir . '/.env';
    if ($dbPath === '' && is_readable($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'DB_PATH=')) {
                $dbPath = trim(substr($line, 8), " \t\"'");
                break;
            }
        }
    }
    if ($dbPath === '') {
        $dbPath = 'database/crm.sqlite';
    }
    // 相对路径基于项目根目录解析
    if ($dbPath[0] !== '/' && strpos($dbPath, ':') === false) {
        $dbPath = $baseDir . '/' . ltrim($dbPath, '/');
    }
}
$dbPath = str_replace('\\', '/', $dbPath);

if (!file_exists($schemaFile)) {
    fwrite(STDERR, "Error: schema.sql not found at {$schemaFile}\n");
    exit(1);
}

// 确保数据库文件所在目录存在
$dir = dirname($dbPath);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

echo 'Database : ' . $dbPath . PHP_EOL;

// ---------- 连接 ----------
try {
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, 'Cannot open database: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

// ---------- --status 模式 ----------
if ($statusOnly) {
    DbMigrator::ensureMigrationsTable($pdo);
    echo PHP_EOL . '-- Status --' . PHP_EOL;
    $applied = DbMigrator::appliedNames($pdo);
    $files = glob($migDir . '/*.sql') ?: [];
    sort($files);
    echo 'Schema baseline (schema.sql): always re-applied (idempotent)' . PHP_EOL;
    if ($applied) {
        echo 'Applied incremental migrations:' . PHP_EOL;
        foreach ($applied as $name) {
            echo '  [x] ' . $name . PHP_EOL;
        }
    } else {
        echo 'Applied incremental migrations: (none yet)' . PHP_EOL;
    }
    if ($files) {
        echo 'Pending files in migrations/:' . PHP_EOL;
        foreach ($files as $f) {
            $base = basename($f);
            echo '  ' . (in_array($base, $applied, true) ? '[applied] ' : '[pending] ') . $base . PHP_EOL;
        }
    }
    echo PHP_EOL . 'Tables in database:' . PHP_EOL;
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name <> '_migrations' ORDER BY name")->fetchAll();
    foreach ($tables as $t) {
        echo '  - ' . $t['name'] . PHP_EOL;
    }
    exit(0);
}

// ---------- 演示数据开关 ----------
// 默认带一套演示业务数据（本地跑起来直接有货可看）；生产新库建议用
// `--no-demo` 或环境变量 CRM_DEMO_DATA=0 跳过（管理员账号与系统设置始终会建）。
$demoData = true;
if (array_key_exists('no-demo', $opts)) {
    $demoData = false;
} else {
    $envDemo = getenv('CRM_DEMO_DATA');
    if ($envDemo === false || trim((string) $envDemo) === '') {
        $envFile = $baseDir . '/.env';
        if (is_readable($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if (str_starts_with($line, 'CRM_DEMO_DATA=')) {
                    $envDemo = trim(substr($line, strlen('CRM_DEMO_DATA=')), " \t\"'");
                    break;
                }
            }
        }
    }
    $envDemo = trim((string) $envDemo);
    if ($envDemo !== '') {
        $demoData = in_array(strtolower($envDemo), ['1', 'true', 'yes', 'on'], true);
    }
}

// ---------- 迁移（核心在 DbMigrator::apply） ----------
echo PHP_EOL;
DbMigrator::apply($pdo, $schemaFile, $migDir, $demoData,
    static function (string $line): void { echo $line . PHP_EOL; });

// ---------- 自检：期望表是否齐全 ----------
echo '== Verify ==' . PHP_EOL;
$missing = DbMigrator::missingExpectedTables($pdo);
if ($missing) {
    fwrite(STDERR, '  Missing tables: ' . implode(', ', $missing) . PHP_EOL);
    echo PHP_EOL . 'Done (with warnings).' . PHP_EOL;
    exit(1);
}
echo '  All ' . count(DbMigrator::EXPECTED_TABLES) . ' expected tables present. OK' . PHP_EOL;
echo PHP_EOL . 'Migration complete.' . PHP_EOL;
