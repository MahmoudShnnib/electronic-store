<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Products;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Products\BulkProductActionRequest;
use App\Models\Product;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

/**
 * Applies one action (activate, deactivate, feature, unfeature, delete) to a
 * batch of selected products, authorizing each product individually.
 */
final class BulkProductActionController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(BulkProductActionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $products = Product::query()->whereIn('id', $validated['ids'])->get();

        foreach ($products as $product) {
            if ($validated['action'] === 'delete') {
                $this->authorize('delete', $product);
                $product->delete();

                continue;
            }

            $this->authorize('update', $product);

            $product->update(match ($validated['action']) {
                // First activation stamps the publish date, matching the
                // single-product form — bulk-activated products enter
                // "Newest" too.
                'activate' => ['status' => ProductStatus::Active, 'published_at' => $product->published_at ?? now()],
                'deactivate' => ['status' => ProductStatus::Inactive],
                'feature' => ['is_featured' => true],
                'unfeature' => ['is_featured' => false],
            });
        }

        return back()->with('success', __('Bulk action applied.'));
    }
}
