<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalEntry;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $accounts = Account::active();

        $totalsByType = [];
        foreach (Account::TYPES as $type) {
            $totalsByType[$type] = 0.0;
        }
        foreach ($accounts as $account) {
            $totalsByType[$account['type']] += Account::balance((int) $account['id']);
        }

        $recentEntries = JournalEntry::recent(10);
        $openInvoices = Invoice::query()
            ->whereIn('status', [Invoice::STATUS_POSTED])
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        return $this->view('dashboard.index', [
            'totalsByType'  => $totalsByType,
            'recentEntries' => $recentEntries,
            'openInvoices'  => $openInvoices,
        ]);
    }
}
