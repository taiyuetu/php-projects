<?php
/**
 * 备份与恢复（管理员）。
 *
 * 页面只负责把事实摊开：现在有多少数据、这台 PHP 能用什么方式打包、
 * 包里到底有什么。真正的判断（校验、覆盖、回滚快照）全在 Backup 模型里，
 * 所以这个页面即使被人改了字，也不会改到行为。
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
$s = $stats;
$size = fn($b) => Backup::humanSize((int) $b);
$fmtTime = static fn(int $t): string => date('Y-m-d H:i:s', $t);
/** 快照“含有什么”：优先看 manifest，裸库文件单独说明 */
$badges = static function (array $m): string {
    if (!empty($m['bare_database'])) {
        return '<span class="badge bg-secondary-subtle text-secondary">仅数据库</span>';
    }
    if (!empty($m['unreadable'])) {
        return '<span class="badge bg-danger-subtle text-danger">包不可读</span>';
    }
    $inc = (array) ($m['includes'] ?? []);
    $out = [];
    if (!empty($inc['database'])) {
        $rows = is_array($m['database']['tables'] ?? null) ? array_sum($m['database']['tables']) : null;
        $out[] = '<span class="badge bg-primary-subtle text-primary">数据库' . ($rows !== null ? ' ' . (int) $rows . ' 行' : '') . '</span>';
    }
    if (!empty($inc['uploads'])) {
        $out[] = '<span class="badge bg-info-subtle text-info">附件 ' . (int) ($m['uploads']['count'] ?? 0) . '</span>';
    }
    if (!empty($inc['env'])) {
        $out[] = '<span class="badge bg-warning-subtle text-warning">含 .env（有密钥）</span>';
    }
    if (!empty($m['app_version'])) {
        $out[] = '<span class="badge bg-secondary-subtle text-secondary">v' . e($m['app_version']) . '</span>';
    }
    return $out ? implode(' ', $out) : '<span class="badge bg-secondary-subtle text-secondary">未知</span>';
};
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0"><i class="bi bi-database-check text-primary"></i> 备份与恢复</h3>
    <span class="text-muted small">v<?= e(APP_VERSION) ?> · <?= e($s['generated_at']) ?></span>
</div>

<div class="alert alert-warning">
    <i class="bi bi-shield-exclamation"></i>
    备份包 = <strong>整库明文</strong>（客户 / 订单 / 含 AI 里存的 API Key）+ 全部上传文件。
    它必须和真数据库同等对待：<strong>放到另一台机器或另一块盘才算备份</strong>，落盘加密、限权、定期销毁。
    命令行与 cron 方案见 <code>DEPLOY.md</code> 第 7 节。
</div>

<!-- ================= 数据现状 ================= -->
<div class="card card-table mb-4">
    <div class="card-header bg-white"><i class="bi bi-clipboard-data"></i> 现在要备份的是什么</div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-lg-4">
                <h6 class="text-muted mb-3">业务数据（SQLite 单文件）</h6>
                <ul class="list-unstyled small mb-2">
                    <li class="d-flex justify-content-between"><span>文件</span>
                        <code class="text-break"><?= e($s['db_path']) ?></code></li>
                    <li class="d-flex justify-content-between"><span>体积</span><strong><?= $size($s['db_bytes']) ?></strong></li>
                    <li class="d-flex justify-content-between"><span>表 / 总行数</span>
                        <strong><?= (int) $s['tables'] ?> / <?= (int) $s['total_rows'] ?></strong></li>
                    <?php foreach ($sidecars as $side): ?>
                        <li class="d-flex justify-content-between text-muted">
                            <span>伴随文件</span><span><?= e($side['name']) ?> · <?= $size($side['size']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($s['wal_present']): ?>
                    <div class="alert alert-info py-1 px-2 small mb-2">
                        存在未合并的 <code>-wal</code>：导出走一致性快照，不会漏掉里面已提交的事务。
                    </div>
                <?php endif; ?>
                <details>
                    <summary class="small text-muted">各表行数（对账用）</summary>
                    <div class="mt-2 d-flex flex-wrap gap-1">
                        <?php foreach ($s['counts'] as $t => $n): ?>
                            <span class="badge bg-light text-dark border"><?= e($t) ?> <?= (int) $n ?></span>
                        <?php endforeach; ?>
                    </div>
                </details>
            </div>

            <div class="col-lg-4">
                <h6 class="text-muted mb-3">附件目录</h6>
                <ul class="list-unstyled small mb-2">
                    <li class="d-flex justify-content-between"><span>目录</span>
                        <code class="text-break"><?= e($s['attach_dir']) ?></code></li>
                    <li class="d-flex justify-content-between"><span>文件数</span><strong><?= (int) $s['attachments'] ?></strong></li>
                    <li class="d-flex justify-content-between"><span>总体积</span><strong><?= $size($s['attach_bytes']) ?></strong></li>
                    <li class="d-flex justify-content-between"><span>已有快照</span>
                        <strong><?= (int) $s['backup_count'] ?> 份</strong>
                        <input type="hidden" value="<?= e($s['backup_dir']) ?>"></li>
                </ul>
                <?php $o = $s['orphans']; ?>
                <?php if (!empty($o['error'])): ?>
                    <div class="alert alert-secondary py-1 px-2 small mb-0">对账未执行：<?= e((string) $o['error']) ?></div>
                <?php elseif (!($o['ok'] ?? true)): ?>
                    <div class="alert alert-warning py-1 px-2 small mb-0">
                        <strong>库与目录不一致</strong>（备份仍会照原样打包，但这通常意味着目录被动过）：
                        <?php if (($o['missing_count'] ?? 0) > 0): ?>
                            <div>库里有记录、磁盘上没了：<?= (int) $o['missing_count'] ?> 个 ——
                                <?= e(implode(', ', (array) ($o['missing'] ?? []))) ?></div>
                        <?php endif; ?>
                        <?php if (($o['unused_count'] ?? 0) > 0): ?>
                            <div>磁盘上有、库里没引用：<?= (int) $o['unused_count'] ?> 个 ——
                                <?= e(implode(', ', (array) ($o['unused'] ?? []))) ?></div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="small text-success"><i class="bi bi-check2-circle"></i> 库里的附件记录与目录一一对应。</div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <h6 class="text-muted mb-3">这台服务器的能力</h6>
                <table class="table table-sm small mb-2">
                    <tbody>
                    <?php foreach ($caps as $c): ?>
                        <tr>
                            <td>
                                <?php if ($c['ok']): ?>
                                    <i class="bi bi-check-lg text-success"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-lg text-danger"></i>
                                <?php endif; ?>
                                <?= e($c['label']) ?>
                                <?php if (!empty($c['why'])): ?>
                                    <div class="text-muted"><?= e($c['why']) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="small text-muted">
                    上传限制 <code>upload_max_filesize=<?= e((string) $limits['upload_max_filesize']) ?></code>
                    / <code>post_max_size=<?= e((string) $limits['post_max_size']) ?></code>。
                    包比这大就别走上传，用下面的「从服务器快照恢复」。
                </div>
                <?php if (empty($limits['exec_available'])): ?>
                    <div class="small text-danger mt-1">
                        <i class="bi bi-exclamation-triangle"></i> 这台 PHP 禁用了 <code>exec()</code>：
                        导入后无法自动补结构，旧版本备份恢复完请手工跑一次 <code>php database/migrate.php</code>。
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($last && !empty($last['ok'])): ?>
    <!-- 上一次导入的明细：flash 只有一句话，这里留下可对账的数字 -->
    <div class="card card-table mb-4 border-success">
        <div class="card-header bg-white text-success">
            <i class="bi bi-check-circle"></i> 本次导入结果
            <span class="text-muted small fw-normal ms-2"><?= e((string) $last['at']) ?></span>
        </div>
        <div class="card-body small">
            <div class="row g-3">
                <div class="col-md-3"><div class="text-muted">恢复内容</div>
                    <?= (int) $last['checked']['rows'] ?> 行 / <?= (int) $last['checked']['tables'] ?> 表，
                    附件 <?= (int) $last['uploads']['written'] ?> 个<?= !empty($last['uploads']['removed']) ? '，另删除 ' . (int) $last['uploads']['removed'] . ' 个包里没有的旧文件' : '' ?>
                </div>
                <div class="col-md-3"><div class="text-muted">写入方式</div><code><?= e((string) $last['database']) ?></code></div>
                <div class="col-md-3"><div class="text-muted">回退快照</div>
                    <?php if (!empty($last['safety']['name'])): ?>
                        <code><?= e($last['safety']['name']) ?></code>（<?= $size($last['safety']['size']) ?>）
                    <?php else: ?>—<?php endif; ?>
                </div>
                <div class="col-md-3"><div class="text-muted">结构升级 / 记住登录</div>
                    <?= e(is_array($last['migrated'] ?? null) ? (string) $last['migrated']['note'] : '未执行') ?>
                    <div class="text-muted">remember_tokens：<?= e((string) $last['tokens']) ?></div>
                </div>
            </div>
            <?php if (!empty($last['warnings'])): ?>
                <div class="alert alert-warning mt-3 mb-0 py-2 small">
                    <?php foreach ($last['warnings'] as $w): ?><div>· <?= e((string) $w) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- ================= 一键导出 ================= -->
    <div class="col-lg-6">
        <div class="card card-table h-100">
            <div class="card-header bg-white"><i class="bi bi-download"></i> 一键导出（下载到本机）</div>
            <form method="POST" action="<?= url('/backup/export') ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div class="card-body">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="with_database" id="ex-db" value="1" checked>
                        <label class="form-check-label" for="ex-db">数据库 <span class="text-muted small">（<?= $size($s['db_bytes']) ?>，一致性快照）</span></label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="with_uploads" id="ex-up" value="1" checked>
                        <label class="form-check-label" for="ex-up">附件目录 <span class="text-muted small">（<?= (int) $s['attachments'] ?> 个 / <?= $size($s['attach_bytes']) ?>）</span></label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="with_env" id="ex-env" value="1">
                        <label class="form-check-label" for="ex-env">
                            含 <code>.env</code> <span class="text-danger small">（里面有 AI API Key，默认不勾；只在你要连配置一起搬走时才勾）</span>
                        </label>
                    </div>
                    <div class="mt-3">
                        <label class="form-label small">备注（写进 manifest，便于日后认出这份包）</label>
                        <input type="text" name="note" class="form-control form-control-sm" maxlength="120"
                               placeholder="如：搬到新服务器前的最后一份">
                    </div>
                    <div class="small text-muted mt-2">
                        打包在服务器上临时生成、发完即删，所以<strong>不要在下载中途关页面</strong>；
                        要留在服务器上的副本请用右边的「生成快照」。
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-file-earmark-zip"></i> 导出备份包
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= 服务器快照 ================= -->
    <div class="col-lg-6">
        <div class="card card-table h-100">
            <div class="card-header bg-white"><i class="bi bi-archive"></i> 服务器快照（留在 <code>database/backups/</code>）</div>
            <form method="POST" action="<?= url('/backup/snapshot') ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <span class="small">
                            <label class="form-check-label">内容</label>
                            <label class="form-check form-check-inline m-0">
                                <input class="form-check-input" type="checkbox" name="with_database" value="1" checked id="sn-db">
                                <label class="form-check-label small" for="sn-db">数据库</label>
                            </label>
                            <label class="form-check form-check-inline m-0">
                                <input class="form-check-input" type="checkbox" name="with_uploads" value="1" checked id="sn-up">
                                <label class="form-check-label small" for="sn-up">附件</label>
                            </label>
                            <label class="form-check form-check-inline m-0">
                                <input class="form-check-input" type="checkbox" name="with_env" value="1" id="sn-env">
                                <label class="form-check-label small" for="sn-env">.env</label>
                            </label>
                        </span>
                        <span class="small">
                            <label class="form-label m-0" for="sn-keep">保留最近</label>
                            <input type="number" name="keep" id="sn-keep" class="form-control form-control-sm d-inline-block"
                                   style="width:5.5rem" min="0" max="60" value="10">
                            份（0 = 不清理；回滚用的 pre-restore 永不清理）
                        </span>
                        <button type="submit" class="btn btn-outline-primary btn-sm ms-auto">
                            <i class="bi bi-archive-fill"></i> 生成快照
                        </button>
                    </div>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>文件</th><th>大小</th><th>内容</th><th class="text-end">操作</th></tr></thead>
                    <tbody>
                    <?php if (!$snapshots): ?>
                        <tr><td colspan="4" class="text-muted small">还没有快照。</td></tr>
                    <?php endif; ?>
                    <?php foreach ($snapshots as $snap): ?>
                        <tr>
                            <td class="small">
                                <?php if ($snap['kind'] === 'pre-restore'): ?>
                                    <span class="badge bg-warning-subtle text-warning" title="恢复动作自动留下的回退快照">回滚点</span>
                                <?php endif; ?>
                                <code><?= e($snap['name']) ?></code>
                                <div class="text-muted"><?= e($fmtTime($snap['mtime'])) ?><?= !empty($snap['manifest']['note'])
                                    ? ' · ' . e((string) $snap['manifest']['note']) : '' ?></div>
                                <?php if (!empty($snap['manifest']['unreadable'])): ?>
                                    <div class="text-danger"><?= e((string) $snap['manifest']['unreadable']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= $size($snap['size']) ?></td>
                            <td class="small"><?= $badges((array) $snap['manifest']) ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-outline-secondary btn-sm"
                                   href="<?= url('/backup/download') ?>?name=<?= urlencode($snap['name']) ?>&amp;token=<?= e($csrf) ?>"
                                   title="下载这个包"><i class="bi bi-download"></i></a>
                                <button type="button" class="btn btn-outline-primary btn-sm use-source"
                                        data-name="<?= e($snap['name']) ?>"
                                        title="<?= $snap['kind'] === 'pre-restore' ? '选作恢复来源（只含数据库，不含附件）' : '选作恢复来源' ?>">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                                <button type="submit" class="btn btn-outline-danger btn-sm"
                                        form="del-snap-<?= e(md5($snap['name'])) ?>" title="删除"><i class="bi bi-trash"></i></button>
                                <form id="del-snap-<?= e(md5($snap['name'])) ?>" method="POST"
                                      action="<?= url('/backup/snapshot/delete') ?>" class="d-none"
                                      onsubmit="return confirm('删除 <?= e($snap['name']) ?>？本地若已有副本请先确认。');">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="name" value="<?= e($snap['name']) ?>">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ================= 一键导入 ================= -->
<div class="card card-table mt-4 border-danger">
    <div class="card-header bg-white text-danger">
        <i class="bi bi-cloud-upload"></i> 一键导入（覆盖当前数据）
        <span class="text-muted small fw-normal ms-2">先「仅检查」看清内容，再「导入并覆盖」</span>
    </div>
    <form method="POST" action="<?= url('/backup/import') ?>" enctype="multipart/form-data" id="import-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="MAX_FILE_SIZE" value="2147483648">
        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-5">
                    <h6 class="text-muted mb-3">1 · 来源</h6>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="source_kind" id="src-upload" value="upload" checked>
                        <label class="form-check-label" for="src-upload">上传备份包</label>
                        <input type="file" name="backup_file" id="backup_file" class="form-control form-control-sm mt-2"
                               accept=".zip,.sqlite,.sqlite3,.db,.db3">
                        <div class="form-text">本系统导出的 <code>.zip</code>；只有库文件时也可以直接给 <code>crm.sqlite</code>。</div>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="radio" name="source_kind" id="src-server" value="server"
                               <?= $snapshots ? '' : 'disabled' ?>>
                        <label class="form-check-label" for="src-server">
                            从服务器快照恢复<?= $snapshots ? '' : '（还没有快照）' ?>
                        </label>
                        <select name="snapshot" id="snapshot" class="form-select form-select-sm mt-2" <?= $snapshots ? '' : 'disabled' ?>>
                            <?php foreach ($snapshots as $snap): ?>
                                <?php // 回滚点也能当来源：它就在本机、名字已过白名单，而“刚导错想撤回”
                                      // 恰恰是最不能卡在 upload_max_filesize 上的时刻。只它是裸库文件，
                                      // 不带附件 —— 这个区别必须在标签里说清，不能让人以为它等于全量回滚。
                                      $what = $snap['kind'] === 'pre-restore'
                                          ? '回滚点·仅数据库'
                                          : (((array) ($snap['manifest']['includes'] ?? []))['uploads'] ?? false ? '含附件' : '仅数据库'); ?>
                                <option value="<?= e($snap['name']) ?>">
                                    <?= e($snap['name']) ?> · <?= $size($snap['size']) ?>
                                    · <?= e($what) ?>
                                    · <?= e((string) ($snap['manifest']['exported_at'] ?? ($snap['manifest']['database']['tables'] ? '未标注时间' : ''))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">不受 PHP 上传限制，也是回滚最快的一条路：包从没离开过这台机器。
                            标「回滚点·仅数据库」的条目只含库，恢复它不会动附件。</div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <h6 class="text-muted mb-3">2 · 恢复什么</h6>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="with_database" id="im-db" value="1" checked>
                        <label class="form-check-label" for="im-db">数据库 <span class="text-muted small">（覆盖整库）</span></label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="with_uploads" id="im-up" value="1" checked>
                        <label class="form-check-label" for="im-up">附件</label>
                    </div>
                    <div class="form-check ms-3">
                        <input class="form-check-input" type="checkbox" name="replace_uploads" id="im-replace" value="1">
                        <label class="form-check-label small text-danger" for="im-replace">
                            整目录替换（删掉包里没有的现有附件）—— 默认不勾，只做新增/同名覆盖
                        </label>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="im-migrate" name="no_migrate" value="1">
                        <label class="form-check-label" for="im-migrate">
                            不补结构 <span class="text-muted small">（默认恢复后自动跑一次
                            <code>migrate.php --no-demo</code>，让旧版本备份跟上当前代码，不会注入演示数据）</span>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="with_env" id="im-env" value="1">
                        <label class="form-check-label small" for="im-env">
                            包里若含 <code>.env</code>，另存为 <code>.env.restored-*</code> 供比对
                            <span class="text-muted">（永远不直接覆盖现有配置）</span>
                        </label>
                    </div>
                </div>

                <div class="col-lg-3">
                    <h6 class="text-muted mb-3">3 · 确认</h6>
                    <div class="alert alert-danger py-2 small">
                        这一步会<strong>覆盖当前全部业务数据</strong>。系统会先把现在的库存成一个
                        <code>pre-restore</code> 快照，但请把它当成不可逆操作来做。
                    </div>
                    <label class="form-label small">输入 <code><?= e($confirmWord) ?></code> 以解锁按钮</label>
                    <input type="text" id="confirm-box" class="form-control form-control-sm" autocomplete="off"
                           placeholder="<?= e($confirmWord) ?>">
                    <input type="hidden" name="confirm_text" id="confirm-text" value="">
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex flex-wrap gap-2">
            <button type="submit" formaction="<?= url('/backup/verify') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-search"></i> 仅检查这个包（不写任何数据）
            </button>
            <button type="submit" id="do-import" class="btn btn-danger btn-sm" disabled>
                <i class="bi bi-exclamation-triangle"></i> 导入并覆盖
            </button>
            <span class="small text-muted align-self-center">
                「仅检查」会把包里的表数、行数、附件数与版本差报给你，确认无误再导入。
            </span>
        </div>
    </form>
</div>

<!-- ================= 操作记录 ================= -->
<div class="card card-table mt-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history"></i> 操作记录</span>
        <?php if ($log): ?>
            <form method="POST" action="<?= url('/backup/history/clear') ?>" class="m-0"
                  onsubmit="return confirm('清空本页操作记录？备份文件本身不会被删除。');">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <button class="btn btn-outline-secondary btn-sm py-0">清空</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead><tr><th>时间</th><th>动作</th><th>执行人</th><th>对象</th><th>结果 / 说明</th></tr></thead>
            <tbody>
            <?php if (!$log): ?>
                <tr><td colspan="5" class="text-muted small">还没有记录（日志写在 <code>database/backups/history.jsonl</code>，
                    不随备份恢复被换掉）。</td></tr>
            <?php endif; ?>
            <?php foreach ($log as $row): ?>
                <?php
                $action = (string) ($row['action'] ?? '');
                $failed = str_contains($action, 'failed');
                $obj = $row['file'] ?? $row['source'] ?? '';
                $bits = [];
                if (isset($row['rows'])) { $bits[] = (int) $row['rows'] . ' 行'; }
                if (isset($row['uploads']) && is_numeric($row['uploads'])) { $bits[] = (int) $row['uploads'] . ' 附件'; }
                if (!empty($row['database']) && is_string($row['database'])) { $bits[] = $row['database']; }
                if (!empty($row['safety'])) { $bits[] = '回滚点 ' . $row['safety']; }
                if (!empty($row['error'])) { $bits[] = '错误：' . textClip((string) $row['error'], 120); }
                if (!empty($row['replace'])) { $bits[] = '整目录替换'; }
                ?>
                <tr class="<?= $failed ? 'table-danger' : '' ?>">
                    <td class="small text-nowrap"><?= e((string) ($row['at'] ?? '')) ?></td>
                    <td class="small"><code><?= e($action) ?></code></td>
                    <td class="small"><?= e((string) ($row['user'] ?? '')) ?><?php if (!empty($row['ip'])): ?>
                        <span class="text-muted">@<?= e((string) $row['ip']) ?></span><?php endif; ?></td>
                    <td class="small"><code><?= e((string) $obj) ?></code></td>
                    <td class="small text-muted"><?= e(implode(' · ', $bits)) ?: '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    var box = document.getElementById('confirm-box');
    var hidden = document.getElementById('confirm-text');
    var btn = document.getElementById('do-import');
    var word = <?= json_encode($confirmWord) ?>;
    if (!box || !hidden || !btn) { return; }

    // 确认词只走 hidden 字段：手输框里的字不是凭据，改了才算没确认
    function sync() {
        var ok = box.value.trim() === word;
        hidden.value = ok ? word : '';
        btn.disabled = !ok;
    }
    box.addEventListener('input', sync);
    sync();

    btn.addEventListener('click', function (ev) {
        if (!window.confirm('确认用这个备份包覆盖当前全部业务数据？（会先自动生成回滚快照）')) {
            ev.preventDefault();
        }
    });

    // 快照表里的「选作来源」：填好下拉并切到服务器来源，少一次手工操作
    document.querySelectorAll('.use-source').forEach(function (b) {
        b.addEventListener('click', function () {
            var name = b.getAttribute('data-name');
            var sel = document.getElementById('snapshot');
            var radio = document.getElementById('src-server');
            if (sel) {
                for (var i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].value === name) { sel.selectedIndex = i; }
                }
            }
            if (radio && !radio.disabled) { radio.checked = true; }
            document.getElementById('import-form').scrollIntoView({ behavior: 'smooth', block: 'center' });
            box.focus();
        });
    });
})();
</script>
