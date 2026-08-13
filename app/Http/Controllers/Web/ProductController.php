<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Storefront\ProductPageService;
use Inertia\Inertia;
use Inertia\Response;

/** Renders the storefront product detail page. */
final class ProductController extends Controller
{
    public function show(Product $product, ProductPageService $page): Response
    {
        abort_unless($product->status->isVisibleInStore(), 404);

        return Inertia::render('storefront/Product', $page->showProps($product));
    }
}
