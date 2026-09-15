<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class JournalLine extends Model
{
    protected static string $table = 'journal_lines';
    protected static array $fillable = ['journal_entry_id', 'account_id', 'debit', 'credit', 'memo'];
    protected static array $casts = ['debit' => 'float', 'credit' => 'float'];
    protected static bool $timestamps = false;

    public static function forEntry(int $journalEntryId): array
    {
        return static::query()
            ->select('journal_lines.*, accounts.code AS account_code, accounts.name AS account_name')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_lines.journal_entry_id', '=', $journalEntryId)
            ->get();
    }
}
