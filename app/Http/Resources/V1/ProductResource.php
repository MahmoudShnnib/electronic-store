<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Product list representation for the storefront SPA and the mobile API.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // A variant product is sold by its variants: its price is the cheapest
        // active one ("from …" when they differ), never the product row's own
        // price, and a shopper has to pick one before it can go in the cart.
        // Listings eager-load activeVariants; a single product loads them here.
        $priced = $this->resource;
        $variantPrices = collect();

        if ($this->has_variants) {
            $variants = $this->resource->loadMissing('activeVariants')->activeVariants
                ->each(fn ($variant) => $variant->setRelation('product', $this->resource));
            $priced = $variants->sortBy(fn ($variant) => $variant->currentPriceCents())->first() ?? $priced;
            $variantPrices = $variants->map(fn ($variant) => $variant->currentPriceCents())->unique();
        }

        $onSale = $priced->saleIsActive();

        $image = $this->relationLoaded('images')
            ? ($this->images->firstWhere('is_primary', true) ?? $this->images->first())
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'short_description' => $this->short_description,
            'short_description_ar' => $this->short_description_ar,
            'price' => Money::toDecimal((int) $priced->price_cents),
            'sale_price' => $onSale ? Money::toDecimal((int) $priced->sale_price_cents) : null,
            'current_price' => Money::toDecimal($priced->currentPriceCents()),
            'currency' => (string) config('store.currency'),
            'on_sale' => $onSale,
            'has_variants' => (bool) $this->has_variants,
            'price_from' => $variantPrices->count() > 1,
            'image' => $image !== null ? Storage::disk('public')->url($image->path) : null,
            'in_stock' => $this->status->isPurchasable() && $this->availableQuantity() > 0,
            'is_featured' => $this->is_featured,
            'rating_avg' => isset($this->rating_avg) ? round((float) $this->rating_avg, 1) : null,
            'rating_count' => (int) ($this->rating_count ?? 0),
        ];
    }
}
