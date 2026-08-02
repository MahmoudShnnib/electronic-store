<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardResource;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class WishlistController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::query()
            ->whereIn('id', $request->user()->wishlistItems()->pluck('product_id'))
            ->visibleInStore()
            ->with(['brand:id,name,slug', 'images'])
            ->withAvg('approvedReviews as rating_avg', 'rating')
            ->withCount('approvedReviews as rating_count')
            ->paginate(24);

        return Inertia::render('storefront/Wishlist', [
            'products' => ProductCardResource::collection($products),
        ]);
    }

    public function toggle(Request $request, Product $product): RedirectResponse
    {
        $existing = $request->user()->wishlistItems()->where('product_id', $product->id)->first();

        if ($existing !== null) {
            $existing->delete();

            return back()->with('success', __('Removed from wishlist.'));
        }

        $request->user()->wishlistItems()->create(['product_id' => $product->id]);

        return back()->with('success', __('Added to wishlist.'));
    }
}
