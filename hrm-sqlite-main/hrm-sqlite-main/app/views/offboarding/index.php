<?php
$csrf = htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32)));
$reasonCnMap = [
    'Resignation' => '主动辞职',
    'Termination' => '公司解雇',
    'Contract Expired' => '合同到期',
    'Retirement' => '退休',
    'Other' => '其他原因',
];
?>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
            <div class="icon bg-warning"><i class="bi bi-person-dash"></i></div>
            <div>
                <div class="text-muted small">办理中离职流程</div>
                <div class="fs-4 fw-bold"><?php echo $totalActiveCount ?? count($activeOffboardings); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
            <div class="icon bg-danger"><i class="bi bi-archive"></i></div>
            <div>
                <div class="text-muted small">已离职归档员工</div>
                <div class="fs-4 fw-bold"><?php echo $totalOffboardedCount ?? count($offboardedEmployees); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">离职管理</h4>
    <a href="<?php echo BASE_URL; ?>/employee" class="btn btn-outline-primary">
        <i class="bi bi-people"></i> 选择员工办理离职
    </a>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-tabs mb-3" id="offboardingTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo ($activeTab === 'offboarded' || empty($activeOffboardings)) ? 'active' : ''; ?>" id="offboarded-tab" data-bs-toggle="tab" data-bs-target="#offboarded-pane" type="button" role="tab">
            <i class="bi bi-archive me-1"></i> 已离职员工档案表
            <span class="badge bg-secondary ms-1"><?php echo $totalOffboardedCount ?? count($offboardedEmployees); ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo ($activeTab === 'active' && !empty($activeOffboardings)) ? 'active' : ''; ?>" id="active-tab" data-bs-toggle="tab" data-bs-target="#active-pane" type="button" role="tab">
            <i class="bi bi-hourglass-split me-1"></i> 办理中离职流程
            <span class="badge bg-warning text-dark ms-1"><?php echo $totalActiveCount ?? count($activeOffboardings); ?></span>
        </button>
    </li>
</ul>

<div class="tab-content" id="offboardingTabContent">
    <!-- Offboarded Employees Table Pane -->
    <div class="tab-pane fade <?php echo ($activeTab === 'offboarded' || empty($activeOffboardings)) ? 'show active' : ''; ?>" id="offboarded-pane" role="tabpanel">
        <div class="card p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>工号</th>
                            <th>员工姓名</th>
                            <th>部门</th>
                            <th>职位</th>
                            <th>离职日期</th>
                            <th>离职原因</th>
                            <th>归档时间</th>
                            <th class="text-end">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($offboardedEmployees)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">暂无已离职员工记录。当员工完成离职交接手续后，其档案将显示在此处。</td></tr>
                        <?php else: foreach ($offboardedEmployees as $oe): ?>
                            <tr>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($oe['employee_code']); ?></span></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>/offboarding/view/<?php echo $oe['id']; ?>" class="text-decoration-none fw-semibold">
                                        <?php echo htmlspecialchars($oe['last_name'] . $oe['first_name']); ?>
                                    </a>
                                    <div class="text-muted small"><?php echo htmlspecialchars($oe['email']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($oe['department_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($oe['designation'] ?? '—'); ?></td>
                                <td><span class="fw-semibold text-danger"><?php echo htmlspecialchars($oe['exit_date']); ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($reasonCnMap[$oe['reason']] ?? $oe['reason']); ?></span></td>
                                <td class="text-muted small"><?php echo date('Y-m-d H:i', strtotime($oe['offboarded_at'])); ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="<?php echo BASE_URL; ?>/offboarding/view/<?php echo $oe['id']; ?>" class="btn btn-sm btn-outline-secondary" title="查看档案">
                                        <i class="bi bi-eye"></i> 查看详情
                                    </a>
                                    <form method="POST" action="<?php echo BASE_URL; ?>/offboarding/rehire/<?php echo $oe['id']; ?>" class="d-inline" onsubmit="return confirm('确定将此员工复职吗？员工将被重新添加到在职员工列表中。');">
                                        <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="复职">
                                            <i class="bi bi-arrow-counterclockwise"></i> 复职
                                        </button>
                                    </form>
                                    <form method="POST" action="<?php echo BASE_URL; ?>/offboarding/deleteOffboarded/<?php echo $oe['id']; ?>" class="d-inline" onsubmit="return confirm('确定永久删除此已离职员工记录吗？此操作无法撤销。');">
                                        <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="永久删除">
                                            <i class="bi bi-trash"></i> 删除
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php $pagination = $offboardedPagination; require __DIR__ . '/../partials/pagination.php'; ?>
        </div>
    </div>

    <!-- Active Offboardings Pane -->
    <div class="tab-pane fade <?php echo ($activeTab === 'active' && !empty($activeOffboardings)) ? 'show active' : ''; ?>" id="active-pane" role="tabpanel">
        <div class="card p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>员工信息</th>
                            <th>部门</th>
                            <th>预计离职日期</th>
                            <th>离职原因</th>
                            <th style="width: 220px;">离职交接进度</th>
                            <th>状态</th>
                            <th class="text-end">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activeOffboardings)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">当前没有正在办理离职的员工。您可以在“员工管理”页面为员工发起离职。</td></tr>
                        <?php else: foreach ($activeOffboardings as $ao): 
                            if ($ao['status'] === 'Completed') continue;
                            $percent = $ao['total_tasks'] > 0 ? round(($ao['completed_tasks'] / $ao['total_tasks']) * 100) : 0;
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($ao['last_name'] . $ao['first_name']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($ao['employee_code']); ?> · <?php echo htmlspecialchars($ao['email']); ?></div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($ao['department_name'] ?? '—'); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($ao['designation'] ?? '—'); ?></div>
                                </td>
                                <td><span class="text-danger fw-semibold"><?php echo htmlspecialchars($ao['exit_date']); ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($reasonCnMap[$ao['reason']] ?? $ao['reason']); ?></span></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 8px;">
                                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $percent; ?>%;" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <span class="small text-muted text-nowrap"><?php echo $ao['completed_tasks'] . '/' . $ao['total_tasks']; ?> (<?php echo $percent; ?>%)</span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($ao['status'] === 'Completed'): ?>
                                        <span class="badge bg-success"><i class="bi bi-check2"></i> 已完成</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> 办理中</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?php echo BASE_URL; ?>/offboarding/tasks/<?php echo $ao['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-list-check"></i> 办理交接清单
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php $pagination = $activePagination; require __DIR__ . '/../partials/pagination.php'; ?>
        </div>
    </div>
</div>
