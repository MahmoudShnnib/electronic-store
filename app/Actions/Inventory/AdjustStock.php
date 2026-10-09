<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Manual stock adjustment (increase, decrease, damage write-off, correction).
 */
final class AdjustStock
{
    public function __construct(private readonly RecordMovement $recordMovement) {}

    public function handle(
        Warehouse $warehouse,
        Product $product,
        ?ProductVariant $variant,
        int $quantityChange,
        MovementType $type,
        string $reason,
        ?int $userId = null,
        bool $fromDamaged = false,
    ): InventoryMovement {
        if ($quantityChange === 0) {
            throw new InvalidArgumentException('Adjustment quantity cannot be zero.');
        }

        if (! in_array($type, [
            MovementType::Initial,
            MovementType::ManualIncrease,
            MovementType::ManualDecrease,
            MovementType::Damaged,
            MovementType::Lost,
            MovementType::Correction,
            MovementType::SupplierReturn,
        ], true)) {
            throw new InvalidArgumentException("Movement type {$type->value} is not a manual adjustment.");
        }

        // The stock dialog says "negative to remove", so a minus typed against an
        // increase type is a decrease — never silently flipped into an addition.
        if ($quantityChange < 0 && in_array($type, [MovementType::Initial, MovementType::ManualIncrease], true)) {
            $type = MovementType::ManualDecrease;
        }

        // Otherwise the movement type decides the direction; the admin only types
        // an amount. Only a correction may carry an explicit sign of its own.
        if ($type->isDecrease()) {
            $quantityChange = -abs($quantityChange);
        } elseif ($type !== MovementType::Correction) {
            $quantityChange = abs($quantityChange);
        }

        return DB::transaction(function () use ($warehouse, $product, $variant, $quantityChange, $type, $reason, $userId, $fromDamaged): InventoryMovement {
            $damagedChange = $type === MovementType::Damaged ? abs($quantityChange) : 0;

            // Damaged units already left sellable stock when they were written off, so
            // sending THEM back to the supplier only clears the damaged count; anything
            // beyond what is damaged still comes out of sellable stock.
            if ($type === MovementType::SupplierReturn && $fromDamaged) {
                $damaged = (int) StockLevel::query()
                    ->where('warehouse_id', $warehouse->id)
                    ->where('product_id', $product->id)
                    ->where('product_variant_id', $variant?->id)
                    ->lockForUpdate()
                    ->value('damaged');
                $fromDamaged = min($damaged, abs($quantityChange));
                $damagedChange = -$fromDamaged;
                $quantityChange += $fromDamaged;
            }

            return $this->recordMovement->handle(
                type: $type,
                warehouse: $warehouse,
                product: $product,
                variant: $variant,
                quantityChange: $quantityChange,
                damagedChange: $damagedChange,
                reason: $reason,
                userId: $userId,
            );
        });
    }
}
