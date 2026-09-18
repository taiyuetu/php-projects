<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\PurchaseArrival;

class PurchaseController extends Controller
{
    public function index(): void
    {
        $q = trim($this->input('q', ''));
        $dateFrom = trim($this->input('date_from', ''));
        $dateTo   = trim($this->input('date_to', ''));
        $page = max(1, (int) $this->input('page', 1));

        $filters = ['q' => $q, 'date_from' => $dateFrom, 'date_to' => $dateTo]
                 + $this->customFieldFilters(Purchase::customFields());

        $cfFilters = $this->customFieldFilters(Purchase::customFields());
        $result = Purchase::filterPaginated($q, $dateFrom, $dateTo, $page, 20, $cfFilters);

        $this->view('purchases/index', [
            'title'        => '采购订单',
            'purchases'    => $result['rows'],
            'q'            => $q,
            'date_from'    => $dateFrom,
            'date_to'      => $dateTo,
            'customFields' => Purchase::customFields(),
            'filters'      => $filters,
            'pagination'   => $result,
        ]);
    }

    public function create(): void
    {
        $this->view('purchases/form', [
            'title'        => '新建采购',
            'suppliers'    => Supplier::all('name'),
            'products'     => Product::all('name'),
            'nextInvoice'  => 'PO-' . date('Ymd') . '-' . str_pad((string)(Purchase::count() + 1), 4, '0', STR_PAD_LEFT),
            'customFields' => Purchase::customFields(),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();

        $productIds = $this->input('product_id', []);
        $qtys       = $this->input('qty', []);
        $costs      = $this->input('unit_cost', []);

        $items = [];
        foreach ($productIds as $i => $productId) {
            if (!$productId) continue;

            $qty = (int) ($qtys[$i] ?? 0);
            $unitCost = (float) ($costs[$i] ?? 0);

            if ($qty <= 0) {
                $this->flash('error', '采购行数量必须大于0。');
                $this->redirect('/purchases/create');
            }
            if ($unitCost < 0) {
                $this->flash('error', '采购行成本不能为负数。');
                $this->redirect('/purchases/create');
            }

            $items[] = [
                'product_id' => (int) $productId,
                'qty'        => $qty,
                'unit_cost'  => $unitCost,
            ];
        }

        if (empty($items)) {
            $this->flash('error', '保存前至少添加一个产品行。');
            $this->redirect('/purchases/create');
        }

        // Collect and validate custom fields
        $cfValues = $this->customFieldValues(Purchase::customFields());
        $this->validateCustomFieldsOrFail(Purchase::class, $cfValues, '/purchases/create');

        $header = [
            'invoice_no'            => trim($this->input('invoice_no')),
            'supplier_id'           => (int) $this->input('supplier_id'),
            'purchase_date'         => $this->input('purchase_date', date('Y-m-d')),
            'expected_arrival_date' => $this->input('expected_arrival_date') ?: null,
            'actual_arrival_date'   => $this->input('actual_arrival_date') ?: null,
            'actual_arrival_qty'    => (int) $this->input('actual_arrival_qty', 0),
            'notes'                 => trim($this->input('notes', '')),
            'attributes'            => json_encode($cfValues, JSON_UNESCAPED_UNICODE),
            'created_by'            => Auth::user()['id'] ?? null,
        ];

        try {
            $id = Purchase::createWithItems($header, $items);
            $this->flash('success', '采购记录已保存。请确认到货以更新库存。');
            $this->redirect('/purchases/' . $id);
        } catch (\Throwable $e) {
            $this->flash('error', '无法保存采购: ' . $e->getMessage());
            $this->redirect('/purchases/create');
        }
    }

    public function show(string $id): void
    {
        $purchase = Purchase::withItems((int) $id);
        if (!$purchase) { $this->flash('error', '采购未找到。'); $this->redirect('/purchases'); }

        $this->view('purchases/show', [
            'title'        => '采购 #' . $purchase['invoice_no'],
            'purchase'     => $purchase,
            'customFields' => Purchase::customFields(),
        ]);
    }

    public function recordArrival(string $id): void
    {
        $this->verifyCsrf();

        $purchase = Purchase::find((int) $id);
        if (!$purchase) {
            $this->flash('error', '采购单未找到。');
            $this->redirect('/purchases');
        }

        $arrivalDate = $this->input('arrival_date', date('Y-m-d'));
        $qty = (int) $this->input('qty', 0);
        $notes = trim($this->input('notes', ''));

        if ($qty <= 0) {
            $this->flash('error', '到货数量必须大于0。');
            $this->redirect('/purchases/' . $id);
        }

        // Validate against remaining ordered qty
        $remaining = PurchaseArrival::remainingQty((int) $id);
        if ($remaining <= 0) {
            $this->flash('error', '该采购单已全部到货。');
            $this->redirect('/purchases/' . $id);
        }
        if ($qty > $remaining) {
            $this->flash('error', "到货数量 ({$qty}) 超过剩余订购数量 ({$remaining})。最多只能登记 {$remaining} 件。");
            $this->redirect('/purchases/' . $id);
        }

        try {
            PurchaseArrival::recordArrival((int) $id, $arrivalDate, $qty, $notes);
            $this->flash('success', '到货登记成功！库存已增加 ' . $qty . ' 件。');
            $this->redirect('/purchases/' . $id);
        } catch (\Throwable $e) {
            $this->flash('error', '无法登记到货：' . $e->getMessage());
            $this->redirect('/purchases/' . $id);
        }
    }
}
