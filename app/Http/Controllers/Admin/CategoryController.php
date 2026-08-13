<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class CategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:categories.manage'];
    }

    public function index(Request $request): Response
    {
        $categories = Category::query()
            ->with('parent:id,name')
            ->withCount('products')
            ->when($request->filled('q'), fn ($q) => $q->whereRaw('name ILIKE ?', ['%'.$request->query('q').'%']))
            ->orderBy('position')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'name_ar' => $c->name_ar,
                'slug' => $c->slug,
                'parent' => $c->parent?->name,
                'parent_id' => $c->parent_id,
                'position' => $c->position,
                'is_active' => $c->is_active,
                'products_count' => $c->products_count,
                'description' => $c->description,
                'image_url' => $c->image_path !== null ? Storage::disk('public')->url($c->image_path) : null,
            ]);

        return Inertia::render('admin/categories/Index', [
            'categories' => $categories,
            'parentOptions' => Category::query()->root()->orderBy('name')->get(['id', 'name']),
            'filters' => (object) $request->only(['q']),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create($this->validated($request));

        return back()->with('success', __('Category created.'));
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        return back()->with('success', __('Category updated.'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists() || $category->children()->exists()) {
            return back()->with('error', __('Cannot delete a category that has products or subcategories.'));
        }

        $category->delete();

        return back()->with('success', __('Category deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(CategoryRequest $request, ?Category $category = null): array
    {
        $validated = $request->validated();

        // Replacing or clearing an image deletes the old file so the disk does
        // not accumulate orphans.
        $previous = $category?->image_path;

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('categories', 'public');
        } elseif ($validated['remove_image'] ?? false) {
            $validated['image_path'] = null;
        }

        if (array_key_exists('image_path', $validated) && $previous !== null && $previous !== $validated['image_path']) {
            Storage::disk('public')->delete($previous);
        }

        unset($validated['image'], $validated['remove_image']);

        $validated['slug'] ??= Str::slug($validated['name']);

        return $validated;
    }
}
