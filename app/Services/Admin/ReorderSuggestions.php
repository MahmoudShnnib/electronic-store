<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\ProductStatus;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Support\Money;
use Illuminate\Database\Query\Builder;

/**
 * What needs buying again: sellable stock (all active warehouses together) at
 * or under its low-stock alert level, out-of-stock included — the inventory
 * page's "low" list, turned into purchase-order lines.
 *
 * A line suggests buying back up to twice the alert level (at least one unit)
 * at the last cost on record; the purchase order form leaves both editable.
 */
final class ReorderSuggestions
{
    /**
     * Suppliers with something to reorder, most items first. Products with no
     * supplier set come last, under a null id.
     *
     * @return list<array{supplier_id: int|null, supplier: string|null, count: int}>
     */
    public function bySupplier(): array
    {
        $counts = $this->candidates()->get()->countBy(fn (object $row) => $row->supplier_id ?? 'none');
        $names = Supplier::query()->whereKey($counts->keys()->filter(fn ($id) => $id !== 'none'))->pluck('company_name', 'id');

        return $counts
            ->map(fn (int $count, int|string $id) => [
                'supplier_id' => $id === 'none' ? null : (int) $id,
                'supplier' => $id === 'none' ? null : $names->get($id),
                'count' => $count,
            ])
            ->sortBy([fn (array $row) => $row['supplier_id'] === null ? 1 : 0, fn (array $row) => -$row['count']])
            ->values()
            ->all();
    }

    /**
     * Purchase-order lines for one supplier (null: products without one).
     *
     * @return list<array{product_id: int, product_variant_id: int|null, quantity: int, unit_cost: float}>
     */
    public function lines(?int $supplierId): array
    {
        return $this->candidates()
            ->when($supplierId === null, fn (Builder $q) => $q->whereNull('products.supplier_id'), fn (Builder $q) => $q->where('products.supplier_id', $supplierId))
            ->get()
            ->map(fn (object $row) => [
                'product_id' => (int) $row->product_id,
                'product_variant_id' => $row->product_variant_id !== null ? (int) $row->product_variant_id : null,
                'quantity' => max(1, (int) $row->threshold * 2 - (int) $row->on_hand),
                'unit_cost' => Money::toDecimal((int) $row->unit_cost_cents),
            ])
            ->values()
            ->all();
    }

    /** One row per product/variant still on sale, summed across warehouses, at or under its alert level. */
    private function candidates(): Builder
    {
        $threshold = 'COALESCE(product_variants.low_stock_threshold, products.low_stock_threshold)';

        return StockLevel::query()
            ->sellable()
            ->toBase()
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'stock_levels.product_variant_id')
            ->whereNull('products.deleted_at')
            ->whereIn('products.status', [ProductStatus::Active->value, ProductStatus::OutOfStock->value])
            ->groupBy('stock_levels.product_id', 'stock_levels.product_variant_id', 'products.supplier_id', 'products.name', 'product_variants.low_stock_threshold', 'products.low_stock_threshold', 'product_variants.cost_price_cents', 'products.cost_price_cents')
            ->havingRaw("SUM(stock_levels.quantity) <= {$threshold}")
            ->orderBy('products.name')
            ->selectRaw("stock_levels.product_id, stock_levels.product_variant_id, products.supplier_id, SUM(stock_levels.quantity) AS on_hand, {$threshold} AS threshold, COALESCE(NULLIF(product_variants.cost_price_cents, 0), products.cost_price_cents) AS unit_cost_cents");
    }
}
