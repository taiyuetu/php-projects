<div class="topbar">
    <div>
        <h1>Journal Entries</h1>
        <div class="muted">General ledger transaction history</div>
    </div>
    <a href="/transactions/create" class="btn">+ New Entry</a>
</div>

<div class="card">
    <table>
        <thead><tr><th>Date</th><th>Reference</th><th>Memo</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($entries as $entry): ?>
            <tr>
                <td><?= e($entry['entry_date']) ?></td>
                <td><?= e($entry['reference']) ?></td>
                <td><?= e($entry['memo']) ?></td>
                <td><a href="/transactions/<?= e($entry['id']) ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($entries)): ?>
            <tr><td colspan="4" class="muted">No journal entries yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>
