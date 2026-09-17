<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">请假管理</h4>
    <?php if ($_SESSION['user_role'] === 'employee'): ?>
        <a class="btn btn-primary" href="<?php echo BASE_URL; ?>/leave/create">
            <i class="bi bi-plus-lg"></i> 申请请假
        </a>
    <?php endif; ?>
</div>

<?php
$leaveStatusMap = ['Pending' => '待审批', 'Approved' => '已批准', 'Rejected' => '已拒绝'];
$leaveTypeMap = [
    'Annual' => '年假', 'Sick' => '病假', 'Casual' => '事假',
    'Unpaid' => '无薪假', 'Maternity' => '产假', 'Paternity' => '陪产假'
];
?>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>员工姓名</th>
                    <th>请假类型</th>
                    <th>起止日期</th>
                    <th>请假原因</th>
                    <th>状态</th>
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'hr'])): ?>
                        <th class="text-end">操作</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leaves)): ?>
                    <tr><td colspan="<?php echo in_array($_SESSION['user_role'], ['admin', 'hr']) ? '6' : '5'; ?>" class="text-center text-muted py-4">暂无请假申请记录。</td></tr>
                <?php else: foreach ($leaves as $row): ?>
                    <tr>
                        <td class="fw-semibold"><?php echo htmlspecialchars(($row['last_name'] ?? '') . ($row['first_name'] ?? '')); ?></td>
                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($leaveTypeMap[$row['leave_type']] ?? $row['leave_type']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['start_date'] . ' 至 ' . $row['end_date']); ?></td>
                        <td><?php echo htmlspecialchars($row['reason'] ?? '—'); ?></td>
                        <td><span class="badge badge-status-<?php echo $row['status']; ?>"><?php echo htmlspecialchars($leaveStatusMap[$row['status']] ?? $row['status']); ?></span></td>
                        <?php if (in_array($_SESSION['user_role'], ['admin', 'hr'])): ?>
                            <td class="text-end">
                                <?php if ($row['status'] === 'Pending'): ?>
                                    <form method="POST" action="<?php echo BASE_URL; ?>/leave/approve/<?php echo $row['id']; ?>" class="d-inline">
                                        <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">
                                        <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> 批准</button>
                                    </form>
                                    <form method="POST" action="<?php echo BASE_URL; ?>/leave/reject/<?php echo $row['id']; ?>" class="d-inline">
                                        <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> 拒绝</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>
