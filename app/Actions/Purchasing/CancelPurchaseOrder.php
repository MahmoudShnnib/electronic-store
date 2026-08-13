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
 * Cancels a purchase order that has not been fully received. If the PO was
 * already sent, the incoming stock booked by SendPurchaseOrder is released
 * for every quantity that never arrived — with a ledger entry saying why.
 */
final class CancelPurchaseOrder
{
    public function __construct(private readonly RecordMovement $recordMovement) {}

    public function handle(PurchaseOrder $po, ?int $userId = null): PurchaseOrder
    {
        if (in_array($po->status, [PurchaseOrderStatus::Received, PurchaseOrderStatus::Cancelled], true)) {
            throw new InvalidArgumentException('This purchase order cannot be cancelled.');
        }

        return DB::transaction(function () use ($po, $userId): PurchaseOrder {
            // Drafts never booked incoming stock; sent/partially received POs did.
            if ($po->status !== PurchaseOrderStatus::Draft) {
                foreach ($po->items as $item) {
                    $outstanding = $item->outstandingQuantity();

                    if ($outstanding <= 0) {
                        continue;
                    }

                    $this->recordMovement->handle(
                        type: MovementType::PurchaseCancelled,
                        warehouse: $po->warehouse,
                        product: $item->product,
                        variant: $item->variant,
                        incomingChange: -$outstanding,
                        reference: $po,
                        reason: "PO {$po->po_number} cancelled",
                        userId: $userId,
                    );
                }
            }

            $po->update(['status' => PurchaseOrderStatus::Cancelled]);

            activity()
                ->performedOn($po)
                ->event('po_cancelled')
                ->log("PO {$po->po_number} cancelled");

            return $po;
        });
    }
}
