<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\Product;

class SaleController extends Controller
{
    public function index(): void
    {
        $q = trim($this->input('q', ''));
        $dateFrom = trim($this->input('date_from', ''));
        $dateTo   = trim($this->input('date_to', ''));
        $page = max(1, (int) $this->input('page', 1));

        $filters = ['q' => $q, 'date_from' => $dateFrom, 'date_to' => $dateTo]
                 + $this->customFieldFilters(Sale::customFields());

        $cfFilters = $this->customFieldFilters(Sale::customFields());
        $result = Sale::filterPaginated($q, $dateFrom, $dateTo, $page, 20, $cfFilters);

        $this->view('sales/index', [
            'title'        => '销售发票',
            'sales'        => $result['rows'],
            'q'            => $q,
            'date_from'    => $dateFrom,
            'date_to'      => $dateTo,
            'customFields' => Sale::customFields(),
            'filters'      => $filters,
            'pagination'   => $result,
        ]);
    }

    public function create(): void
    {
        $this->view('sales/form', [
            'title'        => '新建销售',
            'customers'    => Customer::all('name'),
            'products'     => Product::allWithCategory(),
            'nextInvoice'  => 'INV-' . date('Ymd') . '-' . str_pad((string)(Sale::count() + 1), 4, '0', STR_PAD_LEFT),
            'customFields' => Sale::customFields(),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();

        $productIds = $this->input('product_id', []);
        $qtys       = $this->input('qty', []);
        $prices     = $this->input('unit_price', []);

        $items = [];
        foreach ($productIds as $i => $productId) {
            if (!$productId) continue;

            $qty = (int) ($qtys[$i] ?? 0);
            $unitPrice = (float) ($prices[$i] ?? 0);

            if ($qty <= 0) {
                $this->flash('error', '销售行数量必须大于0。');
                $this->redirect('/sales/create');
            }
            if ($unitPrice < 0) {
                $this->flash('error', '销售行价格不能为负数。');
                $this->redirect('/sales/create');
            }

            $items[] = [
                'product_id' => (int) $productId,
                'qty'        => $qty,
                'unit_price' => $unitPrice,
            ];
        }

        if (empty($items)) {
            $this->flash('error', '保存前至少添加一个产品行。');
            $this->redirect('/sales/create');
        }

        // Collect and validate custom fields
        $cfValues = $this->customFieldValues(Sale::customFields());
        $this->validateCustomFieldsOrFail(Sale::class, $cfValues, '/sales/create');

        $header = [
            'invoice_no'  => trim($this->input('invoice_no')),
            'customer_id' => $this->input('customer_id') ?: null,
            'sale_date'   => $this->input('sale_date', date('Y-m-d')),
            'attributes'  => json_encode($cfValues, JSON_UNESCAPED_UNICODE),
            'created_by'  => Auth::user()['id'] ?? null,
        ];

        try {
            $id = Sale::createWithItems($header, $items);
            $this->flash('success', '销售记录已保存，库存已更新。');
            $this->redirect('/sales/' . $id);
        } catch (\Throwable $e) {
            $this->flash('error', '无法保存销售: ' . $e->getMessage());
            $this->redirect('/sales/create');
        }
    }

    public function show(string $id): void
    {
        $sale = Sale::withItems((int) $id);
        if (!$sale) { $this->flash('error', '销售未找到。'); $this->redirect('/sales'); }

        $this->view('sales/show', [
            'title'        => '销售 #' . $sale['invoice_no'],
            'sale'         => $sale,
            'customFields' => Sale::customFields(),
        ]);
    }
}
