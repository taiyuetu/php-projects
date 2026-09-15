<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($title ?? 'EFMS') ?> · Enterprise Financial Management System</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="app-shell">
    <nav class="sidebar">
        <div class="brand">EFMS</div>
        <a href="/dashboard">Dashboard</a>
        <a href="/accounts">Chart of Accounts</a>
        <a href="/transactions">Journal Entries</a>
        <a href="/invoices">Invoices</a>
        <a href="/profile">My Profile</a>
        <form method="POST" action="/logout" style="margin-top: 24px;">
            <?= csrf_field() ?>
            <button type="submit" class="btn secondary small" style="margin-left:20px;">Log out</button>
        </form>
    </nav>
    <main class="main">
        <?= $slot ?? '' ?>
    </main>
</div>
</body>
</html>
