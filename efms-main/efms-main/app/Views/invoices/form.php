<div class="topbar">
    <h1>New Invoice</h1>
</div>

<?php if (!empty(errors())): ?>
    <div class="error-box">
        <?php foreach (errors() as $field => $errs): ?>
            <?php foreach ($errs as $err): ?>
                <div><?= e($err) ?></div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="/invoices">
        <?= csrf_field() ?>

        <div class="row">
            <div style="flex:1;">
                <label>Invoice #</label>
                <input name="invoice_number" value="<?= e(old('invoice_number', '')) ?>" placeholder="INV-1001" required>
            </div>
            <div style="flex:2;">
                <label>Customer Name</label>
                <input name="customer_name" value="<?= e(old('customer_name', '')) ?>" required>
            </div>
            <div style="flex:2;">
                <label>Customer Email</label>
                <input type="email" name="customer_email" value="<?= e(old('customer_email', '')) ?>">
            </div>
        </div>
        <div class="row">
            <div style="flex:1;">
                <label>Issue Date</label>
                <input type="date" name="issue_date" value="<?= e(old('issue_date', date('Y-m-d'))) ?>" required>
            </div>
            <div style="flex:1;">
                <label>Due Date</label>
                <input type="date" name="due_date" value="<?= e(old('due_date', date('Y-m-d', strtotime('+30 days')))) ?>" required>
            </div>
            <div style="flex:1;">
                <label>Tax Rate (e.g. 0.07 = 7%)</label>
                <input type="number" step="0.001" name="tax_rate" value="<?= e(old('tax_rate', '0')) ?>">
            </div>
        </div>

        <table class="line-items-table">
            <thead><tr><th>Description</th><th>Qty</th><th>Unit Price</th></tr></thead>
            <tbody>
                <?php 
                $oldDescs = old('description', []);
                $oldQtys = old('quantity', []);
                $oldPrices = old('unit_price', []);
                $rowCount = max(4, count($oldDescs));
                ?>
                <?php for ($i = 0; $i < $rowCount; $i++): ?>
                <tr>
                    <td><input type="text" name="description[]" value="<?= e($oldDescs[$i] ?? '') ?>"></td>
                    <td><input type="number" step="0.01" name="quantity[]" value="<?= e($oldQtys[$i] ?? '1') ?>"></td>
                    <td><input type="number" step="0.01" name="unit_price[]" value="<?= e($oldPrices[$i] ?? '0') ?>"></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <div class="row" style="margin-top:14px;">
            <div style="flex:1;">
                <label>Notes</label>
                <textarea name="notes" rows="2"><?= e(old('notes', '')) ?></textarea>
            </div>
        </div>

        <button type="submit" class="btn">Create Invoice</button>
        <a href="/invoices" class="btn secondary">Cancel</a>
    </form>
</div>
