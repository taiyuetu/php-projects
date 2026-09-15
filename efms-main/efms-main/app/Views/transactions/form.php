<div class="topbar">
    <h1>New Journal Entry</h1>
</div>

<?php 
$allErrors = array_merge(
    is_array($errors ?? null) ? $errors : [],
    is_array(errors()) ? array_merge(...array_values(errors())) : []
);
?>

<?php if (!empty($allErrors)): ?>
    <div class="error-box">
        <?php foreach ($allErrors as $err): ?>
            <div><?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="/transactions">
        <?= csrf_field() ?>

        <div class="row">
            <div style="flex:1;">
                <label>Entry Date</label>
                <input type="date" name="entry_date" value="<?= e(old('entry_date', date('Y-m-d'))) ?>" required>
            </div>
            <div style="flex:1;">
                <label>Reference</label>
                <input name="reference" value="<?= e(old('reference', '')) ?>" placeholder="e.g. JE-1001">
            </div>
            <div style="flex:2;">
                <label>Memo</label>
                <input name="memo" value="<?= e(old('memo', '')) ?>" placeholder="What is this entry for?">
            </div>
        </div>

        <table class="line-items-table" id="lines-table">
            <thead><tr><th>Account</th><th>Debit</th><th>Credit</th><th>Line Memo</th></tr></thead>
            <tbody id="lines-body">
                <?php 
                $oldAccounts = old('account_id', []);
                $oldDebits = old('debit', []);
                $oldCredits = old('credit', []);
                $oldMemos = old('line_memo', []);
                $rowCount = max(4, count($oldAccounts));
                ?>
                <?php for ($i = 0; $i < $rowCount; $i++): ?>
                <tr>
                    <td>
                        <select name="account_id[]">
                            <option value="">— select —</option>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= e($acc['id']) ?>" <?= ((string) ($oldAccounts[$i] ?? '') === (string) $acc['id']) ? 'selected' : '' ?>>
                                    <?= e($acc['code'] . ' — ' . $acc['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="number" step="0.01" name="debit[]" value="<?= e($oldDebits[$i] ?? '0') ?>"></td>
                    <td><input type="number" step="0.01" name="credit[]" value="<?= e($oldCredits[$i] ?? '0') ?>"></td>
                    <td><input type="text" name="line_memo[]" value="<?= e($oldMemos[$i] ?? '') ?>"></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <p class="muted">Total debits must equal total credits before the entry can be posted.</p>
        <button type="submit" class="btn">Post Entry</button>
        <a href="/transactions" class="btn secondary">Cancel</a>
    </form>
</div>
