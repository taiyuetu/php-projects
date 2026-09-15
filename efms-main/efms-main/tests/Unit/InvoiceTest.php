<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Invoice;
use RuntimeException;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    public function testCreateWithItemsCalculatesTotals(): void
    {
        $invoice = Invoice::createWithItems([
            'invoice_number' => 'INV-TEST-99',
            'customer_name'  => 'Test Customer',
            'issue_date'     => date('Y-m-d'),
            'due_date'       => date('Y-m-d', strtotime('+15 days')),
        ], [
            ['description' => 'Item A', 'quantity' => 2, 'unit_price' => 50.00],
            ['description' => 'Item B', 'quantity' => 1, 'unit_price' => 100.00],
        ], 0.10);

        $this->assertEquals(200.00, $invoice['subtotal']);
        $this->assertEquals(20.00, $invoice['tax']);
        $this->assertEquals(220.00, $invoice['total']);
        $this->assertEquals(Invoice::STATUS_DRAFT, $invoice['status']);
        $this->assertCount(2, $invoice['items']);
    }

    public function testPostToLedgerUpdatesStatusAndBalances(): void
    {
        $initialAR = Account::balance(2); // 1100 Accounts Receivable
        $initialRev = Account::balance(7); // 4000 Sales Revenue

        $invoice = Invoice::createWithItems([
            'invoice_number' => 'INV-TEST-POST-1',
            'customer_name'  => 'Client Corp',
            'issue_date'     => date('Y-m-d'),
            'due_date'       => date('Y-m-d', strtotime('+30 days')),
        ], [
            ['description' => 'Service', 'quantity' => 1, 'unit_price' => 500.00],
        ], 0.0);

        $posted = Invoice::postToLedger($invoice['id'], 2, 7);

        $this->assertEquals(Invoice::STATUS_POSTED, $posted['status']);
        $this->assertEquals($initialAR + 500.00, Account::balance(2));
        $this->assertEquals($initialRev + 500.00, Account::balance(7));
    }

    public function testCannotPostAlreadyPostedInvoice(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only draft invoices can be posted');

        $invoice = Invoice::createWithItems([
            'invoice_number' => 'INV-TEST-DOUBLE-POST',
            'customer_name'  => 'Double Post Customer',
            'issue_date'     => date('Y-m-d'),
            'due_date'       => date('Y-m-d', strtotime('+30 days')),
        ], [
            ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100.00],
        ], 0.0);

        Invoice::postToLedger($invoice['id'], 2, 7);
        // Second post should fail
        Invoice::postToLedger($invoice['id'], 2, 7);
    }
}
