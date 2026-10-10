<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Actions\Inventory\RecordMovement;
use App\Enums\MovementType;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Receives quantities against a purchase order (supports partial receiving).
 * Every received line creates a purchase_receipt inventory movement; the PO
 * flips to partially_received/received automatically.
 */
final class ReceivePurchaseOrder
{
    public function __construct(private readonly RecordMovement $recordMovement) {}

    /**
     * @param  array<int, int>  $quantities  purchase_order_item_id => quantity received now
     */
    public function handle(PurchaseOrder $po, array $quantities, ?int $userId = null): PurchaseOrder
    {
        if (! $po->status->isReceivable()) {
            throw new InvalidArgumentException("Purchase order {$po->po_number} is not receivable (status: {$po->status->value}).");
        }

        $quantities = array_filter($quantities, fn (int $q) => $q > 0);

        if ($quantities === []) {
            throw new InvalidArgumentException('Nothing to receive.');
        }

        // Route-bound models arrive with nothing loaded and preventLazyLoading is on
        // outside production, so every relation this action touches must be explicit.
        $po->loadMissing(['warehouse', 'items']);

        return DB::transaction(function () use ($po, $quantities, $userId): PurchaseOrder {
            // Locked and re-checked: a cancel landing at the same moment must win or lose cleanly.
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);

            if (! $po->status->isReceivable()) {
                throw new InvalidArgumentException("Purchase order {$po->po_number} is not receivable (status: {$po->status->value}).");
            }

            $warehouse = $po->warehouse;

            if (! $warehouse->is_active || $warehouse->trashed()) {
                throw new InvalidArgumentException(__('Warehouse :name is not active; activate it before receiving into it.', ['name' => $warehouse->name]));
            }

            $items = $po->items()
                ->whereIn('id', array_keys($quantities))
                ->with(['product', 'variant'])
                ->lockForUpdate()
                ->get();

            foreach ($quantities as $itemId => $quantity) {
                $item = $items->firstWhere('id', $itemId);

                if ($item === null) {
                    throw new InvalidArgumentException("Item {$itemId} does not belong to PO {$po->po_number}.");
                }

                if ($quantity > $item->outstandingQuantity()) {
                    throw new InvalidArgumentException(sprintf(
                        'Cannot receive %d of "%s" — only %d outstanding.',
                        $quantity,
                        $item->product->name,
                        $item->outstandingQuantity(),
                    ));
                }

                $item->increment('quantity_received', $quantity);

                $this->recordMovement->handle(
                    type: MovementType::PurchaseReceipt,
                    warehouse: $warehouse,
                    product: $item->product,
                    variant: $item->variant,
                    quantityChange: $quantity,
                    incomingChange: -$quantity,
                    reference: $po,
                    userId: $userId,
                    // What these units really cost (FIFO batch): the line price with the PO's
                    // shipping and discount spread over it. A line at 0 means no price was set
                    // — the old PO form prefilled 0 for variants without their own cost — so,
                    // like a variant cost of 0 everywhere else, it falls back to the variant's,
                    // else the product's, cost price.
                    unitCostCents: $item->unit_cost_cents > 0 ? $this->landedUnitCost($po, $item->unit_cost_cents) : null,
                );
            }

            $po->refresh();

            $outstanding = $po->items->sum(fn ($i) => $i->outstandingQuantity());

            $po->update([
                'status' => $outstanding === 0 ? PurchaseOrderStatus::Received : PurchaseOrderStatus::PartiallyReceived,
                'received_at' => $outstanding === 0 ? now() : $po->received_at,
            ]);

            activity()
                ->performedOn($po)
                ->withProperties(['received' => $quantities])
                ->event('po_received')
                ->log("Received stock against PO {$po->po_number}");

            return $po;
        });
    }

    /**
     * A line price scaled by what the whole PO cost against its lines: 10 phones at
     * 700 with 100 shipping cost 710 each; with 200 off, 680 each. Same factor for
     * every partial receipt, since a sent PO's amounts no longer change.
     */
    private function landedUnitCost(PurchaseOrder $po, int $lineUnitCostCents): int
    {
        if ($po->subtotal_cents <= 0) {
            return $lineUnitCostCents;
        }

        return (int) round($lineUnitCostCents * $po->total_cents / $po->subtotal_cents);
    }
}
