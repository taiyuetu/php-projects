<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
            <div class="icon bg-warning"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="text-muted small">待办理入职</div>
                <div class="fs-4 fw-bold"><?php echo $inProgressCount; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">员工入职管理</h4>
    <a href="<?php echo BASE_URL; ?>/onboarding/create" class="btn btn-primary">
        <i class="bi bi-person-plus"></i> 办理新员工入职
    </a>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>员工信息</th>
                    <th>所属部门</th>
                    <th>入职日期</th>
                    <th>目标完成日期</th>
                    <th style="width: 220px;">入职进度</th>
                    <th>状态</th>
                    <th class="text-end">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">暂无入职记录。点击“办理新员工入职”登记并办理新员工入职手续。</td></tr>
                <?php else: foreach ($records as $r): 
                    $percent = $r['total_tasks'] > 0 ? round(($r['completed_tasks'] / $r['total_tasks']) * 100) : 0;
                ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($r['last_name'] . $r['first_name']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($r['employee_code']); ?> · <?php echo htmlspecialchars($r['email']); ?></div>
                        </td>
                        <td>
                            <div><?php echo htmlspecialchars($r['department_name'] ?? '—'); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($r['designation'] ?? '—'); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($r['start_date']); ?></td>
                        <td><?php echo htmlspecialchars($r['target_completion_date'] ?? '—'); ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height: 8px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percent; ?>%;" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <span class="small text-muted text-nowrap"><?php echo $r['completed_tasks'] . '/' . $r['total_tasks']; ?> (<?php echo $percent; ?>%)</span>
                            </div>
                        </td>
                        <td>
                            <?php if ($r['status'] === 'Completed'): ?>
                                <span class="badge bg-success"><i class="bi bi-check2"></i> 已完成</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-arrow-repeat"></i> 进行中</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?php echo BASE_URL; ?>/onboarding/tasks/<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-list-check"></i> 查看清单
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>
