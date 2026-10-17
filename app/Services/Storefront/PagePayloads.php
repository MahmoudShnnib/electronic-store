<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Enums\PageSectionType;
use App\Enums\ProductSource;
use App\Http\Resources\ProductCardResource;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Product;
use App\Models\Setting;
use App\Support\Content\DefaultPages;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the content API payloads (docs/adr/0001-headless-storefront-split.md):
 * the storefront boot payload and a page with its sections resolved, so a
 * page is one request. Product cards come out in the current app locale.
 *
 * Callers cache the result per locale; the cards' `wishlisted` flag is always
 * false here (the content API is anonymous) and the SPA overlays the
 * customer's wishlist itself.
 */
final class PagePayloads
{
    private const DEFAULT_CAROUSEL_LIMIT = 8;

    private const MAX_CAROUSEL_LIMIT = 24;

    public function __construct(private readonly HomePageService $home) {}

    /**
     * @return array<string, mixed>
     */
    public function storefront(): array
    {
        $contact = Setting::get('contact_info') ?? [];

        return [
            // The name dashboard Settings saves (what every Inertia layout shows);
            // the logo is the shipped public/logo.svg — there is no storefront
            // logo upload yet.
            'store' => [
                'name' => Setting::get('store_name') ?: config('app.name'),
                'logo' => is_file(public_path('logo.svg')) ? asset('logo.svg') : null,
            ],
            'locales' => ['ar', 'en'],
            'default_locale' => config('app.locale'),
            'currency' => [
                'code' => config('store.currency'),
                'symbol' => config('store.currency_symbols'),
                'fraction_digits' => 3,
            ],
            'contact' => [
                'phone' => $contact['phone'] ?? null,
                'email' => $contact['email'] ?? null,
            ],
            'social_links' => (object) array_filter(Setting::get('social_links') ?? []),
            'payment_methods' => array_values(config('store.payment_methods')),
            'cities' => config('store.cities'),
            'nav_categories' => $this->home->categoryCards(),
            'pages' => Page::query()
                ->published()
                ->orderBy('id')
                ->get(['key', 'slug', 'title', 'title_ar'])
                ->map(fn (Page $page): array => $page->only(['key', 'slug', 'title', 'title_ar']))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function page(Page $page): array
    {
        $sections = $page->sections()->active()->get();

        return [
            'key' => $page->key,
            'slug' => $page->slug,
            'title' => $page->title,
            'title_ar' => $page->title_ar,
            'seo' => (object) ($page->seo ?? []),
            'sections' => $sections
                ->map(fn (PageSection $section): ?array => $this->section($section, $page))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * One section as served, or null when it has nothing to show (an empty
     * carousel is dropped rather than rendered as a bare heading).
     *
     * @return array{id: int, type: string, data: array<string, mixed>}|null
     */
    private function section(PageSection $section, Page $page): ?array
    {
        $data = $section->data ?? [];

        $served = match ($section->type) {
            PageSectionType::Hero => [
                ...$data,
                // An empty list defers to the dashboard's Homepage screen, then
                // to automatic (null), exactly as the Inertia home page does.
                'slides' => $this->home->heroSlides(($data['slides'] ?? []) ?: null),
            ],
            PageSectionType::PromoTiles => [
                ...$data,
                'tiles' => $this->home->promoTiles(($data['tiles'] ?? []) ?: null),
            ],
            PageSectionType::CategoryRail => [...$data, 'categories' => $this->home->categoryCards()],
            PageSectionType::BrandStrip => [...$data, 'brands' => $this->home->brandCards()],
            PageSectionType::CategorySpotlight => [
                ...$data,
                'rows' => array_map(fn (array $row): array => [
                    'category' => $row['category'],
                    'products' => $row['products']->resolve(request()),
                ], $this->home->categorySections()),
            ],
            PageSectionType::ProductCarousel => $this->carousel($data),
            PageSectionType::ImageBanner => [...$data, 'image' => $this->publicUrl($data['image_path'] ?? null)],
            PageSectionType::RichText => $this->richText($data, $page),
            PageSectionType::TrustStrip,
            PageSectionType::Newsletter => $data,
        };

        if ($served === null
            || ($section->type === PageSectionType::CategorySpotlight && $served['rows'] === [])) {
            return null;
        }

        return ['id' => $section->id, 'type' => $section->type->value, 'data' => $served];
    }

    /**
     * Rich text as written in the page builder. On a static page nobody has
     * written yet, the dashboard Settings text for that page stands in, so the
     * SPA and the Inertia /pages/{page} show the same words. Nothing at all to
     * show drops the section.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function richText(array $data, Page $page): ?array
    {
        $written = filled($data['body'] ?? null) || filled($data['body_ar'] ?? null);

        if (! $written && array_key_exists((string) $page->key, DefaultPages::STATIC_PAGES)) {
            $data['body'] = $this->plainTextToHtml(Setting::get("page.{$page->key}"));
            $data['body_ar'] = null;
        }

        return filled($data['body'] ?? null) || filled($data['body_ar'] ?? null) ? $data : null;
    }

    /** Settings page text is plain: escape it, blank lines → paragraphs, newlines → <br>. */
    private function plainTextToHtml(mixed $text): ?string
    {
        if (! is_string($text) || trim($text) === '') {
            return null;
        }

        return collect(preg_split('/\R{2,}/', trim($text)))
            ->map(fn (string $paragraph): string => '<p>'.nl2br(e(trim($paragraph)), false).'</p>')
            ->implode('');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function carousel(array $data): ?array
    {
        $source = ProductSource::tryFrom((string) ($data['source'] ?? '')) ?? ProductSource::Featured;
        $limit = min(self::MAX_CAROUSEL_LIMIT, max(1, (int) ($data['limit'] ?? self::DEFAULT_CAROUSEL_LIMIT)));
        $categoryId = isset($data['category_id']) ? (int) $data['category_id'] : null;
        $productIds = array_map('intval', $data['product_ids'] ?? []);

        $query = Product::query()
            ->visibleInStore()
            ->with(['brand:id,name,slug', 'images'])
            ->withAvg('approvedReviews as rating_avg', 'rating')
            ->withCount('approvedReviews as rating_count')
            ->withStock();

        match ($source) {
            ProductSource::Featured => $query->featured()->latest('published_at'),
            ProductSource::NewArrivals => $query->latest('published_at'),
            ProductSource::OnSale => $query->onSale()->latest('published_at'),
            ProductSource::BestSellers => $query->addSelect([
                'sold_count' => OrderItem::query()
                    ->selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('order_items.product_id', 'products.id'),
            ])->orderByDesc('sold_count'),
            ProductSource::Category => $query
                ->whereIn('category_id', Category::subtreeIds($categoryId !== null ? [$categoryId] : [], activeOnly: true))
                ->latest('published_at'),
            ProductSource::Manual => $query->whereIn('id', $productIds),
        };

        $products = $query->take($limit)->get();

        if ($source === ProductSource::Manual) {
            // Keep the order the admin picked.
            $products = $products->sortBy(fn (Product $product): int|false => array_search($product->id, $productIds, true))->values();
        }

        if ($products->isEmpty()) {
            return null;
        }

        return [
            ...$data,
            'source' => $source->value,
            'products' => ProductCardResource::collection($products)->resolve(request()),
            'link' => $this->carouselLink($source, $categoryId),
        ];
    }

    /**
     * Query string for the SPA's "view all" link, or null when the carousel
     * has no listing equivalent.
     *
     * @return array<string, string>|null
     */
    private function carouselLink(ProductSource $source, ?int $categoryId): ?array
    {
        return match ($source) {
            ProductSource::Featured => ['featured' => '1'],
            ProductSource::OnSale => ['on_sale' => '1'],
            ProductSource::NewArrivals => ['sort' => 'newest'],
            ProductSource::Category => ($slug = Category::query()->whereKey($categoryId)->value('slug')) !== null
                ? ['category' => $slug]
                : null,
            ProductSource::BestSellers, ProductSource::Manual => null,
        };
    }

    private function publicUrl(?string $path): ?string
    {
        return $path !== null && $path !== '' ? Storage::disk('public')->url($path) : null;
    }
}
