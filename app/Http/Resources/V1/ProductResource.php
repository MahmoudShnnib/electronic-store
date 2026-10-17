<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Product list representation for the mobile API.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $onSale = $this->saleIsActive();

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
            'price' => Money::toDecimal($this->price_cents),
            'sale_price' => $onSale ? Money::toDecimal((int) $this->sale_price_cents) : null,
            'current_price' => Money::toDecimal($this->currentPriceCents()),
            'currency' => (string) config('store.currency'),
            'on_sale' => $onSale,
            'image' => $image !== null ? Storage::disk('public')->url($image->path) : null,
            'in_stock' => $this->status->isPurchasable() && $this->availableQuantity() > 0,
            'is_featured' => $this->is_featured,
            'rating_avg' => isset($this->rating_avg) ? round((float) $this->rating_avg, 1) : null,
            'rating_count' => (int) ($this->rating_count ?? 0),
        ];
    }
}
