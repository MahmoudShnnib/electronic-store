<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The tags a crawler or a link preview sees for one storefront URL, before
 * any JavaScript runs: title, description, image, canonical, Open Graph and
 * (for a product) Product/Offer structured data. Mirrors the SPA's routes.
 *
 * Arabic first, like the shop: a translated name or description wins.
 */
final class StorefrontSeo
{
    /**
     * @return array{status: int, title: string, description: string, image: string|null, url: string, type: string, json_ld: list<array<string, mixed>>}
     */
    public function forPath(string $path): array
    {
        $path = trim($path, '/');
        $segments = $path === '' ? [] : explode('/', $path);

        return match (true) {
            $segments === [] => $this->home(),
            count($segments) === 2 && $segments[0] === 'products' => $this->product($segments[1]),
            count($segments) === 2 && $segments[0] === 'category' => $this->category($segments[1]),
            count($segments) === 2 && $segments[0] === 'brand' => $this->brand($segments[1]),
            count($segments) === 2 && in_array($segments[0], ['p', 'pages'], true) => $this->page($segments[1]),
            default => $this->meta($this->storeName(), $this->defaultDescription(), null, $path),
        };
    }

    public function storeName(): string
    {
        return (string) (Setting::get('store_name') ?: config('app.name'));
    }

    /** The public base URL of the storefront (links in tags must be absolute). */
    public function baseUrl(): string
    {
        return rtrim((string) config('store.storefront_url'), '/');
    }

    private function home(): array
    {
        $meta = $this->meta($this->storeName(), $this->defaultDescription(), null, '');
        $meta['json_ld'] = [[
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $this->storeName(),
            'url' => $this->baseUrl().'/',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => $this->baseUrl().'/search?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ]];

        return $meta;
    }

    private function product(string $slug): array
    {
        $product = Product::query()
            ->visibleInStore()
            ->where('slug', $slug)
            ->with(['brand:id,name', 'images', 'activeVariants'])
            ->withAvg('approvedReviews as rating_avg', 'rating')
            ->withCount('approvedReviews as rating_count')
            ->first();

        if ($product === null) {
            return $this->notFound("products/{$slug}");
        }

        $name = $product->name_ar ?: $product->name;
        $description = $this->plain($product->short_description_ar ?: $product->short_description ?: $product->description_ar ?: $product->description)
            ?: $this->defaultDescription();
        $image = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
        $imageUrl = $image !== null ? Storage::disk('public')->url($image->path) : null;

        // Priced like the listing: the cheapest live variant of a variant product.
        $prices = $product->has_variants && $product->activeVariants->isNotEmpty()
            ? $product->activeVariants->each(fn ($v) => $v->setRelation('product', $product))->map(fn ($v) => $v->currentPriceCents())
            : collect([$product->currentPriceCents()]);

        $offer = [
            '@type' => 'Offer',
            'priceCurrency' => (string) config('store.currency'),
            'price' => number_format($prices->min() / 100, 3, '.', ''),
            'availability' => $product->status->isPurchasable() && $product->availableQuantity() > 0
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
            'url' => $this->baseUrl()."/products/{$product->slug}",
        ];

        $jsonLd = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $name,
            'sku' => $product->sku,
            'description' => $description,
            'image' => $imageUrl,
            'brand' => $product->brand !== null ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
            'offers' => $offer,
            'aggregateRating' => (int) $product->rating_count > 0 ? [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float) $product->rating_avg, 1),
                'reviewCount' => (int) $product->rating_count,
            ] : null,
        ], fn ($value) => $value !== null);

        $meta = $this->meta("{$name} — {$this->storeName()}", $description, $imageUrl, "products/{$product->slug}", 'product');
        $meta['json_ld'] = [$jsonLd];
        $meta['price'] = $offer['price'];

        return $meta;
    }

    private function category(string $slug): array
    {
        $category = Category::query()->active()->where('slug', $slug)->first();

        if ($category === null) {
            return $this->notFound("category/{$slug}");
        }

        $name = $category->name_ar ?: $category->name;
        $image = $category->image_path !== null ? Storage::disk('public')->url($category->image_path) : null;

        return $this->meta("{$name} — {$this->storeName()}", $this->plain($category->description) ?: $this->defaultDescription(), $image, "category/{$slug}");
    }

    private function brand(string $slug): array
    {
        $brand = Brand::query()->active()->where('slug', $slug)->first();

        if ($brand === null) {
            return $this->notFound("brand/{$slug}");
        }

        $name = $brand->name_ar ?: $brand->name;
        $image = $brand->logo_path !== null ? Storage::disk('public')->url($brand->logo_path) : null;

        return $this->meta("{$name} — {$this->storeName()}", $this->plain($brand->description) ?: $this->defaultDescription(), $image, "brand/{$slug}");
    }

    private function page(string $key): array
    {
        $page = Page::query()->published()->addressedBy($key)->first();

        if ($page === null) {
            return $this->notFound("p/{$key}");
        }

        $seo = $page->seo ?? [];

        return $this->meta(
            ($page->title_ar ?: $page->title)." — {$this->storeName()}",
            $this->plain($seo['description'] ?? null) ?: $this->defaultDescription(),
            null,
            'p/'.($page->key ?? $page->slug),
        );
    }

    private function notFound(string $path): array
    {
        return [...$this->meta($this->storeName(), $this->defaultDescription(), null, $path), 'status' => 404];
    }

    /**
     * @return array{status: int, title: string, description: string, image: string|null, url: string, type: string, json_ld: list<array<string, mixed>>}
     */
    private function meta(string $title, string $description, ?string $image, string $path, string $type = 'website'): array
    {
        return [
            'status' => 200,
            'title' => $title,
            'description' => Str::limit($description, 160),
            'image' => $image,
            'url' => $this->baseUrl().'/'.ltrim($path, '/'),
            'type' => $type,
            'json_ld' => [],
        ];
    }

    private function defaultDescription(): string
    {
        return (string) config('store.seo_description');
    }

    private function plain(?string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags((string) $text)) ?? '');
    }
}
