<?php use App\Core\Router; ?>
<div class="card">
    <h2>低库存警报</h2>
    <p class="text-muted">库存达到或低于库存预警线的产品。</p>

    <?php if (empty($products)): ?>
        <p class="empty-state">所有产品库存充足。🎉</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>SKU</th><th>产品</th><th>分类</th><th>数量</th><th>库存预警</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr>
                <td class="text-muted"><?= htmlspecialchars($p['sku']) ?></td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                <td><span class="badge badge-red"><?= $p['quantity'] ?></span></td>
                <td><?= $p['reorder_level'] ?></td>
                <td><a href="<?= Router::url('/purchases/create') ?>" class="btn btn-primary btn-sm">补货</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
