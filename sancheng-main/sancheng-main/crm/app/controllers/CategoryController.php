<?php

/**
 * 分类管理控制器（商品分类 / 线索来源分类，用 ?type= 区分）
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
class CategoryController extends Controller
{
    /** 当前管理的分类类型（URL ?type=，默认商品分类） */
    private function currentType(): string
    {
        $type = (string) ($_GET['type'] ?? $_POST['type'] ?? 'product');
        return Category::isValidType($type) ? $type : 'product';
    }

    /** 携带 type 参数的分类列表地址 */
    private function listUrl(string $type): string
    {
        return $type === 'product' ? '/categories' : '/categories?type=' . $type;
    }

    public function index(): void
    {
        $this->requireAuth();

        $type = $this->currentType();
        $model = $this->model('Category');

        $this->view('categories/index', [
            'type'       => $type,
            'categories' => $model->allWithCount($type),
            'csrf'       => $this->csrfToken(),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $this->view('categories/create', [
            'type' => $this->currentType(),
            'csrf' => $this->csrfToken(),
            'old'  => [],
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $type = $this->currentType();
        $model = $this->model('Category');
        $_POST['type'] = $type;                 // 类型由页面上下文决定，不信任表单改写
        [$data, $errors] = $model->sanitizeInput($_POST);

        if ($errors) {
            $this->view('categories/create', [
                'type'   => $type,
                'csrf'   => $this->csrfToken(),
                'old'    => $_POST,
                'errors' => $errors,
            ]);
            return;
        }

        $model->create($data);
        $this->setFlash('success', Category::typeLabel($type) . '「' . $data['name'] . '」已创建。');
        $this->redirect($this->listUrl($type));
    }

    public function edit(int $id): void
    {
        $this->requireAuth();

        $model = $this->model('Category');
        $category = $model->find($id);
        if (!$category) {
            $this->setFlash('error', '分类不存在。');
            $this->redirect('/categories');
            return;
        }

        $type = Category::isValidType((string) ($category['type'] ?? '')) ? (string) $category['type'] : 'product';

        $this->view('categories/edit', [
            'type'     => $type,
            'csrf'     => $this->csrfToken(),
            'category' => $category,
            'old'      => $category,
            'errors'   => [],
        ]);
    }

    public function update(int $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $model = $this->model('Category');
        $category = $model->find($id);
        if (!$category) {
            $this->setFlash('error', '分类不存在。');
            $this->redirect('/categories');
            return;
        }

        $type = Category::isValidType((string) ($category['type'] ?? '')) ? (string) $category['type'] : 'product';
        // 类型创建后不可改：改了会把商品分类悄悄变成线索分类，引用关系全乱
        $_POST['type'] = $type;
        [$data, $errors] = $model->sanitizeInput($_POST, ['selfId' => $id]);

        if ($errors) {
            $this->view('categories/edit', [
                'type'     => $type,
                'csrf'     => $this->csrfToken(),
                'category' => $category,
                'old'      => $_POST,
                'errors'   => $errors,
            ]);
            return;
        }

        $model->update($id, $data);
        $this->setFlash('success', Category::typeLabel($type) . '「' . $data['name'] . '」已更新。');
        $this->redirect($this->listUrl($type));
    }

    public function destroy(int $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $model = $this->model('Category');
        $category = $model->find($id);
        if (!$category) {
            $this->setFlash('error', '分类不存在。');
            $this->redirect('/categories');
            return;
        }

        $type = Category::isValidType((string) ($category['type'] ?? '')) ? (string) $category['type'] : 'product';
        $count = $type === 'lead_source' ? $model->leadSourceCount($id) : $model->productCount($id);
        $usageLabel = $type === 'lead_source' ? '条线索' : '个商品';
        $model->delete($id);

        $msg = Category::typeLabel($type) . '「' . $category['name'] . '」已删除。';
        if ($count > 0) {
            $msg .= ' ' . $count . ' ' . $usageLabel . '的分类已清空（记录本身不受影响）。';
        }
        $this->setFlash('success', $msg);
        $this->redirect($this->listUrl($type));
    }
}
