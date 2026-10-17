<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Content;

use App\Enums\PageSectionType;
use App\Enums\ProductSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\PageRequest;
use App\Http\Requests\Admin\Content\PageSectionRequest;
use App\Models\Category;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Product;
use App\Services\Admin\ProductSummaries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The page builder for the headless storefront's CMS
 * (docs/adr/0001-headless-storefront-split.md): the page list, and one page
 * with its sections. Sections are edited through PageSectionController.
 *
 * System pages (home) can be edited but never deleted or unpublished.
 */
final class PageController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:settings.manage'];
    }

    public function index(): Response
    {
        return Inertia::render('admin/pages/Index', [
            'pages' => Page::query()
                ->withCount(['sections', 'sections as active_sections_count' => fn ($query) => $query->where('is_active', true)])
                ->orderByDesc('is_system')
                ->orderBy('title')
                ->get()
                ->map(fn (Page $page): array => [
                    ...$page->only(['id', 'key', 'slug', 'title', 'title_ar', 'is_published', 'is_system', 'sections_count', 'active_sections_count']),
                    'updated_at' => $page->updated_at?->toIso8601String(),
                ]),
        ]);
    }

    public function store(PageRequest $request): RedirectResponse
    {
        $page = Page::query()->create([...$request->pageData(), 'is_system' => false]);

        return to_route('admin.pages.edit', $page)->with('success', __('Page created.'));
    }

    public function edit(Page $page, ProductSummaries $summaries): Response
    {
        $sections = $page->sections()->get();

        // Products the hero and manual carousels point at, so the pickers can
        // label them without a search first.
        $productIds = $sections->flatMap(fn (PageSection $section): array => match ($section->type) {
            PageSectionType::Hero => array_column($section->data['slides'] ?? [], 'product_id'),
            PageSectionType::ProductCarousel => $section->data['product_ids'] ?? [],
            default => [],
        })->unique()->values();

        return Inertia::render('admin/pages/Edit', [
            'page' => [
                ...$page->only(['id', 'key', 'slug', 'title', 'title_ar', 'is_published', 'is_system']),
                'seo' => (object) ($page->seo ?? []),
            ],
            'sections' => $sections->map(fn (PageSection $section): array => [
                'id' => $section->id,
                'type' => $section->type->value,
                'is_active' => $section->is_active,
                'data' => (object) ($section->data ?? []),
            ])->values(),
            'products' => $summaries->of(Product::query()->whereIn('id', $productIds))->keyBy('id'),
            'categories' => Category::query()
                ->orderBy('position')
                ->get(['id', 'parent_id', 'name', 'name_ar', 'is_active']),
            'types' => array_column(PageSectionType::cases(), 'value'),
            'sources' => array_column(ProductSource::cases(), 'value'),
            'storage_url' => rtrim(Storage::disk('public')->url(''), '/'),
            'limits' => [
                'slides' => PageSectionRequest::MAX_SLIDES,
                'tiles' => PageSectionRequest::MAX_TILES,
                'trust_items' => PageSectionRequest::MAX_TRUST_ITEMS,
                'carousel' => PageSectionRequest::MAX_CAROUSEL_PRODUCTS,
            ],
            'api_url' => route('api.v1.pages.show', $page->key ?? $page->slug),
        ]);
    }

    public function update(PageRequest $request, Page $page): RedirectResponse
    {
        $page->update($request->pageData());

        return back()->with('success', __('Page saved.'));
    }

    public function destroy(Page $page): RedirectResponse
    {
        abort_if($page->is_system, 403, __('System pages cannot be deleted.'));

        $images = $page->sections()->get()->flatMap(fn (PageSection $section): array => $section->imagePaths())->all();

        $page->delete();

        PageSectionController::deleteImages($images);

        return to_route('admin.pages.index')->with('success', __('Page deleted.'));
    }
}
