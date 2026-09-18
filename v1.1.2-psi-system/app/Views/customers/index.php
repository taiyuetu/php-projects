<?php
use App\Core\Router;
$filters = $filters ?? [];
$customFields = $customFields ?? [];
$exportParams = http_build_query(array_filter($filters, fn($v) => $v !== ''));
$exportUrl = Router::url('/customers/export') . ($exportParams !== '' ? '?' . $exportParams : '');
$hasFilter = count(array_filter($filters, fn($v) => $v !== '')) > 0;
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;">所有客户</h2>
        <div style="display:flex;gap:10px;align-items:center;">
            <a href="<?= Router::url('/customers/import') ?>" class="btn btn-secondary">导入CSV</a>
            <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-secondary">导出CSV</a>
            <a href="<?= Router::url('/customers/create') ?>" class="btn btn-primary">+ 添加客户</a>
        </div>
    </div>

    <form method="get" action="<?= Router::url('/customers') ?>" class="filter-form">
        <input type="search" name="q" placeholder="搜索客户..." value="<?= htmlspecialchars($filters['q'] ?? '') ?>">
        <?php include __DIR__ . '/../partials/custom_fields_filters.php'; ?>
        <button type="submit" class="btn btn-secondary btn-sm">筛选</button>
        <?php if ($hasFilter): ?>
            <a href="<?= Router::url('/customers') ?>" class="btn btn-secondary btn-sm">清除</a>
        <?php endif; ?>
    </form>

    <?php if (empty($customers)): ?>
        <p class="empty-state"><?= $hasFilter ? '没有匹配的客户。' : '还没有客户。' ?></p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>名称</th><th>电话</th><th>邮箱</th><th>地址</th><?php include __DIR__ . '/../partials/custom_fields_headers.php'; ?><th></th></tr></thead>
        <tbody>
        <?php foreach ($customers as $c): ?>
            <?php $attrs = json_decode($c['attributes'] ?? '{}', true) ?: []; ?>
            <tr>
                <td><?= htmlspecialchars($c['name'] ?? '') ?></td>
                <td><?= htmlspecialchars($c['phone'] ?? '') ?></td>
                <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
                <td class="text-muted"><?= htmlspecialchars($c['address'] ?? '') ?></td>
                <?php include __DIR__ . '/../partials/custom_fields_cells.php'; ?>
                <td class="actions">
                    <a href="<?= Router::url('/customers/' . $c['id'] . '/edit') ?>" class="btn btn-secondary btn-sm">编辑</a>
                    <?php $deleteUrl = Router::url('/customers/' . $c['id'] . '/delete'); include __DIR__ . '/../partials/delete_button.php'; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php include __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</div>
