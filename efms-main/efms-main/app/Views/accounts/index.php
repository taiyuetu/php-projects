<div class="topbar">
    <div>
        <h1>Chart of Accounts</h1>
        <div class="muted">All ledger accounts and their current balances</div>
    </div>
    <a href="/accounts/create" class="btn">+ New Account</a>
</div>

<div class="card" style="margin-bottom:12px;">
    <a href="/accounts" class="btn small <?= !$activeType ? '' : 'secondary' ?>">All</a>
    <?php foreach ($types as $type): ?>
        <a href="/accounts?type=<?= e($type) ?>" class="btn small <?= $activeType === $type ? '' : 'secondary' ?>"><?= e(ucfirst($type)) ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <table>
        <thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Balance</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($accounts as $account): ?>
            <tr>
                <td><?= e($account['code']) ?></td>
                <td><a href="/accounts/<?= e($account['id']) ?>"><?= e($account['name']) ?></a></td>
                <td><?= e(ucfirst($account['type'])) ?></td>
                <td class="<?= $account['balance'] >= 0 ? 'positive' : 'negative' ?>">$<?= number_format($account['balance'], 2) ?></td>
                <td><a href="/accounts/<?= e($account['id']) ?>/edit">Edit</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($accounts)): ?>
            <tr><td colspan="5" class="muted">No accounts found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>
