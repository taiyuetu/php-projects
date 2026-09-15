<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login · EFMS</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <h1>EFMS</h1>
        <p class="muted">Enterprise Financial Management System</p>
        <?php if (!empty($error)): ?>
            <div class="error-box"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="/login">
            <?= csrf_field() ?>
            <label>Email</label>
            <input type="email" name="email" value="<?= e(old('email', '')) ?>" required style="margin-bottom:12px;">
            <label>Password</label>
            <input type="password" name="password" required style="margin-bottom:18px;">
            <button type="submit" class="btn" style="width:100%;">Sign in</button>
        </form>

        <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: 6px; padding: 12px; margin-top: 20px; font-size: 12px;">
            <div style="font-weight: 600; margin-bottom: 6px; color: var(--navy);">Default Credentials</div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                <span class="muted">Email:</span>
                <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px;">admin@example.com</code>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <span class="muted">Password:</span>
                <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px;">password</code>
            </div>
            <button type="button" onclick="fillCredentials()" class="btn secondary small" style="width:100%; text-align:center;">Auto-fill</button>
        </div>
    </div>
</div>
<script>
function fillCredentials() {
    document.querySelector('input[name="email"]').value = 'admin@example.com';
    document.querySelector('input[name="password"]').value = 'password';
}
</script>
</body>
</html>
