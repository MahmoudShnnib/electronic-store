<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProductDetailResource;
use App\Http\Resources\V1\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProductController extends Controller
{
    /**
     * Browse the catalog with search, filters, and sorting.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->visibleInStore()
            ->with(['brand:id,name,slug', 'category:id,name,slug', 'images'])
            ->withAvg('approvedReviews as rating_avg', 'rating')
            ->withCount('approvedReviews as rating_count')
            ->when($request->filled('q'), fn ($q) => $q->search((string) $request->query('q')))
            ->when($request->filled('category'), fn ($q) => $q->whereHas(
                'category',
                fn ($qq) => $qq->where('slug', (string) $request->query('category')),
            ))
            ->when($request->filled('brand'), fn ($q) => $q->whereHas(
                'brand',
                fn ($qq) => $qq->where('slug', (string) $request->query('brand')),
            ))
            ->when($request->boolean('featured'), fn ($q) => $q->featured())
            ->when($request->boolean('on_sale'), fn ($q) => $q->onSale())
            ->when($request->query('sort') === 'price_asc', fn ($q) => $q->orderByRaw('COALESCE(sale_price_cents, price_cents) ASC'))
            ->when($request->query('sort') === 'price_desc', fn ($q) => $q->orderByRaw('COALESCE(sale_price_cents, price_cents) DESC'))
            ->when(! in_array($request->query('sort'), ['price_asc', 'price_desc'], true), fn ($q) => $q->latest('published_at'))
            ->paginate(min(50, max(1, (int) $request->query('per_page', '20'))));

        return ProductResource::collection($products);
    }

    /**
     * A single product by slug, with gallery, variants, and specifications.
     */
    public function show(Product $product): ProductDetailResource
    {
        abort_unless($product->status->isVisibleInStore(), 404);

        $product->load([
            'brand:id,name,slug', 'category:id,name,slug', 'images',
            'activeVariants', 'specificationValues.attribute.group',
        ]);

        $product->loadAvg('approvedReviews as rating_avg', 'rating');
        $product->loadCount('approvedReviews as rating_count');

        return new ProductDetailResource($product);
    }
}
