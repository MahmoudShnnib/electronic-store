<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\Brand;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The API catalogue: one set of filters read from the query string, used for
 * both the product listing and its facets so the counts always describe the
 * listing the customer is looking at.
 *
 * Query string: `q`, `categories` / `brands` (slugs, comma-separated or as an
 * array; the older single `category` / `brand` still work), `min_price` /
 * `max_price` (dinars, like the product payload's prices), `in_stock`,
 * `on_sale`, `featured`, `sort`.
 *
 * A category matches its whole active subtree, and several categories (or
 * brands) match any of them. Each facet ignores its own filter, so ticking a
 * second brand still shows what the other brands would add.
 *
 * The Inertia catalogue (Web\CatalogController) has its own copy of these
 * filters; it goes away when the SPA takes over (docs/adr/0001).
 */
final class CatalogQuery
{
    /** @var list<string> */
    public const SORTS = ['newest', 'price_asc', 'price_desc', 'name', 'best_selling'];

    /**
     * @param  list<string>  $categorySlugs
     * @param  list<string>  $brandSlugs
     */
    private function __construct(
        private readonly ?string $search,
        private readonly array $categorySlugs,
        private readonly array $brandSlugs,
        private readonly ?int $minPriceCents,
        private readonly ?int $maxPriceCents,
        private readonly bool $inStock,
        private readonly bool $onSale,
        private readonly bool $featured,
        private readonly string $sort,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $price = fn (string $key): ?int => is_numeric($request->query($key)) && (float) $request->query($key) >= 0
            ? Money::fromDecimal((string) $request->query($key))
            : null;

        $sort = (string) $request->query('sort', 'newest');

        return new self(
            search: $request->filled('q') ? trim((string) $request->query('q')) : null,
            categorySlugs: self::slugs($request, 'categories', 'category'),
            brandSlugs: self::slugs($request, 'brands', 'brand'),
            minPriceCents: $price('min_price'),
            maxPriceCents: $price('max_price'),
            inStock: $request->boolean('in_stock'),
            onSale: $request->boolean('on_sale'),
            featured: $request->boolean('featured'),
            sort: in_array($sort, self::SORTS, true) ? $sort : 'newest',
        );
    }

    /** The filtered, sorted listing with everything a product card needs. */
    public function products(): Builder
    {
        $query = $this->filtered()
            ->with(['brand:id,name,slug', 'category:id,name,slug', 'images', 'activeVariants'])
            ->withAvg('approvedReviews as rating_avg', 'rating')
            ->withCount('approvedReviews as rating_count')
            ->withStock();

        match ($this->sort) {
            // By the price the card shows: the cheapest a shopper can pay, variants included.
            'price_asc' => $query->orderByRaw(Product::MIN_PRICE.' ASC'),
            'price_desc' => $query->orderByRaw(Product::MIN_PRICE.' DESC'),
            'name' => $query->orderBy('name'),
            'best_selling' => $query->addSelect([
                'sold_count' => OrderItem::query()
                    ->selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('order_items.product_id', 'products.id'),
            ])->orderByDesc('sold_count'),
            default => $query->latest('published_at'),
        };

        // A stable tiebreak, so paging never repeats or skips a product.
        return $query->orderByDesc('products.id');
    }

    /**
     * Counts for the filter panel.
     *
     * Categories carry `parent_id` so the client can draw the tree; a count
     * covers the category's whole subtree. Options with nothing to show are
     * left out unless they are selected, so a selection never disappears.
     *
     * @return array<string, mixed>
     */
    public function facets(): array
    {
        $price = $this->filtered(except: 'price')
            ->toBase()
            ->selectRaw('MIN('.Product::MIN_PRICE.') AS min_cents, MAX('.Product::MAX_PRICE.') AS max_cents')
            ->first();

        return [
            'total' => $this->filtered()->count(),
            'categories' => $this->categoryFacet(),
            'brands' => $this->brandFacet(),
            'price' => [
                'min' => $price?->min_cents !== null ? Money::toDecimal((int) $price->min_cents) : null,
                'max' => $price?->max_cents !== null ? Money::toDecimal((int) $price->max_cents) : null,
            ],
            'in_stock' => $this->inStockScope($this->filtered(except: 'in_stock'))->count(),
            'on_sale' => $this->filtered(except: 'on_sale')->onSaleIncludingVariants()->count(),
            'selected' => [
                'categories' => $this->categorySlugs,
                'brands' => $this->brandSlugs,
                'min_price' => $this->minPriceCents !== null ? Money::toDecimal($this->minPriceCents) : null,
                'max_price' => $this->maxPriceCents !== null ? Money::toDecimal($this->maxPriceCents) : null,
                'in_stock' => $this->inStock,
                'on_sale' => $this->onSale,
                'featured' => $this->featured,
                'sort' => $this->sort,
            ],
        ];
    }

    /**
     * Every filter except the one a facet is counting.
     *
     * @param  'categories'|'brands'|'price'|'in_stock'|'on_sale'|null  $except
     */
    private function filtered(?string $except = null): Builder
    {
        return Product::query()
            ->visibleInStore()
            ->when($this->search !== null, fn (Builder $q) => $q->search($this->search))
            ->when($except !== 'categories' && $this->categorySlugs !== [], fn (Builder $q) => $q->whereIn(
                'category_id',
                Category::subtreeIds(
                    Category::query()->active()->whereIn('slug', $this->categorySlugs)->pluck('id')->all(),
                    activeOnly: true,
                ),
            ))
            ->when($except !== 'brands' && $this->brandSlugs !== [], fn (Builder $q) => $q->whereIn(
                'brand_id',
                Brand::query()->active()->whereIn('slug', $this->brandSlugs)->select('id'),
            ))
            // A product matches when any of its prices falls in the range: a phone
            // whose 512GB costs 1,329 belongs under "1,300 and up" even if 256GB is 1,199.
            ->when($except !== 'price' && $this->minPriceCents !== null, fn (Builder $q) => $q->whereRaw(Product::MAX_PRICE.' >= ?', [$this->minPriceCents]))
            ->when($except !== 'price' && $this->maxPriceCents !== null, fn (Builder $q) => $q->whereRaw(Product::MIN_PRICE.' <= ?', [$this->maxPriceCents]))
            ->when($except !== 'in_stock' && $this->inStock, fn (Builder $q) => $this->inStockScope($q))
            ->when($except !== 'on_sale' && $this->onSale, fn (Builder $q) => $q->onSaleIncludingVariants())
            ->when($this->featured, fn (Builder $q) => $q->featured());
    }

    /**
     * Units on a shelf, not merely an active status — nothing in the app ever
     * sets ProductStatus::OutOfStock (same rule as the Inertia catalogue).
     */
    private function inStockScope(Builder $query): Builder
    {
        return $query->purchasable()
            ->whereHas('stockLevels', fn (Builder $s) => $s->sellable()->where('quantity', '>', 0));
    }

    /**
     * @return list<array{id: int, parent_id: int|null, slug: string, name: string, name_ar: string|null, count: int}>
     */
    private function categoryFacet(): array
    {
        $direct = $this->filtered(except: 'categories')
            ->toBase()
            ->selectRaw('category_id, COUNT(*) AS aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        $categories = Category::query()->active()->orderBy('position')->orderBy('name')
            ->get(['id', 'parent_id', 'slug', 'name', 'name_ar']);
        $byParent = $categories->groupBy('parent_id');

        $total = function (int $id) use (&$total, $byParent, $direct): int {
            return (int) ($direct[$id] ?? 0)
                + $byParent->get($id, collect())->sum(fn (Category $child): int => $total($child->id));
        };

        return $categories
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'slug' => $category->slug,
                'name' => $category->name,
                'name_ar' => $category->name_ar,
                'count' => $total($category->id),
            ])
            ->filter(fn (array $option): bool => $option['count'] > 0 || in_array($option['slug'], $this->categorySlugs, true))
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, slug: string, name: string, name_ar: string|null, count: int}>
     */
    private function brandFacet(): array
    {
        $counts = $this->filtered(except: 'brands')
            ->whereNotNull('brand_id')
            ->toBase()
            ->selectRaw('brand_id, COUNT(*) AS aggregate')
            ->groupBy('brand_id')
            ->pluck('aggregate', 'brand_id');

        return Brand::query()->active()->orderBy('position')->orderBy('name')
            ->get(['id', 'slug', 'name', 'name_ar'])
            ->map(fn (Brand $brand): array => [
                'id' => $brand->id,
                'slug' => $brand->slug,
                'name' => $brand->name,
                'name_ar' => $brand->name_ar,
                'count' => (int) ($counts[$brand->id] ?? 0),
            ])
            ->filter(fn (array $option): bool => $option['count'] > 0 || in_array($option['slug'], $this->brandSlugs, true))
            ->values()
            ->all();
    }

    /**
     * Slugs from `key[]=a&key[]=b` or `key=a,b`, plus the older single-valued
     * parameter.
     *
     * @return list<string>
     */
    private static function slugs(Request $request, string $key, string $legacyKey): array
    {
        $raw = $request->query($key, []);
        $values = is_array($raw) ? $raw : explode(',', (string) $raw);
        $values[] = $request->query($legacyKey);

        return array_values(array_unique(array_filter(
            array_map(fn (mixed $slug): string => is_string($slug) ? trim($slug) : '', $values),
            fn (string $slug): bool => $slug !== '' && strlen($slug) <= 255,
        )));
    }
}
