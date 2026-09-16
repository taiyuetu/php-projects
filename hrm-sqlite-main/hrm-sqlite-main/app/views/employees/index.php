<div class="d-flex justify-content-between align-items-center mb-3">
    <form method="GET" action="<?php echo BASE_URL; ?>/employee" class="d-flex gap-2">
        <input type="text" name="q" value="<?php echo htmlspecialchars($keyword ?? ''); ?>" class="form-control" placeholder="按姓名、邮箱或工号搜索..." style="width: 280px;">
        <button class="btn btn-outline-secondary"><i class="bi bi-search"></i> 搜索</button>
    </form>
</div>

<?php
$empStatusMap = ['Active' => '在职', 'On Leave' => '休假', 'Terminated' => '已离职'];
?>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>工号</th><th>姓名</th><th>邮箱</th><th>部门</th>
                    <th>职位</th><th>状态</th><th class="text-end">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">未找到员工记录。</td></tr>
                <?php else: foreach ($employees as $e): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($e['employee_code']); ?></td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/employee/profile/<?php echo $e['id']; ?>" class="text-decoration-none fw-semibold">
                                <?php echo htmlspecialchars($e['first_name'] . ' ' . $e['last_name']); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($e['email']); ?></td>
                        <td><?php echo htmlspecialchars($e['department_name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($e['designation'] ?? '—'); ?></td>
                        <td><span class="badge badge-status-<?php echo $e['status']; ?>"><?php echo $empStatusMap[$e['status']] ?? $e['status']; ?></span></td>
                        <td class="text-end">
                            <a href="<?php echo BASE_URL; ?>/employee/edit/<?php echo $e['id']; ?>" class="btn btn-sm btn-outline-secondary" title="编辑员工"><i class="bi bi-pencil"></i></a>
                            <?php if ($e['status'] === 'Terminated'): ?>
                                <span class="badge bg-secondary ms-1">已离职归档</span>
                            <?php else: ?>
                                <a href="<?php echo BASE_URL; ?>/offboarding/initiate/<?php echo $e['id']; ?>" class="btn btn-sm btn-outline-warning ms-1" title="办理离职">
                                    <i class="bi bi-box-arrow-right"></i> 办理离职
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>
