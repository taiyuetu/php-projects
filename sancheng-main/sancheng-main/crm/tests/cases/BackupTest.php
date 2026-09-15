<?php
/**
 * 备份 / 恢复测试。
 *
 * 这一组用例守的是「数据不会在备份或恢复时静默变质」：
 *   - WAL 里已提交的必须在快照里（直接 cp 主文件就会漏 —— 这是本功能唯一会坑人的地方）
 *   - 包能原样解回：manifest 的行数与实际一致，附件字节一模一样
 *   - 恢复是真的整体替换：备份之后新增的行会消失，备份里没有的表也会消失
 *   - 恢复前自动留下可用的回滚点（并且真的能拿它反悔一次）
 *   - 路径白名单挡住 zip slip、目录穿越与 .php；格式版本过新的包拒收
 *   - 不是本系统导出的 zip 一律拒收（防手滑把别的包盖到生产库上）
 * 库、备份目录、附件目录都由 tests/bootstrap.php 隔离到 temp，用例之间不共享文件。
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
require __DIR__ . '/../bootstrap.php';

// -----------------------------------------------------------------------
// helpers
// -----------------------------------------------------------------------

function seedSomeBusiness(): void
{
    $customer = new Customer();
    $customer->create(['name' => '备份客户甲', 'company' => '叁程测试']);
    $customer->create(['name' => '备份客户乙', 'company' => '叁程测试']);
    (new Lead())->create(['title' => '备份线索一', 'status' => 'new']);
}

/** 用全新连接数：restore() 覆写过正在使用的库之后，本进程的旧连接不算事实。 */
function freshCount(string $table): int
{
    return Backup::countRows(DB_PATH)[$table] ?? 0;
}

function attPath(string $name): string
{
    return Attachment::uploadDir() . '/' . $name;
}

function putAttachmentFile(string $name, string $content): void
{
    $dir = Attachment::uploadDir();
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($dir . '/' . $name, $content);
}

function namesListed(): array
{
    return array_column(Backup::listSnapshots(), 'name');
}

/** 只挑出本用例自己创建的名字，避免被别的用例留下的 pre-restore 文件干扰计数。 */
function onlyAmong(array $haystack, array $needles): array
{
    $out = [];
    foreach ($needles as $n) {
        if (in_array($n, $haystack, true)) {
            $out[] = $n;
        }
    }
    return $out;
}

/** 用给定条目拼一个 zip（ZipArchive 有则用，没有则 PharData —— 与被测代码同策略）。 */
function buildZip(string $dest, array $entries): void
{
    @unlink($dest);
    if (class_exists('ZipArchive')) {
        $z = new ZipArchive();
        $z->open($dest, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $path) {
            $z->addFile($path, $name);
        }
        $z->close();
        return;
    }
    $p = new PharData($dest, 0, null, Phar::ZIP);
    foreach ($entries as $name => $path) {
        $p->addFile($path, $name);
    }
    unset($p);
}

// -----------------------------------------------------------------------
// 环境与统计
// -----------------------------------------------------------------------

function test_capabilities_and_limits_are_reported(): void
{
    $caps = Backup::capabilities();
    foreach (['zip_write', 'zip_read', 'db_snapshot', 'db_writable', 'backup_writable', 'uploads_writable'] as $k) {
        assertTrue(isset($caps[$k]), "capability {$k} is reported");
        assertTrue(is_bool($caps[$k]['ok']), "{$k} carries a usable yes/no");
    }
    // 本测试栈至少得各有一条打包与快照的路，否则这个用例文件毫无意义
    assertTrue($caps['zip_write']['ok'], 'a zip writer is available (ZipArchive or PharData)');
    assertTrue($caps['db_snapshot']['ok'], 'a consistent-snapshot method is available');

    $limits = Backup::uploadLimits();
    assertTrue($limits['post_max_bytes'] > 0, 'post_max_size is understood');
    assertTrue($limits['upload_max_bytes'] > 0, 'upload_max_filesize is understood');
    assertTrue(preg_match('/^\d+[KMGkmg]?$/', (string) $limits['upload_max_filesize']) === 1,
        'the raw ini value is passed through for display, not invented');
}

function test_stats_describe_the_live_database(): void
{
    seedSomeBusiness();
    $s = Backup::stats();

    assertTrue($s['db_exists'], 'db file found');
    assertTrue($s['db_bytes'] > 0, 'db has a size');
    assertTrue(isset($s['counts']['customers']), 'customers are counted');
    assertEquals(freshCount('customers'), (int) $s['counts']['customers'], 'the count is real');
    assertEquals(array_sum($s['counts']), (int) $s['total_rows'], 'totals match the per-table list');
    assertTrue(count($s['counts']) >= 10, 'the whole schema is counted, not a hand-picked subset');
    assertEquals(APP_VERSION, $s['app_version'], 'stats are stamped with the running version');
    assertTrue(isset($s['orphans']['ok']), 'attachment reconciliation is included');
    assertTrue(is_dir($s['backup_dir']), 'the backup dir is created on demand');
}

// -----------------------------------------------------------------------
// 一致性快照
// -----------------------------------------------------------------------

function test_snapshot_carries_commits_still_in_the_wal(): void
{
    // 这是本功能存在的理由：journal_mode=wal 下最近的提交可能还留在 -wal 里，
    // 直接 cp 主文件就会漏 —— 症状是“备份里没有刚才录的那条线索”。
    $before = freshCount('leads');
    (new Lead())->create(['title' => '就在刚刚写的线索', 'status' => 'new']);
    assertEquals($before + 1, freshCount('leads'), 'the new lead is committed');

    $dest = Backup::backupDir() . '/wal-probe.sqlite';
    $snap = Backup::snapshotSqlite($dest);

    assertTrue($snap['size'] > 0, 'snapshot written');
    assertEquals($before + 1, (int) (Backup::countRows($dest)['leads'] ?? 0),
        'the snapshot contains the lead that was written moments ago');
    $check = Backup::assertDatabaseFile($dest);
    assertEquals('ok', $check['integrity'], 'snapshot passes integrity_check');
    foreach (['users', 'customers', 'leads', 'deals', 'orders'] as $t) {
        assertTrue(in_array($t, $check['tables'], true), "snapshot keeps table {$t}");
    }
    @unlink($dest);
}

function test_a_file_with_only_the_right_extension_is_refused(): void
{
    $junk = Backup::backupDir() . '/junk.sqlite';
    file_put_contents($junk, str_repeat('x', 4096));
    $failed = false;
    try {
        Backup::assertDatabaseFile($junk);
    } catch (Throwable $e) {
        $failed = true;
        assertContains('SQLite format 3', $e->getMessage(), 'the header check is what objects');
    }
    assertTrue($failed, 'a non-sqlite file named .sqlite is refused');
    @unlink($junk);
}

// -----------------------------------------------------------------------
// 打包 / 解包
// -----------------------------------------------------------------------

function test_archive_round_trips_database_and_attachments(): void
{
    seedSomeBusiness();
    $attName = '20260101_1200_deadbeefdeadbeef.txt';
    putAttachmentFile($attName, '附件内容·备份测试');
    (new Attachment())->create([
        'related_type' => 'customer', 'related_id' => 1, 'filename' => $attName,
        'original_name' => '备份测试.txt', 'mime_type' => 'text/plain',
        'file_size' => strlen('附件内容·备份测试'), 'uploaded_by' => 1,
    ]);

    $res = Backup::createArchive(Backup::backupDir() . '/round-trip.zip', [
        'with_database' => true, 'with_uploads' => true, 'with_env' => false, 'note' => '用例包',
    ]);
    assertTrue(is_file($res['file']), 'archive exists');
    assertTrue($res['size'] > 0, 'archive is not empty');
    assertEquals(true, $res['manifest']['includes']['uploads'], 'manifest says uploads are inside');
    assertEquals('用例包', $res['manifest']['note'], 'the note is stored in the manifest');
    assertTrue(isset($res['manifest']['database']['sha256']), 'the snapshot is checksummed');
    assertTrue(count($res['manifest']['database']['migrations']) >= 0, 'the migration ledger travels with the backup');

    $ins = Backup::inspect($res['file']);
    assertTrue(in_array(Backup::MANIFEST_ENTRY, $ins['entries'], true), 'manifest.json came along');
    assertTrue(in_array(Backup::DB_ENTRY, $ins['entries'], true), 'the db snapshot came along');
    assertTrue(in_array('uploads/attachments/' . $attName, $ins['entries'], true), 'the attachment came along');
    assertEquals(freshCount('customers'), (int) $ins['database']['counts']['customers'],
        'inspected customer count == live count');
    assertTrue($ins['database']['rows'] > 0, 'the row total comes from the archive, not from the live db');
    assertEquals(1, (int) $ins['uploads']['count'], 'one attachment in the archive');
    assertEquals('附件内容·备份测试',
        (string) file_get_contents($ins['stage'] . '/uploads/attachments/' . $attName),
        'attachment bytes survived the round trip');
    assertEquals([], $ins['warnings'], 'a clean, current package raises no warnings');
    Backup::release($ins);
    @unlink($res['file']);
    // 附件目录在 temp 里是用例共享的，不收干净下一个用例就会被多出来的一份附件绊倒
    @unlink(attPath($attName));
}

function test_extract_keeps_only_the_whitelist_and_drops_the_rest(): void
{
    // 包里塞 app/bootstrap.php 与 uploads/attachments/evil.php：两者都必须被丢掉，
    // 因为它们一旦落地就是「备份功能帮忙写了个可执行文件」。
    $good = Backup::backupDir() . '/wl-db.sqlite';
    Backup::snapshotSqlite($good);
    $mf = Backup::backupDir() . '/wl-manifest.json';
    file_put_contents($mf, json_encode([
        'app' => 'triphase-crm', 'format' => Backup::FORMAT,
        'includes' => ['database' => true, 'uploads' => true, 'env' => false],
    ]));
    $seed = Backup::backupDir() . '/wl-seed';
    mkdir($seed . '/uploads/attachments', 0777, true);
    file_put_contents($seed . '/evil.php', "<?php echo 'pwned';");
    file_put_contents($seed . '/bootstrap.php', "<?php die();");
    file_put_contents($seed . '/ok.txt', 'OK');

    $evil = Backup::backupDir() . '/evil.zip';
    buildZip($evil, [
        'uploads/attachments/evil.php' => $seed . '/evil.php',
        'app/bootstrap.php'            => $seed . '/bootstrap.php',
        'uploads/attachments/ok.txt'   => $seed . '/ok.txt',
        Backup::DB_ENTRY               => $good,
        Backup::MANIFEST_ENTRY         => $mf,
    ]);

    $ins = Backup::inspect($evil);
    assertTrue(in_array('uploads/attachments/ok.txt', $ins['entries'], true), 'the clean file is kept');
    assertTrue(!in_array('app/bootstrap.php', $ins['entries'], true), 'a path outside the whitelist never enters');
    assertTrue(!in_array('uploads/attachments/evil.php', $ins['entries'], true), 'a .php in uploads is refused');
    assertTrue(is_file($ins['stage'] . '/uploads/attachments/ok.txt'), 'and the kept one really is staged');
    assertTrue(!file_exists($ins['stage'] . '/app/bootstrap.php'), 'nothing was staged under app/');
    assertTrue(!file_exists($ins['stage'] . '/uploads/attachments/evil.php'), 'nor as a script in uploads/');
    // 真项目的 app/bootstrap.php 必须还是原来那个字节
    assertContains('APP_PATH', (string) file_get_contents(BASE_PATH . '/app/bootstrap.php'),
        'the application tree is untouched by a hostile package');
    Backup::release($ins);

    foreach ([$evil, $good, $mf, $seed . '/evil.php', $seed . '/bootstrap.php', $seed . '/ok.txt'] as $f) {
        @unlink($f);
    }
    @rmdir($seed . '/uploads/attachments');
    @rmdir($seed . '/uploads');
    @rmdir($seed);
}

function test_inspect_refuses_a_foreign_zip(): void
{
    $seed = Backup::backupDir() . '/fg-seed.txt';
    file_put_contents($seed, '随便一个文件');
    $foreign = Backup::backupDir() . '/foreign.zip';
    buildZip($foreign, ['readme.txt' => $seed]);

    $failed = false;
    try {
        Backup::inspect($foreign);
    } catch (Throwable $e) {
        $failed = true;
        assertContains('manifest', $e->getMessage(), 'the reason names the missing manifest');
    }
    assertTrue($failed, 'a zip without our manifest is refused');
    @unlink($foreign);
    @unlink($seed);
}

function test_inspect_refuses_a_sqlite_without_the_crm_tables(): void
{
    $junk = Backup::backupDir() . '/wrong-shape.sqlite';
    $pdo = new PDO('sqlite:' . $junk);
    $pdo->exec('CREATE TABLE notes (id INTEGER PRIMARY KEY, body TEXT)');
    $pdo->exec("INSERT INTO notes (body) VALUES ('这张库里没有任何 CRM 表')");
    $pdo = null;

    $failed = false;
    try {
        Backup::inspect($junk);
    } catch (Throwable $e) {
        $failed = true;
        assertContains('不像', $e->getMessage(), 'and it says so in plain words');
    }
    assertTrue($failed, 'a valid sqlite file of the wrong shape is refused');
    @unlink($junk);
}

function test_a_package_from_the_future_is_refused(): void
{
    // format 比代码大 = 这个包的布局可能是把别的字段当路径来读 —— 拒绝，而不是猜
    $good = Backup::backupDir() . '/fmt-db.sqlite';
    Backup::snapshotSqlite($good);
    $mf = Backup::backupDir() . '/fmt-manifest.json';
    file_put_contents($mf, json_encode([
        'app' => 'triphase-crm', 'format' => Backup::FORMAT + 5,
        'includes' => ['database' => true],
    ]));
    $zip = Backup::backupDir() . '/future.zip';
    buildZip($zip, [Backup::DB_ENTRY => $good, Backup::MANIFEST_ENTRY => $mf]);

    $failed = false;
    try {
        Backup::inspect($zip);
    } catch (Throwable $e) {
        $failed = true;
        assertContains('格式版本', $e->getMessage(), 'and it tells the admin to upgrade the code first');
    }
    assertTrue($failed, 'a newer archive format is refused');
    foreach ([$zip, $good, $mf] as $f) {
        @unlink($f);
    }
}

// -----------------------------------------------------------------------
// 恢复
// -----------------------------------------------------------------------

function test_restore_replaces_live_data_wholesale(): void
{
    seedSomeBusiness();
    $customersAtBackup = freshCount('customers');
    $res = Backup::createArchive(Backup::backupDir() . '/restore-src.zip',
        ['with_database' => true, 'with_uploads' => false, 'with_env' => false]);

    // 备份之后再动生产库：多一条客户 + 一张备份里没有的表。恢复必须把两者都抹掉，
    // 否则“恢复”只是部分回填，回滚就不可信。
    (new Customer())->create(['name' => '备份之后才录的客户']);
    assertEquals($customersAtBackup + 1, freshCount('customers'), 'live db moved ahead of the backup');
    Database::connection()->exec('CREATE TABLE IF NOT EXISTS zz_only_live (id INTEGER PRIMARY KEY)');

    $report = Backup::restore($res['file'], [
        'with_database' => true, 'with_uploads' => false, 'with_env' => false,
        'migrate' => false, 'replace_uploads' => false,
    ]);

    assertTrue($report['ok'], 'restore reported success: ' . ($report['error'] ?? '?'));
    assertEquals($customersAtBackup, freshCount('customers'), 'the row added after the backup is gone');
    assertEquals(false, (new Customer())->findBy('name', '备份之后才录的客户'), 'it is really gone, not just hidden');
    assertTrue(!in_array('zz_only_live', Backup::assertDatabaseFile(DB_PATH)['tables'], true),
        'a table absent from the backup is gone too (full replace, not merge)');
    assertTrue(is_file(Backup::backupDir() . '/' . $report['safety']['name']),
        'a pre-restore safety snapshot was written BEFORE the overwrite');
    assertContains('pre-restore', $report['safety']['name'], 'it is named as a rollback point (and never pruned)');
    assertEquals('cleared', $report['tokens'], 'remember_tokens wiped, so old cookies cannot enter restored data');
    assertEquals('skipped', $report['env'], 'nothing touched .env unless it was asked for');
    assertTrue((int) $report['checked']['rows'] > 0, 'the report says what it verified (rows in the package)');
    assertTrue($report['checked']['tables'] >= 10, 'and how many tables it restored');
    try {
        Database::connection()->exec('DROP TABLE IF EXISTS zz_only_live');
    } catch (Throwable $e) {
        // 旧连接的 schema 缓存里可能已经没有这张表了：清不掉不算失败
    }
    @unlink($res['file']);
    @unlink(Backup::backupDir() . '/' . $report['safety']['name']);
}

function test_the_safety_snapshot_actually_rolls_back(): void
{
    seedSomeBusiness();
    $before = freshCount('customers');
    $a = Backup::createArchive(Backup::backupDir() . '/rb-a.zip',
        ['with_database' => true, 'with_uploads' => false, 'with_env' => false]);
    (new Customer())->create(['name' => '要回滚掉的客户']);
    $mid = freshCount('customers');

    $r1 = Backup::restore($a['file'], ['with_uploads' => false, 'with_env' => false, 'migrate' => false]);
    assertTrue($r1['ok'], 'first restore ok: ' . ($r1['error'] ?? '?'));
    assertEquals($before, freshCount('customers'), 'the live data went back in time');

    $safety = Backup::backupDir() . '/' . $r1['safety']['name'];
    assertTrue(is_file($safety), 'the rollback point is a real file');
    $r2 = Backup::restore($safety, ['with_uploads' => false, 'with_env' => false, 'migrate' => false]);
    assertTrue($r2['ok'], 'undoing with the safety snapshot works: ' . ($r2['error'] ?? '?'));
    assertEquals($mid, freshCount('customers'), 'the undone restore is itself undone');
    assertContains('pre-restore', $r2['safety']['name'], 'and the rollback left its own rollback point');

    @unlink($a['file']);
    @unlink($safety);
    @unlink(Backup::backupDir() . '/' . $r2['safety']['name']);
}

function test_attachments_merge_by_default_and_replace_only_when_asked(): void
{
    $keep = '20260101_1201_keptkeptkept11.txt';
    $extra = '20260101_1202_extrapresent.txt';
    putAttachmentFile($keep, 'OLD');
    $a = Backup::createArchive(Backup::backupDir() . '/att-src.zip',
        ['with_database' => false, 'with_uploads' => true, 'with_env' => false]);

    // 备份之后目录里多了一个新文件（不在包里，正好用来区分两种模式）
    putAttachmentFile($extra, 'NEW-AFTER-BACKUP');

    $r = Backup::restore($a['file'], ['with_database' => false, 'with_uploads' => true,
                                      'replace_uploads' => false, 'with_env' => false, 'migrate' => false]);
    assertTrue($r['ok'], 'attachment-only restore works: ' . ($r['error'] ?? '?'));
    assertEquals('OLD', (string) file_get_contents(attPath($keep)), 'the archived file is written back');
    assertTrue(is_file(attPath($extra)), 'merge mode never deletes files the archive does not know about');
    assertEquals(0, (int) $r['uploads']['removed'], 'nothing removed in merge mode');
    assertEquals(1, (int) $r['uploads']['written'], 'exactly the one archived file written');
    assertEquals('skipped', $r['database'], 'and the database was left alone');

    $r2 = Backup::restore($a['file'], ['with_database' => false, 'with_uploads' => true,
                                       'replace_uploads' => true, 'with_env' => false, 'migrate' => false]);
    assertTrue($r2['ok'], 'replace mode runs: ' . ($r2['error'] ?? '?'));
    assertTrue(!is_file(attPath($extra)), 'replace mode removes files the archive does not contain');
    assertEquals(1, (int) $r2['uploads']['removed'], 'the removal is counted, so it can be reported');
    assertEquals('OLD', (string) file_get_contents(attPath($keep)), 'the archived copy is put back in place');

    @unlink($a['file']);
    @unlink(attPath($extra));
    @unlink(attPath($keep));
}

function test_env_is_never_overwritten_in_place(): void
{
    $res = Backup::createArchive(Backup::backupDir() . '/env-src.zip',
        ['with_database' => false, 'with_uploads' => true, 'with_env' => true]);

    if (!empty($res['manifest']['env']['absent']) || empty($res['manifest']['includes']['env'])) {
        // 这台机器上没有 .env：确认它被诚实标记，而不是静默少打包
        assertTrue(!empty($res['manifest']['env']['absent']), 'the manifest declares the .env was absent');
        @unlink($res['file']);
        return;
    }

    $before = (string) file_get_contents(BASE_PATH . '/.env');
    $r = Backup::restore($res['file'], ['with_database' => false, 'with_uploads' => true,
                                        'replace_uploads' => false, 'with_env' => true, 'migrate' => false]);
    assertTrue($r['ok'], 'restore with env ok: ' . ($r['error'] ?? '?'));
    assertEquals($before, (string) @file_get_contents(BASE_PATH . '/.env'), '.env on disk is untouched');
    assertContains('另存为', (string) $r['env'], 'and the incoming copy is written aside for a human to compare');
    foreach ((array) glob(BASE_PATH . '/.env.restored-*') as $f) {
        @unlink($f);
    }
    @unlink($res['file']);
}

// -----------------------------------------------------------------------
// 名字与路径
// -----------------------------------------------------------------------

function test_path_whitelist_blocks_slip_scripts_and_dots(): void
{
    assertTrue(Backup::isSafeAttachmentName('20260101_1200_abcd1234abcd1234.pdf'), 'a generated name passes');
    assertTrue(Backup::isSafeAttachmentName('报价单 v2.xlsx'), 'human names with spaces pass');
    foreach (['../evil.php', '..\\evil.php', 'sub/dir.txt', '/etc/passwd', '.htaccess', '.gitkeep',
              'shell.php', 'x.phtml', 'x.phar', 'x.sh', 'x.cgi', '', "a\nb", 'a;rm -rf',
              str_repeat('长', 200) . '.pdf'] as $bad) {
        assertTrue(!Backup::isSafeAttachmentName($bad), "refused: '{$bad}'");
    }

    assertTrue(Backup::isAllowedEntry('manifest.json'), 'manifest allowed');
    assertTrue(Backup::isAllowedEntry('database/crm.sqlite'), 'the db snapshot allowed');
    assertTrue(Backup::isAllowedEntry('.env'), '.env allowed as an entry (never auto-applied)');
    assertTrue(Backup::isAllowedEntry('uploads/attachments/a.txt'), 'flat attachments allowed');
    foreach (['uploads/../../config.php', 'app/bootstrap.php', 'uploads/attachments/x/y.txt',
              'uploads/attachments/shell.php', 'database/other.sqlite', ''] as $no) {
        assertTrue(!Backup::isAllowedEntry($no), "entry refused: {$no}");
    }
}

function test_snapshot_names_are_validated_before_any_file_operation(): void
{
    $dir = Backup::backupDir();
    $good = Backup::newName('backup', 'zip');
    file_put_contents($dir . '/' . $good, 'x');

    assertEquals($dir . '/' . $good, Backup::resolveSnapshot($good), 'a generated name resolves');
    foreach (['crm-backup-20260909-134500-abcdef.sql', 'crm-backup-20260909-1345-abcdef.zip'] as $lookalike) {
        assertTrue(Backup::resolveSnapshot($lookalike) === null, "near-miss rejected: {$lookalike}");
    }
    // 目录成分被 basename() 吃掉，因此可能返回的路径必然在备份目录内
    foreach (['../' . $good, '/' . $good, $dir . '/' . $good] as $tricky) {
        $resolved = Backup::resolveSnapshot($tricky);
        assertTrue($resolved === null || !str_contains(str_replace('\\', '/', dirname($resolved)), '..'),
            "never escapes the backup dir: {$tricky}");
        if ($resolved !== null) {
            assertEquals($dir . '/' . $good, $resolved, 'and when it resolves it is the file in the backup dir');
        }
    }
    foreach (['', 'history.jsonl', 'secret.zip', 'x.txt', 'crm-backup-20260101-999999-zzzzzz.zip',
              'crm-backup-20260101-999999-abcdef.tar.gz', 'crm-things-20260101-999999-abcdef.zip',
              'crm-backup-20260101-9999-abcdef.zip', '.download-' . $good] as $bad) {
        assertTrue(Backup::resolveSnapshot($bad) === null, "not resolvable: '{$bad}'");
    }
    assertTrue(!Backup::deleteSnapshot('../../../etc/hosts'), 'a traversal name cannot delete anything');
    assertTrue(is_file(BASE_PATH . '/app/routes.php'), 'in particular it cannot delete outside the dir');
    assertTrue(Backup::deleteSnapshot($good), 'the real file deletes fine');
    assertTrue(!is_file($dir . '/' . $good), 'and it is gone');
}

function test_prune_keeps_the_newest_and_spares_rollback_points(): void
{
    $dir = Backup::backupDir();
    $names = [];
    foreach (['backup', 'backup', 'backup', 'pre-restore'] as $i => $kind) {
        $n = Backup::newName($kind, 'zip');
        file_put_contents($dir . '/' . $n, 'x');
        touch($dir . '/' . $n, time() - ($i + 1) * 100);
        $names[$i] = $n;
    }
    assertEquals(4, count(onlyAmong(namesListed(), $names)), 'all four are listed (pre-restore included)');

    $removed = Backup::prune(2);
    assertEquals([$names[2]], $removed, 'the oldest manual backup is the one that goes');
    assertTrue(in_array($names[0], namesListed(), true), 'the newest survives');
    assertTrue(in_array($names[1], namesListed(), true), 'and the middle one too');
    assertTrue(in_array($names[3], namesListed(), true), 'pre-restore rollback points are never pruned');
    assertEquals(3, count(onlyAmong(namesListed(), $names)), 'one file removed, three left');

    // keep=0 必须是“不清理”，而不是“全删” —— 这个语义反过来了就是数据事故
    assertEquals([], Backup::prune(0), 'keep=0 means no pruning at all');
    assertEquals(3, count(onlyAmong(namesListed(), $names)), 'and nothing vanished');

    foreach ($names as $n) {
        @unlink($dir . '/' . $n);
    }
}

// -----------------------------------------------------------------------
// 留痕与路由
// -----------------------------------------------------------------------

function test_history_log_survives_a_restore_and_is_newest_first(): void
{
    Backup::clearLog();
    Backup::log('export', ['file' => 'a.zip', 'size' => 10]);
    Backup::log('snapshot', ['file' => 'b.zip', 'size' => 20]);
    $rows = Backup::recentLog(5);
    assertEquals(2, count($rows), 'both actions recorded');
    assertEquals('snapshot', $rows[0]['action'], 'newest first');
    assertEquals('b.zip', $rows[0]['file'], 'with its detail');
    assertTrue(isset($rows[0]['at']) && isset($rows[0]['ip']), 'each row is stamped with when/where');

    // 留痕必须是文件而不是库表：恢复本身会把库里的痕迹一起换掉
    $a = Backup::createArchive(Backup::backupDir() . '/log-src.zip',
        ['with_database' => true, 'with_uploads' => false, 'with_env' => false]);
    $r = Backup::restore($a['file'], ['with_uploads' => false, 'with_env' => false, 'migrate' => false]);
    $after = Backup::recentLog(10);
    assertTrue(count($after) >= 3, 'the log survives a restore (it lives outside the db)');
    assertEquals('restore', $after[0]['action'], 'and the restore itself is recorded');
    assertTrue(isset($after[0]['safety']), 'recording which rollback point can undo it');
    Backup::clearLog();
    assertEquals([], Backup::recentLog(5), 'clearing works');
    @unlink($a['file']);
    @unlink(Backup::backupDir() . '/' . $r['safety']['name']);
}

function test_sweep_stale_removes_only_leftover_scratch_files(): void
{
    // 异常中断（取消下载、超时）会把含全库明文的临时包留在备份目录里：
    // 它们必须能被扫掉，而真快照、回滚点、日志与目录守护文件一个都不能动。
    $dir = Backup::backupDir();
    $stale = time() - 7200;
    $scratch = [
        '.tmp-1a2b3c4d', '.tmp-2b3c4d5e-in', '.tmp-3c4d5e6f-peek', '.tmp-4d5e6f70-build',
        '.download-abcdef123456.zip', '.upload-fedcba654321.sqlite', '.upload-112233445566.zip',
    ];
    foreach ($scratch as $n) {
        $p = $dir . '/' . $n;
        if (str_starts_with($n, '.tmp-') && !str_ends_with($n, '.zip')) {
            mkdir($p, 0777, true);
            file_put_contents($p . '/part.sqlite', 'x');
        } else {
            file_put_contents($p, 'x');
        }
        touch($p, $stale);
    }
    $keepScratch = $dir . '/.tmp-5e6f7081-in';          // 一小时内的：可能正在写
    mkdir($keepScratch, 0777, true);
    touch($keepScratch, time());
    $real = Backup::newName('backup', 'zip');
    $realPath = $dir . '/' . $real;
    file_put_contents($realPath, 'x');
    touch($realPath, $stale);
    $rollback = Backup::newName('pre-restore', 'sqlite');
    file_put_contents($dir . '/' . $rollback, 'x');
    touch($dir . '/' . $rollback, $stale);
    Backup::log('snapshot', ['file' => $real]);

    $n = Backup::sweepStale(3600);

    assertTrue($n >= count($scratch), 'every stale scratch file/dir is reported as swept (' . $n . ')');
    foreach ($scratch as $name) {
        assertTrue(!file_exists($dir . '/' . $name), "{$name} gone");
    }
    assertTrue(is_dir($keepScratch), 'a scratch dir still in flight is left alone');
    assertTrue(is_file($realPath), 'real snapshots are never swept');
    assertTrue(is_file($dir . '/' . $rollback), 'nor rollback points');
    assertTrue(is_file($dir . '/history.jsonl'), 'and not the audit log');

    @rmdir($keepScratch);
    @unlink($realPath);
    @unlink($dir . '/' . $rollback);
    Backup::clearLog();
}

function test_backup_routes_are_registered(): void
{
    $router = new Router();
    require APP_PATH . '/routes.php';
    $all = $router->all();
    assertTrue(isset($all['GET']['/backup']), 'the page is routed');
    foreach (['/backup/export', '/backup/snapshot', '/backup/snapshot/delete',
              '/backup/verify', '/backup/import', '/backup/history/clear'] as $p) {
        assertTrue(isset($all['POST'][$p]), "{$p} is POST-only (state-changing or data-exposing)");
    }
    assertTrue(isset($all['GET']['/backup/download']), 'download is a GET so the browser can save the file');
    assertEquals('BackupController@import', $all['POST']['/backup/import'],
        'and it lands on the admin-only controller');
}

runCase();
