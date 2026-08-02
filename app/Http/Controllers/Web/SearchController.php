<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class SearchController extends Controller
{
    /** Autocomplete endpoint (debounced client-side). */
    public function suggest(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['products' => []]);
        }

        $products = Product::query()
            ->visibleInStore()
            ->search($term)
            ->with(['images', 'brand:id,name'])
            ->take(8)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => app()->getLocale() === 'ar' && $p->name_ar !== null ? $p->name_ar : $p->name,
                'slug' => $p->slug,
                'brand' => $p->brand?->name,
                'price_formatted' => Money::format($p->currentPriceCents()),
                'image' => ($img = $p->images->firstWhere('is_primary', true) ?? $p->images->first()) !== null
                    ? Storage::disk('public')->url($img->path)
                    : null,
            ]);

        return response()->json(['products' => $products]);
    }
}
