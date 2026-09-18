<?php use App\Core\Router; ?>

<?php if (!empty($appVersion)): ?>
    <div class="dashboard-version">
        Version <?= htmlspecialchars($appVersion) ?>
    </div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat-card accent-blue">
        <div class="label">总产品数</div>
        <div class="value"><?= $totalProducts ?></div>
    </div>
    <div class="stat-card accent-green">
        <div class="label">销售总额</div>
        <div class="value"><?= money($salesTotal) ?></div>
    </div>
    <div class="stat-card accent-amber">
        <div class="label">采购支出</div>
        <div class="value"><?= money($purchaseTotal) ?></div>
    </div>
    <div class="stat-card accent-red">
        <div class="label">低库存商品</div>
        <div class="value"><?= count($lowStock) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">库存价值（成本）</div>
        <div class="value"><?= money($stockValue) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">订单（采购/销售）</div>
        <div class="value"><?= $totalPurchases ?> / <?= $totalSales ?></div>
    </div>
</div>

<div class="form-row">
    <div class="card">
        <h2>⚠️ 低库存警报</h2>
        <?php if (empty($lowStock)): ?>
            <p class="empty-state">所有产品库存充足。🎉</p>
        <?php else: ?>
            <div class="table-wrap">
            <table>
                <thead><tr><th>产品</th><th>数量</th><th>库存预警</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($lowStock, 0, 8) as $p): ?>
                    <tr>
                        <td><a href="<?= Router::url('/products/' . $p['id']) ?>"><?= htmlspecialchars($p['name']) ?></a></td>
                        <td><span class="badge badge-red"><?= $p['quantity'] ?></span></td>
                        <td><?= $p['reorder_level'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p><a href="<?= Router::url('/inventory/low-stock') ?>">查看全部 &rarr;</a></p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>📒 最近库存变动</h2>
        <?php if (empty($recentTransactions)): ?>
            <p class="empty-state">暂无库存变动。</p>
        <?php else: ?>
            <div class="table-wrap">
            <table>
                <thead><tr><th>产品</th><th>类型</th><th>变动</th><th>余额</th><th>日期</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($recentTransactions, 0, 8) as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['product_name']) ?></td>
                        <td>
                            <?php $badge = ['purchase' => 'badge-blue', 'sale' => 'badge-green', 'adjustment' => 'badge-amber'][$t['type']] ?? 'badge-gray'; ?>
                            <span class="badge <?= $badge ?>"><?= ['purchase'=>'采购','sale'=>'销售','adjustment'=>'调整','purchase_arrival'=>'采购到货'][$t['type']] ?? htmlspecialchars($t['type']) ?></span>
                        </td>
                        <td><?= $t['qty_change'] > 0 ? '+' . $t['qty_change'] : $t['qty_change'] ?></td>
                        <td><?= $t['balance_after'] ?></td>
                        <td class="text-muted"><?= htmlspecialchars(substr($t['created_at'], 0, 16)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p><a href="<?= Router::url('/inventory') ?>">查看完整账本 &rarr;</a></p>
        <?php endif; ?>
    </div>
</div>
