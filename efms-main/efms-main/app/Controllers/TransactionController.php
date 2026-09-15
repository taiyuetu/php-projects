<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Account;
use App\Models\JournalEntry;

/**
 * TransactionController
 *
 * Manages general-ledger journal entries. This controller is the
 * reference example for "non-trivial" writes — it composes several
 * form rows into the lines array JournalEntry::post() expects,
 * rather than mapping 1:1 to a single table.
 */
class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query('page', 1));
        $paginated = JournalEntry::query()
            ->orderBy('entry_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->paginate($page, 15);

        return $this->view('transactions.index', [
            'entries'    => $paginated['data'],
            'pagination' => $paginated,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('transactions.form', ['accounts' => Account::active(), 'errors' => null]);
    }

    public function store(Request $request): Response
    {
        $accountIds = $request->input('account_id', []);
        $debits = $request->input('debit', []);
        $credits = $request->input('credit', []);
        $memos = $request->input('line_memo', []);

        $lines = [];
        foreach ($accountIds as $i => $accountId) {
            if ($accountId === '' || $accountId === null) {
                continue;
            }
            $lines[] = [
                'account_id' => (int) $accountId,
                'debit'      => (float) ($debits[$i] ?? 0),
                'credit'     => (float) ($credits[$i] ?? 0),
                'memo'       => $memos[$i] ?? null,
            ];
        }

        try {
            $entry = JournalEntry::post(
                [
                    'entry_date' => $request->input('entry_date', date('Y-m-d')),
                    'reference'  => $request->input('reference'),
                    'memo'       => $request->input('memo'),
                    'created_by' => $this->currentUserId(),
                ],
                $lines
            );
        } catch (\InvalidArgumentException $e) {
            return $this->view('transactions.form', [
                'accounts' => Account::active(),
                'errors'   => [$e->getMessage()],
            ]);
        }

        return $this->redirect('/transactions/' . $entry['id']);
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->param('id');
        $entry = JournalEntry::findOrFail($id);
        $entry = new JournalEntry($entry);

        return $this->view('transactions.show', [
            'entry' => $entry->toArray(),
            'lines' => $entry->lines(),
        ]);
    }
}
