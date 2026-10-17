<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Actions\Inventory\RecordMovement;
use App\Enums\MovementType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierTransactionType;
use App\Models\PurchaseOrder;
use App\Models\SupplierTransaction;
use App\Support\CostLayers;
use App\Support\LandedCost;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Receives quantities against a purchase order (supports partial receiving).
 * Every received line creates a purchase_receipt inventory movement; the PO
 * flips to partially_received/received automatically.
 *
 * What arrived is also owed: the same landed cost the stock is booked at is
 * written to the supplier's account, so inventory and the debt for it always
 * agree to the cent.
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
            $po->load('items');
            // Each line's share of the PO total (shipping and discount spread in), exact to the cent.
            $lineTotals = LandedCost::lineTotals($po);
            $owed = 0;

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

                // What these units really cost (FIFO batches): the line's share of the PO
                // total, booked as received. A line at 0 means no price was set — the old
                // PO form prefilled 0 for variants without their own cost — so, like a
                // variant cost of 0 everywhere else, it falls back to the variant's, else
                // the product's, cost price, and adds nothing to what the supplier is owed.
                $layers = $item->unit_cost_cents > 0
                    ? LandedCost::receiptLayers($lineTotals[$item->id] ?? 0, $item->quantity_ordered, $item->quantity_received, $quantity)
                    : null;
                $owed += $layers === null ? 0 : CostLayers::total($layers);

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
                    costLayers: $layers,
                );
            }

            if ($owed > 0) {
                SupplierTransaction::create([
                    'supplier_id' => $po->supplier_id,
                    'type' => SupplierTransactionType::Purchase,
                    'amount_cents' => $owed,
                    'purchase_order_id' => $po->id,
                    'note' => "PO {$po->po_number}",
                    'created_by' => $userId,
                ]);
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
