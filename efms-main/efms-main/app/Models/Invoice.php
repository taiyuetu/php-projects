<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Model;

/**
 * Invoice
 *
 * Accounts-receivable document. Line items are stored separately
 * (InvoiceItem). Posting an invoice creates a balanced JournalEntry
 * (Debit Accounts Receivable / Credit Revenue) — this is the pattern
 * to follow for any other document that should hit the general
 * ledger (bills, payroll, etc).
 */
class Invoice extends Model
{
    protected static string $table = 'invoices';
    protected static array $fillable = [
        'invoice_number', 'customer_name', 'customer_email', 'issue_date', 'due_date',
        'status', 'subtotal', 'tax', 'total', 'notes', 'created_by',
    ];
    protected static array $casts = ['subtotal' => 'float', 'tax' => 'float', 'total' => 'float'];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_PAID = 'paid';
    public const STATUS_VOID = 'void';

    public static function withItems(int $id): ?array
    {
        $invoice = static::find($id);
        if ($invoice === null) {
            return null;
        }

        $invoice['items'] = InvoiceItem::forInvoice($id);

        return $invoice;
    }

    /**
     * Creates the invoice header + line items, computing totals
     * server-side (never trust client-submitted totals in a
     * financial system).
     */
    public static function createWithItems(array $header, array $items, float $taxRate = 0.0): array
    {
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += ((float) $item['quantity']) * ((float) $item['unit_price']);
        }
        $tax = round($subtotal * $taxRate, 2);
        $total = round($subtotal + $tax, 2);

        return Database::getInstance()->transaction(function () use ($header, $items, $subtotal, $tax, $total) {
            $invoice = new self($header + [
                'status'   => self::STATUS_DRAFT,
                'subtotal' => round($subtotal, 2),
                'tax'      => $tax,
                'total'    => $total,
            ]);
            $invoice->save();
            $invoiceId = (int) $invoice->id;

            foreach ($items as $item) {
                $amount = round(((float) $item['quantity']) * ((float) $item['unit_price']), 2);
                InvoiceItem::create([
                    'invoice_id'  => $invoiceId,
                    'description' => $item['description'],
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['unit_price'],
                    'amount'      => $amount,
                ]);
            }

            return self::withItems($invoiceId);
        });
    }

    /**
     * Posts a draft invoice to the general ledger:
     *   Debit  Accounts Receivable   total
     *   Credit Revenue               total
     * Accounts are resolved by code so the chart of accounts stays
     * the single source of truth for account IDs.
     */
    public static function postToLedger(int $invoiceId, int $arAccountId, int $revenueAccountId, ?int $userId = null): array
    {
        $invoice = static::findOrFail($invoiceId);

        if ($invoice['status'] !== self::STATUS_DRAFT) {
            throw new \RuntimeException('Only draft invoices can be posted.');
        }

        $entry = JournalEntry::post(
            [
                'entry_date'  => date('Y-m-d'),
                'reference'   => $invoice['invoice_number'],
                'memo'        => 'Invoice posted: ' . $invoice['invoice_number'],
                'source_type' => 'invoice',
                'source_id'   => $invoiceId,
                'created_by'  => $userId,
            ],
            [
                ['account_id' => $arAccountId, 'debit' => $invoice['total'], 'credit' => 0, 'memo' => 'Accounts receivable'],
                ['account_id' => $revenueAccountId, 'debit' => 0, 'credit' => $invoice['total'], 'memo' => 'Revenue recognized'],
            ]
        );

        static::update($invoiceId, ['status' => self::STATUS_POSTED]);
        Logger::audit('invoice_posted', $userId, ['invoice_id' => $invoiceId, 'journal_entry_id' => $entry['id']]);

        return static::withItems($invoiceId);
    }
}
