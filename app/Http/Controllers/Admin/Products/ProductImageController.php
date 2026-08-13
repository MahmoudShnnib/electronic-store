<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Products;

use App\Actions\Catalog\StoreProductImages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Products\UploadProductImagesRequest;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

/** Uploads and removes a product's gallery images. */
final class ProductImageController extends Controller
{
    use AuthorizesRequests;

    public function store(UploadProductImagesRequest $request, Product $product, StoreProductImages $storeImages): RedirectResponse
    {
        $this->authorize('update', $product);

        $storeImages->handle($product, $request->file('images'));

        return back()->with('success', __('Images uploaded.'));
    }

    public function destroy(Product $product, ProductImage $image, StoreProductImages $storeImages): RedirectResponse
    {
        $this->authorize('update', $product);

        abort_unless($image->product_id === $product->id, 404);

        $storeImages->delete($image);

        return back()->with('success', __('Image removed.'));
    }
}
