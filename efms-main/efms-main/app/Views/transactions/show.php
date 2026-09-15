<div class="topbar">
    <div>
        <h1><?= e($entry['reference'] ?: ('Entry #' . $entry['id'])) ?></h1>
        <div class="muted"><?= e($entry['entry_date']) ?> · <?= e($entry['memo']) ?></div>
    </div>
</div>

<div class="card">
    <table>
        <thead><tr><th>Account</th><th>Debit</th><th>Credit</th><th>Memo</th></tr></thead>
        <tbody>
        <?php $totalDebit = 0; $totalCredit = 0; ?>
        <?php foreach ($lines as $line): $totalDebit += $line['debit']; $totalCredit += $line['credit']; ?>
            <tr>
                <td><?= e($line['account_code'] . ' — ' . $line['account_name']) ?></td>
                <td><?= $line['debit'] > 0 ? '$' . number_format($line['debit'], 2) : '' ?></td>
                <td><?= $line['credit'] > 0 ? '$' . number_format($line['credit'], 2) : '' ?></td>
                <td><?= e($line['memo']) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr style="font-weight:700;">
            <td>Total</td>
            <td>$<?= number_format($totalDebit, 2) ?></td>
            <td>$<?= number_format($totalCredit, 2) ?></td>
            <td></td>
        </tr>
        </tbody>
    </table>
</div>
