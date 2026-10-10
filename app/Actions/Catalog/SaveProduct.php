<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Actions\Inventory\RecordMovement;
use App\Enums\MovementType;
use App\Enums\PriceType;
use App\Models\CartItem;
use App\Models\OrderItem;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrderItem;
use App\Models\StockCountItem;
use App\Models\StockLevel;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Support\CostLayers;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a product plus its variants and specification values in
 * one transaction, recording price history for every price change.
 */
final class SaveProduct
{
    /**
     * @param  array<string, mixed>  $attributes  validated product fields (prices in cents)
     * @param  list<array<string, mixed>>|null  $variants  null = leave variants untouched
     * @param  array<int, array{value: string, value_ar?: ?string}>|null  $specifications  attribute_id => value, null = untouched
     * @param  list<int>|null  $knownVariantIds  variants the editing form loaded; only these can be removed by omission (null = all)
     * @param  list<array{warehouse_id: int, sku: string, quantity: int}>|null  $stockSplit  how a plain product's shelf stock goes to its new variants
     */
    public function handle(
        ?Product $product,
        array $attributes,
        ?array $variants = null,
        ?array $specifications = null,
        ?int $userId = null,
        ?array $knownVariantIds = null,
        ?array $stockSplit = null,
    ): Product {
        return DB::transaction(function () use ($product, $attributes, $variants, $specifications, $userId, $knownVariantIds, $stockSplit): Product {
            $attributes['slug'] ??= $this->uniqueSlug($attributes['name'] ?? $product?->name ?? '', $product?->id);

            if ($product === null) {
                $product = Product::create([...$attributes, 'created_by' => $userId, 'updated_by' => $userId]);

                $this->recordPriceHistory($product, null, null, (int) $product->price_cents, PriceType::Regular, $userId);
            } else {
                $this->trackPriceChanges($product, $attributes, $userId);

                $product->update([...$attributes, 'updated_by' => $userId]);
            }

            if ($variants !== null) {
                $this->syncVariants($product, $variants, $userId, $knownVariantIds, $stockSplit);
            }

            if ($specifications !== null) {
                $this->syncSpecifications($product, $specifications);
            }

            return $product->refresh();
        });
    }

    private function trackPriceChanges(Product $product, array $attributes, ?int $userId): void
    {
        $map = [
            'price_cents' => PriceType::Regular,
            'sale_price_cents' => PriceType::Sale,
            'cost_price_cents' => PriceType::Cost,
        ];

        foreach ($map as $field => $type) {
            if (! array_key_exists($field, $attributes)) {
                continue;
            }

            $old = $product->{$field};
            $new = $attributes[$field];

            if ($old !== $new && $new !== null) {
                $this->recordPriceHistory($product, null, $old === null ? null : (int) $old, (int) $new, $type, $userId);
            } elseif ($type === PriceType::Sale && $old !== null && $new === null) {
                // A sale ending is a price change too: back to the regular price.
                $regular = (int) ($attributes['price_cents'] ?? $product->price_cents);
                $this->recordPriceHistory($product, null, (int) $old, $regular, $type, $userId, 'Sale ended');
            }
        }
    }

    private function recordPriceHistory(
        Product $product,
        ?ProductVariant $variant,
        ?int $old,
        int $new,
        PriceType $type,
        ?int $userId,
        ?string $reason = null,
    ): void {
        PriceHistory::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'price_type' => $type,
            'old_price_cents' => $old,
            'new_price_cents' => $new,
            'reason' => $reason,
            'user_id' => $userId,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $variants  each may carry an `id` for updates
     * @param  list<int>|null  $knownVariantIds
     * @param  list<array{warehouse_id: int, sku: string, quantity: int}>|null  $stockSplit
     */
    private function syncVariants(Product $product, array $variants, ?int $userId, ?array $knownVariantIds, ?array $stockSplit): void
    {
        $hadVariants = $product->variants()->exists();
        $keptIds = [];

        foreach ($variants as $position => $data) {
            $data['position'] = $position;

            if (isset($data['id'])) {
                $variant = $product->variants()->whereKey($data['id'])->first();

                if ($variant !== null) {
                    if (array_key_exists('price_cents', $data) && (int) $variant->price_cents !== (int) $data['price_cents']) {
                        $this->recordPriceHistory($product, $variant, (int) $variant->price_cents, (int) $data['price_cents'], PriceType::Regular, $userId);
                    }

                    $oldSale = $variant->sale_price_cents === null ? null : (int) $variant->sale_price_cents;
                    $newSale = ($data['sale_price_cents'] ?? null) === null ? null : (int) $data['sale_price_cents'];

                    if (array_key_exists('sale_price_cents', $data) && $oldSale !== $newSale) {
                        $this->recordPriceHistory(
                            $product, $variant, $oldSale,
                            $newSale ?? (int) ($data['price_cents'] ?? $variant->price_cents),
                            PriceType::Sale, $userId, $newSale === null ? 'Sale ended' : null,
                        );
                    }

                    $variant->update(collect($data)->except('id')->all());
                    $keptIds[] = $variant->id;

                    continue;
                }
            }

            unset($data['id']);

            // A soft-deleted variant still occupies the sku's unique index, so
            // re-adding one with the same code revives it instead of crashing.
            $trashed = isset($data['sku'])
                ? $product->variants()->onlyTrashed()->where('sku', $data['sku'])->first()
                : null;

            if ($trashed !== null) {
                $trashed->restore();
                $trashed->update($data);
                $variant = $trashed;
            } else {
                $variant = $product->variants()->create($data);
            }

            $this->recordPriceHistory($product, $variant, null, (int) $variant->price_cents, PriceType::Regular, $userId);
            $keptIds[] = $variant->id;
        }

        // A variant left out of the form is removed — unless the form never knew
        // it (a colleague added it after the page was opened).
        $removed = $product->variants()->whereNotIn('id', $keptIds)
            ->when($knownVariantIds !== null, fn ($q) => $q->whereIn('id', $knownVariantIds))
            ->pluck('id');
        $remaining = $product->variants()->whereNotIn('id', $removed)->exists();

        // Removing every variant turns the product plain again, and nothing reads
        // variant rows of a plain product — refuse while they still hold stock.
        if ($hadVariants && ! $remaining && $product->stockLevels()->whereIn('product_variant_id', $removed)
            ->where(fn ($q) => $q->where('quantity', '>', 0)->orWhere('reserved', '>', 0)->orWhere('incoming', '>', 0))
            ->exists()) {
            throw ValidationException::withMessages([
                'variants' => __('These variants still hold stock. Clear it on the Stock page before removing every variant.'),
            ]);
        }

        // Variants removed from the payload are soft-deleted (order items keep
        // their snapshots, so history is safe). Any stock left on them stays
        // visible on the Stock page, where it can be written off.
        $product->variants()->whereIn('id', $removed)->delete();

        $product->update(['has_variants' => $remaining]);

        if (! $hadVariants && $keptIds !== []) {
            $this->movePlainProductOntoVariants($product, $keptIds, $userId, $stockSplit);
        }

        // The Stock page lists stock rows, so a brand-new variant needs an empty
        // one before staff can give it opening stock there.
        $warehouseId = Warehouse::query()->where('is_default', true)->value('id');

        foreach ($warehouseId === null ? [] : $keptIds as $variantId) {
            StockLevel::query()->firstOrCreate(['warehouse_id' => $warehouseId, 'product_id' => $product->id, 'product_variant_id' => $variantId]);
        }
    }

    /**
     * A plain product that gains variants hands its stock to them, through the
     * ledger. The shelf units (`quantity`) go where staff say in `$stockSplit`;
     * with a single variant they all go to it. Everything that can't be told
     * apart by hand — units reserved for open orders, damaged units, incoming
     * purchase-order units, and every old order/purchase/transfer/count/cart
     * line — goes to the first variant, so cancelling, returning or receiving
     * them later lands on stock the storefront can sell.
     *
     * @param  list<int>  $keptIds
     * @param  list<array{warehouse_id: int, sku: string, quantity: int}>|null  $stockSplit
     */
    private function movePlainProductOntoVariants(Product $product, array $keptIds, ?int $userId, ?array $stockSplit): void
    {
        $levels = $product->stockLevels()->whereNull('product_variant_id')->with('warehouse')->lockForUpdate()->get();

        $hasHistory = $levels->contains(fn ($level): bool => $level->quantity !== 0 || $level->reserved > 0 || $level->damaged > 0 || $level->incoming > 0)
            || OrderItem::query()->where('product_id', $product->id)->whereNull('product_variant_id')->exists()
            || PurchaseOrderItem::query()->where('product_id', $product->id)->whereNull('product_variant_id')->exists();

        if (! $hasHistory) {
            return;
        }

        $variants = ProductVariant::query()->whereIn('id', $keptIds)->get()->sortBy(fn (ProductVariant $v) => array_search($v->id, $keptIds, true))->values();
        $main = $variants->first();
        $shares = $this->resolveStockSplit($levels, $variants, $stockSplit);
        $recordMovement = app(RecordMovement::class);

        foreach ($levels as $level) {
            $drained = $recordMovement->handle(
                type: MovementType::VariantSplit,
                warehouse: $level->warehouse,
                product: $product,
                quantityChange: -$level->quantity,
                reservedChange: -$level->reserved,
                damagedChange: -$level->damaged,
                incomingChange: -$level->incoming,
                reason: 'Moved to variants when variants were added',
                userId: $userId,
                allowNegative: true,
            );
            // The units keep their costs and ages, handed out in variant order.
            $layers = $drained->costLayers;

            foreach ($variants as $variant) {
                $isMain = $variant->is($main);
                $quantity = $shares[$level->warehouse_id][$variant->id] ?? 0;

                if (! $isMain && $quantity === 0) {
                    continue;
                }

                [$share, $layers] = CostLayers::split($layers, $quantity);

                $recordMovement->handle(
                    type: MovementType::VariantSplit,
                    warehouse: $level->warehouse,
                    product: $product,
                    variant: $variant,
                    quantityChange: $quantity,
                    reservedChange: $isMain ? $level->reserved : 0,
                    damagedChange: $isMain ? $level->damaged : 0,
                    incomingChange: $isMain ? $level->incoming : 0,
                    reason: 'Moved from product-level stock when variants were added',
                    userId: $userId,
                    allowNegative: true,
                    costLayers: $share,
                );
            }
        }

        foreach ([OrderItem::class, PurchaseOrderItem::class, StockTransferItem::class, StockCountItem::class, CartItem::class] as $model) {
            $model::query()->where('product_id', $product->id)->whereNull('product_variant_id')->update(['product_variant_id' => $main->id]);
        }
    }

    /**
     * Shelf units per warehouse per variant id. One variant takes everything;
     * several need a split that adds up, warehouse by warehouse, to what is on
     * the shelf — otherwise units would appear or vanish.
     *
     * @param  Collection<int, StockLevel>  $levels
     * @param  Collection<int, ProductVariant>  $variants
     * @param  list<array{warehouse_id: int, sku: string, quantity: int}>|null  $stockSplit
     * @return array<int, array<int, int>>
     */
    private function resolveStockSplit(Collection $levels, Collection $variants, ?array $stockSplit): array
    {
        $onShelf = $levels->filter(fn ($level) => $level->quantity > 0);

        if ($variants->count() === 1 || $onShelf->isEmpty()) {
            return $onShelf->mapWithKeys(fn ($level) => [$level->warehouse_id => [$variants->first()->id => $level->quantity]])->all();
        }

        if ($stockSplit === null) {
            throw ValidationException::withMessages([
                'stock_split' => __('This product has :count units on the shelf. Say how many go to each variant.', ['count' => $onShelf->sum('quantity')]),
            ]);
        }

        $idBySku = $variants->pluck('id', 'sku');
        $shares = [];

        foreach ($stockSplit as $row) {
            if ($row['quantity'] === 0) {
                continue;
            }

            if (! $idBySku->has($row['sku'])) {
                throw ValidationException::withMessages(['stock_split' => __('The stock split names a variant (:sku) that is not on this product.', ['sku' => $row['sku']])]);
            }

            $shares[$row['warehouse_id']][$idBySku[$row['sku']]] = ($shares[$row['warehouse_id']][$idBySku[$row['sku']]] ?? 0) + $row['quantity'];
        }

        foreach (array_keys($shares) as $warehouseId) {
            $level = $onShelf->firstWhere('warehouse_id', $warehouseId);
            $given = array_sum($shares[$warehouseId]);

            if ($level === null || $given !== $level->quantity) {
                throw ValidationException::withMessages([
                    'stock_split' => __(':warehouse: the split adds up to :given but :count units are on the shelf.', [
                        'warehouse' => $level?->warehouse?->name ?? '#'.$warehouseId,
                        'given' => $given,
                        'count' => $level?->quantity ?? 0,
                    ]),
                ]);
            }
        }

        foreach ($onShelf as $level) {
            if (! isset($shares[$level->warehouse_id])) {
                throw ValidationException::withMessages([
                    'stock_split' => __(':warehouse: the split adds up to :given but :count units are on the shelf.', [
                        'warehouse' => $level->warehouse?->name, 'given' => 0, 'count' => $level->quantity,
                    ]),
                ]);
            }
        }

        return $shares;
    }

    /**
     * @param  array<int, array{value: string, value_ar?: ?string}>  $specifications
     */
    private function syncSpecifications(Product $product, array $specifications): void
    {
        // Only touch attributes present in this submission — the form hides attributes
        // out of the product's category scope, so a blanket delete would silently wipe
        // values for any attribute not currently visible on the form.
        $product->specificationValues()->whereIn('specification_attribute_id', array_keys($specifications))->delete();

        foreach ($specifications as $attributeId => $value) {
            if (trim($value['value'] ?? '') === '') {
                continue;
            }

            $product->specificationValues()->create([
                'specification_attribute_id' => $attributeId,
                'value' => $value['value'],
                'value_ar' => $value['value_ar'] ?? null,
            ]);
        }
    }

    private function uniqueSlug(string $name, ?int $ignoreId): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $suffix = 2;

        while (Product::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
