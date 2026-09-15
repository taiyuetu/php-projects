<div class="topbar">
    <div>
        <h1>Dashboard</h1>
        <div class="muted">Live balances from posted journal entries</div>
    </div>
</div>

<div class="grid grid-4">
    <?php foreach ($totalsByType as $type => $total): ?>
        <div class="card">
            <div class="stat-label"><?= e(ucfirst($type)) ?></div>
            <div class="stat-value <?= $total >= 0 ? 'positive' : 'negative' ?>">
                $<?= number_format($total, 2) ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <h2>Recent Journal Entries</h2>
    <table>
        <thead><tr><th>Date</th><th>Reference</th><th>Memo</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($recentEntries as $entry): ?>
            <tr>
                <td><?= e($entry['entry_date']) ?></td>
                <td><?= e($entry['reference']) ?></td>
                <td><?= e($entry['memo']) ?></td>
                <td><a href="/transactions/<?= e($entry['id']) ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($recentEntries)): ?>
            <tr><td colspan="4" class="muted">No journal entries yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Open Invoices</h2>
    <table>
        <thead><tr><th>Invoice #</th><th>Customer</th><th>Due</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($openInvoices as $invoice): ?>
            <tr>
                <td><a href="/invoices/<?= e($invoice['id']) ?>"><?= e($invoice['invoice_number']) ?></a></td>
                <td><?= e($invoice['customer_name']) ?></td>
                <td><?= e($invoice['due_date']) ?></td>
                <td>$<?= number_format((float) $invoice['total'], 2) ?></td>
                <td><span class="badge <?= e($invoice['status']) ?>"><?= e($invoice['status']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($openInvoices)): ?>
            <tr><td colspan="5" class="muted">No open invoices.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
