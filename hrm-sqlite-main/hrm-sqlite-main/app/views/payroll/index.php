<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">薪酬管理</h4>
    <form method="POST" action="<?php echo BASE_URL; ?>/payroll/generate" class="d-flex gap-2">
        <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">
        <input type="number" name="month" min="1" max="12" value="<?php echo date('n'); ?>" class="form-control" style="width:90px" title="月份">
        <input type="number" name="year" value="<?php echo date('Y'); ?>" class="form-control" style="width:110px" title="年份">
        <button class="btn btn-primary"><i class="bi bi-gear"></i> 生成薪资单</button>
    </form>
</div>

<?php
$payrollStatusMap = ['Paid' => '已发放', 'Unpaid' => '未发放', 'Pending' => '待处理'];
?>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>员工姓名</th>
                    <th>发薪周期</th>
                    <th>基本工资</th>
                    <th>实发工资</th>
                    <th>状态</th>
                    <th class="text-end">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payroll)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">暂无薪水发放记录。</td></tr>
                <?php else: foreach ($payroll as $row): ?>
                    <tr>
                        <td class="fw-semibold"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td><span class="badge bg-light text-dark border"><?php echo sprintf('%d年%02d月', $row['year'], $row['month']); ?></span></td>
                        <td>￥<?php echo number_format($row['basic_salary'], 2); ?></td>
                        <td class="fw-bold text-success">￥<?php echo number_format($row['net_salary'], 2); ?></td>
                        <td><span class="badge badge-status-<?php echo $row['status']; ?>"><?php echo htmlspecialchars($payrollStatusMap[$row['status']] ?? $row['status']); ?></span></td>
                        <td class="text-end">
                            <?php if ($row['status'] !== 'Paid'): ?>
                                <form method="POST" action="<?php echo BASE_URL; ?>/payroll/paid/<?php echo $row['id']; ?>" class="d-inline">
                                    <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">
                                    <button class="btn btn-sm btn-outline-success"><i class="bi bi-check2"></i> 标记为已发放</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted small"><i class="bi bi-check-circle-fill text-success"></i> 已发结清</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>
