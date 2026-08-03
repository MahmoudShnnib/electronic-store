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
        $onSale = $this->saleIsActive();

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
            'price_cents' => $this->price_cents,
            'price_formatted' => Money::format($this->price_cents),
            'sale_price_cents' => $onSale ? $this->sale_price_cents : null,
            'sale_price_formatted' => $onSale ? Money::format((int) $this->sale_price_cents) : null,
            'current_price_formatted' => Money::format($this->currentPriceCents()),
            'on_sale' => $onSale,
            'discount_percent' => $onSale && $this->price_cents > 0
                ? (int) round((1 - $this->sale_price_cents / $this->price_cents) * 100)
                : null,
            'image' => $image !== null ? Storage::disk('public')->url($image->path) : null,
            'status' => $this->status->value,
            'in_stock' => $this->status->isPurchasable(),
            'has_variants' => (bool) $this->has_variants,
            'is_featured' => $this->is_featured,
            'rating_avg' => isset($this->rating_avg) ? round((float) $this->rating_avg, 1) : null,
            'rating_count' => (int) ($this->rating_count ?? 0),
        ];
    }
}
