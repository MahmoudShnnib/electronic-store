<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
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
    ): InventoryMovement {
        if ($quantityChange === 0) {
            throw new InvalidArgumentException('Adjustment quantity cannot be zero.');
        }

        if (! in_array($type, [
            MovementType::Initial,
            MovementType::ManualIncrease,
            MovementType::ManualDecrease,
            MovementType::Damaged,
            MovementType::Correction,
            MovementType::SupplierReturn,
        ], true)) {
            throw new InvalidArgumentException("Movement type {$type->value} is not a manual adjustment.");
        }

        return DB::transaction(fn (): InventoryMovement => $this->recordMovement->handle(
            type: $type,
            warehouse: $warehouse,
            product: $product,
            variant: $variant,
            quantityChange: $quantityChange,
            damagedChange: $type === MovementType::Damaged ? abs($quantityChange) : 0,
            reason: $reason,
            userId: $userId,
        ));
    }
}
