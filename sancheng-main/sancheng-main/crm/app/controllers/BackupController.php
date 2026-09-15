<?php

/**
 * 备份与恢复（仅管理员）
 *
 * 页面上四件事，对应 Backup 里的四组静态方法：
 *   一键导出   → 浏览器下载 zip（临时包，发完就删，不进快照列表）
 *   生成快照   → 同内容落一份到 database/backups/，可用于「本机回滚」与异地 rsync
 *   检查备份包 → 只校验不落手（把恢复前该看见的数字先给管理员看一遍）
 *   一键导入   → 校验通过后先自动打 pre-restore 安全快照，再覆写库 + 放回附件
 *
 * 为什么这些路由全是 admin：备份包 = 全库明文 + 全部上传文件 + 可选 .env（里面有 AI Key），
 * 而导入更是直接改写整站数据。sales 账号能看到客户数据，但不该有能力把整站换掉。
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
class BackupController extends Controller
{
    /** 确认词：覆盖式恢复必须手打这四个字，防误点（也防自动化脚本一句话跑掉）。 */
    private const CONFIRM_WORD = '覆盖导入';

    /**
     * resolveSource() 说清楚“为什么没拿到可用的包”，比笼统的“未收到来源”有用得多
     * （超限与格式不对是两件完全不同的事，处理方法也不同）。
     */
    private ?string $sourceError = null;

    /** 快照保留份数上限（页面可填，这里兜底）。 */
    private const MAX_KEEP = 60;

    public function index(): void
    {
        $this->requireRole('admin', '/');
        $this->sweepStale();
        $last = $_SESSION['backup_last'] ?? null;
        unset($_SESSION['backup_last']);

        $this->view('backup/index', [
            'stats'    => Backup::stats(),
            'caps'     => Backup::capabilities(),
            'limits'   => Backup::uploadLimits(),
            'sidecars' => Backup::sidecarFiles(),
            'snapshots' => Backup::listSnapshots(),
            'log'      => Backup::recentLog(10),
            'last'     => $last,
            'csrf'     => $this->csrfToken(),
            'confirmWord' => self::CONFIRM_WORD,
        ]);
    }

    /**
     * 一键导出：直接把包发到浏览器，不在服务器上留副本（临时包发送完就删）。
     * 写成 POST + CSRF：虽然导出不改数据，但它把全库（含 AI Key）打成一个包递出去，
     * 不能让任意页面一个自动提交就把管理员会话里的这份东西提走。
     */
    public function export(): void
    {
        $this->requireRole('admin', '/backup');
        $this->verifyCsrf();
        $opts = $this->readOptions();
        $file = null;

        try {
            set_time_limit(0);
            $res = Backup::buildForDownload($opts);
            $file = $res['file'];
            $this->logAction('export', ['file' => $res['download_name'], 'size' => $res['size'],
                                        'database' => $opts['with_database'], 'uploads' => $opts['with_uploads'],
                                        'env' => $opts['with_env'], 'writer' => $res['writer']]);
            $this->sendFile($file, $res['download_name']);
        } catch (Throwable $e) {
            if ($file === null) {
                // 还没生成东西 → 正常回页面报错
                $this->setFlash('error', '导出失败：' . $e->getMessage());
                $this->redirect('/backup');
                return;
            }
            // 已经开始发送了，只能把错误写在正文里（此时 header 已不可改）
            $this->logAction('export-failed', ['error' => $e->getMessage()]);
            echo "\n\n<!-- 导出中断：" . htmlspecialchars($e->getMessage(), ENT_QUOTES) . ' -->';
        } finally {
            if (is_string($file) && is_file($file)) {
                @unlink($file);
            }
        }
        exit;
    }

    /** 生成服务器快照（留在 database/backups/，可按份数保留旧的）。 */
    public function snapshot(): void
    {
        $this->requireRole('admin', '/backup');
        $this->verifyCsrf();
        $opts = $this->readOptions();

        try {
            set_time_limit(0);
            $res = Backup::createArchive(null, $opts);
            $keep = (int) ($_POST['keep'] ?? 10);
            $removed = Backup::prune(max(0, min(self::MAX_KEEP, $keep)));
            $this->logAction('snapshot', ['file' => basename($res['file']), 'size' => $res['size'],
                                          'database' => $opts['with_database'], 'uploads' => $opts['with_uploads'],
                                          'env' => $opts['with_env'], 'writer' => $res['writer']]);
            $msg = '快照已生成：' . basename($res['file']) . '（' . Backup::humanSize($res['size']) . '）';
            if ($removed) {
                $msg .= '，按保留 ' . $keep . ' 份清理了 ' . count($removed) . ' 个旧包。';
            }
            $this->setFlash('success', $msg);
        } catch (Throwable $e) {
            $this->logAction('snapshot-failed', ['error' => $e->getMessage()]);
            $this->setFlash('error', '生成快照失败：' . $e->getMessage());
        }
        $this->redirect('/backup');
    }

    /** 下载 / 删除单个快照都按文件名校验，路径永远不接受用户输入。 */
    public function download(): void
    {
        $this->requireRole('admin', '/');
        $this->verifyTokenInQuery();
        $path = Backup::resolveSnapshot((string) ($_GET['name'] ?? ''));
        if ($path === null) {
            $this->setFlash('error', '找不到该快照（文件名不合法或已被清理）。');
            $this->redirect('/backup');
        }
        $this->logAction('download', ['file' => basename($path)]);
        $this->sendFile($path, basename($path));
        exit;
    }

    public function deleteSnapshot(): void
    {
        $this->requireRole('admin', '/backup');
        $this->verifyCsrf();
        $name = (string) ($_POST['name'] ?? '');
        $path = Backup::resolveSnapshot($name);
        if ($path === null) {
            $this->setFlash('error', '快照名不合法，未删除任何文件。');
        } elseif (Backup::deleteSnapshot($name)) {
            $this->logAction('delete', ['file' => basename($path)]);
            $this->setFlash('success', '已删除快照 ' . basename($path) . '。');
        } else {
            $this->setFlash('error', '删除失败（文件被占用或权限不足）。');
        }
        $this->redirect('/backup');
    }

    /** 只检查不恢复：把包里的行数/附件数/版本差先摊开看。 */
    public function verify(): void
    {
        $this->requireRole('admin', '/backup');
        $this->guardPostSize();
        $this->verifyCsrf();

        $msg = $this->sourceError ?? '未收到可用的备份来源。';
        $ok = false;
        [$source, $tmpFile] = $this->resolveSource();

        try {
            if ($source !== null) {
                // 只带附件的包也是合法备份：要不要库，看表单里勾了什么（与 import 同一判断）
                $ins = Backup::inspect($source, isset($_POST['with_database']));
                $msg = $this->summaryOf($ins);
                $ok = true;
                Backup::release($ins);
                Backup::log('verify', ['source' => $this->sourceLabel(), 'rows' => $ins['database']['rows']]);
            }
        } catch (Throwable $e) {
            $msg = '检查未通过：' . $e->getMessage();
        } finally {
            // 把出口收在一处：redirect() 里是 exit，在 try/catch 里直接 return 会跳过
            // 上面的 finally，上传的备份包就会在 database/backups/ 里多躺一小时。
            $this->finishSource($tmpFile);
        }
        $this->backWith($msg, $ok);
    }

    /**
     * 一键导入。顺序不可换：确认词（fail fast，不动文件）→ 把来源落到可控位置
     * → Backup::inspect 全套校验 → 才允许覆写生产库。任一步失败都不会留下半个状态。
     */
    public function import(): void
    {
        $this->requireRole('admin', '/backup');
        $this->guardPostSize();
        $this->verifyCsrf();

        $confirm = trim((string) ($_POST['confirm_text'] ?? ''));
        if (!hash_equals(self::CONFIRM_WORD, $confirm)) {
            $this->setFlash('error', '未确认：请在框里原样输入「' . self::CONFIRM_WORD . '」，这一步会覆盖当前全部业务数据。');
            $this->redirect('/backup');
            return;
        }

        $opts = $this->readOptions();

        $msg = $this->sourceError ?? '未收到可用的备份来源。';
        $ok = false;
        [$source, $tmpFile] = $this->resolveSource();
        if ($source !== null && !$opts['with_database'] && !$opts['with_uploads']) {
            $msg = '至少要选一项恢复内容（数据库 / 附件）。';
            $source = null;
        }

        if ($source !== null) {
            try {
                set_time_limit(0);
                $report = Backup::restore($source, [
                    'with_database'   => $opts['with_database'],
                    'with_uploads'    => $opts['with_uploads'],
                    'replace_uploads' => $opts['replace_uploads'],
                    'with_env'        => $opts['with_env'],
                    'migrate'         => $opts['migrate'],
                ]);
                $this->logAction($report['ok'] ? 'import' : 'import-failed', [
                    'source'   => basename($source),
                    'database' => $report['database'] ?? null,
                    'rows'     => $report['checked']['rows'] ?? null,
                    'uploads'  => $report['uploads']['written'] ?? null,
                    'replace'  => $opts['replace_uploads'],
                    'safety'   => $report['safety']['name'] ?? null,
                    'error'    => $report['error'] ?? null,
                ]);
                $_SESSION['backup_last'] = $report;

                if ($report['ok']) {
                    $msg = $this->restoreMessage($report);
                    $ok = true;
                } else {
                    // 只有真没动过库才能说“未改写”：库已覆写、附件那一步才失败的情况，
                    // 必须把管理员引向安全快照，而不是让他以为一切如旧。
                    $untouched = ($report['database'] ?? 'skipped') === 'skipped';
                    $msg = '恢复未完成：' . $report['error'] . ' ' . ($untouched
                        ? '未改动任何生产数据。'
                        : '（库已被覆写，需要反悔就用安全快照 ' . ($report['safety']['name'] ?? '—') . ' 再导一次。）');
                }
            } catch (Throwable $e) {
                $this->logAction('import-failed', ['error' => $e->getMessage()]);
                $msg = '恢复失败：' . $e->getMessage();
            } finally {
                // 同上：出口只留最后这一行，否则 finally 清不掉上传来的临时包。
                $this->finishSource($tmpFile);
            }
        } else {
            $this->finishSource($tmpFile);
        }
        $this->backWith($msg, $ok);
    }

    /** 清空操作记录（不改业务数据，只是本页的审计文件）。 */
    public function clearLog(): void
    {
        $this->requireRole('admin', '/backup');
        $this->verifyCsrf();
        Backup::clearLog();
        $this->setFlash('success', '操作记录已清空。');
        $this->redirect('/backup');
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------

    /**
     * 表单范围选项。复选框以“有没有这个键”为准（没勾就是不要）；
     * migrate 用 no_migrate 反向表达，因为恢复默认就该补结构，不让表单默默关掉它。
     */
    private function readOptions(): array
    {
        return [
            'with_database'   => isset($_POST['with_database']),
            'with_uploads'    => isset($_POST['with_uploads']),
            'with_env'        => isset($_POST['with_env']),
            'replace_uploads' => isset($_POST['replace_uploads']),
            'migrate'         => !isset($_POST['no_migrate']),
            'note'            => textClip(trim((string) ($_POST['note'] ?? '')), 120),
        ];
    }

    /**
     * 来源有两种：上传的包（受 upload_max_filesize 限制）/ 服务器上已有的快照
     * （不受限制，也是大附件站唯一的可行路径）。返回 [绝对路径, 需在结束时删除的临时文件]。
     */
    private function resolveSource(): array
    {
        $kind = (string) ($_POST['source_kind'] ?? 'upload');

        if ($kind === 'server') {
            $path = Backup::resolveSnapshot((string) ($_POST['snapshot'] ?? ''));
            if ($path === null) {
                return [null, null];
            }
            return [$path, null];
        }

        $file = $_FILES['backup_file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            $this->uploadError((int) $file['error']);
            return [null, null];
        }
        $name = (string) ($file['name'] ?? 'backup.zip');
        if (preg_match('~\.(zip|sqlite|sqlite3|db|db3)$~i', $name) !== 1) {
            $this->uploadError(0, '只接受本系统导出的 .zip 备份包，或裸数据库文件（.sqlite/.db）。');
            return [null, null];
        }
        // 上传文件按后缀改名再落地：后面所有判断都基于它自己的扩展名，不接受“名字叫 x.zip 的任意文件”
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $tmp = Backup::backupDir() . '/.upload-' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!@move_uploaded_file($file['tmp_name'], $tmp)) {
            $this->uploadError(0, '临时文件写入失败（检查 ' . Backup::backupDir() . ' 是否可写）。');
            return [null, null];
        }
        return [$tmp, $tmp];
    }

    private function finishSource(?string $tmpFile): void
    {
        if (is_string($tmpFile) && is_file($tmpFile)) {
            @unlink($tmpFile);
        }
    }

    private function uploadError(int $code, ?string $custom = null): void
    {
        $limits = Backup::uploadLimits();
        if ($custom !== null) {
            $this->sourceError = $custom;
            return;
        }
        $map = [
            UPLOAD_ERR_INI_SIZE   => '文件超过服务器 upload_max_filesize（当前 ' . $limits['upload_max_filesize'] . '）。',
            UPLOAD_ERR_FORM_SIZE  => '文件超过表单 MAX_FILE_SIZE。',
            UPLOAD_ERR_PARTIAL    => '文件只上传了一部分（网络或超时）。',
            UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录（php upload_tmp_dir）。',
            UPLOAD_ERR_CANT_WRITE => '服务器写临时目录失败（磁盘满/权限）。',
            UPLOAD_ERR_EXTENSION  => '上传被 PHP 扩展中断。',
        ];
        $msg = $map[$code] ?? ('上传失败（错误码 ' . $code . '）。');
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            $msg .= '两种解法：① 把 php.ini 的 upload_max_filesize / post_max_size 调大（当前 '
                  . $limits['upload_max_filesize'] . ' / ' . $limits['post_max_size'] . '）后重启 PHP；'
                  . '② 不传了 —— 在旧机器「生成快照」，把文件拷到新机器的 database/backups/ 下，选「从服务器快照恢复」。';
        }
        $this->sourceError = $msg;
    }

    /** 一个人类可读的来源描述（写审计用，避免把整条绝对路径塞进日志）。 */
    private function sourceLabel(): string
    {
        if (($_POST['source_kind'] ?? 'upload') === 'server') {
            return 'server:' . basename((string) ($_POST['snapshot'] ?? ''));
        }
        return 'upload:' . basename((string) (($_FILES['backup_file']['name'] ?? '')));
    }

    /**
     * 扫掉异常中断留下的垃圾（.tmp-* 目录、.download-*.zip、.upload-*.ext）。
     * 只动一小时前的：正在解包的大文件不该被下一个请求删掉。
     * 放在 index() 里顺带跑，不另开 cron。
     */
    private function sweepStale(): void
    {
        try {
            Backup::sweepStale(3600);
        } catch (Throwable $e) {
            // 扫垃圾失败不该影响备份页打开
        }
    }

    private function backWith(string $msg, bool $ok = false): void
    {
        $this->setFlash($ok ? 'success' : 'error', $msg);
        $this->redirect('/backup');
    }

    /** 检查通过后的那句话：把管理员真正要核对的数字念出来。 */
    private function summaryOf(array $ins): string
    {
        $db = $ins['database'];
        $msg = '备份包可用：' . (int) $db['tables'] . ' 张表 / ' . (int) $db['rows'] . ' 行，'
             . '附件 ' . (int) $ins['uploads']['count'] . ' 个（' . Backup::humanSize($ins['uploads']['bytes']) . '），'
             . '库 ' . Backup::humanSize($db['size']) . '，导出于 ' . $db['exported_at'];
        if ($db['source_version'] !== '') {
            $msg .= '（v' . $db['source_version'] . '）';
        }
        $msg .= '。' . textClip($ins['warnings'] ? '提醒：' . implode(' ', $ins['warnings']) : '没有发现异常。', 400);
        return $msg;
    }

    private function restoreMessage(array $r): string
    {
        $parts = [];
        if ($r['database'] !== 'skipped') {
            $parts[] = '数据库已恢复（' . (int) $r['checked']['rows'] . ' 行）';
        }
        if (($r['uploads']['written'] ?? 0) > 0) {
            $parts[] = '附件 ' . (int) $r['uploads']['written'] . ' 个';
        }
        if (($r['uploads']['removed'] ?? 0) > 0) {
            $parts[] = '并按整目录替换清掉了 ' . (int) $r['uploads']['removed'] . ' 个包里没有的旧文件';
        }
        $msg = '导入完成：' . implode('，', $parts) . '。';
        if (!empty($r['safety']['name'])) {
            $msg .= '恢复前的库已存为 ' . $r['safety']['name'] . '，要反悔就用它再导一次。';
        }
        if (is_array($r['migrated'] ?? null) && !empty($r['migrated']['note'])) {
            $msg .= ' ' . $r['migrated']['note'];
        }
        if ($r['warnings']) {
            $msg .= ' 注意：' . implode(' ', array_slice($r['warnings'], 0, 3));
        }
        return $msg;
    }

    /**
     * 超过 post_max_size 的 POST 会被 PHP 整个丢弃（$_POST/$_FILES 全空），
     * 那时 CSRF 一定失败、只能报“表单无效”，管理员完全看不出是体积问题。
     * 所以在验 CSRF 之前先把这一种情况说清楚。注：这里不改任何数据，
     * 未登录者最多给自己会话里丢一句提示，够不着备份。
     */
    private function guardPostSize(): void
    {
        $len = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST || $_FILES || $len <= 0) {
            return;
        }
        $limits = Backup::uploadLimits();
        $this->setFlash('error', '提交被服务器丢弃：POST 内容（约 ' . Backup::humanSize($len)
            . '）超过 post_max_size=' . $limits['post_max_size'] . '。'
            . '要么调大 php.ini 的 post_max_size / upload_max_filesize 并重启 PHP，'
            . '要么走「从服务器快照恢复」：把包拷到 database/backups/ 下（文件名保持导出生成的那个），不经过上传。');
        $this->redirect('/backup');
    }

    /** 记一笔操作日志（Backup::log 写的是文件，不依赖会话与库）。 */
    private function logAction(string $action, array $detail): void
    {
        Backup::log($action, $detail);
    }

    /** 下载链接带 ?token= ：GET 也验一下，避免被人用 <img> 之类的把式远程触发下载。 */
    private function verifyTokenInQuery(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(419);
            die('链接无效（CSRF 校验失败），请返回备份页重新点击。');
        }
    }

    /** 发送文件并结束请求：分块读，不把整个包压进内存。 */
    private function sendFile(string $path, string $downloadName): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $safe = preg_replace('/[^\w.\-]+/', '-', $downloadName) ?? 'backup.zip';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $safe . '"');
        header('Content-Length: ' . (int) filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');

        $fh = fopen($path, 'rb');
        if ($fh === false) {
            echo '无法读取备份文件。';
            return;
        }
        while (!feof($fh)) {
            $chunk = fread($fh, 262144);
            if ($chunk === false) {
                break;
            }
            echo $chunk;
            flush();
        }
        fclose($fh);
    }
}
