<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Auth;

class PurchaseArrival extends Model
{
    protected static string $table = 'purchase_arrivals';
    protected static array $fillable = ['purchase_id', 'arrival_date', 'qty', 'notes', 'created_by'];

    /** Get all arrivals for a purchase, ordered by date */
    public static function byPurchase(int $purchaseId): array
    {
        return self::raw(
            'SELECT pa.*, u.name AS created_by_name
             FROM purchase_arrivals pa
             LEFT JOIN users u ON u.id = pa.created_by
             WHERE pa.purchase_id = ?
             ORDER BY pa.arrival_date ASC, pa.id ASC',
            [$purchaseId]
        );
    }

    /** Get total arrived qty for a purchase */
    public static function totalArrivedQty(int $purchaseId): int
    {
        $result = self::raw(
            'SELECT COALESCE(SUM(qty), 0) AS total FROM purchase_arrivals WHERE purchase_id = ?',
            [$purchaseId]
        );
        return (int)($result[0]['total'] ?? 0);
    }

    /** Get total ordered qty for a purchase (sum of purchase_items qty) */
    public static function totalOrderedQty(int $purchaseId): int
    {
        $result = self::raw(
            'SELECT COALESCE(SUM(qty), 0) AS total FROM purchase_items WHERE purchase_id = ?',
            [$purchaseId]
        );
        return (int)($result[0]['total'] ?? 0);
    }

    /** Get remaining qty that can still arrive */
    public static function remainingQty(int $purchaseId): int
    {
        return max(0, self::totalOrderedQty($purchaseId) - self::totalArrivedQty($purchaseId));
    }

    /** Record a new arrival and update product stock */
    public static function recordArrival(int $purchaseId, string $arrivalDate, int $qty, string $notes = ''): int
    {
        if ($qty <= 0) {
            throw new \Exception('Arrival qty must be greater than 0.');
        }

        $db = self::db();
        $db->beginTransaction();

        try {
            // Get purchase info
            $purchase = Purchase::find($purchaseId);
            if (!$purchase) throw new \Exception('Purchase not found');

            // Validate: arrival qty must not exceed remaining ordered qty
            $remaining = self::remainingQty($purchaseId);
            if ($remaining <= 0) {
                throw new \Exception('This purchase order is already fully arrived. No more arrivals can be recorded.');
            }
            if ($qty > $remaining) {
                throw new \Exception("Arrival qty ({$qty}) exceeds remaining ordered qty ({$remaining}). You can record at most {$remaining} units.");
            }

            // Get purchase items to distribute qty
            $items = PurchaseItem::byPurchase($purchaseId);
            if (empty($items)) throw new \Exception('No items in this purchase');

            // Create arrival record
            $arrivalId = self::create([
                'purchase_id'  => $purchaseId,
                'arrival_date' => $arrivalDate,
                'qty'          => $qty,
                'notes'        => $notes,
                'created_by'   => Auth::user()['id'] ?? null,
            ]);

            $allocations = self::allocateArrivalQty($items, $qty);
            foreach ($allocations as $allocation) {
                if ($allocation['qty'] > 0) {
                    Product::increaseStock(
                        $allocation['product_id'],
                        $allocation['qty'],
                        'purchase_arrival',
                        $purchase['invoice_no'],
                        'Purchase Arrival #' . $purchase['invoice_no'] . ' (Batch)'
                    );
                }
            }

            $db->commit();
            return $arrivalId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private static function allocateArrivalQty(array $items, int $arrivalQty): array
    {
        $totalOrdered = 0;
        foreach ($items as $item) {
            $totalOrdered += max(0, (int) $item['qty']);
        }

        if ($totalOrdered <= 0 || $arrivalQty <= 0) {
            return [];
        }

        $allocations = [];
        $allocated = 0;

        foreach ($items as $index => $item) {
            $orderedQty = max(0, (int) $item['qty']);
            $exactQty = ($arrivalQty * $orderedQty) / $totalOrdered;
            $baseQty = (int) floor($exactQty);
            $allocated += $baseQty;

            $allocations[] = [
                'product_id' => (int) $item['product_id'],
                'qty'        => $baseQty,
                'remainder'  => $exactQty - $baseQty,
                'index'      => $index,
            ];
        }

        $remaining = $arrivalQty - $allocated;
        usort($allocations, function (array $a, array $b): int {
            $byRemainder = $b['remainder'] <=> $a['remainder'];
            return $byRemainder !== 0 ? $byRemainder : $a['index'] <=> $b['index'];
        });

        for ($i = 0; $i < $remaining; $i++) {
            $allocations[$i % count($allocations)]['qty']++;
        }

        usort($allocations, fn(array $a, array $b): int => $a['index'] <=> $b['index']);

        return array_map(
            fn(array $allocation): array => [
                'product_id' => $allocation['product_id'],
                'qty'        => $allocation['qty'],
            ],
            $allocations
        );
    }
}
