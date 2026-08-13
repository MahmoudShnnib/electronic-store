<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Products;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

/** Brings a soft-deleted product back from the trash. */
final class RestoreProductController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Product $product): RedirectResponse
    {
        $this->authorize('restore', $product);

        $product->restore();

        return back()->with('success', 'Product restored.');
    }
}
