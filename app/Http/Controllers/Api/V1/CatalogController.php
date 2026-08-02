<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BrandResource;
use App\Http\Resources\V1\CategoryResource;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CatalogController extends Controller
{
    /**
     * The active category tree.
     */
    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()
                ->active()
                ->root()
                ->with(['children' => fn ($q) => $q->where('is_active', true)->withCount('products')])
                ->withCount('products')
                ->orderBy('position')
                ->get(),
        );
    }

    /**
     * All active brands.
     */
    public function brands(): AnonymousResourceCollection
    {
        return BrandResource::collection(
            Brand::query()->active()->withCount('products')->orderBy('position')->get(),
        );
    }
}
