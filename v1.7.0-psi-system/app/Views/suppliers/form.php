<?php
use App\Core\Router;
$attrs = json_decode($supplier['attributes'] ?? '{}', true) ?: [];
?>
<div class="card" style="max-width:600px;">
    <h2><?= $supplier ? '编辑供应商' : '添加供应商' ?></h2>
    <form method="post" action="<?= $supplier ? Router::url('/suppliers/' . $supplier['id']) : Router::url('/suppliers') ?>">
        <?= $this->csrfField() ?>
        <div class="form-group">
            <label>名称</label>
            <input type="text" name="name" required value="<?= htmlspecialchars($supplier['name'] ?? '') ?>">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>电话</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($supplier['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>邮箱</label>
                <input type="email" name="email" value="<?= htmlspecialchars($supplier['email'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>地址</label>
            <textarea name="address" rows="2"><?= htmlspecialchars($supplier['address'] ?? '') ?></textarea>
        </div>
        <?php include __DIR__ . '/../partials/custom_fields_form.php'; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存</button>
            <a href="<?= Router::url('/suppliers') ?>" class="btn btn-secondary">取消</a>
        </div>
    </form>
</div>
