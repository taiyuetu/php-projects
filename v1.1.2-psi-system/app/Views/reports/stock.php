<?php use App\Core\Router; ?>
<div class="card">
    <h2>库存估值报表</h2>
    <p class="text-muted">按成本价计算各产品的当前库存价值。</p>

    <?php if (empty($products)): ?>
        <p class="empty-state">暂无产品。</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>SKU</th><th>产品</th><th>分类</th><th class="text-right">数量</th><th class="text-right">成本价</th><th class="text-right">价值</th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr>
                <td class="text-muted"><?= htmlspecialchars($p['sku']) ?></td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                <td class="text-right"><?= $p['quantity'] ?></td>
                <td class="text-right">$<?= number_format($p['cost_price'], 2) ?></td>
                <td class="text-right">$<?= number_format($p['quantity'] * $p['cost_price'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="5" class="text-right"><strong>库存总价值</strong></td><td class="text-right"><strong>$<?= number_format($totalValue, 2) ?></strong></td></tr>
        </tfoot>
    </table>
    </div>
    <?php endif; ?>
</div>
