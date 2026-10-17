<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Http\Controllers\Admin\HomepageController;
use App\Http\Resources\ProductCardResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the Inertia props for the storefront home page: the category rail,
 * brand strip and the four product carousels. Everything expensive is cached
 * for ten minutes.
 */
final class HomePageService
{
    /**
     * @return array<string, mixed>
     */
    public function props(): array
    {
        $sections = Cache::remember('home.sections', now()->addMinutes(10), function (): array {
            $card = fn ($query) => $query
                ->visibleInStore()
                ->with(['brand:id,name,slug', 'images'])
                ->withAvg('approvedReviews as rating_avg', 'rating')
                ->withCount('approvedReviews as rating_count')
                ->withStock()
                ->take(8)
                ->get();

            return [
                'featured' => $card(Product::query()->featured()->latest('published_at')),
                'newArrivals' => $card(Product::query()->latest('published_at')),
                'onSale' => $card(Product::query()->onSale()),
                'bestSellers' => $card(Product::query()
                    ->withCount(['stockLevels'])
                    ->addSelect([
                        'sold_count' => OrderItem::query()
                            ->selectRaw('COALESCE(SUM(quantity), 0)')
                            ->whereColumn('order_items.product_id', 'products.id'),
                    ])
                    ->orderByDesc('sold_count')),
            ];
        });

        return [
            'categories' => $this->categoryCards(),
            'brands' => $this->brandCards(),
            'featured' => ProductCardResource::collection($sections['featured']),
            'newArrivals' => ProductCardResource::collection($sections['newArrivals']),
            'onSale' => ProductCardResource::collection($sections['onSale']),
            'bestSellers' => ProductCardResource::collection($sections['bestSellers']),
            'categorySections' => $this->categorySections(),
            'heroSlides' => $this->heroSlides(),
            'promoTiles' => $this->promoTiles(),
        ];
    }

    /**
     * The hero as curated in the dashboard (Homepage page), or null for
     * "automatic" — the storefront then rotates featured products itself.
     *
     * Products that can't be shown (draft, hidden, deleted since) are skipped
     * rather than failing the page; if none survive, it falls back to
     * automatic so the hero is never empty.
     *
     * @return list<array{product: array<string, mixed>, title: string|null, title_ar: string|null, image: string|null}>|null
     */
    public function heroSlides(?array $slides = null): ?array
    {
        $config = $slides ?? Setting::get(HomepageController::HERO_KEY) ?? [];

        if ($config === []) {
            return null;
        }

        // Only the dashboard's own list shares the cache; a page section's
        // slides are cached with the rest of its page by ContentCache.
        $query = fn () => Product::query()
            ->visibleInStore()
            ->whereIn('id', array_column($config, 'product_id'))
            ->with(['brand:id,name,slug', 'images'])
            ->withAvg('approvedReviews as rating_avg', 'rating')
            ->withCount('approvedReviews as rating_count')
            ->withStock()
            ->get()
            ->keyBy('id');

        $products = $slides === null ? Cache::remember('home.hero', now()->addMinutes(10), $query) : $query();

        $resolved = [];

        foreach ($config as $slide) {
            $product = $products->get($slide['product_id']);

            if ($product === null) {
                continue;
            }

            $resolved[] = [
                // Resolved here, not cached: the card carries the viewer's own
                // wishlist state.
                'product' => (new ProductCardResource($product))->resolve(request()),
                'title' => $slide['title'] ?? null,
                'title_ar' => $slide['title_ar'] ?? null,
                'image' => $this->publicUrl($slide['image_path'] ?? null),
            ];
        }

        return $resolved === [] ? null : $resolved;
    }

    /**
     * The two promo tiles beside the hero as curated in the dashboard, or null
     * for "automatic" (best live discount, then biggest category). A tile whose
     * category has nothing to sell is dropped, like on the category rail.
     *
     * @return list<array<string, mixed>>|null
     */
    public function promoTiles(?array $tiles = null): ?array
    {
        $config = $tiles ?? Setting::get(HomepageController::TILES_KEY) ?? [];

        if ($config === []) {
            return null;
        }

        $cards = collect($this->categoryCards())->keyBy('id');
        $tiles = [];

        foreach ($config as $tile) {
            $card = $cards->get($tile['category_id']);

            if ($card === null) {
                continue;
            }

            $tiles[] = [
                ...$card,
                'title' => $tile['title'] ?? null,
                'title_ar' => $tile['title_ar'] ?? null,
                'image' => $this->publicUrl($tile['image_path'] ?? null) ?? $card['image'],
            ];
        }

        return $tiles === [] ? null : $tiles;
    }

    /**
     * The brand strip: active brands in their dashboard order, each with its
     * uploaded logo as a public URL (null falls back to a monogram tile).
     *
     * @return list<array{id: int, name: string, name_ar: string|null, slug: string, logo: string|null}>
     */
    public function brandCards(): array
    {
        return Brand::query()
            ->active()
            ->orderBy('position')
            ->orderBy('name')
            ->take(12)
            ->get(['id', 'name', 'name_ar', 'slug', 'logo_path'])
            ->map(fn (Brand $brand): array => [
                'id' => $brand->id,
                'name' => $brand->name,
                'name_ar' => $brand->name_ar,
                'slug' => $brand->slug,
                'logo' => $this->publicUrl($brand->logo_path),
            ])
            ->all();
    }

    private function publicUrl(?string $path): ?string
    {
        return $path !== null ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Themed product rows for the two biggest root categories — the "gaming
     * chairs" / "headsets" style sections, driven by whatever this catalogue
     * actually sells most of rather than a hardcoded pair.
     *
     * @return list<array{category: array{name: string, name_ar: string|null, slug: string}, products: mixed}>
     */
    public function categorySections(): array
    {
        $sections = Cache::remember('home.category_sections', now()->addMinutes(10), function (): array {
            $roots = collect($this->categoryCards())
                ->sortByDesc('products_count')
                ->take(2);

            $categories = Category::query()->active()->get(['id', 'parent_id', 'name', 'name_ar', 'slug']);
            $byParent = $categories->groupBy('parent_id');

            $subtree = function (int $id) use (&$subtree, $byParent): array {
                $ids = [$id];

                foreach ($byParent->get($id, collect()) as $child) {
                    $ids = [...$ids, ...$subtree($child->id)];
                }

                return $ids;
            };

            return $roots
                ->map(function (array $card) use ($subtree, $categories): ?array {
                    $root = $categories->firstWhere('id', $card['id']);

                    if ($root === null) {
                        return null;
                    }

                    $products = Product::query()
                        ->visibleInStore()
                        ->whereIn('category_id', $subtree($root->id))
                        ->with(['brand:id,name,slug', 'images'])
                        ->withAvg('approvedReviews as rating_avg', 'rating')
                        ->withCount('approvedReviews as rating_count')
                        ->withStock()
                        ->latest('published_at')
                        ->take(6)
                        ->get();

                    if ($products->count() < 4) {
                        return null;
                    }

                    return [
                        'category' => [
                            'name' => $root->name,
                            'name_ar' => $root->name_ar,
                            'slug' => $root->slug,
                        ],
                        'products' => $products,
                    ];
                })
                ->filter()
                ->values()
                ->all();
        });

        return array_map(fn (array $section): array => [
            'category' => $section['category'],
            'products' => ProductCardResource::collection($section['products']),
        ], $sections);
    }

    /**
     * Root categories for the homepage rail, each carrying how many products sit
     * anywhere beneath it and a photo borrowed from one of those products.
     *
     * Categories with nothing to sell are dropped — a storefront tile that leads
     * to an empty listing is worse than no tile.
     *
     * Public because the layout's "All categories" header menu shares the same
     * cached list via Inertia::share (see AppServiceProvider).
     *
     * @return list<array{id: int, name: string, name_ar: string|null, slug: string, image: string|null, products_count: int, max_discount: int}>
     */
    public function categoryCards(): array
    {
        return Cache::remember('home.category_cards', now()->addMinutes(10), function (): array {
            $categories = Category::query()->active()->orderBy('position')->get();

            /** @var Collection<int|null, Collection<int, Category>> $byParent */
            $byParent = $categories->groupBy('parent_id');

            // Every id in a category's subtree, itself included.
            $subtree = function (int $id) use (&$subtree, $byParent): array {
                $ids = [$id];

                foreach ($byParent->get($id, collect()) as $child) {
                    $ids = [...$ids, ...$subtree($child->id)];
                }

                return $ids;
            };

            // One pass over the visible catalogue; grouped in memory afterwards.
            $products = Product::query()
                ->visibleInStore()
                ->with(['images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('position')])
                ->get(['id', 'category_id', 'price_cents', 'sale_price_cents', 'sale_starts_at', 'sale_ends_at']);

            $byCategory = $products->groupBy('category_id');

            $cards = [];

            foreach ($byParent->get(null, collect()) as $root) {
                $ids = $subtree($root->id);
                $owned = collect($ids)->flatMap(fn (int $id) => $byCategory->get($id, collect()));

                if ($owned->isEmpty()) {
                    continue;
                }

                // An image uploaded against the category in the dashboard always
                // wins; borrowing a product photo is only the fallback for
                // categories nobody has given artwork to yet.
                $path = $root->image_path
                    ?? $owned->flatMap(fn (Product $product) => $product->images)->first()?->path;

                // Best live discount anywhere in the subtree, for the promo tiles.
                $maxDiscount = $owned
                    ->map(function (Product $product): int {
                        if (! $product->saleIsActive() || $product->price_cents <= 0) {
                            return 0;
                        }

                        return (int) round(($product->price_cents - $product->currentPriceCents()) / $product->price_cents * 100);
                    })
                    ->max() ?? 0;

                $cards[] = [
                    'id' => $root->id,
                    'name' => $root->name,
                    'name_ar' => $root->name_ar,
                    'slug' => $root->slug,
                    'image' => $path !== null ? Storage::disk('public')->url($path) : null,
                    'products_count' => $owned->count(),
                    'max_discount' => $maxDiscount,
                ];
            }

            return $cards;
        });
    }
}
