<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The ONLY code path that mutates stock. Locks the stock row, applies the
 * change, and writes the immutable ledger entry — all inside the caller's
 * transaction (enforced). Stock is never updated silently.
 */
final class RecordMovement
{
    /**
     * @param  int  $quantityChange  signed change to available-to-sell `quantity`
     * @param  int  $reservedChange  signed change to `reserved`
     * @param  int  $damagedChange  signed change to `damaged`
     * @param  int  $incomingChange  signed change to `incoming`
     */
    public function handle(
        MovementType $type,
        Warehouse $warehouse,
        Product $product,
        ?ProductVariant $variant = null,
        int $quantityChange = 0,
        int $reservedChange = 0,
        int $damagedChange = 0,
        int $incomingChange = 0,
        ?Model $reference = null,
        ?string $reason = null,
        ?int $userId = null,
        bool $allowNegative = false,
    ): InventoryMovement {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('RecordMovement must run inside a database transaction.');
        }

        $stock = $this->lockedStockRow($warehouse, $product, $variant);

        $before = $stock->quantity;
        $after = $before + $quantityChange;

        if ($after < 0 && ! $allowNegative) {
            throw new InsufficientStockException(
                $product,
                $variant,
                requested: abs($quantityChange),
                available: $before,
            );
        }

        if ($stock->reserved + $reservedChange < 0) {
            throw new RuntimeException('Reserved stock cannot go negative.');
        }

        $stock->quantity = $after;
        $stock->reserved += $reservedChange;
        $stock->damaged = max(0, $stock->damaged + $damagedChange);
        $stock->incoming = max(0, $stock->incoming + $incomingChange);
        $stock->save();

        return InventoryMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'type' => $type,
            'quantity_before' => $before,
            'quantity_change' => $quantityChange,
            'quantity_after' => $after,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'reason' => $reason,
            'user_id' => $userId ?? auth()->id(),
        ]);
    }

    private function lockedStockRow(Warehouse $warehouse, Product $product, ?ProductVariant $variant): StockLevel
    {
        // Ensure the row exists before locking it. The unique index makes the
        // create race-safe: a concurrent insert loses and we lock the winner's row.
        $stock = StockLevel::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->lockForUpdate()
            ->first();

        if ($stock !== null) {
            return $stock;
        }

        try {
            StockLevel::create([
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Lost the race — the row now exists; fall through and lock it.
        }

        return StockLevel::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
