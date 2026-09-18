<?php
/**
 * Web 端自动初始化测试（Database::ensureSchema，由 app/bootstrap.php 触发）。
 *
 * 覆盖的部署场景：新机器上放好代码、建好 .env，还没来得及（或不想）手动跑
 * php database/migrate.php 就直接访问 —— 第一个请求应该把库建出来，而不是
 * "no such table" 白屏。迁移核心与 CLI 共用（database/migrator.php 的
 * DbMigrator），本用例在子进程里真实加载 app/bootstrap.php，然后在父进程
 * 校验建出来的库（子进程 dispatch 到登录页后会 exit，这是正常的首访行为）。
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
require __DIR__ . '/../bootstrap.php';

function tempAutoDb(string $tag): string
{
    return sys_get_temp_dir() . '/crm_auto_' . $tag . '_' . getmypid() . '_'
         . bin2hex(random_bytes(3)) . '.sqlite';
}

/**
 * 在独立 PHP 进程里加载 app/bootstrap.php（DB_PATH 指向 $db），返回 [exitCode, stdout]。
 * bootstrap 一加载就会执行 Database::ensureSchema()。用临时脚本文件而不是 php -r：
 * Windows 下 -r 的引号转义不可靠。
 */
function runBootstrapScript(string $db, bool $demoOff = false): array
{
    $script = tempnam(sys_get_temp_dir(), 'crm_auto_boot_') . '.php';
    file_put_contents($script, '<?php
        putenv("DB_PATH=' . addslashes($db) . '");
        ' . ($demoOff ? 'putenv("CRM_DEMO_DATA=0");' : '') . '
        require ' . var_export(BASE_PATH . '/app/bootstrap.php', true) . ';
    ');
    $out = [];
    $exit = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1', $out, $exit);
    @unlink($script);
    return [$exit, implode("\n", $out)];
}

/** 父进程侧的"结构就绪"判定：期望表齐全 + 每个增量文件都登记进台账。 */
function autoDbIsReady(string $db): bool
{
    $pdo = new PDO('sqlite:' . $db, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $tables = array_column($pdo->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(), 'name');
    foreach (['users', 'customers', 'leads', 'deals', 'orders', 'app_settings', '_migrations'] as $need) {
        if (!in_array($need, $tables, true)) {
            return false;
        }
    }
    $recorded = $pdo->query('SELECT name FROM _migrations')->fetchAll(PDO::FETCH_COLUMN);
    foreach (glob(BASE_PATH . '/database/migrations/*.sql') ?: [] as $file) {
        if (!in_array(basename($file), $recorded, true)) {
            return false;
        }
    }
    return true;
}

function autoDbPdo(string $db): PDO
{
    return new PDO('sqlite:' . $db, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function autoDbCleanup(string $db): void
{
    foreach ([$db, $db . '-wal', $db . '-shm'] as $f) {
        @unlink($f);
    }
}

function test_first_request_builds_the_database_without_the_cli(): void
{
    $db = tempAutoDb('fresh');
    try {
        assertTrue(!file_exists($db), '前置：数据库文件还不存在');
        [$exit, $out] = runBootstrapScript($db);
        assertEquals(0, $exit, "首个请求（bootstrap 加载）不应报错：\n{$out}");
        assertTrue(file_exists($db), '数据库文件被自动建出');
        assertTrue(autoDbIsReady($db), '初始化完成后结构判定为就绪（期望表 + 增量台账齐全）');

        // 管理员账号与系统默认设置始终会建（种子数据在 schema.sql 里）
        $pdo = autoDbPdo($db);
        assertTrue((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0, '管理员账号存在');
        foreach (glob(BASE_PATH . '/database/migrations/*.sql') ?: [] as $file) {
            $recorded = $pdo->query('SELECT name FROM _migrations')->fetchAll(PDO::FETCH_COLUMN);
            assertTrue(in_array(basename($file), $recorded, true), basename($file) . ' 登记进 _migrations');
        }
    } finally {
        autoDbCleanup($db);
    }
}

function test_second_request_is_a_fast_no_op(): void
{
    $db = tempAutoDb('second');
    try {
        [$first, $out] = runBootstrapScript($db);
        assertEquals(0, $first, "首次初始化成功：\n{$out}");
        assertTrue(autoDbIsReady($db), '首次访问后结构就绪');
        [$second, $out] = runBootstrapScript($db);
        assertEquals(0, $second, "第二次请求不报错：\n{$out}");
        assertTrue(autoDbIsReady($db), '已就绪时直接放行，结构保持完好');
    } finally {
        autoDbCleanup($db);
    }
}

function test_auto_init_honors_crm_demo_data_off(): void
{
    $db = tempAutoDb('nodemo');
    try {
        // 环境变量在临时脚本里 putenv（CRM_DEMO_DATA=0），口径与 migrate.php 一致
        [$exit, $out] = runBootstrapScript($db, true);
        assertEquals(0, $exit, "CRM_DEMO_DATA=0 下初始化成功：\n{$out}");
        $pdo = autoDbPdo($db);
        assertEquals(0, (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
            '演示商品数据没有灌入');
        assertTrue((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0,
            '管理员账号仍然存在');
    } finally {
        autoDbCleanup($db);
    }
}

runCase();
