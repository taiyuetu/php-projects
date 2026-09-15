<div class="topbar">
    <div>
        <h1>My Profile</h1>
        <div class="muted">Manage your account details and password</div>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="card" style="background:#f0fdf4; border-color:#bbf7d0; color:var(--green); font-weight:600; padding:12px 16px;">
        <?= e($success) ?>
    </div>
<?php endif; ?>

<?php if (!empty(errors())): ?>
    <div class="error-box">
        <?php foreach (errors() as $field => $errs): ?>
            <?php foreach ($errs as $err): ?>
                <div><?= e($err) ?></div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="grid" style="grid-template-columns: 1fr 1fr; max-width: 900px;">
    <div class="card">
        <h2>Account Information</h2>
        <div style="margin-top: 16px;">
            <label>Name</label>
            <div style="font-weight: 600; margin-bottom: 12px;"><?= e($user['name'] ?? 'User') ?></div>

            <label>Email Address</label>
            <div style="font-weight: 600; margin-bottom: 12px;"><?= e($user['email'] ?? '') ?></div>

            <label>Role</label>
            <div>
                <span class="badge posted"><?= e(ucfirst($user['role'] ?? 'viewer')) ?></span>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Change Password</h2>
        <form method="POST" action="/profile/password" style="margin-top: 16px;">
            <?= csrf_field() ?>

            <div style="margin-bottom: 12px;">
                <label>Current Password</label>
                <input type="password" name="current_password" required>
            </div>

            <div style="margin-bottom: 12px;">
                <label>New Password (min. 8 characters)</label>
                <input type="password" name="new_password" required minlength="8">
            </div>

            <div style="margin-bottom: 18px;">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required minlength="8">
            </div>

            <button type="submit" class="btn">Update Password</button>
        </form>
    </div>
</div>
