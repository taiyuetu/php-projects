<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Account;
use App\Models\JournalEntry;
use InvalidArgumentException;
use Tests\TestCase;

class JournalEntryTest extends TestCase
{
    public function testUnbalancedEntryThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Journal entry does not balance');

        JournalEntry::post([
            'entry_date' => date('Y-m-d'),
            'reference'  => 'JE-FAIL-01',
            'memo'       => 'Unbalanced entry',
        ], [
            ['account_id' => 1, 'debit' => 100.00, 'credit' => 0.00],
            ['account_id' => 2, 'debit' => 0.00, 'credit' => 50.00],
        ]);
    }

    public function testNeedsAtLeastTwoLines(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least two lines');

        JournalEntry::post([
            'entry_date' => date('Y-m-d'),
            'reference'  => 'JE-FAIL-02',
        ], [
            ['account_id' => 1, 'debit' => 100.00, 'credit' => 100.00],
        ]);
    }

    public function testBalancedEntryPostsSuccessfully(): void
    {
        $initialBalance = Account::balance(1);

        $entry = JournalEntry::post([
            'entry_date' => date('Y-m-d'),
            'reference'  => 'JE-OK-01',
            'memo'       => 'Balanced entry',
        ], [
            ['account_id' => 1, 'debit' => 250.00, 'credit' => 0.00, 'memo' => 'Cash in'],
            ['account_id' => 6, 'debit' => 0.00, 'credit' => 250.00, 'memo' => 'Equity in'],
        ]);

        $this->assertNotEmpty($entry['id']);
        $this->assertEquals(1, $entry['posted']);

        $newBalance = Account::balance(1);
        $this->assertEquals($initialBalance + 250.00, $newBalance);
    }
}
