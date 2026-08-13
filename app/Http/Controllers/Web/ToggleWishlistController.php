<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Adds a product to the wishlist, or removes it if it is already there. */
final class ToggleWishlistController extends Controller
{
    public function __invoke(Request $request, Product $product): RedirectResponse
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
