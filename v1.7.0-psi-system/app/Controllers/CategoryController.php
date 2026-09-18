<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Model;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index(): void
    {
        $filters = ['q' => trim($this->input('q', ''))] + $this->customFieldFilters(Category::customFields());
        $page = max(1, (int) $this->input('page', 1));

        $result = Category::filterWithCustomFieldsPaginated($filters, ['name'], 'name', $page);

        $this->view('categories/index', [
            'title'        => '分类管理',
            'categories'   => $result['rows'],
            'customFields' => Category::customFields(),
            'filters'      => $filters,
            'pagination'   => $result,
        ]);
    }

    public function create(): void
    {
        $this->view('categories/form', [
            'title'        => '添加分类',
            'category'     => null,
            'customFields' => Category::customFields(),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $name = trim($this->input('name'));
        if ($name === '') {
            $this->flash('error', '分类名称不能为空。');
            $this->redirect('/categories/create');
        }
        if (Category::exists('name', $name)) {
            $this->flash('error', '名为 "' . htmlspecialchars($name) . '" 的分类已存在。');
            $this->redirect('/categories/create');
        }
        Category::create([
            'name'       => $name,
            'attributes' => json_encode($this->collectAttributes(), JSON_UNESCAPED_UNICODE),
        ]);
        $this->flash('success', '分类添加成功。');
        $this->redirect('/categories');
    }

    public function edit(string $id): void
    {
        $category = Category::find($id);
        if (!$category) { $this->flash('error', '分类未找到。'); $this->redirect('/categories'); }
        $this->view('categories/form', [
            'title'        => '编辑分类',
            'category'     => $category,
            'customFields' => Category::customFields(),
        ]);
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $name = trim($this->input('name'));
        if ($name === '') {
            $this->flash('error', '分类名称不能为空。');
            $this->redirect('/categories/' . $id . '/edit');
        }
        if (Category::exists('name', $name, (int)$id)) {
            $this->flash('error', '名为 "' . htmlspecialchars($name) . '" 的分类已存在。');
            $this->redirect('/categories/' . $id . '/edit');
        }
        $category = Category::find($id);
        if (!$category) { $this->flash('error', '分类未找到。'); $this->redirect('/categories'); }
        $existingAttrs = Category::parseCustomFields($category['attributes'] ?? '{}');
        Category::update($id, [
            'name'       => $name,
            'attributes' => json_encode($this->collectAttributes($existingAttrs), JSON_UNESCAPED_UNICODE),
        ]);
        $this->flash('success', '分类更新成功。');
        $this->redirect('/categories');
    }

    public function delete(string $id): void
    {
        $this->verifyCsrf();
        Model::raw("DELETE FROM change_logs WHERE table_name = 'categories' AND record_id = ?", [$id]);
        Category::delete($id);
        $this->flash('success', '分类删除成功。');
        $this->redirect('/categories');
    }

    private function collectAttributes(array $existing = []): array
    {
        $attrs = $this->customFieldValues(Category::customFields());
        return Category::handleCustomFieldUploads(array_merge($existing, $attrs));
    }
}
