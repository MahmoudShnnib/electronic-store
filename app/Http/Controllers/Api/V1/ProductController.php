<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProductDetailResource;
use App\Http\Resources\V1\ProductResource;
use App\Models\Product;
use App\Services\Storefront\CatalogQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProductController extends Controller
{
    /**
     * Browse the catalog with search, filters, and sorting (see CatalogQuery
     * for the query string).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = CatalogQuery::fromRequest($request)
            ->products()
            ->paginate(min(50, max(1, (int) $request->query('per_page', '20'))))
            ->withQueryString();

        return ProductResource::collection($products);
    }

    /**
     * Counts for the filter panel, for the same query string as the listing.
     */
    public function facets(Request $request): JsonResponse
    {
        return response()->json(['data' => CatalogQuery::fromRequest($request)->facets()], options: JSON_UNESCAPED_UNICODE);
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
