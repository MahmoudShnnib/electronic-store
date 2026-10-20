<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * "You may also like" for the product page: the same category first, then
 * the rest of its parent's subtree, newest first. Deterministic (unlike the
 * Inertia page's random pick) so the response caches and paginates cleanly.
 */
final class RelatedProductController extends Controller
{
    private const LIMIT = 8;

    public function __invoke(Product $product): AnonymousResourceCollection
    {
        abort_unless($product->status->isVisibleInStore(), 404);

        $scope = $product->category?->parent_id ?? $product->category_id;

        $related = Product::query()
            ->visibleInStore()
            ->whereKeyNot($product->id)
            ->whereIn('category_id', Category::subtreeIds([$scope], activeOnly: true))
            ->with(['brand:id,name,slug', 'category:id,name,slug', 'images', 'activeVariants'])
            ->withAvg('approvedReviews as rating_avg', 'rating')
            ->withCount('approvedReviews as rating_count')
            ->withStock()
            ->orderByRaw('CASE WHEN category_id = ? THEN 0 ELSE 1 END', [$product->category_id])
            ->latest('published_at')
            ->orderByDesc('id')
            ->take(self::LIMIT)
            ->get();

        return ProductResource::collection($related);
    }
}
