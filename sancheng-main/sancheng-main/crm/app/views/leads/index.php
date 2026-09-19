<?php
/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
/** @var array $leads @var string $status @var string $search @var string $grade
 *  @var int $sourceCategoryId @var array $sourceCategories @var int $page @var int $totalPages @var int $total
 */
$gradeFilters = ['' => '全部星级'] + Lead::gradeOptions();

/** 拼接带筛选条件的列表 URL（保持当前其他参数） */
$leadListUrl = function (array $overrides = []) use ($status, $search, $grade, $sourceCategoryId): string {
    $params = array_filter([
        'status' => $overrides['status'] ?? $status,
        'q'      => $overrides['q'] ?? $search,
        'grade'  => $overrides['grade'] ?? $grade,
        'cat'    => $overrides['cat'] ?? $sourceCategoryId,
    ], fn($v) => $v !== '' && $v !== 0 && $v !== null);
    return url('/leads' . ($params ? '?' . http_build_query($params) : ''));
};
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">线索</h3>
    <a href="<?= url('/leads/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> 新建线索</a>
</div>

<div class="card card-table">
    <div class="card-header bg-white d-flex flex-column gap-2">
        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="d-flex flex-wrap gap-2">
                <?php $filters = ['' => '全部', 'new' => '新建', 'contacted' => '已联系', 'qualified' => '已确认', 'lost' => '已流失']; ?>
                <?php foreach ($filters as $value => $label): ?>
                    <a href="<?= $leadListUrl(['status' => $value]) ?>"
                       class="btn btn-sm <?= $status === $value ? 'btn-secondary' : 'btn-outline-secondary' ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </div>
            <form method="GET" action="<?= url('/leads') ?>" class="d-flex gap-2">
                <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
                <?php if ($grade !== ''): ?><input type="hidden" name="grade" value="<?= e($grade) ?>"><?php endif; ?>
                <?php if ($sourceCategoryId > 0): ?><input type="hidden" name="cat" value="<?= (int) $sourceCategoryId ?>"><?php endif; ?>
                <input type="text" name="q" class="form-control form-control-sm" style="max-width:260px"
                       placeholder="搜索标题、公司、联系人、来源…" value="<?= e($search) ?>">
                <button class="btn btn-sm btn-outline-secondary" type="submit">搜索</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= $leadListUrl(['q' => '']) ?>" class="btn btn-sm btn-link">清除</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="small text-muted"><i class="bi bi-star"></i> 星级：</span>
            <?php foreach ($gradeFilters as $value => $label): ?>
                <a href="<?= $leadListUrl(['grade' => $value]) ?>"
                   class="btn btn-sm <?= $grade === $value ? 'btn-secondary' : 'btn-outline-secondary' ?>"
                   <?= $value !== '' ? 'title="' . e(Lead::gradeDescriptions()[$value] ?? '') . '"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
            <span class="small text-muted ms-2"><i class="bi bi-tags"></i> 来源分类：</span>
            <form method="GET" action="<?= url('/leads') ?>" class="d-flex gap-2">
                <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
                <?php if ($grade !== ''): ?><input type="hidden" name="grade" value="<?= e($grade) ?>"><?php endif; ?>
                <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
                <select name="cat" class="form-select form-select-sm" style="max-width:180px" onchange="this.form.submit()">
                    <option value="">全部分类</option>
                    <?php foreach ($sourceCategories as $cid => $cname): ?>
                        <option value="<?= (int) $cid ?>" <?= $sourceCategoryId === (int) $cid ? 'selected' : '' ?>><?= e($cname) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="<?= url('/categories?type=lead_source') ?>" class="btn btn-sm btn-link ms-auto">
                <i class="bi bi-gear"></i> 管理来源分类
            </a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr class="text-muted small">
                    <th>线索</th>
                    <th>联系人</th>
                    <th>来源</th>
                    <th class="text-center">星级</th>
                    <th>预估金额</th>
                    <th>状态</th>
                    <?php if ($status === 'lost'): ?>
                    <th>流失原因</th>
                    <?php endif; ?>
                    <th class="text-end">操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$leads): ?>
                <tr><td colspan="<?= $status === 'lost' ? 8 : 7 ?>" class="text-center text-muted p-4">未找到线索。</td></tr>
            <?php endif; ?>
            <?php foreach ($leads as $l): ?>
                <tr class="<?= $l['status'] === 'lost' ? 'table-light' : '' ?>">
                    <td class="fw-semibold"><a href="<?= url('/leads/' . $l['id']) ?>" class="text-decoration-none"><?= e($l['title']) ?></a>
                        <div class="small text-muted fw-normal" title="稳定编号：对 AI 说这个就行"><?= e((new Lead())->codeOf($l)) ?></div></td>
                    <td><?= e($l['contact_name'] ?: '—') ?></td>
                    <td>
                        <?= e($l['source'] ?: '—') ?>
                        <?php if (!empty($l['source_category_name'])): ?>
                            <div class="small text-muted"><i class="bi bi-tag"></i> <?= e($l['source_category_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if (!empty($l['grade'])): ?>
                            <span class="badge <?= Lead::gradeBadgeClass((string) $l['grade']) ?>"
                                  title="<?= e(Lead::gradeDescriptions()[(string) $l['grade']] ?? '') ?>">
                                <?= e(Lead::gradeLabel((string) $l['grade'])) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= money($l['value']) ?></td>
                    <td><?= statusBadge($l['status']) ?></td>
                    <?php if ($status === 'lost'): ?>
                    <td>
                        <?php if ($l['lost_reason']): ?>
                            <span class="badge bg-danger-subtle text-danger">
                                <?= e(Lead::lostReasonLabel($l['lost_reason'])) ?>
                            </span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td class="text-end">
                        <?php if ($l['status'] === 'lost'): ?>
                            <!-- 流失线索：显示重新激活按钮 -->
                            <form method="POST" action="<?= url('/leads/' . $l['id'] . '/reactivate') ?>" class="d-inline"
                                  onsubmit="return confirm('确定重新激活此线索？将恢复为"已联系"状态。');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-success" title="重新激活">
                                    <i class="bi bi-arrow-counterclockwise"></i> 激活
                                </button>
                            </form>
                        <?php elseif ($l['status'] !== 'qualified'): ?>
                            <!-- 未转化线索：显示转商机和标记流失 -->
                            <form method="POST" action="<?= url('/leads/' . $l['id'] . '/convert') ?>" class="d-inline"
                                  onsubmit="return confirm('确定将此线索转为商机？系统将自动创建客户和商机记录。');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-success" title="转为商机">
                                    <i class="bi bi-arrow-right-circle"></i>
                                </button>
                            </form>
                            <button type="button" class="btn btn-sm btn-outline-warning" title="标记流失"
                                    data-bs-toggle="modal" data-bs-target="#lostModal<?= $l['id'] ?>">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        <?php else: ?>
                            <!-- 已转化线索 -->
                            <span class="badge bg-success-subtle text-success">
                                <i class="bi bi-check-circle"></i> 已转商机
                            </span>
                        <?php endif; ?>

                        <a href="<?= url('/leads/' . $l['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="<?= url('/leads/' . $l['id']) ?>" class="d-inline"
                              onsubmit="return confirm('确定删除此线索？');">
                            <input type="hidden" name="_method" value="DELETE">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 星级判定标准说明 -->
<div class="card card-table mt-3 p-3">
    <h6 class="text-muted small text-uppercase mb-2"><i class="bi bi-star me-1"></i> 星级判定标准</h6>
    <div class="row g-2 small">
        <?php foreach (Lead::gradeOptions() as $gv => $glabel): ?>
            <div class="col-md-6 col-xl-3">
                <span class="badge <?= Lead::gradeBadgeClass((string) $gv) ?> me-1"><?= e($glabel) ?></span>
                <span class="text-muted"><?= e(Lead::gradeDescriptions()[(string) $gv] ?? '') ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- 流失原因弹窗 -->
<?php foreach ($leads as $l): ?>
    <?php if ($l['status'] !== 'lost' && $l['status'] !== 'qualified'): ?>
    <div class="modal fade" id="lostModal<?= $l['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="<?= url('/leads/' . $l['id'] . '/lost') ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">标记线索流失</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>线索：<strong><?= e($l['title']) ?></strong></p>
                        <div class="mb-3">
                            <label class="form-label">流失原因 <span class="text-danger">*</span></label>
                            <select name="lost_reason" class="form-select" required>
                                <option value="">请选择...</option>
                                <?php foreach (Lead::lostReasonOptions() as $value => $label): ?>
                                    <option value="<?= e($value) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-warning">确认流失</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endforeach; ?>

<?php
$qs = [];
if ($status !== '') {
    $qs[] = 'status=' . urlencode($status);
}
if ($search !== '') {
    $qs[] = 'q=' . urlencode($search);
}
if ($grade !== '') {
    $qs[] = 'grade=' . urlencode($grade);
}
if ($sourceCategoryId > 0) {
    $qs[] = 'cat=' . (int) $sourceCategoryId;
}
$baseUrl = $qs ? url('/leads?' . implode('&', $qs) . '&page=') : url('/leads?page=');
include APP_PATH . '/views/partials/_pagination.php';
?>
