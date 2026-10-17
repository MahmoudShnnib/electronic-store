<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Full product payload for the product screen, including gallery, variants
 * with their own stock, and grouped specifications.
 *
 * Text comes in both languages (`x` / `x_ar`, the Arabic one null when not
 * translated) and the client picks. `specifications` is the original
 * English-keyed shape kept for existing clients; `specification_groups` is
 * the ordered, bilingual one.
 *
 * @mixin Product
 */
final class ProductDetailResource extends ProductResource
{
    public function toArray(Request $request): array
    {
        $stock = $this->resource->sellableQuantities();

        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'description_ar' => $this->description_ar,
            'has_variants' => $this->has_variants,
            // Units the customer can buy right now (largest single stock row).
            'available' => $stock['product'],
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
                'current_price' => Money::toDecimal($v->setRelation('product', $this->resource)->currentPriceCents()),
                'on_sale' => $v->saleIsActive(),
                'image' => $v->image_path !== null ? Storage::disk('public')->url($v->image_path) : null,
                'available' => $stock['variants'][$v->id] ?? 0,
            ])),
            'specifications' => $this->whenLoaded('specificationValues', fn () => $this->specificationValues
                ->sortBy(fn ($v) => [$v->attribute->group->position, $v->attribute->position])
                ->groupBy(fn ($v) => $v->attribute->group->name)
                ->map(fn ($values) => $values->map(fn ($v) => [
                    'name' => $v->attribute->name,
                    'value' => $v->value,
                    'unit' => $v->attribute->unit,
                ])->values())),
            'specification_groups' => $this->whenLoaded('specificationValues', fn () => $this->specificationValues
                ->sortBy(fn ($v) => [$v->attribute->group->position, $v->attribute->position])
                ->groupBy(fn ($v) => $v->attribute->group->id)
                ->map(fn ($values) => [
                    'name' => $values->first()->attribute->group->name,
                    'name_ar' => $values->first()->attribute->group->name_ar,
                    'items' => $values->map(fn ($v) => [
                        'name' => $v->attribute->name,
                        'name_ar' => $v->attribute->name_ar,
                        'value' => $v->value,
                        'value_ar' => $v->value_ar,
                        'unit' => $v->attribute->unit,
                    ])->values(),
                ])->values()),
        ];
    }
}
