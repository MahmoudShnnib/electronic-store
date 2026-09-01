<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Http\Resources\ProductCardResource;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the Inertia props for the storefront product page: localized detail,
 * grouped specifications, stock availability, related items and reviews.
 */
final class ProductPageService
{
    /**
     * @return array<string, mixed>
     */
    public function showProps(Product $product): array
    {
        $product->load([
            'brand:id,name,slug',
            'category:id,name,name_ar,slug,parent_id',
            'category.parent:id,name,name_ar,slug',
            'images',
            'activeVariants',
            'specificationValues.attribute.group',
        ]);

        $locale = app()->getLocale();

        // Group specifications for display: group name => [{name, value, unit}]
        $specs = $product->specificationValues
            ->sortBy(fn ($v) => [$v->attribute->group->position, $v->attribute->position])
            ->groupBy(fn ($v) => $locale === 'ar' && $v->attribute->group->name_ar !== null
                ? $v->attribute->group->name_ar
                : $v->attribute->group->name)
            ->map(fn ($values) => $values->map(fn ($v) => [
                'name' => $locale === 'ar' && $v->attribute->name_ar !== null ? $v->attribute->name_ar : $v->attribute->name,
                'value' => $locale === 'ar' && $v->value_ar !== null ? $v->value_ar : $v->value,
                'unit' => $v->attribute->unit,
            ])->values());

        $availability = $this->availability($product);

        $related = Product::query()
            ->visibleInStore()
            ->whereKeyNot($product->id)
            ->where('category_id', $product->category_id)
            ->with(['brand:id,name,slug', 'images'])
            ->withAvg('approvedReviews as rating_avg', 'rating')
            ->withCount('approvedReviews as rating_count')
            ->withStock()
            ->inRandomOrder()
            ->take(8)
            ->get();

        $reviews = $product->approvedReviews()
            ->with('user:id,name')
            ->latest()
            ->paginate(10, ['*'], 'reviews_page');

        return [
            'product' => [
                'id' => $product->id,
                'name' => $locale === 'ar' && $product->name_ar !== null ? $product->name_ar : $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'brand' => $product->brand?->only(['id', 'name', 'slug']),
                'category' => $product->category?->only(['id', 'name', 'name_ar', 'slug']),
                'parent_category' => $product->category?->parent?->only(['id', 'name', 'name_ar', 'slug']),
                'short_description' => $locale === 'ar' && $product->short_description_ar !== null
                    ? $product->short_description_ar : $product->short_description,
                'description' => $locale === 'ar' && $product->description_ar !== null
                    ? $product->description_ar : $product->description,
                'price_cents' => $product->price_cents,
                'price_formatted' => Money::format($product->price_cents),
                'on_sale' => $product->saleIsActive(),
                'sale_price_formatted' => $product->saleIsActive() ? Money::format((int) $product->sale_price_cents) : null,
                'current_price_cents' => $product->currentPriceCents(),
                'current_price_formatted' => Money::format($product->currentPriceCents()),
                'warranty_months' => $product->warranty_months,
                'weight_grams' => $product->weight_grams,
                'status' => $product->status->value,
                'purchasable' => $product->status->isPurchasable(),
                'wishlisted' => auth()->check()
                    && auth()->user()->wishlistItems()->where('product_id', $product->id)->exists(),
                'has_variants' => $product->has_variants,
                'meta_title' => $product->meta_title,
                'meta_description' => $product->meta_description,
                'images' => $product->images->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => Storage::disk('public')->url($img->path),
                    'alt' => $img->alt,
                    'is_primary' => $img->is_primary,
                ]),
                'variants' => $product->activeVariants->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'sku' => $v->sku,
                    'attributes' => $v->attributes,
                    'price_cents' => $v->price_cents,
                    'current_price_cents' => $v->currentPriceCents(),
                    'current_price_formatted' => Money::format($v->currentPriceCents()),
                    'on_sale' => $v->saleIsActive(),
                    'image' => $v->image_path !== null ? Storage::disk('public')->url($v->image_path) : null,
                    'available' => $availability['variants'][$v->id] ?? 0,
                ]),
                'available' => $availability['product'],
            ],
            'specifications' => $specs,
            'related' => ProductCardResource::collection($related),
            'reviews' => $reviews->through(fn ($r) => [
                'id' => $r->id,
                'rating' => $r->rating,
                'title' => $r->title,
                'body' => $r->body,
                'author' => $r->user?->name ?? 'Anonymous',
                'verified' => $r->isVerifiedPurchase(),
                'created_at' => $r->created_at->toDateString(),
            ]),
            'ratingSummary' => [
                'avg' => round((float) $product->approvedReviews()->avg('rating'), 1),
                'count' => $product->approvedReviews()->count(),
            ],
            'canReview' => $this->canReview($product),
        ];
    }

    /**
     * @return array{product: int, variants: array<int, int>}
     */
    private function availability(Product $product): array
    {
        $levels = $product->stockLevels()
            ->sellable()
            ->selectRaw('product_variant_id, MAX(quantity) as qty')
            ->groupBy('product_variant_id')
            ->pluck('qty', 'product_variant_id');

        $variants = [];
        foreach ($levels as $variantId => $qty) {
            if ($variantId !== null) {
                $variants[(int) $variantId] = (int) $qty;
            }
        }

        return [
            'product' => (int) ($levels[null] ?? $levels[''] ?? array_sum($variants)),
            'variants' => $variants,
        ];
    }

    private function canReview(Product $product): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($product->reviews()->where('user_id', $user->id)->exists()) {
            return false;
        }

        // Verified purchase required: user must have a delivered order containing it.
        return OrderItem::query()
            ->where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q
                ->where('user_id', $user->id)
                ->whereIn('status', ['delivered', 'completed']))
            ->exists();
    }
}
