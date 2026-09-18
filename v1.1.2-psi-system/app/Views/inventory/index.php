<?php
use App\Core\Router;
$customFields = $customFields ?? [];
$filters = $filters ?? [];
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div>
            <h2 style="margin:0;">完整库存流水</h2>
            <p class="text-muted" style="margin:4px 0 0;">所有产品的全部库存变动事件——采购、销售和手动调整——按时间倒序排列。</p>
        </div>
        <form method="get" action="<?= Router::url('/inventory') ?>" class="search-form" style="display:flex;gap:8px;align-items:center;">
            <input type="search" name="q" placeholder="搜索流水..." value="<?= htmlspecialchars($q ?? '') ?>">
            <?php include __DIR__ . '/../partials/custom_fields_filters.php'; ?>
            <button type="submit" class="btn btn-secondary btn-sm">筛选</button>
            <?php if (($q ?? '') !== ''): ?>
                <a href="<?= Router::url('/inventory') ?>" class="btn btn-secondary btn-sm">清除</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($transactions)): ?>
        <p class="empty-state">暂无库存变动记录。</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>日期</th><th>SKU</th><th>产品</th><th>类型</th><th>变动</th><th>变动后余额</th><th>参考</th><?php if (!empty($customFields)) { include __DIR__ . '/../partials/custom_fields_headers.php'; } ?></tr></thead>
        <tbody>
        <?php foreach ($transactions as $t): ?>
            <?php $attrs = json_decode($t['attributes'] ?? '{}', true) ?: []; ?>
            <tr>
                <td class="text-muted"><?= htmlspecialchars(substr($t['created_at'], 0, 16)) ?></td>
                <td class="text-muted"><?= htmlspecialchars($t['sku']) ?></td>
                <td><a href="<?= Router::url('/products/' . $t['product_id']) ?>"><?= htmlspecialchars($t['product_name']) ?></a></td>
                <td>
                    <?php $badge = ['purchase' => 'badge-blue', 'sale' => 'badge-green', 'adjustment' => 'badge-amber', 'purchase_arrival' => 'badge-blue'][$t['type']] ?? 'badge-gray'; ?>
                    <span class="badge <?= $badge ?>"><?= ['purchase'=>'采购','sale'=>'销售','adjustment'=>'调整','purchase_arrival'=>'采购到货'][$t['type']] ?? htmlspecialchars($t['type']) ?></span>
                </td>
                <td><?= $t['qty_change'] > 0 ? '+' . $t['qty_change'] : $t['qty_change'] ?></td>
                <td><?= $t['balance_after'] ?></td>
                <td class="text-muted"><?= htmlspecialchars($t['reference']) ?></td>
                <?php if (!empty($customFields)) { include __DIR__ . '/../partials/custom_fields_cells.php'; } ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php include __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</div>
