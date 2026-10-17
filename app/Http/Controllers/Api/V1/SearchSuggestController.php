<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Header search autocomplete (the API twin of web /search/suggest): up to
 * eight products and four categories for a term of two characters or more.
 */
final class SearchSuggestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => ['products' => [], 'categories' => []]]);
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $products = Product::query()
            ->visibleInStore()
            ->search($term)
            ->with(['brand:id,name,slug', 'images'])
            ->withStock()
            ->latest('published_at')
            ->take(8)
            ->get();

        $categories = Category::query()
            ->active()
            ->where(fn ($q) => $q->whereRaw('name ILIKE ?', [$like])->orWhereRaw('name_ar ILIKE ?', [$like]))
            ->orderBy('position')
            ->take(4)
            ->get(['id', 'name', 'name_ar', 'slug']);

        return response()->json(['data' => [
            'products' => ProductResource::collection($products)->resolve($request),
            'categories' => $categories->map(fn (Category $category): array => $category->only(['id', 'name', 'name_ar', 'slug']))->all(),
        ]], options: JSON_UNESCAPED_UNICODE);
    }
}
