<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** The active category tree for the mobile catalog. */
final class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
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
}
