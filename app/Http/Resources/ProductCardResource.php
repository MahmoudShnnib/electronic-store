<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Compact product representation for cards/grids on the storefront.
 *
 * @mixin Product
 */
final class ProductCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        // A variant product is priced by its variants: the card shows the
        // cheapest active one ("from …") instead of the product's base price.
        // ponytail: loads variants per variant product on the page; eager-load activeVariants in the listing if that ever shows up in profiling.
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
            'name' => $locale === 'ar' && $this->name_ar !== null ? $this->name_ar : $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'brand' => $this->whenLoaded('brand', fn () => $this->brand === null ? null : [
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
            ]),
            'price_cents' => $priced->price_cents,
            'price_formatted' => Money::format($priced->price_cents),
            'sale_price_cents' => $onSale ? $priced->sale_price_cents : null,
            'sale_price_formatted' => $onSale ? Money::format((int) $priced->sale_price_cents) : null,
            'current_price_formatted' => Money::format($priced->currentPriceCents()),
            'price_from' => $variantPrices->count() > 1,
            'on_sale' => $onSale,
            'discount_percent' => $onSale && $priced->price_cents > 0
                ? (int) round((1 - $priced->sale_price_cents / $priced->price_cents) * 100)
                : null,
            'image' => $image !== null ? Storage::disk('public')->url($image->path) : null,
            'status' => $this->status->value,
            'in_stock' => $this->status->isPurchasable() && $this->availableQuantity() > 0,
            'has_variants' => (bool) $this->has_variants,
            'is_featured' => $this->is_featured,
            'rating_avg' => isset($this->rating_avg) ? round((float) $this->rating_avg, 1) : null,
            'rating_count' => (int) ($this->rating_count ?? 0),
            'wishlisted' => in_array($this->id, self::wishlistedIds(), true),
        ];
    }

    /**
     * Product ids on the current customer's wishlist, fetched once per request.
     *
     * The card needs to render a filled heart for products already saved, and
     * doing that per model would be an N+1 across a 24-product grid. `once()`
     * is request-scoped, so the list is read a single time however many cards
     * the page renders — and it stays out of the homepage's section cache,
     * which holds models rather than rendered resources.
     *
     * @return list<int>
     */
    private static function wishlistedIds(): array
    {
        return once(fn (): array => auth()->check()
            ? auth()->user()->wishlistItems()->pluck('product_id')->all()
            : []);
    }
}
