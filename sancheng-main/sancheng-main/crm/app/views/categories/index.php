<?php
/** @var array $categories @var string $csrf @var string $type
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
$type = $type ?? 'product';
$typeLabel = Category::typeLabel($type);
$isLeadSource = $type === 'lead_source';
$countLabel = $isLeadSource ? '线索数量' : '商品数量';
$icon = $isLeadSource ? 'bi-funnel' : 'bi-tags';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0"><i class="bi <?= e($icon) ?> me-2"></i><?= e($typeLabel) ?></h3>
    <a href="<?= url('/categories/create' . ($isLeadSource ? '?type=lead_source' : '')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> 新增分类</a>
</div>

<ul class="nav nav-pills mb-3">
    <li class="nav-item">
        <a class="nav-link <?= !$isLeadSource ? 'active' : '' ?>" href="<?= url('/categories') ?>">
            <i class="bi bi-tags"></i> 商品分类
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $isLeadSource ? 'active' : '' ?>" href="<?= url('/categories?type=lead_source') ?>">
            <i class="bi bi-funnel"></i> 线索来源分类
        </a>
    </li>
</ul>

<div class="card card-table">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>分类名称</th>
                    <th class="text-center">排序</th>
                    <th class="text-center"><?= e($countLabel) ?></th>
                    <th class="text-center">状态</th>
                    <th class="text-end">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            暂无分类，点击右上角「新增分类」创建第一个。
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td>
                                <strong><?= e($cat['name']) ?></strong>
                            </td>
                            <td class="text-center"><?= (int) $cat['sort_order'] ?></td>
                            <td class="text-center">
                                <span class="badge bg-secondary"><?= (int) $cat['usage_count'] ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($cat['status'] === 'active'): ?>
                                    <span class="badge bg-success">启用</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">停用</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= url('/categories/' . $cat['id'] . '/edit' . ($isLeadSource ? '?type=lead_source' : '')) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="<?= url('/categories/' . $cat['id']) ?>" class="d-inline"
                                      onsubmit="return confirm('确定删除该分类吗？其下<?= $isLeadSource ? '线索' : '商品' ?>不会被删除，只是清空分类。');">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    <a href="<?= url($isLeadSource ? '/leads' : '/products') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> 返回<?= $isLeadSource ? '线索列表' : '商品库' ?>
    </a>
</div>
