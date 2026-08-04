<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Enums\TransferStatus;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Support\ReferenceNumber;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates and immediately executes a warehouse-to-warehouse transfer:
 * stock leaves the source (transfer_out) and arrives at the destination
 * (transfer_in) atomically, with one ledger entry per side per item.
 */
final class TransferStock
{
    public function __construct(private readonly RecordMovement $recordMovement) {}

    /**
     * @param  list<array{product_id: int, product_variant_id: ?int, quantity: int}>  $items
     */
    public function handle(
        Warehouse $from,
        Warehouse $to,
        array $items,
        ?string $notes = null,
        ?int $userId = null,
    ): StockTransfer {
        if ($from->id === $to->id) {
            throw new InvalidArgumentException('Source and destination warehouses must differ.');
        }

        if ($items === []) {
            throw new InvalidArgumentException('A transfer needs at least one item.');
        }

        return DB::transaction(function () use ($from, $to, $items, $notes, $userId): StockTransfer {
            $transfer = StockTransfer::create([
                'reference' => ReferenceNumber::generate('TRF'),
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'status' => TransferStatus::Completed,
                'notes' => $notes,
                'created_by' => $userId ?? auth()->id(),
                'completed_at' => now(),
            ]);

            foreach ($items as $item) {
                $transferItem = $transfer->items()->create([
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'quantity' => $item['quantity'],
                ]);

                $product = $transferItem->product;
                $variant = $transferItem->variant;

                $this->recordMovement->handle(
                    type: MovementType::TransferOut,
                    warehouse: $from,
                    product: $product,
                    variant: $variant,
                    quantityChange: -$item['quantity'],
                    reference: $transfer,
                    userId: $userId,
                );

                $this->recordMovement->handle(
                    type: MovementType::TransferIn,
                    warehouse: $to,
                    product: $product,
                    variant: $variant,
                    quantityChange: $item['quantity'],
                    reference: $transfer,
                    userId: $userId,
                );
            }

            return $transfer;
        });
    }
}
