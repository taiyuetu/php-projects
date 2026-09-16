<?php
$reasonMap = [
    'Resignation' => '主动辞职',
    'Termination' => '解雇 / 辞退',
    'End of Contract' => '合同到期',
    'Layoff' => '裁员',
    'Retirement' => '退休',
    'Other' => '其他'
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 text-danger"><i class="bi bi-archive me-2"></i> 已离职员工档案记录</h4>
        <div class="text-muted">
            员工永久离职档案：<strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong> (<?php echo htmlspecialchars($employee['employee_code']); ?>)
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>/offboarding?tab=offboarded" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> 返回已离职员工列表
        </a>
        <button onclick="window.print()" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-printer"></i> 打印档案记录
        </button>
        <form method="POST" action="<?php echo BASE_URL; ?>/offboarding/deleteOffboarded/<?php echo $employee['id']; ?>" class="d-inline" onsubmit="return confirm('确定要永久删除此离职员工记录吗？此操作无法撤销。');">
            <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">
            <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-trash"></i> 永久删除
            </button>
        </form>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-5">
        <div class="card p-3 mb-4">
            <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-person me-2"></i> 员工基本信息</h6>
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-muted" style="width: 140px;">工号：</td>
                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($employee['employee_code']); ?></span></td>
                </tr>
                <tr>
                    <td class="text-muted">姓名：</td>
                    <td class="fw-semibold"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">电子邮箱：</td>
                    <td><?php echo htmlspecialchars($employee['email']); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">联系电话：</td>
                    <td><?php echo htmlspecialchars($employee['phone'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">部门：</td>
                    <td><?php echo htmlspecialchars($employee['department_name'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">职位：</td>
                    <td><?php echo htmlspecialchars($employee['designation'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">入职日期：</td>
                    <td><?php echo htmlspecialchars($employee['hire_date'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">离职日期：</td>
                    <td><span class="text-danger fw-bold"><?php echo htmlspecialchars($employee['exit_date']); ?></span></td>
                </tr>
                <?php if (!empty($employee['hire_date']) && !empty($employee['exit_date'])): 
                    $hire = new DateTime($employee['hire_date']);
                    $exit = new DateTime($employee['exit_date']);
                    $diff = $hire->diff($exit);
                ?>
                <tr>
                    <td class="text-muted">服务年限：</td>
                    <td><span class="badge bg-secondary"><?php echo $diff->y . ' 年 ' . $diff->m . ' 个月'; ?></span></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="card p-3">
            <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-info-circle me-2"></i> 归档元数据</h6>
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-muted" style="width: 140px;">结算清算状态：</td>
                    <td><span class="badge bg-success"><?php echo htmlspecialchars(($employee['final_settlement_status'] ?? 'Completed') === 'Completed' ? '已完成' : ($employee['final_settlement_status'] ?? '已完成')); ?></span></td>
                </tr>
                <tr>
                    <td class="text-muted">离职原因：</td>
                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($reasonMap[$employee['reason']] ?? $employee['reason']); ?></span></td>
                </tr>
                <tr>
                    <td class="text-muted">归档时间：</td>
                    <td><?php echo htmlspecialchars($employee['offboarded_at']); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">操作人：</td>
                    <td><?php echo htmlspecialchars($employee['offboarded_by_username'] ?? 'HR 系统管理员'); ?></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card p-3 mb-4">
            <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-check2-all me-2"></i> 交接清单汇总说明</h6>
            <?php if (!empty($employee['completed_tasks_summary'])): ?>
                <div class="p-3 bg-light rounded font-monospace small" style="white-space: pre-wrap; line-height: 1.6;">
<?php echo htmlspecialchars($employee['completed_tasks_summary']); ?>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0 small">暂无交接事项记录。</p>
            <?php endif; ?>
        </div>

        <div class="card p-3">
            <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-chat-left-text me-2"></i> 离职与结算备注</h6>
            <?php if (!empty($employee['notes'])): ?>
                <div class="p-3 bg-light rounded small">
                    <?php echo nl2br(htmlspecialchars($employee['notes'])); ?>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0 small">无附加备注说明。</p>
            <?php endif; ?>
        </div>
    </div>
</div>
