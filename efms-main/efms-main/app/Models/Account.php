<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Account
 *
 * A single line in the chart of accounts (e.g. "1000 - Cash",
 * "4000 - Sales Revenue"). `type` drives how balances are presented:
 * asset/expense accounts normally carry debit balances, liability/
 * equity/revenue accounts normally carry credit balances.
 */
class Account extends Model
{
    protected static string $table = 'accounts';
    protected static array $fillable = ['code', 'name', 'type', 'parent_id', 'is_active', 'description'];
    protected static array $casts = ['is_active' => 'bool', 'parent_id' => 'int'];

    public const TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'];

    public static function active(): array
    {
        return static::query()->where('is_active', '=', 1)->orderBy('code')->get();
    }

    public static function byType(string $type): array
    {
        return static::query()->where('type', '=', $type)->orderBy('code')->get();
    }

    /**
     * Current balance = sum(debits) - sum(credits) for debit-normal
     * accounts (asset/expense), or the reverse for credit-normal
     * accounts (liability/equity/revenue). Only counts posted entries.
     */
    public static function balance(int $accountId): float
    {
        $db = static::db();

        $row = $db->query(
            "SELECT
                COALESCE(SUM(jl.debit), 0) AS total_debit,
                COALESCE(SUM(jl.credit), 0) AS total_credit
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_entry_id
             WHERE jl.account_id = ? AND je.posted = 1",
            [$accountId]
        )->fetch();

        $account = static::find($accountId);
        $debitNormal = in_array($account['type'] ?? 'asset', ['asset', 'expense'], true);

        $debit = (float) ($row['total_debit'] ?? 0);
        $credit = (float) ($row['total_credit'] ?? 0);

        return $debitNormal ? $debit - $credit : $credit - $debit;
    }
}
