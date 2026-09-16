<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">部门管理</h4>
    <a class="btn btn-primary" href="<?php echo BASE_URL; ?>/department/create">
        <i class="bi bi-plus-lg"></i> 添加部门
    </a>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>部门名称</th>
                    <th>描述</th>
                    <th>员工人数</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($departments)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">暂无部门数据。</td></tr>
                <?php else: foreach ($departments as $department): ?>
                    <tr>
                        <td class="fw-semibold"><?php echo htmlspecialchars($department['name']); ?></td>
                        <td><?php echo htmlspecialchars($department['description'] ?? '—'); ?></td>
                        <td><span class="badge bg-primary rounded-pill"><?php echo (int) $department['employee_count']; ?> 人</span></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>
