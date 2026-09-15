<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Model;

/**
 * JournalEntry
 *
 * The core double-entry accounting record. Every financial event in
 * the system (invoice posted, payment received, manual adjustment)
 * ultimately becomes a JournalEntry with two or more JournalLines
 * whose debits and credits balance.
 *
 * This is the model to study/copy when adding a new transaction type
 * (e.g. PayrollRun, AssetDepreciation) — build up a set of lines,
 * then call JournalEntry::post().
 */
class JournalEntry extends Model
{
    protected static string $table = 'journal_entries';
    protected static array $fillable = ['entry_date', 'reference', 'memo', 'source_type', 'source_id', 'created_by', 'posted'];
    protected static array $casts = ['posted' => 'bool', 'created_by' => 'int'];

    /**
     * Creates a journal entry plus its lines atomically, after
     * verifying debits == credits. This is the ONLY supported way to
     * write to journal_lines — never insert lines directly, or the
     * books can go out of balance.
     *
     * @param array{entry_date:string, reference?:string, memo?:string, source_type?:string, source_id?:int, created_by?:int} $header
     * @param array<int, array{account_id:int, debit?:float, credit?:float, memo?:string}> $lines
     */
    public static function post(array $header, array $lines): array
    {
        if (count($lines) < 2) {
            throw new \InvalidArgumentException('A journal entry needs at least two lines.');
        }

        $totalDebit = array_sum(array_column($lines, 'debit'));
        $totalCredit = array_sum(array_column($lines, 'credit'));

        if (abs($totalDebit - $totalCredit) > 0.001) {
            throw new \InvalidArgumentException(
                sprintf('Journal entry does not balance: debits %.2f != credits %.2f', $totalDebit, $totalCredit)
            );
        }

        return Database::getInstance()->transaction(function () use ($header, $lines, $totalDebit) {
            $entry = new self($header + ['posted' => 1]);
            $entry->save();
            $entryId = (int) $entry->id;

            foreach ($lines as $line) {
                JournalLine::create($line + ['journal_entry_id' => $entryId, 'debit' => 0, 'credit' => 0]);
            }

            Logger::audit('journal_entry_posted', $header['created_by'] ?? null, [
                'entry_id' => $entryId,
                'total'    => $totalDebit,
            ]);

            return $entry->toArray() + ['id' => $entryId];
        });
    }

    public static function recent(int $limit = 25): array
    {
        return static::query()->orderBy('entry_date', 'DESC')->orderBy('id', 'DESC')->limit($limit)->get();
    }

    public function lines(): array
    {
        return JournalLine::forEntry((int) $this->id);
    }
}
