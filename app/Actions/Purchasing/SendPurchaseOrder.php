<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Marks a draft PO as sent to the supplier and flags the ordered quantities
 * as incoming stock on the destination warehouse.
 */
final class SendPurchaseOrder
{
    public function handle(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== PurchaseOrderStatus::Draft) {
            throw new InvalidArgumentException('Only draft purchase orders can be sent.');
        }

        if ($po->items()->count() === 0) {
            throw new InvalidArgumentException('Cannot send an empty purchase order.');
        }

        return DB::transaction(function () use ($po): PurchaseOrder {
            foreach ($po->items as $item) {
                StockLevel::query()->firstOrCreate([
                    'warehouse_id' => $po->warehouse_id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                ])->increment('incoming', $item->quantity_ordered);
            }

            $po->update(['status' => PurchaseOrderStatus::Sent]);

            return $po;
        });
    }
}
