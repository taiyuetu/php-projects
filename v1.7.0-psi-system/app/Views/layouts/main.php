<?php
use App\Core\Auth;
use App\Core\Router;
$currentPath = '/' . trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$isActive = fn(string $prefix) => str_starts_with($currentPath, $prefix) ? 'active' : '';
$user = Auth::user();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'PSI System') ?> · <?= htmlspecialchars(setting('app_name', 'PSI 系统')) ?></title>
    <link rel="stylesheet" href="<?= Router::url('/assets/css/style.css') ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">PSI<span>·</span>System</div>
        <nav>
            <a href="<?= Router::url('/dashboard') ?>" class="<?= $isActive('/dashboard') ?>">📊 仪表盘</a>

            <div class="section-title">商品目录</div>
            <a href="<?= Router::url('/products') ?>" class="<?= $isActive('/products') ?>">📦 产品管理</a>
            <a href="<?= Router::url('/categories') ?>" class="<?= $isActive('/categories') ?>">🏷️ 分类管理</a>

            <div class="section-title">业务交易</div>
            <a href="<?= Router::url('/purchases') ?>" class="<?= $isActive('/purchases') ?>">🛒 采购管理</a>
            <a href="<?= Router::url('/sales') ?>" class="<?= $isActive('/sales') ?>">💵 销售管理</a>

            <div class="section-title">库存</div>
            <a href="<?= Router::url('/inventory') ?>" class="<?= $currentPath === '/inventory' ? 'active' : '' ?>">📒 库存流水</a>
            <a href="<?= Router::url('/inventory/low-stock') ?>" class="<?= $isActive('/inventory/low-stock') ?>">⚠️ 低库存</a>

            <div class="section-title">联系人</div>
            <a href="<?= Router::url('/suppliers') ?>" class="<?= $isActive('/suppliers') ?>">🏭 供应商</a>
            <a href="<?= Router::url('/customers') ?>" class="<?= $isActive('/customers') ?>">🧑‍💼 客户</a>

            <div class="section-title">报表</div>
            <a href="<?= Router::url('/reports/sales') ?>" class="<?= $isActive('/reports/sales') ?>">📈 销售报表</a>
            <a href="<?= Router::url('/reports/purchases') ?>" class="<?= $isActive('/reports/purchases') ?>">📉 采购报表</a>
            <a href="<?= Router::url('/reports/stock') ?>" class="<?= $isActive('/reports/stock') ?>">🧮 库存估值</a>
            
            <div class="section-title">系统</div>
            <a href="<?= Router::url('/changelogs') ?>" class="<?= $isActive('/changelogs') ?>">📋 变更日志</a>
            <?php if (($user['role'] ?? '') === 'admin'): ?>
                <a href="<?= Router::url('/admin/changelogs') ?>" class="<?= $isActive('/admin/changelogs') ?>">⚙️ 日志管理</a>
                <a href="<?= Router::url('/settings') ?>" class="<?= $isActive('/settings') ?>">🛠️ 系统设置</a>
            <?php endif; ?>
        </nav>
    </aside>

    <div class="main">
        <div class="topbar">
            <h1><?= htmlspecialchars($title ?? '') ?></h1>
            <div class="user-menu">
                <span><?= htmlspecialchars($user['name'] ?? '') ?> <span class="badge badge-gray"><?= htmlspecialchars($user['role'] ?? '') ?></span></span>
                <a href="<?= Router::url('/logout') ?>" class="btn btn-secondary btn-sm">退出登录</a>
            </div>
        </div>

        <div class="content">
            <?php if (!empty($_SESSION['flash']['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash']['success']) ?></div>
                <?php unset($_SESSION['flash']['success']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['flash']['error'])): ?>
                <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']['error']) ?></div>
                <?php unset($_SESSION['flash']['error']); ?>
            <?php endif; ?>

            <?= $content ?>
        </div>
    </div>
</div>
</body>
</html>
