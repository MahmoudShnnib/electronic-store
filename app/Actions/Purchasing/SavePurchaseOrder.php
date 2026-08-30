<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Support\Money;
use App\Support\ReferenceNumber;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a draft purchase order together with its line items,
 * recomputing the subtotal and total from the submitted lines.
 */
final class SavePurchaseOrder
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(?PurchaseOrder $po, array $validated, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $validated, $userId): PurchaseOrder {
            if ($po === null) {
                $po = PurchaseOrder::create([
                    'po_number' => ReferenceNumber::generate('PO'),
                    'supplier_id' => $validated['supplier_id'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'status' => PurchaseOrderStatus::Draft,
                    'discount_cents' => Money::fromDecimal($validated['discount'] ?? 0),
                    'shipping_cents' => Money::fromDecimal($validated['shipping'] ?? 0),
                    'expected_at' => $validated['expected_at'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $userId,
                ]);
            } else {
                $po->update([
                    'supplier_id' => $validated['supplier_id'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'discount_cents' => Money::fromDecimal($validated['discount'] ?? 0),
                    'shipping_cents' => Money::fromDecimal($validated['shipping'] ?? 0),
                    'expected_at' => $validated['expected_at'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ]);

                $po->items()->delete();
            }

            $this->syncItems($po, $validated['items']);

            return $po;
        });
    }

    /**
     * @param  list<array{product_id: int, product_variant_id: ?int, quantity: int, unit_cost: float|string}>  $items
     */
    private function syncItems(PurchaseOrder $po, array $items): void
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $unitCost = Money::fromDecimal($item['unit_cost']);
            $lineTotal = $unitCost * (int) $item['quantity'];
            $subtotal += $lineTotal;

            $po->items()->create([
                'product_id' => $item['product_id'],
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'quantity_ordered' => $item['quantity'],
                'unit_cost_cents' => $unitCost,
                'line_total_cents' => $lineTotal,
            ]);
        }

        $po->update([
            'subtotal_cents' => $subtotal,
            'total_cents' => max(0, $subtotal - $po->discount_cents) + $po->shipping_cents,
        ]);
    }
}
