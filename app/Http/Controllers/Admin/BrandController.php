<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\BrandRequest;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class BrandController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:brands.manage'];
    }

    public function index(Request $request): Response
    {
        $brands = Brand::query()
            ->withCount('products')
            ->when($request->filled('q'), fn ($q) => $q->whereRaw('name ILIKE ?', ['%'.$request->query('q').'%']))
            ->orderBy('position')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Brand $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'name_ar' => $b->name_ar,
                'slug' => $b->slug,
                'logo' => $b->logo_path !== null ? Storage::disk('public')->url($b->logo_path) : null,
                'position' => $b->position,
                'is_active' => $b->is_active,
                'products_count' => $b->products_count,
                'description' => $b->description,
            ]);

        return Inertia::render('admin/brands/Index', [
            'brands' => $brands,
            'filters' => (object) $request->only(['q']),
        ]);
    }

    public function store(BrandRequest $request): RedirectResponse
    {
        Brand::create($this->validated($request));

        return back()->with('success', __('Brand created.'));
    }

    public function update(BrandRequest $request, Brand $brand): RedirectResponse
    {
        $previous = $brand->logo_path;

        $brand->update($this->validated($request));

        // Replacing the logo deletes the old file so the disk does not
        // accumulate orphans.
        if ($previous !== null && $brand->logo_path !== $previous) {
            Storage::disk('public')->delete($previous);
        }

        return back()->with('success', __('Brand updated.'));
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->products()->exists()) {
            return back()->with('error', __('Cannot delete a brand that has products.'));
        }

        $brand->delete();

        return back()->with('success', __('Brand deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(BrandRequest $request): array
    {
        $validated = $request->validated();

        $validated['slug'] ??= Str::slug($validated['name']);

        if ($request->hasFile('logo')) {
            $validated['logo_path'] = $request->file('logo')->store('brands', 'public');
        }

        unset($validated['logo']);

        return $validated;
    }
}
