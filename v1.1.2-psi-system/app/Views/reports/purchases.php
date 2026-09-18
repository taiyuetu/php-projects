<?php use App\Core\Router; ?>
<div class="card">
    <h2>采购报表</h2>
    <form method="get" action="<?= Router::url('/reports/purchases') ?>" class="form-row" style="align-items:end;">
        <div class="form-group">
            <label>开始日期</label>
            <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
        </div>
        <div class="form-group">
            <label>结束日期</label>
            <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary">筛选</button>
        </div>
    </form>

    <?php if (empty($rows)): ?>
        <p class="empty-state">该日期范围内没有采购记录。</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>发票号</th><th>日期</th><th>供应商</th><th class="text-right">总计</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['invoice_no']) ?></td>
                <td class="text-muted"><?= htmlspecialchars($r['purchase_date']) ?></td>
                <td><?= htmlspecialchars($r['supplier_name']) ?></td>
                <td class="text-right">$<?= number_format($r['total'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="3" class="text-right"><strong>总计</strong></td><td class="text-right"><strong>$<?= number_format($total, 2) ?></strong></td></tr>
        </tfoot>
    </table>
    </div>
    <?php endif; ?>
</div>
