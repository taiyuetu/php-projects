<?php
use App\Core\Router;
$filters = $filters ?? [];
$customFields = $customFields ?? [];
$hasFilter = count(array_filter($filters, fn($v) => $v !== '')) > 0;
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;">所有分类</h2>
        <a href="<?= Router::url('/categories/create') ?>" class="btn btn-primary">+ 添加分类</a>
    </div>

    <form method="get" action="<?= Router::url('/categories') ?>" class="filter-form">
        <input type="search" name="q" placeholder="搜索分类..." value="<?= htmlspecialchars($filters['q'] ?? '') ?>">
        <?php include __DIR__ . '/../partials/custom_fields_filters.php'; ?>
        <button type="submit" class="btn btn-secondary btn-sm">筛选</button>
        <?php if ($hasFilter): ?>
            <a href="<?= Router::url('/categories') ?>" class="btn btn-secondary btn-sm">清除</a>
        <?php endif; ?>
    </form>

    <?php if (empty($categories)): ?>
        <p class="empty-state"><?= $hasFilter ? '没有匹配的分类。' : '暂无分类。' ?></p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>名称</th><th>创建时间</th><?php include __DIR__ . '/../partials/custom_fields_headers.php'; ?><th></th></tr></thead>
        <tbody>
        <?php foreach ($categories as $c): ?>
            <?php $attrs = json_decode($c['attributes'] ?? '{}', true) ?: []; ?>
            <tr>
                <td><?= htmlspecialchars($c['name']) ?></td>
                <td class="text-muted"><?= htmlspecialchars(substr($c['created_at'], 0, 10)) ?></td>
                <?php include __DIR__ . '/../partials/custom_fields_cells.php'; ?>
                <td class="actions">
                    <a href="<?= Router::url('/categories/' . $c['id'] . '/edit') ?>" class="btn btn-secondary btn-sm">编辑</a>
                    <?php $deleteUrl = Router::url('/categories/' . $c['id'] . '/delete'); include __DIR__ . '/../partials/delete_button.php'; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php include __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</div>
