<?php
/** @var string $csrf @var array $category @var array $old @var array $errors @var string $type
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
$typeLabel = Category::typeLabel($type ?? 'product');
?>
<h3 class="mb-4"><i class="bi bi-pencil-square me-2"></i>编辑<?= e($typeLabel) ?></h3>

<?php
// 编辑表单要 PUT 到本分类；视图负责给定 action / editing，_form 才走更新分支。
$action = url('/categories/' . (int) $category['id'] . (($type ?? 'product') === 'product' ? '' : '?type=' . $type));
$editing = true;
$submitText = '保存修改';
include __DIR__ . '/_form.php';
?>
