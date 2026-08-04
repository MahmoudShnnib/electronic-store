<?php

declare(strict_types=1);

namespace App\Actions\Returns;

use App\Actions\Inventory\RecordMovement;
use App\Enums\ItemCondition;
use App\Enums\MovementType;
use App\Enums\ReturnStatus;
use App\Models\ProductReturn;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marks an approved return's items as physically received. Resellable items
 * go back into stock (customer_return movement); damaged items are recorded
 * on the damaged counter with a damage ledger note.
 */
final class ReceiveReturnItems
{
    public function __construct(private readonly RecordMovement $recordMovement) {}

    /**
     * @param  array<int, string>  $conditions  return_item_id => resellable|damaged
     */
    public function handle(ProductReturn $return, array $conditions, Warehouse $warehouse, ?int $userId = null): ProductReturn
    {
        if ($return->status !== ReturnStatus::Approved) {
            throw ValidationException::withMessages(['status' => __('Only approved returns can be received.')]);
        }

        return DB::transaction(function () use ($return, $conditions, $warehouse, $userId): ProductReturn {
            foreach ($return->items()->with('orderItem.product', 'orderItem.variant')->get() as $item) {
                $condition = ItemCondition::tryFrom($conditions[$item->id] ?? '')
                    ?? throw ValidationException::withMessages(['items' => "Missing condition for return item {$item->id}."]);

                $product = $item->orderItem->product;

                if ($product === null) {
                    continue; // product hard-deleted; nothing to restock
                }

                $restock = $condition === ItemCondition::Resellable;

                $this->recordMovement->handle(
                    type: MovementType::CustomerReturn,
                    warehouse: $warehouse,
                    product: $product,
                    variant: $item->orderItem->variant,
                    quantityChange: $restock ? $item->quantity : 0,
                    damagedChange: $restock ? 0 : $item->quantity,
                    reference: $return,
                    reason: $restock ? 'Return received — restocked' : 'Return received — damaged',
                    userId: $userId,
                );

                $item->update([
                    'condition' => $condition,
                    'restock' => $restock,
                    'restocked_at' => $restock ? now() : null,
                ]);
            }

            $return->update([
                'status' => ReturnStatus::ItemReceived,
                'received_at' => now(),
            ]);

            activity()
                ->performedOn($return)
                ->withProperties(['conditions' => $conditions])
                ->event('return_received')
                ->log("Return {$return->rma_number} items received");

            return $return;
        });
    }
}
