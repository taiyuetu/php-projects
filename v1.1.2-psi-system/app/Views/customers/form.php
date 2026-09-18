<?php
use App\Core\Router;
$attrs = json_decode($customer['attributes'] ?? '{}', true) ?: [];
?>
<div class="card" style="max-width:600px;">
    <h2><?= $customer ? '编辑客户' : '添加客户' ?></h2>
    <form method="post" action="<?= $customer ? Router::url('/customers/' . $customer['id']) : Router::url('/customers') ?>">
        <?= $this->csrfField() ?>
        <div class="form-group">
            <label>名称</label>
            <input type="text" name="name" required value="<?= htmlspecialchars($customer['name'] ?? '') ?>">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>电话</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>邮箱</label>
                <input type="email" name="email" value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>地址</label>
            <textarea name="address" rows="2"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
        </div>
        <?php include __DIR__ . '/../partials/custom_fields_form.php'; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存</button>
            <a href="<?= Router::url('/customers') ?>" class="btn btn-secondary">取消</a>
        </div>
    </form>
</div>
