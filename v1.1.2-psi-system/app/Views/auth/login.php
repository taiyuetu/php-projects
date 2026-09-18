<?php use App\Core\Router; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login · PSI 系统</title>
    <link rel="stylesheet" href="<?= Router::url('/assets/css/style.css') ?>">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <h1>PSI System</h1>
        <p class="subtitle">采购 · 销售 · 库存管理系统</p>

        <?php if (!empty($_SESSION['flash']['error'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']['error']) ?></div>
            <?php unset($_SESSION['flash']['error']); ?>
        <?php endif; ?>

        <form method="post" action="<?= Router::url('/login') ?>">
            <?= $this->csrfField() ?>
            <div class="form-group">
                <label>邮箱</label>
                <input type="email" name="email" required autofocus value="admin@psi.local">
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" required value="admin123">
            </div>
            <button type="submit" class="btn btn-primary">登录</button>
        </form>

        <p class="login-hint">默认管理员：<strong>admin@psi.local</strong> / <strong>admin123</strong><br>如尚未初始化，请先运行 <code>php database/setup.php</code>。</p>
    </div>
</div>
</body>
</html>
