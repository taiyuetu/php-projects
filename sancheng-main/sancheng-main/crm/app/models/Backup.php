<?php

/**
 * 备份 / 恢复 —— 全部数据都在这两个地方，本类负责把它们打包与回写：
 *
 *   1. database/crm.sqlite     所有业务数据（含 AI 里存进 app_settings 的 API Key）
 *   2. public/uploads/attachments/   上传的附件文件（库里只存文件名，见 Attachment::uploadDir）
 *
 * 备份包（zip）内的固定布局，也是恢复时唯一接受的白名单：
 *   manifest.json                 自述文件：版本、时间、各表行数、每个附件的 size/sha256
 *   database/crm.sqlite           一致性快照（不是运行时 cp，见 snapshotSqlite）
 *   uploads/attachments/<文件>     附件原样平铺（本系统不留子目录）
 *   .env                          可选，默认不含（里面有密钥，见 envIncluded 的说明）
 *
 * 三个必须避开的坑（都是这个栈的实际约束，不是保守主义）：
 *   - WAL：journal_mode=wal 下直接 cp 主文件会漏掉 -wal 里已提交的事务 →
 *     一律用 SQLite3::backup / VACUUM INTO 取一致性快照。
 *   - 扩展：Windows 默认 php.ini 里 extension=zip 是注释掉的，ZipArchive 常常不可用 →
 *     全部 zip 操作都写了 ZipArchive / PharData 双路径（PharData 实测能读写外部 zip）。
 *   - 文件锁：Windows 上被 PDO 打开着的 sqlite 文件删不掉也改名不了，而恢复时本请求
 *     已经拿着这个连接（requireAuth 查过 users）→ 恢复用 SQLite3::backup 反向
 *     「把备份写进正在使用的库」，不替换文件；只有 SQLite3 不可用时才退化成 copy。
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
class Backup
{
    /** 备份包自述字段 */
    public const MANIFEST_ENTRY = 'manifest.json';
    public const DB_ENTRY       = 'database/crm.sqlite';
    public const UPLOAD_DIR_IN  = 'uploads/attachments/';
    public const ENV_ENTRY      = '.env';

    /** 只认这个标记，防止管理员把一个随手压的 zip 当成备份包盖掉生产数据 */
    private const APP_MARKER  = 'triphase-crm';
    public const FORMAT       = 1;

    /** 单个备份包允许的最大体积（磁盘/内存保护）；附件超过它就用 rsync，见 DEPLOY.md 第 7 节 */
    public const MAX_ARCHIVE_BYTES = 2048 * 1024 * 1024;

    /** 服务器快照文件名：crm-backup-20260909-134500-a1b2c3.zip（newName() 与这条必须同步改） */
    private const NAME_PATTERN = '~^crm-(backup|pre-restore)-\d{8}-\d{6}-[a-f0-9]{6}\.(zip|sqlite)$~i';

    // =====================================================================
    // 路径与环境
    // =====================================================================

    public static function dbFile(): string
    {
        return DB_PATH;
    }

    /** 服务器快照目录（不存在就建）。 */
    public static function backupDir(): string
    {
        $dir = defined('BACKUP_PATH') ? BACKUP_PATH : BASE_PATH . '/database/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    public static function attachmentDir(): string
    {
        return Attachment::uploadDir();
    }

    private static function ensureDir(string $dir): bool
    {
        return is_dir($dir) || @mkdir($dir, 0775, true);
    }

    private static function tmpDir(string $suffix = ''): string
    {
        $base = self::backupDir();
        $dir = rtrim($base, '/\\') . '/.tmp-' . bin2hex(random_bytes(4)) . $suffix;
        self::ensureDir($dir);
        return $dir;
    }

    private static function rmDir(?string $dir): void
    {
        if (!$dir || !is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    /**
     * 异常中断（浏览器取消下载、PHP 超时、进程被杀）会在备份目录里留下
     * 名为 .tmp-xxxx、.download-xxxx.zip、.upload-xxxx.ext 的中间产物：
     * 它们含全库明文，不能长期躺在服务器上。备份页打开时顺带清理早于
     * $olderThan 秒的那些（正在跑的大包不该被下一个请求删掉）。
     */
    public static function sweepStale(int $olderThan = 3600): int
    {
        $dir = self::backupDir();
        $now = time();
        $n = 0;
        foreach ((array) scandir($dir) as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            if (preg_match('/^(\.tmp-[a-f0-9]{8}(-[a-z]+)?|\.download-[a-f0-9]{12}\.zip|\.upload-[a-f0-9]{12}\.\w+)$/', $name) !== 1) {
                continue;
            }
            $path = $dir . '/' . $name;
            if ((int) @filemtime($path) > $now - $olderThan) {
                continue;
            }
            is_dir($path) ? self::rmDir($path) : @unlink($path);
            $n++;
        }
        return $n;
    }

    /**
     * 这台机器能用什么（后台页面直接显示它，避免管理员靠猜）。
     * @return array<string,array{ok:bool,label:string,why:string}>
     */
    public static function capabilities(): array
    {
        $zip = class_exists('ZipArchive');
        $phar = class_exists('PharData');
        $sqlite3 = class_exists('SQLite3');
        $vac = false;
        if (!$sqlite3) {
            try {
                $pdo = new PDO('sqlite::memory:');
                $v = (string) $pdo->query('select sqlite_version()')->fetchColumn();
                $vac = version_compare($v, '3.27.0') >= 0;
            } catch (Throwable $e) {
                $vac = false;
            }
        }
        $db = self::dbFile();

        return [
            'zip_write' => [
                'ok'    => $zip || $phar,
                'label' => 'zip 打包：' . ($zip ? 'ZipArchive' : ($phar ? 'PharData（phar 扩展）' : '不可用')),
                'why'   => $zip ? '' : 'php.ini 里 extension=zip 默认是注释掉的；没开时自动改用 PharData，功能相同。',
            ],
            'zip_read' => [
                'ok'    => $zip || $phar,
                'label' => 'zip 解包：' . ($zip ? 'ZipArchive' : ($phar ? 'PharData' : '不可用')),
                'why'   => '',
            ],
            'db_snapshot' => [
                'ok'    => $sqlite3 || $vac,
                'label' => '一致性快照：' . ($sqlite3 ? 'SQLite3::backup' : ($vac ? 'VACUUM INTO' : '不可用')),
                'why'   => $sqlite3 ? '' : '需要 ext-sqlite3，或 SQLite ≥ 3.27（VACUUM INTO）。',
            ],
            'db_writable' => [
                'ok'    => is_writable(dirname($db)),
                'label' => '数据库目录可写：' . (is_writable(dirname($db)) ? '是' : '否'),
                'why'   => '恢复要写它；WAL 还要求同目录可写 -wal/-shm。',
            ],
            'backup_writable' => [
                'ok'    => is_writable(self::backupDir()),
                'label' => '备份目录可写：' . (is_writable(self::backupDir()) ? '是' : '否'),
                'why'   => self::backupDir(),
            ],
            'uploads_writable' => [
                'ok'    => is_writable(self::attachmentDir()),
                'label' => '附件目录可写：' . (is_writable(self::attachmentDir()) ? '是' : '否'),
                'why'   => '恢复附件要往这里写。',
            ],
        ];
    }

    /** 上传限制（后台提示用）：超过它必须走「从服务器快照恢复」。 */
    public static function uploadLimits(): array
    {
        $toBytes = static function (string $v): int {
            $v = trim($v);
            if ($v === '') {
                return 0;
            }
            $n = (int) $v;
            switch (strtolower(substr($v, -1))) {
                case 'g': $n *= 1024; break;
                case 'm': break;
                case 'k': $n = intdiv($n, 1024); break;
                default: return (int) $v;
            }
            return $n * 1024 * 1024;
        };
        return [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size'       => ini_get('post_max_size'),
            'upload_max_bytes'    => $toBytes((string) ini_get('upload_max_filesize')),
            'post_max_bytes'      => $toBytes((string) ini_get('post_max_size')),
            'exec_available'      => function_exists('exec') && !self::execDisabled(),
        ];
    }

    private static function execDisabled(): bool
    {
        $d = strtolower((string) ini_get('disable_functions'));
        return $d !== '' && preg_match('/(^|[,;\s])exec($|[;,(\s])/i', $d) === 1;
    }

    // =====================================================================
    // 现状统计
    // =====================================================================

    /**
     * 当前库/附件的体检数字，备份页与备份包 manifest 共用同一个来源，
     * 免得「页面上 12 个客户，备份包里 11 个」这种说不清的情况。
     */
    public static function stats(): array
    {
        $counts = [];
        $totalRows = 0;
        try {
            foreach (Schema::tableNames() as $t) {
                $n = (int) (new Database())->query("SELECT COUNT(*) AS c FROM \"{$t}\"")->single()['c'];
                $counts[$t] = $n;
                $totalRows += $n;
            }
            ksort($counts);
        } catch (Throwable $e) {
            // 表结构异常时不谎报数字，让调用方看到空清单
        }

        [$attCount, $attBytes] = self::attachmentInventory();
        $db = self::dbFile();

        return [
            'db_path'      => $db,
            'db_exists'    => is_file($db),
            'db_readable'  => is_readable($db),
            'db_bytes'     => is_file($db) ? (int) filesize($db) : 0,
            'db_realpath'  => is_file($db) ? (string) realpath($db) : '',
            'wal_present'  => is_file($db . '-wal') && filesize($db . '-wal') > 0,
            'counts'       => $counts,
            'total_rows'   => $totalRows,
            'tables'       => count($counts),
            'attachments'  => $attCount,
            'attach_bytes' => $attBytes,
            'attach_dir'   => self::attachmentDir(),
            'backup_dir'   => self::backupDir(),
            'backup_count' => count(self::listSnapshots()),
            'orphans'      => self::orphanReport(),
            'app_version'  => APP_VERSION,
            'generated_at' => appNow('Y-m-d H:i:s'),
        ];
    }

    /** @return array{0:int,1:int} 附件数与总字节（跳过 .gitkeep 这类占位） */
    public static function attachmentInventory(): array
    {
        $count = 0;
        $bytes = 0;
        foreach (self::attachmentFiles() as $f) {
            $count++;
            $bytes += (int) $f['size'];
        }
        return [$count, $bytes];
    }

    /**
     * 附件目录里的真实文件（平铺，不递归，不跟随符号链接）。
     * @return array<int,array{name:string,path:string,size:int,mtime:int,sha?:string}>
     */
    public static function attachmentFiles(bool $withHash = false): array
    {
        $dir = self::attachmentDir();
        $out = [];
        if (!is_dir($dir)) {
            return $out;
        }
        foreach ((array) scandir($dir) as $name) {
            if ($name === '.' || $name === '..' || !self::isSafeAttachmentName($name)) {
                continue;
            }
            $path = $dir . '/' . $name;
            if (!is_file($path) || is_link($path)) {
                continue;
            }
            $row = [
                'name'  => $name,
                'path'  => $path,
                'size'  => (int) filesize($path),
                'mtime' => (int) filemtime($path),
            ];
            if ($withHash) {
                $h = @hash_file('sha256', $path);
                if ($h !== false) {
                    $row['sha256'] = $h;
                }
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * 附件一致性对账：库里有记录但磁盘没了 / 磁盘有文件但库里没引用。
     * 备份前看一眼，恢复后再看一眼 —— 两边不一致就说明目录被动过手脚。
     */
    public static function orphanReport(): array
    {
        $missing = [];
        $unused  = [];
        try {
            $rows = (new Database())->query('SELECT filename FROM attachments ORDER BY filename')->resultSet();
            $indexed = [];
            foreach ($rows as $r) {
                $f = (string) ($r['filename'] ?? '');
                if ($f !== '') {
                    $indexed[$f] = true;
                    if (!is_file(self::attachmentDir() . '/' . basename($f))) {
                        $missing[] = $f;
                    }
                }
            }
            $onDisk = [];
            foreach (self::attachmentFiles() as $f) {
                $onDisk[$f['name']] = true;
                if (!isset($indexed[$f['name']])) {
                    $unused[] = $f['name'];
                }
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        return [
            'ok'             => !$missing && !$unused,
            'missing_count'  => count($missing),
            'missing'        => array_slice($missing, 0, 10),
            'unused_count'   => count($unused),
            'unused'         => array_slice($unused, 0, 10),
        ];
    }

    /**
     * 附件文件名白名单：Attachment::upload() 生成的是 20260909_1345_ab12cd34.jpg 这种，
     * 所以任何带路径分隔符 / 上跳 / 隐藏文件 / .php 的名字都不是本系统产物。
     * 恢复时靠它挡 zip slip（比 extractTo 的清洗多一道自己的锁）。
     */
    public static function isSafeAttachmentName(string $name): bool
    {
        if ($name === '' || $name !== basename(str_replace('\\', '/', $name))) {
            return false;                       // 带目录 = 不要
        }
        if ($name[0] === '.') {
            return false;                       // .gitkeep / .htaccess 等占位
        }
        if (preg_match('#[/\\\\]|\.\.ctl|\.\.#', $name)) {
            return false;
        }
        if (preg_match('/\.(php[0-9]?|phtml|phar|pl|py|cgi|sh|bash|htaccess)$/i', $name)) {
            return false;                       // 上传目录里绝不落可执行文件
        }
        return strlen($name) <= 200 && (bool) preg_match('/^[\w \-.,()@#%+]{1,200}$/u', $name);
    }

    // =====================================================================
    // 一致性快照
    // =====================================================================

    /**
     * 把正在使用的库复制成一个独立文件（含 WAL 里已提交的内容）。
     * @return array{path:string,size:int,method:string}
     * @throws RuntimeException
     */
    public static function snapshotSqlite(string $dest): array
    {
        $src = self::dbFile();
        if (!is_file($src)) {
            throw new RuntimeException('找不到数据库文件：' . $src);
        }
        $dir = dirname($dest);
        if (!self::ensureDir($dir)) {
            throw new RuntimeException('无法创建目录：' . $dir);
        }
        @unlink($dest);

        $method = '';
        if (class_exists('SQLite3')) {
            // 故意不用 SQLITE3_READONLY：WAL 库在只读打开时，若 -wal 里有待回放的帧
            // 会报 SQLITE_READONLY_RECOVERY（“ unable to open database file ”），
            // 而那恰恰是我们最需要快照能工作的时刻。
            $from = @new SQLite3($src, SQLITE3_OPEN_READWRITE);
            $to   = @new SQLite3($dest, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            $from->backup($to, 'main', 'main');
            $from = null;
            $to   = null;                       // 先关句柄再取 size
            $method = 'SQLite3::backup';
        } elseif (self::vacuumInto($src, $dest)) {
            $method = 'VACUUM INTO';
        } else {
            if (!@copy($src, $dest)) {
                throw new RuntimeException('数据库快照失败：SQLite3 与 VACUUM INTO 都不可用，复制也被拒。');
            }
            $method = 'copy（不带 WAL 一致性保证，仅兜底）';
        }

        if (!is_file($dest) || filesize($dest) === 0) {
            throw new RuntimeException('数据库快照写入失败（0 字节）。');
        }
        self::assertDatabaseFile($dest);

        return ['path' => $dest, 'size' => (int) filesize($dest), 'method' => $method];
    }

    /** SQLite ≥ 3.27 的在线备份；单引号按 SQL 规则双写，路径不做任何拼接解释。 */
    private static function vacuumInto(string $src, string $dest): bool
    {
        try {
            $pdo = new PDO('sqlite:' . $src, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("VACUUM INTO '" . str_replace("'", "''", $dest) . "'");
            $pdo = null;
            return is_file($dest);
        } catch (Throwable $e) {
            return false;
        }
    }

    /** 校验一个 sqlite 文件确实可读、结构完整、并且像本系统的库。 */
    public static function assertDatabaseFile(string $file): array
    {
        if (!is_file($file) || filesize($file) < 512) {
            throw new RuntimeException('不是有效的 sqlite 文件（太小）。');
        }
        $sig = (string) file_get_contents($file, false, null, 0, 16);
        if (!str_starts_with($sig, 'SQLite format 3')) {
            throw new RuntimeException('文件头不是 "SQLite format 3"，这个包里的库可能已被改动或损坏。');
        }
        try {
            $pdo = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (Throwable $e) {
            throw new RuntimeException('无法打开备份里的数据库：' . $e->getMessage());
        }
        $integrity = trim((string) $pdo->query('PRAGMA integrity_check')->fetchColumn());
        if (strcasecmp($integrity, 'ok') !== 0) {
            throw new RuntimeException('完整性检查未通过：' . textClip($integrity, 160));
        }
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
            ->fetchAll(PDO::FETCH_COLUMN);
        $pdo = null;

        $missing = array_values(array_diff(['users', 'customers', 'leads', 'deals', 'orders'], $tables));
        if ($missing) {
            throw new RuntimeException('这个库里没有 ' . implode(' / ', $missing) . ' 表，不像是叁程 CRM 的数据。');
        }
        return ['tables' => array_values($tables), 'integrity' => 'ok'];
    }

    // =====================================================================
    // zip 读写（ZipArchive 优先，PharData 兜底）
    // =====================================================================

    private static function zipWriterAvailable(): bool
    {
        return class_exists('ZipArchive') || class_exists('PharData');
    }

    /**
     * 写一个 zip。$entries = ['包内路径' => '/绝对路径'] 或 ['包内路径' => ['data' => '内容']]。
     * 一次调用写完再关（PharData 打开已存在的包再改，行为不如新建可靠）。
     */
    private static function writeZip(string $dest, array $entries): string
    {
        if (!self::ensureDir(dirname($dest))) {
            throw new RuntimeException('无法创建目录：' . dirname($dest));
        }
        @unlink($dest);

        if (class_exists('ZipArchive')) {
            $z = new ZipArchive();
            if ($z->open($dest, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('ZipArchive 无法创建 ' . basename($dest));
            }
            foreach ($entries as $name => $src) {
                if (is_array($src)) {
                    $z->addFromString($name, (string) $src['data']);
                } elseif (!$z->addFile($src, $name)) {
                    $z->close();
                    @unlink($dest);
                    throw new RuntimeException('写入 zip 失败：' . $name);
                }
            }
            // 不 close 就没有中央目录，整个文件是废的
            $closed = $z->close();
            if ($entries && $closed !== true) {
                @unlink($dest);
                throw new RuntimeException('zip 收尾失败（磁盘满？）。');
            }
            return 'ZipArchive';
        }

        if (!class_exists('PharData')) {
            throw new RuntimeException('这台 PHP 既没有 ZipArchive 也没有 PharData，打不了包。');
        }
        $p = new PharData($dest, 0, null, Phar::ZIP);
        foreach ($entries as $name => $src) {
            if (is_array($src)) {
                $p->addFromString($name, (string) $src['data']);
            } else {
                $p->addFile($src, $name);
            }
        }
        unset($p);                             // flush；不 unset 时包里没有内容
        if (!is_file($dest)) {
            throw new RuntimeException('PharData 未能生成 ' . basename($dest));
        }
        return 'PharData';
    }

    /**
     * 解包到临时目录，并只留下白名单里的路径。
     * 用 extractTo 而不是遍历 phar://：PharData 遍历外部 zip 的嵌套条目在 Windows 上
     * 会 stat 失败（实测），而解到磁盘再走文件系统没有这个问题。清洗 .. 由 phar 自己做，
     * 我们再按白名单复制一遍，双保险。
     *
     * @return array{dir:string,writer:string,entries:array<int,string>}
     */
    private static function extractZip(string $archive, string $toDir): array
    {
        if (!self::ensureDir($toDir)) {
            throw new RuntimeException('无法创建临时目录：' . $toDir);
        }
        $stage = $toDir . '/.in';
        self::ensureDir($stage);
        $writer = '';

        if (class_exists('ZipArchive')) {
            $z = new ZipArchive();
            if ($z->open($archive) !== true) {
                throw new RuntimeException('无法打开这个 zip（可能不是备份包，或已损坏）。');
            }
            $z->extractTo($stage);
            $z->close();
            $writer = 'ZipArchive';
        } elseif (class_exists('PharData')) {
            try {
                (new PharData($archive))->extractTo($stage);
            } catch (Throwable $e) {
                // phar 会按扩展名猜格式，给一个明确后缀再试一次
                $alias = $toDir . '/' . bin2hex(random_bytes(4)) . '.zip';
                @copy($archive, $alias);
                try {
                    (new PharData($alias))->extractTo($stage);
                } catch (Throwable $e2) {
                    @unlink($alias);
                    throw new RuntimeException('无法解包：' . $e->getMessage());
                }
                @unlink($alias);
            }
            $writer = 'PharData';
        } else {
            throw new RuntimeException('这台 PHP 没有 zip 读取能力（ZipArchive / PharData 都没有）。');
        }

        $kept = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if (!$f->isFile()) {
                continue;
            }
            $rel = self::relativeFrom($stage, $f->getPathname());
            if ($rel === null) {
                continue;                       // 规范化后仍在 _stage 之外 → 丢弃
            }
            if (!self::isAllowedEntry($rel)) {
                continue;                       // 包里的其它东西一律不带进系统
            }
            if ($f->getSize() > self::MAX_ARCHIVE_BYTES) {
                throw new RuntimeException('「' . $rel . '」过大，拒绝解出。');
            }
            $target = $toDir . '/' . $rel;
            self::ensureDir(dirname($target));
            if (!@rename($f->getPathname(), $target)) {
                throw new RuntimeException('无法放置 ' . $rel);
            }
            $kept[] = $rel;
        }
        self::rmDir($stage);
        sort($kept);

        return ['dir' => $toDir, 'writer' => $writer, 'entries' => $kept];
    }

    /** 包内路径 → 落在 stage 根下的相对路径；任何越界（绝对、含 ..）返回 null。 */
    private static function relativeFrom(string $root, string $abs): ?string
    {
        $rootReal = realpath($root);
        $absReal  = realpath($abs);
        if ($rootReal === false) {
            return null;
        }
        $rel = str_replace('\\', '/', (string) $absReal);
        $rootReal = str_replace('\\', '/', $rootReal);
        if (!str_starts_with($rel, $rootReal . '/')) {
            return null;
        }
        $rel = substr($rel, strlen($rootReal) + 1);
        foreach (explode('/', $rel) as $seg) {
            if ($seg === '' || $seg === '.') {
                return null;                    // 绝对路径 / 怪名字：不认
            }
        }
        return $rel;
    }

    /** 白名单：只有这四类条目能被放进系统。 */
    public static function isAllowedEntry(string $rel): bool
    {
        $rel = str_replace('\\', '/', ltrim($rel, '/'));
        if ($rel === self::MANIFEST_ENTRY || $rel === self::DB_ENTRY || $rel === self::ENV_ENTRY) {
            return true;
        }
        if (str_starts_with($rel, self::UPLOAD_DIR_IN)) {
            return self::isSafeAttachmentName(substr($rel, strlen(self::UPLOAD_DIR_IN)));
        }
        return false;
    }

    // =====================================================================
    // 导出
    // =====================================================================

    /**
     * 生成一个备份包。
     * @param array $opts with_database / with_uploads / with_env / note
     * @return array{file:string,size:int,writer:string,manifest:array}
     */
    public static function createArchive(?string $dest, array $opts = []): array
    {
        $opts += ['with_database' => true, 'with_uploads' => true, 'with_env' => false, 'note' => ''];

        if (!$opts['with_database'] && !$opts['with_uploads']) {
            throw new RuntimeException('至少要勾上数据库或附件。');
        }
        if (!$opts['with_database']) {
            // 只备份附件是合法用法，但必须提醒：库里的附件引用不在包里
            $opts['note'] = trim((string) $opts['note'] . ' [不含数据库：仅附件文件]');
        }
        if (!$opts['with_uploads']) {
            $opts['note'] = trim((string) $opts['note'] . ' [不含附件目录]');
        }

        $workspace = self::tmpDir('-build');
        $entries = [];
        $manifest = [
            'app'           => self::APP_MARKER,
            'format'        => self::FORMAT,
            'product'       => APP_NAME . ' (' . APP_NAME_EN . ')',
            'app_version'   => APP_VERSION,
            'exported_at'   => appNow('Y-m-d H:i:s'),
            'exported_by'   => (string) (currentUser()['name'] ?? 'cli'),
            'note'          => (string) $opts['note'],
            'php'           => PHP_VERSION,
            'sqlite'        => self::sqliteVersion(),
            'includes'      => [
                'database'  => (bool) $opts['with_database'],
                'uploads'   => (bool) $opts['with_uploads'],
                'env'       => (bool) $opts['with_env'],
            ],
        ];

        try {
            if ($opts['with_database']) {
                $snap = self::snapshotSqlite($workspace . '/crm.sqlite');
                $entries[self::DB_ENTRY] = $snap['path'];
                $manifest['database'] = [
                    'size'    => $snap['size'],
                    'sha256'  => (string) hash_file('sha256', $snap['path']),
                    'method'  => $snap['method'],
                    // 行数从快照文件本身数出来（不是从正在使用的库）：并发写入会让
                    // manifest 的数字和包里的内容对不上，对账就失去意义。
                    'tables'  => self::countRows($snap['path']),
                    'migrations' => self::appliedMigrations($snap['path']),
                ];
            }
            if ($opts['with_uploads']) {
                $files = [];
                foreach (self::attachmentFiles(true) as $f) {
                    if ($f['size'] > self::MAX_ARCHIVE_BYTES) {
                        throw new RuntimeException('附件「' . $f['name'] . '」过大，无法打包。');
                    }
                    $entries[self::UPLOAD_DIR_IN . $f['name']] = $f['path'];
                    $files[] = array_filter([
                        'name' => $f['name'],
                        'size' => $f['size'],
                        'sha256' => $f['sha256'] ?? null,
                    ], static fn($v) => $v !== null);
                }
                $manifest['uploads'] = ['count' => count($files), 'bytes' => array_sum(array_column($files, 'size')), 'files' => $files];
            }
            if ($opts['with_env']) {
                $env = BASE_PATH . '/.env';
                if (is_file($env) && is_readable($env)) {
                    $entries[self::ENV_ENTRY] = $env;
                    $manifest['env'] = ['size' => (int) filesize($env)];
                } else {
                    $manifest['env'] = ['size' => 0, 'absent' => true];
                }
            }

            $manifest['checksum_note'] = 'sha256 只对数据库快照与附件文件计算；manifest 本身不参与校验。';
            $entries[self::MANIFEST_ENTRY] = ['data' => json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];

            $dest ??= self::backupDir() . '/' . self::newName('backup', 'zip');
            $writer = self::writeZip($dest, $entries);
            if (!is_file($dest)) {
                throw new RuntimeException('备份包未生成。');
            }
            return [
                'file'     => $dest,
                'size'     => (int) filesize($dest),
                'writer'   => $writer,
                'manifest' => $manifest,
            ];
        } finally {
            self::rmDir($workspace);
        }
    }

    /**
     * 为浏览器下载生成临时包。文件名以 . 开头，所以 listSnapshots() 与保留策略看不见它 ——
     * 下载到本地那一份不应该算作「服务器上的快照」。发送与删除由控制器负责。
     */
    public static function buildForDownload(array $opts): array
    {
        $file = self::backupDir() . '/.download-' . bin2hex(random_bytes(6)) . '.zip';
        $res = self::createArchive($file, $opts);
        // 管理员看到的文件名用规范名，跟服务器上的快照同一形状
        $res['download_name'] = self::newName('backup', 'zip');
        return $res;
    }

    /** 数一个 sqlite 文件里各表的行数（打包对账与备份预览共用一份算法）。 */
    public static function countRows(string $file): array
    {
        $counts = [];
        try {
            $pdo = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
                ->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $t) {
                $counts[(string) $t] = (int) $pdo->query('SELECT COUNT(*) FROM "'
                    . str_replace('"', '""', (string) $t) . '"')->fetchColumn();
            }
            $pdo = null;
            ksort($counts);
        } catch (Throwable $e) {
            // 读不动就交空清单；inspect() 会先把文件验过一遍才走到这里
        }
        return $counts;
    }

    private static function sqliteVersion(): string
    {
        try {
            $row = (new Database())->query('SELECT sqlite_version() AS v')->single();
            return (string) ($row['v'] ?? '');
        } catch (Throwable $e) {
            return '';
        }
    }

    /** 从一个 sqlite 文件里读 _migrations 台账（不带运行时连接）。 */
    public static function appliedMigrations(string $file): array
    {
        try {
            $pdo = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $rows = $pdo->query("SELECT name FROM _migrations ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
            $pdo = null;
            return array_values($rows);
        } catch (Throwable $e) {
            return [];
        }
    }

    // =====================================================================
    // 服务器快照
    // =====================================================================

    public static function newName(string $kind, string $ext): string
    {
        return sprintf('crm-%s-%s-%s.%s', $kind, date('Ymd-His'), substr(bin2hex(random_bytes(4)), 0, 6), $ext);
    }

    /** 文件名必须是本系统生成的形状，且不含目录 —— 用户可控的下载/删除参数全靠这一条守住。 */
    public static function resolveSnapshot(string $name): ?string
    {
        $base = basename(str_replace('\\', '/', $name));
        if (preg_match(self::NAME_PATTERN, $base) !== 1) {
            return null;
        }
        $path = self::backupDir() . '/' . $base;
        return is_file($path) ? $path : null;
    }

    /** @return array<int,array{name:string,path:string,size:int,mtime:int,kind:string,manifest:array}> */
    public static function listSnapshots(): array
    {
        $dir = self::backupDir();
        $out = [];
        foreach ((array) scandir($dir) as $name) {
            if (preg_match(self::NAME_PATTERN, $name) !== 1) {
                continue;
            }
            $path = $dir . '/' . $name;
            if (!is_file($path)) {
                continue;
            }
            $out[] = [
                'name'     => $name,
                'path'     => $path,
                'size'     => (int) filesize($path),
                'mtime'    => (int) filemtime($path),
                'kind'     => str_contains($name, 'pre-restore') ? 'pre-restore' : 'backup',
                'manifest' => self::peekManifest($path),
            ];
        }
        usort($out, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);
        return $out;
    }

    /** 只读 manifest（列表页要显示"这份包里有什么"，不值得为它解全包）。 */
    public static function peekManifest(string $path): array
    {
        $tmp = null;
        try {
            if (self::looksLikeSqlite($path)) {
                return ['app' => self::APP_MARKER, 'format' => self::FORMAT, 'bare_database' => true,
                        'database' => ['size' => (int) filesize($path), 'tables' => self::countRows($path)]];
            }
            $tmp = self::tmpDir('-peek');
            $ex = self::extractZip($path, $tmp);
            $file = $ex['dir'] . '/' . self::MANIFEST_ENTRY;
            if (!is_file($file)) {
                return [];
            }
            $m = json_decode((string) file_get_contents($file), true);
            return is_array($m) ? $m : [];
        } catch (Throwable $e) {
            // 列表页不该因为一个坏包而整页报错：把原因带出去显示
            return ['unreadable' => $e->getMessage()];
        } finally {
            if (is_string($tmp)) {
                self::rmDir($tmp);
            }
        }
    }

    /** sqlite 判定看两件事：扩展名 + 文件头签名，不看它叫什么名字。 */
    private static function looksLikeSqlite(string $path): bool
    {
        return (bool) preg_match('/\.(sqlite|sqlite3|db|db3)$/i', $path)
            && str_starts_with((string) @file_get_contents($path, false, null, 0, 16), 'SQLite format 3');
    }

    /** 保留最近 $keep 个手工备份（pre-restore 安全快照永不清理）。 */
    public static function prune(int $keep = 10): array
    {
        $removed = [];
        if ($keep < 1) {
            return $removed;
        }
        $manual = array_values(array_filter(self::listSnapshots(), static fn($s) => $s['kind'] === 'backup'));
        foreach (array_slice($manual, $keep) as $old) {
            if (@unlink($old['path'])) {
                $removed[] = $old['name'];
            }
        }
        return $removed;
    }

    public static function deleteSnapshot(string $name): bool
    {
        $path = self::resolveSnapshot($name);
        return $path !== null && @unlink($path);
    }

    // =====================================================================
    // 导入前的检查（不写任何东西）
    // =====================================================================

    /**
     * 检查一个备份来源（zip 包 / 裸 sqlite），返回恢复前管理员该看到的全部事实。
     * 恢复动作复用同一个函数，因此"看过的"与"用掉的"必然是同一份数据。
     *
     * @return array{ok:bool,file:string,stage:string,entries:array<int,string>,manifest:array,
     *               database:array,uploads:array,env:array,warnings:array<int,string>}
     */
    /**
     * 检查一个备份来源（zip 包 / 裸 sqlite），返回恢复前管理员该看到的全部事实。
     * 恢复动作内部复用同一个函数，因此“看过的”与“用掉的”必然是同一份数据。
     *
     * @param bool $needsDatabase 附件包（只带 uploads/ 的包）也是合法备份：
     *                            预览与“只恢复附件”时传 false，要盖库时传 true。
     *
     * @return array{ok:bool,file:string,stage:string,entries:array<int,string>,manifest:array,.database:array,uploads:array,env:array,warnings:array<int,string>}
     */
    public static function inspect(string $source, bool $needsDatabase = true): array
    {
        if (!is_file($source)) {
            throw new RuntimeException('来源文件不存在。');
        }
        $size = (int) filesize($source);
        if ($size > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('备份包超过 ' . self::humanSize(self::MAX_ARCHIVE_BYTES) . ' 上限。');
        }

        $stage = self::tmpDir('-in');
        $entries = [];

        try {
            if (self::looksLikeSqlite($source)) {
                // 裸 .sqlite：也当合法备份来源（很多管理员只把库文件拷走）
                $target = $stage . '/' . self::DB_ENTRY;
                self::ensureDir(dirname($target));
                if (!@copy($source, $target)) {
                    throw new RuntimeException('无法暂存数据库文件。');
                }
                $entries = [self::DB_ENTRY];
                $manifest = ['app' => self::APP_MARKER, 'format' => self::FORMAT, 'bare_database' => true,
                             'includes' => ['database' => true, 'uploads' => false, 'env' => false]];
            } else {
                $ex = self::extractZip($source, $stage);
                $entries = $ex['entries'];
                $mfFile = $stage . '/' . self::MANIFEST_ENTRY;
                $manifest = [];
                if (is_file($mfFile)) {
                    $decoded = json_decode((string) file_get_contents($mfFile), true);
                    if (is_array($decoded)) {
                        $manifest = $decoded;
                    }
                }
                if (($manifest['app'] ?? '') !== self::APP_MARKER) {
                    throw new RuntimeException(
                        '这个包里缺少叁程 CRM 的 manifest.json（或 app 字段不对）。'
                        . '为避免把别的 zip 盖到生产库上，拒绝导入；只带一个 crm.sqlite 的裸库文件也可以直接上传。'
                    );
                }
                if ((int) ($manifest['format'] ?? 1) > self::FORMAT) {
                    throw new RuntimeException('备份包格式版本 v' . (int) $manifest['format'] . ' 比当前代码（v'
                        . self::FORMAT . '）新，请先把代码升级到能读它的版本再导入。');
                }
            }

            $hasDb = in_array(self::DB_ENTRY, $entries, true);
            $dbFile = $stage . '/' . self::DB_ENTRY;
            if (!$hasDb && $needsDatabase) {
                throw new RuntimeException('包里没有 ' . self::DB_ENTRY . '，无法恢复数据库。');
            }

            $counts = [];
            $totalRows = 0;
            $sqliteVer = '';
            $migrations = [];
            if ($hasDb) {
                self::assertDatabaseFile($dbFile);

                // 行数直接从（已落到临时目录的）备份库里数，与 manifest 对账用同一算法
                $counts = self::countRows($dbFile);
                $totalRows = array_sum($counts);
                $migrations = self::appliedMigrations($dbFile);
                try {
                    $pdo = new PDO('sqlite:' . $dbFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $sqliteVer = (string) $pdo->query('SELECT sqlite_version()')->fetchColumn();
                    $pdo = null;
                } catch (Throwable $e) {
                    $sqliteVer = '';
                }
            }

            // manifest 里声明的附件哈希，按名字查 —— 下面写回磁盘时拿它做真校验
            $declaredSha = [];
            foreach ((array) ($manifest['uploads']['files'] ?? []) as $d) {
                if (isset($d['name'])) {
                    $declaredSha[(string) $d['name']] = ['sha256' => (string) ($d['sha256'] ?? ''), 'size' => (int) ($d['size'] ?? -1)];
                }
            }

            $uploads = [];
            $uploadBytes = 0;
            foreach ($entries as $rel) {
                if (str_starts_with($rel, self::UPLOAD_DIR_IN)) {
                    $f = $stage . '/' . $rel;
                    $bytes = (int) filesize($f);
                    $uploadBytes += $bytes;
                    $name = basename($rel);
                    $uploads[] = [
                        'name'  => $name,
                        'size'  => $bytes,
                        'sha256' => (string) hash_file('sha256', $f),
                        'expected' => $declaredSha[$name]['sha256'] ?? '',
                    ];
                }
            }

            $warnings = [];
            if (!$hasDb) {
                $warnings[] = '这个包里没有数据库（只打了附件），本次只能恢复附件。';
            } else {
                // manifest 与实物对账：不一致说明包被人动过（或打包中途失败）
                $declaredSize = $manifest['database']['size'] ?? null;
                if ($declaredSize !== null && (int) $declaredSize !== (int) filesize($dbFile)) {
                    $warnings[] = 'manifest 声明的库大小与包内实际不一致（可能被人改过）。';
                }
                $declaredDbSha = $manifest['database']['sha256'] ?? null;
                if ($declaredDbSha) {
                    $actual = (string) hash_file('sha256', $dbFile);
                    if (!hash_equals((string) $declaredDbSha, $actual)) {
                        $warnings[] = 'manifest 声明的库 sha256 与包内实际不符。';
                    }
                }
                $mfCounts = $manifest['database']['tables'] ?? [];
                if (is_array($mfCounts) && $mfCounts) {
                    $drift = [];
                    foreach ($mfCounts as $t => $n) {
                        if (array_key_exists((string) $t, $counts) && (int) $n !== $counts[(string) $t]) {
                            $drift[] = $t . '(' . (int) $n . '→' . (int) $counts[(string) $t] . ')';
                        }
                    }
                    if ($drift) {
                        $warnings[] = '与打包时相比行数已变：' . implode(' ', array_slice($drift, 0, 6));
                    }
                }
                if ($totalRows === 0) {
                    $warnings[] = '这个库里一行数据都没有，确认这是你想要的？';
                }
            }
            foreach ($uploads as $u) {
                if ($u['size'] === 0) {
                    $warnings[] = '附件 ' . $u['name'] . ' 是 0 字节。';
                }
                if ($u['expected'] !== '' && !hash_equals($u['expected'], $u['sha256'])) {
                    $warnings[] = '附件 ' . $u['name'] . ' 与打包时不一致（sha256 不符），恢复时会拒绝写入。';
                }
            }
            if (empty($manifest['includes']['uploads']) && !$uploads) {
                $warnings[] = '这个包不含附件目录（数据库不受影响）。';
            }
            $liveAtt = self::attachmentInventory();
            if ($liveAtt[0] > 0 && !$uploads) {
                $warnings[] = '当前服务器上有 ' . $liveAtt[0] . ' 个附件而包里一个都没有 —— 若选「整目录替换」会被清空（默认合并不会）。';
            }
            if (isset($manifest['app_version']) && $manifest['app_version'] !== APP_VERSION) {
                $warnings[] = '备份来自 v' . $manifest['app_version'] . '，当前代码 v' . APP_VERSION
                    . '。恢复后会自动跑一次 migrate.php --no-demo 补齐结构。';
            }

            return [
                'ok'        => true,
                'file'      => $source,
                'stage'     => $stage,
                'entries'   => $entries,
                'manifest'  => $manifest,
                'database'  => [
                    'present'   => $hasDb,
                    'size'      => $hasDb ? (int) filesize($dbFile) : 0,
                    'tables'    => count($counts),
                    'rows'      => (int) $totalRows,
                    'counts'    => $counts,
                    'migrations' => $migrations,
                    'sqlite'    => $sqliteVer,
                    'exported_at' => (string) ($manifest['exported_at'] ?? '未知（裸库文件）'),
                    'exported_by' => (string) ($manifest['exported_by'] ?? ''),
                    'source_version' => (string) ($manifest['app_version'] ?? ''),
                ],
                'uploads'   => ['count' => count($uploads), 'bytes' => $uploadBytes, 'files' => $uploads],
                'env'       => ['present' => in_array(self::ENV_ENTRY, $entries, true)],
                'warnings'  => $warnings,
            ];
        } catch (Throwable $e) {
            self::rmDir($stage);
            throw $e;
        }
    }

    /** 清掉 inspect() 留下的临时目录（restore 内部会自己收尾，只做预览时得由控制器调）。 */
    public static function release(array $inspection): void
    {
        if (!empty($inspection['stage']) && is_string($inspection['stage'])) {
            self::rmDir($inspection['stage']);
        }
    }

    // =====================================================================
    // 导入
    // =====================================================================

    /**
     * 恢复。顺序刻意是「先安全快照 → 再校验 → 再写库 → 再放附件 → 最后补结构」：
     * 任一步抛异常时，要么还没动过生产数据，要么已经留下 pre-restore 快照可回退。
     *
     * @param array $opts with_database / with_uploads / replace_uploads / with_env / migrate
     * @return array<string,mixed> 给页面看的报告
     */
    public static function restore(string $source, array $opts = []): array
    {
        $opts += [
            'with_database'   => true,
            'with_uploads'    => true,
            'replace_uploads' => false,
            'with_env'        => false,
            'migrate'         => true,
            'wipe_tokens'     => true,
        ];

        $inspection = self::inspect($source, (bool) $opts['with_database']);
        $stage = $inspection['stage'];
        $report = [
            'checked'    => [
                'rows'   => $inspection['database']['rows'],
                'tables' => $inspection['database']['tables'],
                'uploads' => $inspection['uploads']['count'],
                'exported_at' => $inspection['database']['exported_at'],
            ],
            'warnings'   => $inspection['warnings'],
            'safety'     => null,
            'database'   => 'skipped',
            'migrated'   => null,
            'uploads'    => ['written' => 0, 'skipped' => 0, 'removed' => 0, 'bytes' => 0],
            'env'        => 'skipped',
            'tokens'     => 'skipped',
            'at'         => appNow('Y-m-d H:i:s'),
        ];

        try {
            if ($opts['with_database']) {
                // 回退用的安全快照：只拷库，代价小，但它是唯一能反悔的东西
                $safety = self::backupDir() . '/' . self::newName('pre-restore', 'sqlite');
                $report['safety'] = ['name' => basename($safety), 'size' => 0];
                $snap = self::snapshotSqlite($safety);
                $report['safety']['size'] = $snap['size'];

                $report['database'] = self::applyDatabase($stage . '/' . self::DB_ENTRY);

                if ($opts['wipe_tokens']) {
                    $report['tokens'] = self::wipeRememberTokens();
                }
            }

            if ($opts['with_uploads'] && $inspection['uploads']['count'] > 0) {
                $report['uploads'] = self::applyUploads($stage, $inspection, (bool) $opts['replace_uploads']);
            }

            if ($opts['with_env']) {
                $report['env'] = self::applyEnv($stage);
            }

            if ($opts['with_database'] && $opts['migrate']) {
                $report['migrated'] = self::runMigrations();
            }

            $report['ok'] = true;
            self::log('restore', [
                'source'    => basename($source),
                'database'  => $report['database'],
                'rows'      => $report['checked']['rows'],
                'uploads'   => $report['uploads']['written'],
                'replace'   => (bool) $opts['replace_uploads'],
                'env'       => $report['env'],
                'safety'    => $report['safety']['name'] ?? null,
                'warnings'  => count($report['warnings']),
            ]);
            return $report;
        } catch (Throwable $e) {
            self::log('restore-failed', ['source' => basename($source), 'error' => $e->getMessage(),
                                         'safety' => $report['safety']['name'] ?? null]);
            $report['ok'] = false;
            $report['error'] = $e->getMessage();
            return $report;
        } finally {
            self::rmDir($stage);
        }
    }

    /**
     * 把备份库写进「正在被使用」的库文件。
     * 不删不替换文件：本请求的 PDO 连接还开着（Windows 上就会锁死重命名），
     * 而且直接换文件会让 -wal/-shm 与新主文件对不上。SQLite3::backup 反向跑，
     * 由 SQLite 自己按页覆写并保证事务性，失败时库还是原样。
     */
    private static function applyDatabase(string $srcFile): string
    {
        $live = self::dbFile();
        if (class_exists('SQLite3')) {
            $from = @new SQLite3($srcFile, SQLITE3_OPEN_READONLY);
            $to   = @new SQLite3($live, SQLITE3_OPEN_READWRITE);
            $ok = $from->backup($to, 'main', 'main');
            $to->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            $from = null;
            $to = null;
            if ($ok === false) {
                throw new RuntimeException('SQLite3::backup 未能写入数据库（并发占用？稍后重试，或先短暂停站再恢复）。');
            }
            return 'SQLite3::backup（原地覆写）';
        }

        // 没有 ext-sqlite3 时只能替换文件：先把 WAL 落盘，再尝试 copy。
        try {
            $pdo = new PDO('sqlite:' . $live);
            $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            $pdo = null;
        } catch (Throwable $e) {
            // checkpoint 失败不致命，继续尝试覆盖
        }
        if (!@copy($srcFile, $live)) {
            throw new RuntimeException('覆写数据库失败（文件被占用）。这台 PHP 没有 ext-sqlite3，'
                . '请停掉 Web 服务后用命令恢复：copy / php 覆盖 ' . $live);
        }
        foreach (['-wal', '-shm'] as $extra) {
            @unlink($live . $extra);            // 旧 WAL 属于旧库，留着会对不上
        }
        return 'copy（已 checkpoint 并清掉旧 -wal/-shm）';
    }

    private static function wipeRememberTokens(): string
    {
        try {
            $pdo = new PDO('sqlite:' . self::dbFile(), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $n = (int) $pdo->exec('DELETE FROM remember_tokens');
            $pdo = null;
            return 'cleared';
        } catch (Throwable $e) {
            return '跳过（' . textClip($e->getMessage(), 60) . '）';
        }
    }

    /**
     * 附件恢复。默认 merge：只新增/覆盖同名文件，绝不删现有文件 —— 因为附件在库里
     * 只存文件名，误删一个就是永久丢失（没有第二份真相可回填）。
     * replace 需要显式勾选，且只删「本次包里没带」的文件。
     */
    private static function applyUploads(string $stage, array $inspection, bool $replace): array
    {
        $dir = self::attachmentDir();
        if (!self::ensureDir($dir)) {
            throw new RuntimeException('无法写入附件目录：' . $dir);
        }
        $out = ['written' => 0, 'skipped' => 0, 'removed' => 0, 'bytes' => 0];
        $fromArchive = [];

        foreach ($inspection['uploads']['files'] as $f) {
            $name = $f['name'];
            if (!self::isSafeAttachmentName($name)) {
                $out['skipped']++;
                continue;
            }
            $src = $stage . '/' . self::UPLOAD_DIR_IN . $name;
            if (!is_file($src)) {
                $out['skipped']++;
                continue;
            }
            $fromArchive[$name] = true;
            // 只信 manifest 声明的哈希（导出时算的）。与刚暂存的文件自己对己没有意义，
            // 但能拦到“包被人事后改过/下载不完整”这两类真问题。
            if (!empty($f['expected']) && !hash_equals((string) $f['expected'], (string) hash_file('sha256', $src))) {
                throw new RuntimeException('附件校验失败（与 manifest 不符）：' . $name);
            }
            if (!@copy($src, $dir . '/' . $name)) {
                throw new RuntimeException('无法写入附件：' . $name);
            }
            $out['written']++;
            $out['bytes'] += (int) filesize($dir . '/' . $name);
        }

        if ($replace) {
            foreach (self::attachmentFiles() as $existing) {
                if (isset($fromArchive[$existing['name']])) {
                    continue;                   // 包里带回来的那份，不算“删”
                }
                if (@unlink($dir . '/' . $existing['name'])) {
                    $out['removed']++;
                } else {
                    $out['skipped']++;
                }
            }
        }
        return $out;
    }

    /**
     * .env 默认不覆盖（它是部署期配置，且里面有密钥）。勾选后也只是「另存为 .env.restored-时间戳」，
     * 让管理员自己比对再替换 —— 一键导入不应该静默改掉连接串和开关。
     */
    private static function applyEnv(string $stage): string
    {
        $src = $stage . '/' . self::ENV_ENTRY;
        if (!is_file($src)) {
            return '包里不含 .env';
        }
        $dest = BASE_PATH . '/.env.restored-' . date('Ymd-His');
        if (!@copy($src, $dest)) {
            return '写入失败';
        }
        return '已另存为 ' . basename($dest) . '（原 .env 未改，确认后再手工替换）';
    }

    /** 备份可能来自旧版本：跑一次官方迁移器补结构（幂等自愈，见 database/migrate.php）。 */
    private static function runMigrations(): array
    {
        if (!function_exists('exec') || self::execDisabled()) {
            return ['ok' => null, 'note' => 'exec 被禁用，未自动补结构。请手工执行：php database/migrate.php'];
        }
        $script = BASE_PATH . '/database/migrate.php';
        if (!is_file($script)) {
            return ['ok' => null, 'note' => '找不到 database/migrate.php，未补结构。'];
        }
        // --no-demo 是必须的：migrate.php 默认会灌演示数据，而恢复一份真备份
        // 之后塞进“演示客户 1、演示商机 2”是灾难（schema.sql 里是 INSERT OR IGNORE，
        // 只要 id 不撞车就会真的多出行来）。本步只需要“补结构”，不需要种子。
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' --no-demo'
             . ' --db=' . escapeshellarg(self::dbFile()) . ' 2>&1';
        $lines = [];
        $code = 0;
        @exec($cmd, $lines, $code);
        $text = implode("\n", $lines);
        return [
            'ok'    => $code === 0,
            'code'  => $code,
            'note'  => $code === 0 ? '结构已按当前代码补齐（--no-demo，不会注入演示数据）。'
                                   : '自动补结构失败，请手工执行 php database/migrate.php --no-demo 后复查。',
            'log'   => textClip(preg_replace('/\s+/', ' ', $text) ?? '', 240),
        ];
    }

    // =====================================================================
    // 操作留痕
    // =====================================================================

    /**
     * 谁在什么时候导出/恢复了什么。写在备份目录里的一个 jsonl（不入库：
     * 恢复本身会把库里的痕迹一起换掉，放库外的文件才留得住）。
     */
    public static function log(string $action, array $detail = []): void
    {
        $file = self::backupDir() . '/history.jsonl';
        $user = currentUser();
        $row = array_merge([
            'at'    => appNow('Y-m-d H:i:s'),
            'action' => $action,
            'user'  => $user['name'] ?? '—',
            'role'  => $user['role'] ?? '',
            'ip'    => (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'),
        ], $detail);

        $lines = [];
        if (is_file($file)) {
            $lines = array_slice(array_filter(explode("\n", (string) file_get_contents($file))), -499);
        }
        $lines[] = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        @file_put_contents($file, implode("\n", $lines) . "\n", LOCK_EX);
    }

    /** @return array<int,array<string,mixed>> 最近若干条（新的在前） */
    public static function recentLog(int $limit = 12): array
    {
        $file = self::backupDir() . '/history.jsonl';
        if (!is_file($file)) {
            return [];
        }
        $lines = array_filter(explode("\n", (string) file_get_contents($file)));
        $rows = [];
        foreach (array_slice($lines, -$limit) as $l) {
            $d = json_decode($l, true);
            if (is_array($d)) {
                $rows[] = $d;
            }
        }
        return array_reverse($rows);
    }

    public static function clearLog(): void
    {
        @unlink(self::backupDir() . '/history.jsonl');
    }

    // =====================================================================
    // 小工具
    // =====================================================================

    public static function humanSize($bytes): string
    {
        $bytes = (float) $bytes;
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $u) {
            if ($bytes < 1024 || $u === 'TB') {
                return ($u === 'B' ? (int) $bytes : round($bytes, 1)) . ' ' . $u;
            }
            $bytes /= 1024;
        }
        return '';
    }

    /** 供页面显示的库文件伴随件（-wal / -shm）。 */
    public static function sidecarFiles(): array
    {
        $out = [];
        foreach (['-wal', '-shm', '-journal'] as $s) {
            $f = self::dbFile() . $s;
            if (is_file($f)) {
                $out[] = ['name' => basename($f), 'size' => (int) filesize($f)];
            }
        }
        return $out;
    }
}
