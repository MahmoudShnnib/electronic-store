<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Catalog\SaveProduct;
use App\Actions\Catalog\StoreProductImages;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Requests\Admin\Products\BulkProductActionRequest;
use App\Http\Requests\Admin\Products\UploadProductImagesRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SpecificationGroup;
use App\Models\Supplier;
use App\Support\Money;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class ProductController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::query()
            ->with(['category:id,name', 'brand:id,name', 'images'])
            ->withSum('stockLevels as stock_quantity', 'quantity')
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->when($request->filled('q'), fn ($q) => $q->search((string) $request->query('q')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->query('status')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', (int) $request->query('category_id')))
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', (int) $request->query('brand_id')))
            ->when(
                in_array($request->query('sort'), ['name', 'sku', 'price_cents', 'created_at', 'stock_quantity'], true),
                fn ($q) => $q->orderBy((string) $request->query('sort'), $request->query('dir') === 'desc' ? 'desc' : 'asc'),
                fn ($q) => $q->latest(),
            )
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'slug' => $p->slug,
                'category' => $p->category?->name,
                'brand' => $p->brand?->name,
                'price_formatted' => Money::format($p->price_cents),
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'is_featured' => $p->is_featured,
                'stock' => (int) ($p->stock_quantity ?? 0),
                'low_stock' => (int) ($p->stock_quantity ?? 0) <= $p->low_stock_threshold,
                'image' => ($img = $p->images->firstWhere('is_primary', true) ?? $p->images->first()) !== null
                    ? Storage::disk('public')->url($img->path) : null,
                'deleted_at' => $p->deleted_at?->toDateTimeString(),
            ]);

        return Inertia::render('admin/products/Index', [
            'products' => $products,
            // Cast so an empty filter set serializes as {} — as a JS array, a
            // `sort` key would resolve to Array.prototype.sort in the client.
            'filters' => (object) $request->only(['q', 'status', 'category_id', 'brand_id', 'sort', 'dir', 'trashed']),
            'statuses' => array_map(fn (ProductStatus $s) => ['value' => $s->value, 'label' => $s->label()], ProductStatus::cases()),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'can' => [
                'create' => $request->user()->can('create', Product::class),
                'update' => $request->user()->can('products.update'),
                'delete' => $request->user()->can('products.delete'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('admin/products/Form', $this->formProps(null));
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

    public function edit(Request $request, Product $product): Response
    {
        $this->authorize('update', $product);

        return Inertia::render('admin/products/Form', $this->formProps($product));
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

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product moved to trash.');
    }

    public function restore(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('restore', $product);

        $product->restore();

        return back()->with('success', 'Product restored.');
    }

    public function bulk(BulkProductActionRequest $request): RedirectResponse
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
                'activate' => ['status' => ProductStatus::Active],
                'deactivate' => ['status' => ProductStatus::Inactive],
                'feature' => ['is_featured' => true],
                'unfeature' => ['is_featured' => false],
            });
        }

        return back()->with('success', 'Bulk action applied.');
    }

    public function storeImages(UploadProductImagesRequest $request, Product $product, StoreProductImages $storeImages): RedirectResponse
    {
        $this->authorize('update', $product);

        $storeImages->handle($product, $request->file('images'));

        return back()->with('success', 'Images uploaded.');
    }

    public function destroyImage(Request $request, Product $product, ProductImage $image, StoreProductImages $storeImages): RedirectResponse
    {
        $this->authorize('update', $product);

        abort_unless($image->product_id === $product->id, 404);

        $storeImages->delete($image);

        return back()->with('success', 'Image removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?Product $product): array
    {
        $product?->load(['images', 'variants', 'specificationValues']);

        return [
            'product' => $product === null ? null : [
                'id' => $product->id,
                'name' => $product->name,
                'name_ar' => $product->name_ar,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'category_id' => $product->category_id,
                'brand_id' => $product->brand_id,
                'supplier_id' => $product->supplier_id,
                'short_description' => $product->short_description,
                'short_description_ar' => $product->short_description_ar,
                'description' => $product->description,
                'description_ar' => $product->description_ar,
                'cost_price' => Money::toDecimal($product->cost_price_cents),
                'price' => Money::toDecimal($product->price_cents),
                'sale_price' => $product->sale_price_cents !== null ? Money::toDecimal($product->sale_price_cents) : null,
                'sale_starts_at' => $product->sale_starts_at?->format('Y-m-d\TH:i'),
                'sale_ends_at' => $product->sale_ends_at?->format('Y-m-d\TH:i'),
                'tax_rate' => $product->tax_rate,
                'low_stock_threshold' => $product->low_stock_threshold,
                'warranty_months' => $product->warranty_months,
                'weight_grams' => $product->weight_grams,
                'length_mm' => $product->length_mm,
                'width_mm' => $product->width_mm,
                'height_mm' => $product->height_mm,
                'status' => $product->status->value,
                'is_featured' => $product->is_featured,
                'meta_title' => $product->meta_title,
                'meta_description' => $product->meta_description,
                'images' => $product->images->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => Storage::disk('public')->url($img->path),
                    'is_primary' => $img->is_primary,
                ]),
                'variants' => $product->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'sku' => $v->sku,
                    'barcode' => $v->barcode,
                    'attributes' => $v->attributes,
                    'cost_price' => Money::toDecimal($v->cost_price_cents),
                    'price' => Money::toDecimal($v->price_cents),
                    'sale_price' => $v->sale_price_cents !== null ? Money::toDecimal($v->sale_price_cents) : null,
                    'low_stock_threshold' => $v->low_stock_threshold,
                    'is_active' => $v->is_active,
                ]),
                'specifications' => $product->specificationValues
                    ->mapWithKeys(fn ($v) => [$v->specification_attribute_id => [
                        'value' => $v->value,
                        'value_ar' => $v->value_ar,
                    ]]),
            ],
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'parent_id']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->orderBy('company_name')->get(['id', 'company_name']),
            'specificationGroups' => SpecificationGroup::query()
                ->with('attributes')
                ->orderBy('position')
                ->get()
                ->map(fn (SpecificationGroup $g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'attributes' => $g->attributes->map(fn ($a) => [
                        'id' => $a->id,
                        'name' => $a->name,
                        'unit' => $a->unit,
                        'type' => $a->type,
                    ]),
                ]),
            'statuses' => array_map(fn (ProductStatus $s) => ['value' => $s->value, 'label' => $s->label()], ProductStatus::cases()),
        ];
    }
}
