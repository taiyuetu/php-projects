<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
            <div class="icon bg-warning"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="text-muted small">待审核</div>
                <div class="fs-4 fw-bold"><?php echo (int) $pendingCount; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
            <div class="icon bg-success"><i class="bi bi-person-check"></i></div>
            <div>
                <div class="text-muted small">已批准</div>
                <div class="fs-4 fw-bold"><?php echo (int) $approvedCount; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
            <div class="icon bg-danger"><i class="bi bi-person-x"></i></div>
            <div>
                <div class="text-muted small">已拒绝</div>
                <div class="fs-4 fw-bold"><?php echo (int) $rejectedCount; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-inbox text-primary me-2"></i>入职申请</h4>
    <a href="<?php echo BASE_URL; ?>/onboarding/qr" class="btn btn-outline-secondary">
        <i class="bi bi-qr-code"></i> 入职二维码
    </a>
</div>

<ul class="nav nav-tabs mb-3">
    <?php foreach (['' => '全部', 'Pending' => '待审核', 'Approved' => '已批准', 'Rejected' => '已拒绝'] as $value => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $status === $value ? 'active' : ''; ?>"
               href="<?php echo BASE_URL; ?>/onboarding/applications<?php echo $value !== '' ? '?status=' . $value : ''; ?>">
                <?php echo $label; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>申请人</th>
                    <th>联系方式</th>
                    <th>意向部门 / 职位</th>
                    <th>期望薪资</th>
                    <th>提交时间</th>
                    <th>状态</th>
                    <th class="text-end">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">暂无入职申请。可点击右上角“入职二维码”生成二维码，让新员工扫码填写。</td></tr>
                <?php else: foreach ($records as $r): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($r['last_name'] . $r['first_name']); ?></div>
                            <?php if ($r['id_number']): ?>
                                <div class="text-muted small">身份证：<?php echo htmlspecialchars($r['id_number']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><?php echo htmlspecialchars($r['email']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($r['phone']); ?></div>
                        </td>
                        <td>
                            <div><?php echo htmlspecialchars($r['department_name'] ?? '—'); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($r['designation'] ?? '—'); ?></div>
                        </td>
                        <td><?php echo $r['expected_salary'] > 0 ? number_format((float) $r['expected_salary'], 2) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($r['submitted_at']); ?></td>
                        <td>
                            <?php if ($r['status'] === 'Pending'): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> 待审核</span>
                            <?php elseif ($r['status'] === 'Approved'): ?>
                                <span class="badge bg-success"><i class="bi bi-check2"></i> 已批准</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="bi bi-x"></i> 已拒绝</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?php echo BASE_URL; ?>/onboarding/application/<?php echo (int) $r['id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> 查看
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>
