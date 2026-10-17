<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * What the dashboard's product pickers show for a product, including whether
 * the storefront will actually display it — a draft or hidden product is
 * skipped there, and the admin should see why before saving.
 */
final class ProductSummaries
{
    /**
     * @param  Builder<Product>  $query
     * @return Collection<int, array<string, mixed>>
     */
    public function of(Builder $query): Collection
    {
        $products = $query->with('images')->get(['id', 'name', 'name_ar', 'sku', 'price_cents', 'sale_price_cents', 'sale_starts_at', 'sale_ends_at']);

        $visible = Product::query()->visibleInStore()->whereIn('id', $products->pluck('id'))->pluck('id')->all();

        return $products->map(function (Product $product) use ($visible): array {
            $image = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

            return [
                'id' => $product->id,
                'name' => $product->name,
                'name_ar' => $product->name_ar,
                'sku' => $product->sku,
                'price_formatted' => Money::format($product->currentPriceCents()),
                'image' => $image !== null ? Storage::disk('public')->url($image->path) : null,
                'visible' => in_array($product->id, $visible, true),
            ];
        });
    }
}
