<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Full product payload for the product screen, including gallery, variants,
 * and grouped specifications.
 *
 * @mixin Product
 */
final class ProductDetailResource extends ProductResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'warranty_months' => $this->warranty_months,
            'weight_grams' => $this->weight_grams,
            'has_variants' => $this->has_variants,
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => Storage::disk('public')->url($img->path),
                'is_primary' => $img->is_primary,
            ])),
            'variants' => $this->whenLoaded('activeVariants', fn () => $this->activeVariants->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'sku' => $v->sku,
                'attributes' => $v->attributes,
                'price' => Money::toDecimal($v->price_cents),
                'current_price' => Money::toDecimal($v->currentPriceCents()),
                'image' => $v->image_path !== null ? Storage::disk('public')->url($v->image_path) : null,
            ])),
            'specifications' => $this->whenLoaded('specificationValues', fn () => $this->specificationValues
                ->sortBy(fn ($v) => [$v->attribute->group->position, $v->attribute->position])
                ->groupBy(fn ($v) => $v->attribute->group->name)
                ->map(fn ($values) => $values->map(fn ($v) => [
                    'name' => $v->attribute->name,
                    'value' => $v->value,
                    'unit' => $v->attribute->unit,
                ])->values())),
        ];
    }
}
