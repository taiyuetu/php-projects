<div class="topbar">
    <div>
        <h1><?= e($invoice['invoice_number']) ?></h1>
        <div class="muted"><?= e($invoice['customer_name']) ?> · <?= e($invoice['customer_email']) ?></div>
    </div>
    <span class="badge <?= e($invoice['status']) ?>"><?= e($invoice['status']) ?></span>
</div>

<div class="card">
    <table>
        <thead><tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Amount</th></tr></thead>
        <tbody>
        <?php foreach ($invoice['items'] as $item): ?>
            <tr>
                <td><?= e($item['description']) ?></td>
                <td><?= e($item['quantity']) ?></td>
                <td>$<?= number_format($item['unit_price'], 2) ?></td>
                <td>$<?= number_format($item['amount'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div style="text-align:right; margin-top:14px; font-size:14px;">
        <div>Subtotal: $<?= number_format($invoice['subtotal'], 2) ?></div>
        <div>Tax: $<?= number_format($invoice['tax'], 2) ?></div>
        <div style="font-weight:700; font-size:16px;">Total: $<?= number_format($invoice['total'], 2) ?></div>
    </div>
</div>

<?php if ($invoice['status'] === 'draft'): ?>
<div class="card">
    <h2>Post to General Ledger</h2>
    <p class="muted">Debits Accounts Receivable and credits Revenue for the invoice total.</p>
    <form method="POST" action="/invoices/<?= e($invoice['id']) ?>/post">
        <?= csrf_field() ?>
        <div class="row">
            <div style="flex:1;">
                <label>A/R Account</label>
                <select name="ar_account_id" required>
                    <?php foreach ($postableAccounts as $acc): if ($acc['type'] !== 'asset') continue; ?>
                        <option value="<?= e($acc['id']) ?>"><?= e($acc['code'] . ' — ' . $acc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1;">
                <label>Revenue Account</label>
                <select name="revenue_account_id" required>
                    <?php foreach ($postableAccounts as $acc): if ($acc['type'] !== 'revenue') continue; ?>
                        <option value="<?= e($acc['id']) ?>"><?= e($acc['code'] . ' — ' . $acc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit" class="btn">Post Invoice</button>
    </form>
</div>
<?php endif; ?>
