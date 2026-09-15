<div class="card shadow-lg border-0" style="width: 380px;">
    <div class="card-body p-4">
        <div class="text-center mb-4">
            <i class="bi bi-people-fill fs-1 text-primary"></i>
            <h4 class="mt-2 mb-0">HRMS Login</h4>
            <p class="text-muted small">Human Resource Management System</p>
        </div>

        <?php if (!empty($_SESSION['flash'])): ?>
            <?php foreach ($_SESSION['flash'] as $type => $message): ?>
                <div class="alert alert-danger py-2 small"><?php echo htmlspecialchars($message); ?></div>
            <?php endforeach; ?>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>

        <form method="POST" action="<?php echo BASE_URL; ?>/auth/login">
            <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Sign In</button>
        </form>

        <div class="text-center mt-3 small text-muted">
            Demo: <code>admin / password123</code>
        </div>
    </div>
</div>
