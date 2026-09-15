<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Account;
use App\Models\JournalEntry;
use Tests\TestCase;

class AccountTest extends TestCase
{
    public function testActiveAccountsAreRetrieved(): void
    {
        $accounts = Account::active();
        $this->assertNotEmpty($accounts);
        foreach ($accounts as $acc) {
            $this->assertEquals(1, $acc['is_active']);
        }
    }

    public function testDebitNormalBalanceCalculation(): void
    {
        // Account 1000 (Cash) is an asset -> debit normal
        $initialBalance = Account::balance(1);

        JournalEntry::post([
            'entry_date' => date('Y-m-d'),
            'reference'  => 'ACC-DEBIT-TEST',
        ], [
            ['account_id' => 1, 'debit' => 300.00, 'credit' => 0.00],
            ['account_id' => 6, 'debit' => 0.00, 'credit' => 300.00],
        ]);

        $this->assertEquals($initialBalance + 300.00, Account::balance(1));
    }

    public function testCreditNormalBalanceCalculation(): void
    {
        // Account 4000 (Sales Revenue) is revenue -> credit normal
        $initialBalance = Account::balance(7);

        JournalEntry::post([
            'entry_date' => date('Y-m-d'),
            'reference'  => 'ACC-CREDIT-TEST',
        ], [
            ['account_id' => 1, 'debit' => 450.00, 'credit' => 0.00],
            ['account_id' => 7, 'debit' => 0.00, 'credit' => 450.00],
        ]);

        $this->assertEquals($initialBalance + 450.00, Account::balance(7));
    }
}
