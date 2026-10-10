<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Enums\ProductStatus;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBatch;
use App\Models\StockLevel;
use App\Models\Warehouse;
use App\Support\CostLayers;
use App\Support\HomeCache;
use Illuminate\Database\Eloquent\Model;
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
     * @param  int|null  $unitCostCents  what each added unit cost (default: the variant's, else the product's cost)
     * @param  list<array{cost: int, qty: int, received_at: string}>|null  $costLayers  slices to add back at
     *                                                                                  their old cost and age (a give-back, a transfer in)
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
        ?int $unitCostCents = null,
        ?array $costLayers = null,
    ): InventoryMovement {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('RecordMovement must run inside a database transaction.');
        }

        // Once a product has variants, product-level stock can only be drained (the
        // migration in SaveProduct does this), never topped up — otherwise it becomes
        // an orphaned row that AddToCart/PlaceOrder never look at (B23 residual).
        if ($variant === null && $product->has_variants && $quantityChange > 0) {
            throw new RuntimeException('Cannot add product-level stock once a product has variants; choose a variant.');
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

        $damagedBefore = $stock->damaged;
        $stock->quantity = $after;
        // `reserved` is a counter, not sellable stock: clamp it like `damaged`
        // and `incoming` below. Throwing here meant that one drifted row made
        // cancelling an order fail inside the transaction, leaving the order
        // stuck on its old status with nothing to explain it.
        $stock->reserved = max(0, $stock->reserved + $reservedChange);
        $stock->damaged = max(0, $stock->damaged + $damagedChange);
        $stock->incoming = max(0, $stock->incoming + $incomingChange);
        $stock->save();

        // FIFO cost batches follow the shelf (`quantity`) only: reserved units left
        // their batches when an order took them, damaged ones when written off.
        $defaultCost = (int) ($variant?->cost_price_cents ?: $product->cost_price_cents);
        $layers = match (true) {
            $quantityChange < 0 => $this->takeOldest($stock, -$quantityChange, max(0, $before), $defaultCost),
            $quantityChange > 0 => $this->addBatches(
                $stock,
                max(0, $after) - max(0, $before),
                $costLayers ?? [['cost' => $unitCostCents ?? $defaultCost, 'qty' => $quantityChange, 'received_at' => CostLayers::stamp(now())]],
                $defaultCost,
            ),
            default => [],
        };

        // The cached home page shows "in stock" badges; refresh it when a shelf
        // empties or gets its first units back.
        if (($before > 0) !== ($after > 0)) {
            HomeCache::flush();
        }

        // A product someone marked "Out of stock" goes back on sale once units arrive.
        if ($before <= 0 && $after > 0 && $product->status === ProductStatus::OutOfStock) {
            $product->update(['status' => ProductStatus::Active]);
        }

        $movement = InventoryMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'type' => $type,
            'quantity_before' => $before,
            'quantity_change' => $quantityChange,
            'quantity_after' => $after,
            // What actually moved in or out of the damaged count (after the clamp).
            'damaged_change' => $stock->damaged - $damagedBefore,
            // What the units that left or joined the shelf cost (FIFO), for the write-off report.
            'cost_cents' => CostLayers::total($layers),
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'reason' => $reason,
            'user_id' => $userId ?? auth()->id(),
        ]);
        $movement->costLayers = $layers;

        return $movement;
    }

    /**
     * Uses up the oldest batches first. Units no batch covers (only after the
     * shelf drifted from its batches, which S10 reports) are costed at the default.
     *
     * @return list<array{cost: int, qty: int, received_at: string}>
     */
    private function takeOldest(StockLevel $stock, int $units, int $onShelf, int $defaultCost): array
    {
        $need = min($units, $onShelf);
        $layers = [];

        $batches = $need === 0 ? [] : StockBatch::query()
            ->where('stock_level_id', $stock->id)
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();

        foreach ($batches as $batch) {
            if ($need === 0) {
                break;
            }

            $take = min($need, $batch->quantity_remaining);
            $batch->update(['quantity_remaining' => $batch->quantity_remaining - $take]);
            $layers[] = ['cost' => $batch->unit_cost_cents, 'qty' => $take, 'received_at' => CostLayers::stamp($batch->received_at)];
            $need -= $take;
        }

        $short = $units - CostLayers::units($layers);

        if ($short > 0) {
            $layers[] = ['cost' => $defaultCost, 'qty' => $short, 'received_at' => CostLayers::stamp(now())];
        }

        return $layers;
    }

    /**
     * New batches on this stock row for $units units, at the given slices' costs
     * and ages — a give-back keeps its old place in the FIFO queue.
     *
     * @param  list<array{cost: int, qty: int, received_at: string}>  $layers
     * @return list<array{cost: int, qty: int, received_at: string}>
     */
    private function addBatches(StockLevel $stock, int $units, array $layers, int $defaultCost): array
    {
        [$layers] = CostLayers::split($layers, $units);
        $short = $units - CostLayers::units($layers);

        if ($short > 0) {
            $layers[] = ['cost' => $defaultCost, 'qty' => $short, 'received_at' => CostLayers::stamp(now())];
        }

        foreach ($layers as $layer) {
            StockBatch::create([
                'stock_level_id' => $stock->id,
                'unit_cost_cents' => $layer['cost'],
                'quantity_received' => $layer['qty'],
                'quantity_remaining' => $layer['qty'],
                'received_at' => $layer['received_at'],
            ]);
        }

        return $layers;
    }

    private function lockedStockRow(Warehouse $warehouse, Product $product, ?ProductVariant $variant): StockLevel
    {
        // Ensure the row exists before locking it. ON CONFLICT DO NOTHING makes the
        // create race-safe: a concurrent insert waits for the winner, inserts nothing
        // and we lock the winner's row. A plain insert that hit the unique index would
        // abort the whole Postgres transaction (25P02), so the re-lock below would fail.
        $stock = StockLevel::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->lockForUpdate()
            ->first();

        if ($stock !== null) {
            return $stock;
        }

        StockLevel::query()->fillAndInsertOrIgnore([[
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
        ]]);

        return StockLevel::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
