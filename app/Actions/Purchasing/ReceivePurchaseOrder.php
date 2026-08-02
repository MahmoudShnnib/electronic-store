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
    public function __construct(private readonly RecordMovement $recordMovement)
    {
    }

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

        return DB::transaction(function () use ($po, $quantities, $userId): PurchaseOrder {
            $warehouse = $po->warehouse;
            $items = $po->items()->whereIn('id', array_keys($quantities))->lockForUpdate()->get();

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
}
