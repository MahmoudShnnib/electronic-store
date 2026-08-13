<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Products;

use App\Actions\Catalog\SaveProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Product;
use App\Services\Admin\ProductQueryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Product CRUD only. Restore, bulk actions and image uploads each have their
 * own single-action controller in this namespace.
 */
final class ProductController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ProductQueryService $query) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        return Inertia::render('admin/products/Index', $this->query->indexProps($request));
    }

    public function create(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('admin/products/Form', $this->query->formProps(null));
    }

    public function store(ProductRequest $request, SaveProduct $saveProduct): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $product = $saveProduct->handle(
            null,
            $request->productAttributes(),
            $request->variantPayload(),
            $request->specificationPayload(),
            $request->user()->id,
        );

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', "Product “{$product->name}” created.");
    }

    public function edit(Product $product): Response
    {
        $this->authorize('update', $product);

        return Inertia::render('admin/products/Form', $this->query->formProps($product));
    }

    public function update(ProductRequest $request, Product $product, SaveProduct $saveProduct): RedirectResponse
    {
        $this->authorize('update', $product);

        $saveProduct->handle(
            $product,
            $request->productAttributes(),
            $request->variantPayload(),
            $request->specificationPayload(),
            $request->user()->id,
        );

        return back()->with('success', __('Product updated.'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return redirect()->route('admin.products.index')->with('success', __('Product moved to trash.'));
    }
}
