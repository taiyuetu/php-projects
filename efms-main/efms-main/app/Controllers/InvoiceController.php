<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Account;
use App\Models\Invoice;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query('page', 1));
        $paginated = Invoice::query()->orderBy('issue_date', 'DESC')->paginate($page, 15);

        return $this->view('invoices.index', [
            'invoices'   => $paginated['data'],
            'pagination' => $paginated,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('invoices.form', []);
    }

    public function store(Request $request): Response
    {
        $header = $this->validate($request, [
            'invoice_number' => 'required|max:50',
            'customer_name'  => 'required|max:255',
            'customer_email' => 'email',
            'issue_date'     => 'required|date',
            'due_date'       => 'required|date',
        ]);
        $header['created_by'] = $this->currentUserId();

        $descriptions = $request->input('description', []);
        $quantities = $request->input('quantity', []);
        $prices = $request->input('unit_price', []);

        $items = [];
        foreach ($descriptions as $i => $description) {
            if ($description === '') {
                continue;
            }
            $items[] = [
                'description' => $description,
                'quantity'    => (float) ($quantities[$i] ?? 1),
                'unit_price'  => (float) ($prices[$i] ?? 0),
            ];
        }

        $invoice = Invoice::createWithItems($header, $items, (float) $request->input('tax_rate', 0));

        return $this->redirect('/invoices/' . $invoice['id']);
    }

    public function show(Request $request): Response
    {
        $invoice = Invoice::withItems((int) $request->param('id'));
        if ($invoice === null) {
            return Response::html('404 Not Found', 404);
        }

        return $this->view('invoices.show', [
            'invoice'          => $invoice,
            'postableAccounts' => Account::query()->whereIn('type', ['asset', 'revenue'])->orderBy('code')->get(),
        ]);
    }

    /** POST /invoices/{id}/post — posts a draft invoice to the general ledger. */
    public function post(Request $request): Response
    {
        $id = (int) $request->param('id');

        $invoice = Invoice::postToLedger(
            $id,
            (int) $request->input('ar_account_id'),
            (int) $request->input('revenue_account_id'),
            $this->currentUserId()
        );

        return $this->redirect('/invoices/' . $invoice['id']);
    }
}
