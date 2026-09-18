<?php
use App\Core\Router;
$attrs = json_decode($category['attributes'] ?? '{}', true) ?: [];
?>
<div class="card" style="max-width:480px;">
    <h2><?= $category ? '编辑分类' : '添加分类' ?></h2>
    <form method="post" action="<?= $category ? Router::url('/categories/' . $category['id']) : Router::url('/categories') ?>">
        <?= $this->csrfField() ?>
        <div class="form-group">
            <label>名称</label>
            <input type="text" name="name" required value="<?= htmlspecialchars($category['name'] ?? '') ?>">
        </div>
        <?php include __DIR__ . '/../partials/custom_fields_form.php'; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存</button>
            <a href="<?= Router::url('/categories') ?>" class="btn btn-secondary">取消</a>
        </div>
    </form>
</div>
