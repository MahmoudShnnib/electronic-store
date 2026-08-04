<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $sections = Cache::remember('home.sections', now()->addMinutes(10), function (): array {
            $card = fn ($query) => $query
                ->visibleInStore()
                ->with(['brand:id,name,slug', 'images'])
                ->withAvg('approvedReviews as rating_avg', 'rating')
                ->withCount('approvedReviews as rating_count')
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

        return Inertia::render('storefront/Home', [
            'categories' => $this->categoryCards(),
            'brands' => Brand::query()->active()->orderBy('position')->take(12)->get(),
            'featured' => ProductCardResource::collection($sections['featured']),
            'newArrivals' => ProductCardResource::collection($sections['newArrivals']),
            'onSale' => ProductCardResource::collection($sections['onSale']),
            'bestSellers' => ProductCardResource::collection($sections['bestSellers']),
        ]);
    }

    /**
     * Root categories for the homepage rail, each carrying how many products sit
     * anywhere beneath it and a photo borrowed from one of those products.
     *
     * Categories with nothing to sell are dropped — a storefront tile that leads
     * to an empty listing is worse than no tile.
     *
     * @return list<array{id: int, name: string, name_ar: string|null, slug: string, image: string|null, products_count: int}>
     */
    private function categoryCards(): array
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
