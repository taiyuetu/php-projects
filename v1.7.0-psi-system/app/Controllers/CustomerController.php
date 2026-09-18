<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\CsvImportExport;
use App\Core\Model;
use App\Models\Customer;

class CustomerController extends Controller
{
    use CsvImportExport;

    protected function csvModelClass(): string { return Customer::class; }
    protected function csvColumns(): array { return ['name', 'phone', 'email', 'address']; }
    protected function csvMatchField(): string { return 'name'; }
    protected function csvBasePath(): string { return '/customers'; }
    protected function csvEntityLabel(): string { return '客户'; }

    public function index(): void
    {
        $filters = ['q' => trim($this->input('q', ''))] + $this->customFieldFilters(Customer::customFields());
        $page = max(1, (int) $this->input('page', 1));

        $result = Customer::filterWithCustomFieldsPaginated($filters, ['name', 'phone', 'email'], 'name', $page);

        $this->view('customers/index', [
            'title'        => '客户列表',
            'customers'    => $result['rows'],
            'customFields' => Customer::customFields(),
            'filters'      => $filters,
            'pagination'   => $result,
        ]);
    }

    public function create(): void
    {
        $this->view('customers/form', [
            'title'        => '添加客户',
            'customer'     => null,
            'customFields' => Customer::customFields(),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        Customer::create([
            'name'       => trim($this->input('name')),
            'phone'      => trim($this->input('phone')),
            'email'      => trim($this->input('email')),
            'address'    => trim($this->input('address')),
            'attributes' => json_encode($this->collectAttributes(), JSON_UNESCAPED_UNICODE),
        ]);
        $this->flash('success', '客户添加成功。');
        $this->redirect('/customers');
    }

    public function edit(string $id): void
    {
        $customer = Customer::find($id);
        if (!$customer) { $this->flash('error', '客户未找到。'); $this->redirect('/customers'); }
        $this->view('customers/form', [
            'title'        => '编辑客户',
            'customer'     => $customer,
            'customFields' => Customer::customFields(),
        ]);
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $customer = Customer::find($id);
        if (!$customer) { $this->flash('error', '客户未找到。'); $this->redirect('/customers'); }
        $existingAttrs = Customer::parseCustomFields($customer['attributes'] ?? '{}');
        Customer::update($id, [
            'name'       => trim($this->input('name')),
            'phone'      => trim($this->input('phone')),
            'email'      => trim($this->input('email')),
            'address'    => trim($this->input('address')),
            'attributes' => json_encode($this->collectAttributes($existingAttrs), JSON_UNESCAPED_UNICODE),
        ]);
        $this->flash('success', '客户更新成功。');
        $this->redirect('/customers');
    }

    public function delete(string $id): void
    {
        $this->verifyCsrf();
        // Check if customer is referenced by any sales
        $saleCount = Model::raw('SELECT COUNT(*) AS cnt FROM sales WHERE customer_id = ?', [$id])[0]['cnt'] ?? 0;
        if ($saleCount > 0) {
            $this->flash('error', '无法删除此客户，因为它被现有的销售记录引用。');
            $this->redirect('/customers');
        }
        Model::raw("DELETE FROM change_logs WHERE table_name = 'customers' AND record_id = ?", [$id]);
        Customer::delete($id);
        $this->flash('success', '客户删除成功。');
        $this->redirect('/customers');
    }

    private function collectAttributes(array $existing = []): array
    {
        $attrs = $this->customFieldValues(Customer::customFields());
        return Customer::handleCustomFieldUploads(array_merge($existing, $attrs));
    }
}
