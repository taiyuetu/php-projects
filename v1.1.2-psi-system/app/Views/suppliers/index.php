<?php
use App\Core\Router;
$filters = $filters ?? [];
$customFields = $customFields ?? [];
$exportParams = http_build_query(array_filter($filters, fn($v) => $v !== ''));
$exportUrl = Router::url('/suppliers/export') . ($exportParams !== '' ? '?' . $exportParams : '');
$hasFilter = count(array_filter($filters, fn($v) => $v !== '')) > 0;
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;">所有供应商</h2>
        <div style="display:flex;gap:10px;align-items:center;">
            <a href="<?= Router::url('/suppliers/import') ?>" class="btn btn-secondary">导入CSV</a>
            <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-secondary">导出CSV</a>
            <a href="<?= Router::url('/suppliers/create') ?>" class="btn btn-primary">+ 添加供应商</a>
        </div>
    </div>

    <form method="get" action="<?= Router::url('/suppliers') ?>" class="filter-form">
        <input type="search" name="q" placeholder="搜索供应商..." value="<?= htmlspecialchars($filters['q'] ?? '') ?>">
        <?php include __DIR__ . '/../partials/custom_fields_filters.php'; ?>
        <button type="submit" class="btn btn-secondary btn-sm">筛选</button>
        <?php if ($hasFilter): ?>
            <a href="<?= Router::url('/suppliers') ?>" class="btn btn-secondary btn-sm">清除</a>
        <?php endif; ?>
    </form>

    <?php if (empty($suppliers)): ?>
        <p class="empty-state"><?= $hasFilter ? '没有匹配的供应商。' : '暂无供应商。' ?></p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>名称</th><th>电话</th><th>邮箱</th><th>地址</th><?php include __DIR__ . '/../partials/custom_fields_headers.php'; ?><th></th></tr></thead>
        <tbody>
        <?php foreach ($suppliers as $s): ?>
            <?php $attrs = json_decode($s['attributes'] ?? '{}', true) ?: []; ?>
            <tr>
                <td><?= htmlspecialchars($s['name'] ?? '') ?></td>
                <td><?= htmlspecialchars($s['phone'] ?? '') ?></td>
                <td><?= htmlspecialchars($s['email'] ?? '') ?></td>
                <td class="text-muted"><?= htmlspecialchars($s['address'] ?? '') ?></td>
                <?php include __DIR__ . '/../partials/custom_fields_cells.php'; ?>
                <td class="actions">
                    <a href="<?= Router::url('/suppliers/' . $s['id'] . '/edit') ?>" class="btn btn-secondary btn-sm">编辑</a>
                    <?php $deleteUrl = Router::url('/suppliers/' . $s['id'] . '/delete'); include __DIR__ . '/../partials/delete_button.php'; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php include __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</div>
