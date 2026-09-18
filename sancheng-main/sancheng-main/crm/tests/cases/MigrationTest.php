<?php
/**
 * Migration tooling tests (database/migrate.php).
 *
 * These guard the invariant that made the whole suite unrunnable once before:
 * schema.sql is the authoritative, self-healing baseline AND ALSO carries columns
 * that older one-off migrations add with ALTER TABLE ... ADD COLUMN. A fresh
 * database must therefore skip those redundant increments instead of dying with
 * "duplicate column name", while a legacy database (columns missing) must still
 * have them applied for real.
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
require __DIR__ . '/../bootstrap.php';

const MIGRATE_PHP = BASE_PATH . '/database/migrate.php';

/** Column names of a table in the given sqlite file. */
function dbColumns(string $dbFile, string $table): array
{
    $pdo = new PDO('sqlite:' . $dbFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->prepare('SELECT name FROM pragma_table_info(:t)');
    $stmt->execute([':t' => $table]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/** Run migrate.php against $dbFile; returns [exitCode, output]. */
function runMigrate(string $dbFile, string $extra = ''): array
{
    $out = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(MIGRATE_PHP)
        . ' --db=' . escapeshellarg($dbFile) . ($extra !== '' ? ' ' . $extra : '') . ' 2>&1', $out, $code);
    return [$code, implode("\n", $out)];
}

function tempDb(string $tag): string
{
    return sys_get_temp_dir() . '/crm_mig_' . $tag . '_' . getmypid() . '_'
         . bin2hex(random_bytes(3)) . '.sqlite';
}

function test_bootstrapped_db_matches_baseline_columns(): void
{
    // The DB this very process runs on was built by migrate.php — so if the tool
    // is healthy, the baseline-only columns must already be present.
    $cols = dbColumns($GLOBALS['TEST_DB_PATH'], 'customers');
    assertTrue(in_array('wechat', $cols, true), 'customers.wechat present after migrate');
    assertTrue(in_array('shipping_address', $cols, true), 'customers.shipping_address present after migrate');

    $pdo = new PDO('sqlite:' . $GLOBALS['TEST_DB_PATH']);
    $recorded = $pdo->query('SELECT name FROM _migrations ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
    foreach (glob(BASE_PATH . '/database/migrations/*.sql') ?: [] as $file) {
        assertTrue(in_array(basename($file), $recorded, true),
            basename($file) . ' recorded in _migrations');
    }
}

function test_fresh_database_builds_without_duplicate_column_error(): void
{
    $db = tempDb('fresh');
    try {
        [$code, $out] = runMigrate($db);
        assertEquals(0, $code, "migrate.php on an empty database must succeed:\n{$out}");
        assertContains('schema.sql (baseline)', $out, 'baseline applied');
        // Increments whose columns the baseline already provides are skipped, not run.
        assertContains('skipped: 002_add_wechat_to_customers.sql', $out, 'redundant 002 skipped');
        assertContains('skipped: 004_add_shipping_address_to_customers.sql', $out, 'redundant 004 skipped');
        assertTrue(!str_contains($out, 'duplicate column name'), 'no duplicate column error');
        assertTrue(in_array('wechat', dbColumns($db, 'customers'), true), 'wechat present on fresh db');
    } finally {
        @unlink($db);
    }
}

function test_second_run_is_a_no_op(): void
{
    $db = tempDb('again');
    try {
        [$first, ] = runMigrate($db);
        assertEquals(0, $first, 'first run ok');
        [$second, $out] = runMigrate($db);
        assertEquals(0, $second, "second run ok:\n{$out}");
        assertContains('nothing pending', $out, 'second run has nothing left to apply');
    } finally {
        @unlink($db);
    }
}

/** Remove the given columns from ONE table's CREATE statement in a schema string. */
function stripColumnsFromTable(string $sql, string $table, array $columns): string
{
    $pattern = '/CREATE TABLE IF NOT EXISTS ' . preg_quote($table, '/')
             . ' \((.*?)\n\);/s';
    $quoted = implode('|', array_map(fn($c) => preg_quote($c, '/'), $columns));
    return preg_replace_callback($pattern, static function (array $m) use ($table, $quoted): string {
        $lines = [];
        foreach (preg_split("/\r?\n/", $m[1]) as $line) {
            $trim = trim($line);
            if ($trim === '' || preg_match('/^\s*(' . $quoted . ')\s+[A-Za-z][^,]*,?\s*$/i', $trim)) {
                continue;   // blank, or one of the columns under test
            }
            $lines[] = $trim;
        }
        // The block may now end on a comment or on a line with a dangling comma.
        while ($lines && str_starts_with(end($lines), '--')) {
            array_pop($lines);
        }
        if ($lines) {
            $lines[count($lines) - 1] = rtrim($lines[count($lines) - 1], ' ,');
        }
        return "CREATE TABLE IF NOT EXISTS {$table} (\n    " . implode("\n    ", $lines) . "\n);";
    }, $sql, 1) ?? $sql;
}

function test_legacy_database_still_gets_real_columns(): void
{
    // Simulate a pre-v1.2.0 / pre-v1.3.0 database: the current baseline minus the
    // columns the one-off increments add. Because schema.sql uses
    // CREATE TABLE IF NOT EXISTS, re-applying it will NOT add the missing columns
    // — the increments must.
    $legacy = tempDb('legacy');
    try {
        $sql = file_get_contents(BASE_PATH . '/database/schema.sql');
        $sql = stripColumnsFromTable($sql, 'customers', ['wechat', 'shipping_address']);
        $sql = stripColumnsFromTable($sql, 'users',
            ['phone', 'whatsapp', 'job_title', 'notes', 'updated_at']);

        $pdo = new PDO('sqlite:' . $legacy, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec($sql);
        unset($pdo);

        assertTrue(!in_array('wechat', dbColumns($legacy, 'customers'), true),
            'fixture really lacks customers.wechat');
        assertTrue(!in_array('job_title', dbColumns($legacy, 'users'), true),
            'fixture really lacks users.job_title');
        assertTrue(in_array('phone', dbColumns($legacy, 'customers'), true),
            'other tables are untouched (customers.phone still there)');

        [$code, $out] = runMigrate($legacy);
        assertEquals(0, $code, "upgrade of a legacy database must succeed:\n{$out}");
        assertContains('applied: 002_add_wechat_to_customers.sql', $out, '002 actually applied on legacy db');
        assertContains('applied: 005_add_profile_fields_to_users.sql', $out, '005 actually applied on legacy db');

        $cols = dbColumns($legacy, 'customers');
        assertTrue(in_array('wechat', $cols, true), 'customers.wechat added by the increment');
        assertTrue(in_array('shipping_address', $cols, true), 'customers.shipping_address added by the increment');

        $userCols = dbColumns($legacy, 'users');
        foreach (['phone', 'whatsapp', 'job_title', 'notes', 'updated_at'] as $column) {
            assertTrue(in_array($column, $userCols, true), "users.{$column} added by the increment");
        }
        // app_settings is a brand-new table, so it comes from the baseline — an
        // upgraded database must have it too, not only a freshly built one.
        $pdo = new PDO('sqlite:' . $legacy);
        assertTrue(in_array('app_settings',
            $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN), true),
            'app_settings created by the baseline during the upgrade');
        $rows = (int) $pdo->query('SELECT COUNT(*) FROM app_settings')->fetchColumn();
        assertTrue($rows >= 4, 'setting defaults are seeded on upgrade (got ' . $rows . ')');
        unset($pdo);
    } finally {
        @unlink($legacy);
    }
}

function test_skipped_increments_are_recorded_once(): void
{
    // A skipped increment is still registered in _migrations, so the next run
    // reports "nothing pending" instead of re-deciding every time.
    $db = tempDb('log');
    try {
        [$code, ] = runMigrate($db);
        assertEquals(0, $code, 'first run ok');
        $pdo = new PDO('sqlite:' . $db);
        $recorded = $pdo->query('SELECT name FROM _migrations ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
        assertTrue(in_array('002_add_wechat_to_customers.sql', $recorded, true),
            'skipped 002 is still marked applied');
        $count = (int) $pdo->query('SELECT COUNT(*) FROM _migrations')->fetchColumn();
        unset($pdo);

        [$second, $out] = runMigrate($db);
        assertEquals(0, $second, 'second run ok');
        $pdo = new PDO('sqlite:' . $db);
        assertEquals($count, (int) $pdo->query('SELECT COUNT(*) FROM _migrations')->fetchColumn(),
            'no duplicate _migrations rows on re-run');
        unset($pdo);
    } finally {
        @unlink($db);
    }
}

/**
 * --no-demo（或 CRM_DEMO_DATA=0）跳过演示业务数据，但管理员与系统默认设置始终要建。
 */
function test_no_demo_flag_skips_sample_rows_but_keeps_admin_and_settings(): void
{
    $db = tempDb('nodemo');
    try {
        [$code, $out] = runMigrate($db, '--no-demo');
        assertEquals(0, $code, 'migrate --no-demo exits 0: ' . $out);

        $pdo = new PDO('sqlite:' . $db, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        assertEquals(1, (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'admin@example.com'")->fetchColumn(),
            '管理员账号始终建');
        assertTrue((int) $pdo->query('SELECT COUNT(*) FROM app_settings')->fetchColumn() >= 2, '系统默认设置始终建');
        foreach (['customers', 'products', 'leads', 'deals', 'orders', 'order_items', 'follow_ups'] as $table) {
            assertEquals(0, (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn(),
                "演示数据不落地：{$table} 为空");
        }
        assertContains('demo sample data skipped', $out, '输出里说明跳过了演示数据');

        // 默认（不带开关）仍带样例：证明开关只影响演示段，不影响普通建库
        $db2 = tempDb('withdemo');
        try {
            [$code2, $out2] = runMigrate($db2);
            assertEquals(0, $code2, 'plain migrate exits 0: ' . $out2);
            $pdo2 = new PDO('sqlite:' . $db2, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            assertTrue((int) $pdo2->query('SELECT COUNT(*) FROM customers')->fetchColumn() > 0,
                '默认建库仍含样例客户');
        } finally {
            @unlink($db2);
        }
    } finally {
        @unlink($db);
    }
}

/** 某张表的附属对象（索引 + 触发器）——重建表最容易把它们弄丢 */
function dbAttachedObjects(string $dbFile, string $table): array
{
    $pdo = new PDO('sqlite:' . $dbFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->prepare("SELECT type || ':' || name FROM sqlite_master WHERE tbl_name = :t AND type IN ('index','trigger') ORDER BY 1");
    $stmt->execute([':t' => $table]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * 把基线里 customers.status 的 CHECK 退回旧口径（active|inactive），用来造一个"旧库"。
 * 基线的这段文本一旦改写，这个函数会直接断言失败——别让它静静地造不出旧库。
 */
function downgradeCustomerStatusCheck(string $sql): string
{
    $pattern = '/CREATE TABLE IF NOT EXISTS customers \((.*?)\n\);/s';
    $done = false;
    $out = preg_replace_callback($pattern, static function (array $m) use (&$done): string {
        $body = str_replace(
            "CHECK (status IN ('active','one_time','inactive','dormant','lost'))",
            "CHECK (status IN ('active','inactive'))",
            $m[1],
            $count
        );
        if ($count === 1) {
            $done = true;
        }
        return 'CREATE TABLE IF NOT EXISTS customers (' . $body . "\n);";
    }, $sql, 1);
    assertTrue($done, 'fixture 重写了 customers 的 CHECK（基线文本变了要同步这里）');
    return (string) $out;
}

/**
 * 客户状态 2 → 5（migrations/021）。SQLite 改不了 CHECK，只能建新表→拷数据→换名，
 * 这是全库最危险的一种迁移（丢数据、断外键、掉索引/触发器都在这一步发生），
 * 所以造一个“CHECK 还是旧口径、且有数据与关联行”的旧库，真跑一遍 migrate.php。
 */
function test_customer_status_enum_upgrade_rebuilds_the_table_safely(): void
{
    $legacy = tempDb('status');
    try {
        $sql = downgradeCustomerStatusCheck((string) file_get_contents(BASE_PATH . '/database/schema.sql'));
        assertTrue(!str_contains($sql, "'dormant'"), 'fixture 里真的没有新状态');

        $pdo = new PDO('sqlite:' . $legacy, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec($sql);
        $pdo->exec("INSERT INTO customers (id, public_code, name, status, owner_id, notes) VALUES (901,'CUS-000901','旧客户A','active',1,'n')");
        $pdo->exec("INSERT INTO customers (id, public_code, name, status, owner_id) VALUES (902,'CUS-000902','旧客户B','inactive',1)");
        $pdo->exec("INSERT INTO deals (id, title, customer_id, stage, owner_id) VALUES (901,'d',901,'open',1)");
        $pdo->exec("INSERT INTO follow_ups (id, customer_id, user_id, type, title) VALUES (901,901,1,'other','f')");
        unset($pdo);

        [$code, $out] = runMigrate($legacy);
        assertEquals(0, $code, "旧库升级必须成功:\n{$out}");
        assertContains('applied: 021_customers_status_enum.sql', $out, '021 真的执行了（不是被跳过）');

        $pdo = new PDO('sqlite:' . $legacy, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $check = (string) $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='customers'")->fetchColumn();
        assertTrue(str_contains($check, "'dormant'"), '新 CHECK 已落到库里');

        assertEquals(2, (int) $pdo->query('SELECT COUNT(*) FROM customers WHERE id IN (901,902)')->fetchColumn(),
            '客户行一条不少');
        assertEquals('inactive', (string) $pdo->query('SELECT status FROM customers WHERE id = 902')->fetchColumn(),
            '旧值 inactive 原样搬过来');
        assertEquals(1, (int) $pdo->query('SELECT COUNT(*) FROM deals WHERE customer_id = 901')->fetchColumn(),
            '关联商机还在');
        assertEquals(1, (int) $pdo->query('SELECT COUNT(*) FROM follow_ups WHERE customer_id = 901')->fetchColumn(),
            '关联跟进还在');

        // 新的可选值要能写，旧 CHECK 之外的值仍要被拒——证明换掉的是真的约束
        $pdo->exec("INSERT INTO customers (name, status) VALUES ('新状态客户1','one_time')");
        $pdo->exec("INSERT INTO customers (name, status) VALUES ('新状态客户2','dormant')");
        $pdo->exec("INSERT INTO customers (name, status) VALUES ('新状态客户3','lost')");
        $rejected = false;
        try {
            $pdo->exec("INSERT INTO customers (name, status) VALUES ('非法客户','archived')");
        } catch (Throwable $e) {
            $rejected = true;
        }
        assertTrue($rejected, '非法状态仍被 CHECK 拒绝');

        // 索引与触发器是表的附属物，会随 DROP TABLE 消失，迁移必须把它们建回来
        assertEquals(1, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND name='idx_customers_status'")->fetchColumn(),
            'idx_customers_status 已重建');
        assertEquals(1, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='trigger' AND name='trg_customers_updated'")->fetchColumn(),
            'trg_customers_updated 已重建');

        // 重建表最容易弄坏的两件事：外键完整性与级联删除
        $pdo->exec('PRAGMA foreign_keys = ON');
        assertEquals([], $pdo->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC), '没有悬空外键');
        $pdo->exec('DELETE FROM customers WHERE id = 901');
        assertEquals(0, (int) $pdo->query('SELECT COUNT(*) FROM deals WHERE customer_id = 901')->fetchColumn(),
            '删客户仍级联删商机');
        unset($pdo);

        // “老库/新库结构一致”是 migrate.php 的书面约定：升级后的列清单要与新建库一致
        $fresh = tempDb('statusfresh');
        try {
            [$freshCode, $freshOut] = runMigrate($fresh);
            assertEquals(0, $freshCode, "新建库也要成功:\n{$freshOut}");
            assertEquals(dbColumns($fresh, 'customers'), dbColumns($legacy, 'customers'),
                '升级后的列清单与新建库一致');
            // 索引/触发器是“重建表”最容易弄丢的东西（uidx_customers_public_code 就真丢过一次）
            assertEquals(dbAttachedObjects($fresh, 'customers'), dbAttachedObjects($legacy, 'customers'),
                '升级后 customers 的索引/触发器与新建库一致');
        } finally {
            @unlink($fresh);
        }
    } finally {
        @unlink($legacy);
    }
}

runCase();
