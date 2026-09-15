<div class="topbar">
    <div>
        <h1><?= e($account['code']) ?> — <?= e($account['name']) ?></h1>
        <div class="muted"><?= e(ucfirst($account['type'])) ?></div>
    </div>
    <a href="/accounts/<?= e($account['id']) ?>/edit" class="btn secondary">Edit</a>
</div>

<div class="card">
    <div class="stat-label">Current Balance</div>
    <div class="stat-value <?= $account['balance'] >= 0 ? 'positive' : 'negative' ?>">
        $<?= number_format($account['balance'], 2) ?>
    </div>
</div>

<?php if (!empty($account['description'])): ?>
<div class="card"><?= e($account['description']) ?></div>
<?php endif; ?>
