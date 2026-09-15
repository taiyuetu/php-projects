<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class InvoiceItem extends Model
{
    protected static string $table = 'invoice_items';
    protected static array $fillable = ['invoice_id', 'description', 'quantity', 'unit_price', 'amount'];
    protected static array $casts = ['quantity' => 'float', 'unit_price' => 'float', 'amount' => 'float'];
    protected static bool $timestamps = false;

    public static function forInvoice(int $invoiceId): array
    {
        return static::query()->where('invoice_id', '=', $invoiceId)->orderBy('id')->get();
    }
}
