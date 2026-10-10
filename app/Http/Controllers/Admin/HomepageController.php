<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateHomepageRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Support\HomeCache;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Curates the storefront homepage: which products the hero rotates through
 * (with an optional headline and artwork per slide) and which categories the
 * two promo tiles beside it point at.
 *
 * Stored as two settings rows rather than tables — they are short ordered
 * lists, read on every homepage render, and settings are already cached
 * forever and busted on write. An empty list means "automatic": the
 * storefront keeps choosing for itself (featured products, best discount).
 */
final class HomepageController extends Controller implements HasMiddleware
{
    public const HERO_KEY = 'home.hero';

    public const TILES_KEY = 'home.tiles';

    public static function middleware(): array
    {
        return ['can:settings.manage'];
    }

    public function index(): Response
    {
        $hero = collect(Setting::get(self::HERO_KEY) ?? []);
        $tiles = collect(Setting::get(self::TILES_KEY) ?? []);

        $products = $this->summaries(
            Product::query()->whereIn('id', $hero->pluck('product_id'))
        )->keyBy('id');

        return Inertia::render('admin/homepage/Index', [
            'hero' => $hero->map(fn (array $slide): array => [
                'product_id' => $slide['product_id'],
                'product' => $products->get($slide['product_id']),
                'title' => $slide['title'] ?? null,
                'title_ar' => $slide['title_ar'] ?? null,
                'image_path' => $slide['image_path'] ?? null,
                'image_url' => $this->url($slide['image_path'] ?? null),
            ])->values(),
            'tiles' => $tiles->map(fn (array $tile): array => [
                'category_id' => $tile['category_id'],
                'title' => $tile['title'] ?? null,
                'title_ar' => $tile['title_ar'] ?? null,
                'image_path' => $tile['image_path'] ?? null,
                'image_url' => $this->url($tile['image_path'] ?? null),
            ])->values(),
            // Root categories only: the tiles link to a department, the same
            // level the header's "All categories" menu lists.
            'categories' => Category::query()->whereNull('parent_id')->orderBy('position')->get(['id', 'name', 'name_ar', 'is_active']),
            'limits' => ['slides' => UpdateHomepageRequest::MAX_SLIDES, 'tiles' => UpdateHomepageRequest::MAX_TILES],
        ]);
    }

    /** Product picker search: name (either language), SKU or barcode. */
    public function products(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        $query = Product::query()->latest('id');

        if ($term !== '') {
            $query->search($term);
        }

        return response()->json(['products' => $this->summaries($query->take(20))->values()]);
    }

    public function update(UpdateHomepageRequest $request): RedirectResponse
    {
        $before = $this->storedImages();

        $hero = $this->rows($request, 'hero', 'product_id', $before);
        $tiles = $this->rows($request, 'tiles', 'category_id', $before);

        Setting::set(self::HERO_KEY, $hero);
        Setting::set(self::TILES_KEY, $tiles);

        // Artwork a row no longer uses goes, so replacing a banner doesn't
        // leave the old one on disk forever.
        $kept = collect([...$hero, ...$tiles])->pluck('image_path')->filter()->all();

        foreach (array_diff($before, $kept) as $orphan) {
            Storage::disk('public')->delete($orphan);
        }

        HomeCache::flush();

        activity()->event('settings_updated')->withProperties(['keys' => [self::HERO_KEY, self::TILES_KEY]])->log('Homepage updated');

        return back()->with('success', __('Homepage saved.'));
    }

    /**
     * @param  list<string>  $known  images the server stored for these blocks
     * @return list<array<string, mixed>>
     */
    private function rows(UpdateHomepageRequest $request, string $field, string $idKey, array $known): array
    {
        $rows = [];

        foreach ($request->validated($field) ?? [] as $index => $row) {
            if ($request->hasFile("{$field}.{$index}.image")) {
                $path = $request->file("{$field}.{$index}.image")->store('homepage', 'public');
            } else {
                $existing = $row['image_path'] ?? null;
                $path = in_array($existing, $known, true) ? $existing : null;
            }

            $rows[] = [
                $idKey => (int) $row[$idKey],
                'title' => filled($row['title'] ?? null) ? trim($row['title']) : null,
                'title_ar' => filled($row['title_ar'] ?? null) ? trim($row['title_ar']) : null,
                'image_path' => $path,
            ];
        }

        return $rows;
    }

    /** @return list<string> */
    private function storedImages(): array
    {
        return collect([...(Setting::get(self::HERO_KEY) ?? []), ...(Setting::get(self::TILES_KEY) ?? [])])
            ->pluck('image_path')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * What the picker and the slide list show for a product, including whether
     * the storefront will actually display it — a draft or hidden product is
     * skipped there, and the admin should see why before saving.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function summaries($query): Collection
    {
        $products = $query->with('images')->get(['id', 'name', 'name_ar', 'sku', 'price_cents', 'sale_price_cents', 'sale_starts_at', 'sale_ends_at']);

        $visible = Product::query()->visibleInStore()->whereIn('id', $products->pluck('id'))->pluck('id')->all();

        return $products->map(function (Product $product) use ($visible): array {
            $image = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

            return [
                'id' => $product->id,
                'name' => $product->name,
                'name_ar' => $product->name_ar,
                'sku' => $product->sku,
                'price_formatted' => Money::format($product->currentPriceCents()),
                'image' => $image !== null ? Storage::disk('public')->url($image->path) : null,
                'visible' => in_array($product->id, $visible, true),
            ];
        });
    }

    private function url(?string $path): ?string
    {
        return $path !== null ? Storage::disk('public')->url($path) : null;
    }
}
