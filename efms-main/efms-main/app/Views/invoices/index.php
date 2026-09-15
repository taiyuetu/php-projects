<div class="topbar">
    <div>
        <h1>Invoices</h1>
        <div class="muted">Accounts receivable</div>
    </div>
    <a href="/invoices/create" class="btn">+ New Invoice</a>
</div>

<div class="card">
    <table>
        <thead><tr><th>Invoice #</th><th>Customer</th><th>Issued</th><th>Due</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($invoices as $invoice): ?>
            <tr>
                <td><a href="/invoices/<?= e($invoice['id']) ?>"><?= e($invoice['invoice_number']) ?></a></td>
                <td><?= e($invoice['customer_name']) ?></td>
                <td><?= e($invoice['issue_date']) ?></td>
                <td><?= e($invoice['due_date']) ?></td>
                <td>$<?= number_format((float) $invoice['total'], 2) ?></td>
                <td><span class="badge <?= e($invoice['status']) ?>"><?= e($invoice['status']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($invoices)): ?>
            <tr><td colspan="6" class="muted">No invoices yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>
