<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Product listing with search, filters (category, brand, price, specs,
 * availability), and sorting — shared by /products, /category/{slug},
 * /brand/{slug}, and /search.
 */
final class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render($request, null, null);
    }

    public function category(Request $request, Category $category): Response
    {
        abort_unless($category->is_active, 404);

        return $this->render($request, $category, null);
    }

    public function brand(Request $request, Brand $brand): Response
    {
        abort_unless($brand->is_active, 404);

        return $this->render($request, null, $brand);
    }

    private function render(Request $request, ?Category $category, ?Brand $brand): Response
    {
        $filters = $request->only(['q', 'brands', 'categories', 'min_price', 'max_price', 'in_stock', 'on_sale', 'sort']);

        $query = $this->baseQuery($request, $category, $brand);

        $products = $query->paginate(24)->withQueryString();

        return Inertia::render('storefront/Catalog', [
            'products' => ProductCardResource::collection($products),
            'category' => $category?->only(['id', 'name', 'name_ar', 'slug', 'description']),
            'childCategories' => $category !== null
                ? $category->children()->where('is_active', true)->get(['id', 'name', 'name_ar', 'slug', 'parent_id'])
                : null,
            'brand' => $brand?->only(['id', 'name', 'slug', 'description', 'logo_path']),
            // Cast so an empty filter set serializes as {} — as a JS array, a
            // `sort` key would resolve to Array.prototype.sort in the client.
            'filters' => (object) $filters,
            'filterOptions' => [
                'brands' => Brand::query()->active()->orderBy('name')->get(['id', 'name', 'slug']),
                'categories' => Category::query()->active()->orderBy('position')->get(['id', 'name', 'name_ar', 'slug', 'parent_id']),
            ],
            'sortOptions' => ['newest', 'price_asc', 'price_desc', 'name', 'best_selling'],
        ]);
    }

    private function baseQuery(Request $request, ?Category $category, ?Brand $brand): Builder
    {
        return Product::query()
            ->visibleInStore()
            ->with(['brand:id,name,slug', 'images'])
            ->withAvg('approvedReviews as rating_avg', 'rating')
            ->withCount('approvedReviews as rating_count')
            ->when($category !== null, function (Builder $q) use ($category): void {
                $ids = [$category->id, ...$category->children()->pluck('id')->all()];
                $q->whereIn('category_id', $ids);
            })
            ->when($brand !== null, fn (Builder $q) => $q->where('brand_id', $brand->id))
            ->when($request->filled('q'), fn (Builder $q) => $q->search((string) $request->query('q')))
            ->when($request->filled('brands'), fn (Builder $q) => $q->whereIn(
                'brand_id',
                array_filter(array_map('intval', (array) $request->query('brands'))),
            ))
            ->when($request->filled('categories'), fn (Builder $q) => $q->whereIn(
                'category_id',
                array_filter(array_map('intval', (array) $request->query('categories'))),
            ))
            ->when($request->filled('min_price'), fn (Builder $q) => $q->where('price_cents', '>=', (int) ((float) $request->query('min_price') * 100)))
            ->when($request->filled('max_price'), fn (Builder $q) => $q->where('price_cents', '<=', (int) ((float) $request->query('max_price') * 100)))
            ->when($request->boolean('in_stock'), fn (Builder $q) => $q->purchasable())
            ->when($request->boolean('on_sale'), fn (Builder $q) => $q->onSale())
            ->tap(fn (Builder $q) => $this->applySort($q, (string) $request->query('sort', 'newest')));
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderByRaw('COALESCE(sale_price_cents, price_cents) ASC'),
            'price_desc' => $query->orderByRaw('COALESCE(sale_price_cents, price_cents) DESC'),
            'name' => $query->orderBy('name'),
            'best_selling' => $query->addSelect([
                'sold_count' => \App\Models\OrderItem::query()
                    ->selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('order_items.product_id', 'products.id'),
            ])->orderByDesc('sold_count'),
            default => $query->latest('published_at')->latest('id'),
        };
    }
}
