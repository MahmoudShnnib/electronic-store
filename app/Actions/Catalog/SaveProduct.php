<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Actions\Inventory\RecordMovement;
use App\Enums\MovementType;
use App\Enums\PriceType;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
     */
    public function handle(
        ?Product $product,
        array $attributes,
        ?array $variants = null,
        ?array $specifications = null,
        ?int $userId = null,
    ): Product {
        return DB::transaction(function () use ($product, $attributes, $variants, $specifications, $userId): Product {
            $attributes['slug'] ??= $this->uniqueSlug($attributes['name'] ?? $product?->name ?? '', $product?->id);

            if ($product === null) {
                $product = Product::create([...$attributes, 'created_by' => $userId, 'updated_by' => $userId]);

                $this->recordPriceHistory($product, null, null, (int) $product->price_cents, PriceType::Regular, $userId);
            } else {
                $this->trackPriceChanges($product, $attributes, $userId);

                $product->update([...$attributes, 'updated_by' => $userId]);
            }

            if ($variants !== null) {
                $this->syncVariants($product, $variants, $userId);
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
     */
    private function syncVariants(Product $product, array $variants, ?int $userId): void
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

        // Variants removed from the payload are soft-deleted (order items keep
        // their snapshots, so history is safe).
        $product->variants()->whereNotIn('id', $keptIds)->delete();

        $product->update(['has_variants' => $keptIds !== []]);

        // Stock recorded before the product had variants lives on product-level
        // rows the storefront can no longer sell from (availability is read
        // per-variant). Move it onto the first variant, through the ledger.
        if (! $hadVariants && $keptIds !== []) {
            $this->migrateProductStockToVariant($product, $keptIds[0], $userId);
        }
    }

    private function migrateProductStockToVariant(Product $product, int $variantId, ?int $userId): void
    {
        $variant = $product->variants()->find($variantId);

        if ($variant === null) {
            return;
        }

        $recordMovement = app(RecordMovement::class);

        $levels = $product->stockLevels()
            ->whereNull('product_variant_id')
            ->where('quantity', '>', 0)
            ->with('warehouse')
            ->get();

        foreach ($levels as $level) {
            $recordMovement->handle(
                type: MovementType::Correction,
                warehouse: $level->warehouse,
                product: $product,
                quantityChange: -$level->quantity,
                reason: "Moved to variant \"{$variant->name}\" when variants were added",
                userId: $userId,
            );

            $recordMovement->handle(
                type: MovementType::Correction,
                warehouse: $level->warehouse,
                product: $product,
                variant: $variant,
                quantityChange: $level->quantity,
                reason: 'Moved from product-level stock when variants were added',
                userId: $userId,
            );
        }
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
