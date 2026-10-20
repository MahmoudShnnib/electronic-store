<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class WishlistController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->whereIn('id', $request->user()->wishlistItems()->pluck('product_id'))
            ->visibleInStore()
            ->with(['brand:id,name,slug', 'images', 'activeVariants'])
            ->withAvg('approvedReviews as rating_avg', 'rating')
            ->withCount('approvedReviews as rating_count')
            ->withStock()
            ->paginate(min(50, max(1, (int) $request->query('per_page', '20'))));

        return ProductResource::collection($products);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $request->user()->wishlistItems()->firstOrCreate(['product_id' => $product->id]);

        return response()->json(['message' => 'Added to wishlist.'], 201);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $request->user()->wishlistItems()->where('product_id', $product->id)->delete();

        return response()->json(['message' => 'Removed from wishlist.']);
    }
}
