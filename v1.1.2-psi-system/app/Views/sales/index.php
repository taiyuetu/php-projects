<?php
use App\Core\Router;
$customFields = $customFields ?? [];
$filters = $filters ?? [];
$hasFilter = count(array_filter($filters, fn($v) => $v !== '')) > 0;
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;">销售发票</h2>
        <div style="display:flex;gap:10px;align-items:center;">
            <form method="get" action="<?= Router::url('/sales') ?>" class="search-form" style="display:flex;gap:8px;align-items:center;">
                <input type="search" name="q" placeholder="搜索销售..." value="<?= htmlspecialchars($q ?? '') ?>">
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from ?? '') ?>" title="开始日期">
                <span style="color:#9ca3af;">–</span>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to ?? '') ?>" title="结束日期">
                <?php include __DIR__ . '/../partials/custom_fields_filters.php'; ?>
                <button type="submit" class="btn btn-secondary btn-sm">筛选</button>
                <?php if ($hasFilter): ?>
                    <a href="<?= Router::url('/sales') ?>" class="btn btn-secondary btn-sm">清除</a>
                <?php endif; ?>
            </form>
            <a href="<?= Router::url('/sales/create') ?>" class="btn btn-primary">+ New Sale</a>
        </div>
    </div>

    <?php if (empty($sales)): ?>
        <p class="empty-state">暂无销售记录。</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>发票 #</th><th>客户</th><th>日期</th><?php if (!empty($customFields)) { include __DIR__ . '/../partials/custom_fields_headers.php'; } ?><th class="text-right">总计</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($sales as $s): ?>
            <?php $saleAttrs = json_decode($s['attributes'] ?? '{}', true) ?: []; ?>
            <tr>
                <td><a href="<?= Router::url('/sales/' . $s['id']) ?>"><?= htmlspecialchars($s['invoice_no']) ?></a></td>
                <td><?= htmlspecialchars($s['customer_name'] ?? '散客') ?></td>
                <td class="text-muted"><?= htmlspecialchars($s['sale_date']) ?></td>
                <?php $attrs = $saleAttrs; if (!empty($customFields)) { include __DIR__ . '/../partials/custom_fields_cells.php'; } ?>
                <td class="text-right"><?= money($s['total']) ?></td>
                <td><a href="<?= Router::url('/sales/' . $s['id']) ?>" class="btn btn-secondary btn-sm">查看</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php include __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</div>
