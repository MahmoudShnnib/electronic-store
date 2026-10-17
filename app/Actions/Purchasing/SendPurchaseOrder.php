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
 * Marks a draft PO as sent to the supplier and flags the ordered quantities
 * as incoming stock on the destination warehouse — through the movement
 * ledger, so every incoming change is explained in the history.
 *
 * Nothing is mailed anywhere: how the order actually reaches the supplier is
 * the buyer's business (phone, the printable copy, whatever). This records
 * that it was placed and when.
 */
final class SendPurchaseOrder
{
    public function __construct(private readonly RecordMovement $recordMovement) {}

    public function handle(PurchaseOrder $po, ?int $userId = null): PurchaseOrder
    {
        if ($po->status !== PurchaseOrderStatus::Draft) {
            throw new InvalidArgumentException(__('Only draft purchase orders can be sent.'));
        }

        if ($po->items()->count() === 0) {
            throw new InvalidArgumentException(__('Cannot send an empty purchase order.'));
        }

        DB::transaction(function () use ($po, $userId): void {
            // Locked and re-checked: two "send" clicks landing together would
            // otherwise both pass the draft check above and book the incoming
            // units twice, leaving phantom stock "on its way" forever.
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);

            if ($po->status !== PurchaseOrderStatus::Draft) {
                throw new InvalidArgumentException(__('Only draft purchase orders can be sent.'));
            }

            // Loaded after the lock: preventLazyLoading is on outside
            // production, so every relation this action touches must be explicit.
            $po->load(['warehouse', 'items.product', 'items.variant']);

            foreach ($po->items as $item) {
                $this->recordMovement->handle(
                    type: MovementType::PurchaseIncoming,
                    warehouse: $po->warehouse,
                    product: $item->product,
                    variant: $item->variant,
                    incomingChange: $item->quantity_ordered,
                    reference: $po,
                    reason: "PO {$po->po_number} sent to supplier",
                    userId: $userId,
                );
            }

            $po->update([
                'status' => PurchaseOrderStatus::Sent,
                'sent_at' => now(),
            ]);

            activity()
                ->performedOn($po)
                ->event('po_sent')
                ->log("PO {$po->po_number} sent to supplier");
        });

        // The work above ran on a locked copy; bring the caller's model up to date.
        return $po->refresh();
    }
}
