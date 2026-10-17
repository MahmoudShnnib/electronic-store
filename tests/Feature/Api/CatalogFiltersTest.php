<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;

/** Ids of the products the listing returned, sorted for order-insensitive checks. */
function listedIds(string $query): array
{
    return collect(test()->getJson('/api/v1/products?'.$query)->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
}

it('filters by several brands and categories at once', function (): void {
    $sony = Brand::factory()->create(['slug' => 'sony']);
    $logi = Brand::factory()->create(['slug' => 'logi']);
    $audio = Category::factory()->create(['slug' => 'audio']);
    $mice = Category::factory()->create(['slug' => 'mice']);

    $headset = Product::factory()->create(['brand_id' => $sony->id, 'category_id' => $audio->id]);
    $mouse = Product::factory()->create(['brand_id' => $logi->id, 'category_id' => $mice->id]);
    Product::factory()->create(); // another brand and category

    expect(listedIds('brands=sony,logi'))->toBe(collect([$headset->id, $mouse->id])->sort()->values()->all())
        ->and(listedIds('brands[]=sony&brands[]=logi&categories=mice'))->toBe([$mouse->id])
        // The older single-valued parameters still work.
        ->and(listedIds('brand=sony'))->toBe([$headset->id]);
});

it('matches a category with its whole subtree', function (): void {
    $gaming = Category::factory()->create(['slug' => 'gaming']);
    $chairs = Category::factory()->create(['slug' => 'chairs', 'parent_id' => $gaming->id]);
    $chair = Product::factory()->create(['category_id' => $chairs->id]);

    expect(listedIds('categories=gaming'))->toBe([$chair->id]);
});

it('filters by price range in dinars using the sale price when one is live', function (): void {
    $cheap = Product::factory()->create(['price_cents' => 10_000]);
    $discounted = Product::factory()->create(['price_cents' => 50_000, 'sale_price_cents' => 15_000]);
    Product::factory()->create(['price_cents' => 90_000]);

    expect(listedIds('min_price=50&max_price=200'))->toBe(collect([$cheap->id, $discounted->id])->sort()->values()->all())
        ->and(listedIds('min_price=120&max_price=200'))->toBe([$discounted->id]);
});

it('filters to products with stock on a shelf', function (): void {
    $stocked = Product::factory()->create();
    giveStock($stocked, 3);
    Product::factory()->create();

    expect(listedIds('in_stock=1'))->toBe([$stocked->id]);
});

it('sorts by price and ignores an unknown sort', function (): void {
    $low = Product::factory()->create(['price_cents' => 1_000]);
    $high = Product::factory()->create(['price_cents' => 9_000]);

    $this->getJson('/api/v1/products?sort=price_desc')->assertJsonPath('data.0.id', $high->id);
    $this->getJson('/api/v1/products?sort=price_asc')->assertJsonPath('data.0.id', $low->id);
    $this->getJson('/api/v1/products?sort=drop-table')->assertOk();
});

it('counts facets with each facet ignoring its own filter', function (): void {
    $sony = Brand::factory()->create(['slug' => 'sony']);
    $logi = Brand::factory()->create(['slug' => 'logi']);
    $gaming = Category::factory()->create(['slug' => 'gaming']);
    $chairs = Category::factory()->create(['slug' => 'chairs', 'parent_id' => $gaming->id]);

    Product::factory()->count(2)->create(['brand_id' => $sony->id, 'category_id' => $chairs->id, 'price_cents' => 20_000]);
    Product::factory()->onSale()->create(['brand_id' => $logi->id, 'category_id' => $chairs->id, 'price_cents' => 40_000]);

    $facets = $this->getJson('/api/v1/products/facets?brands=sony')->assertOk()->json('data');

    $brand = fn (string $slug) => collect($facets['brands'])->firstWhere('slug', $slug)['count'];
    $category = fn (string $slug) => collect($facets['categories'])->firstWhere('slug', $slug)['count'];

    expect($facets['total'])->toBe(2)
        // Brand counts ignore the brand filter, so Logitech still shows 1.
        ->and($brand('sony'))->toBe(2)
        ->and($brand('logi'))->toBe(1)
        // Category counts honour it, and roll up from child to parent.
        ->and($category('chairs'))->toBe(2)
        ->and($category('gaming'))->toBe(2)
        ->and($facets['price'])->toBe(['min' => 200, 'max' => 200])
        ->and($facets['on_sale'])->toBe(0)
        ->and($facets['selected']['brands'])->toBe(['sony']);
});

it('keeps a selected option in the facets even when it has no products', function (): void {
    Brand::factory()->create(['slug' => 'empty-brand']);
    Product::factory()->create();

    $brands = collect($this->getJson('/api/v1/products/facets?brands=empty-brand')->json('data.brands'));

    expect($brands->firstWhere('slug', 'empty-brand')['count'])->toBe(0);
});

it('ships the static pages with the dashboard text', function (): void {
    Setting::set('page.terms', "First rule.\nSecond line.\n\n<b>Not bold</b>");

    $this->getJson('/api/v1/pages/terms')
        ->assertOk()
        ->assertJsonPath('data.title_ar', 'الشروط والأحكام')
        ->assertJsonPath('data.sections.0.type', 'rich_text')
        ->assertJsonPath('data.sections.0.data.body', '<p>First rule.<br>'."\n".'Second line.</p><p>&lt;b&gt;Not bold&lt;/b&gt;</p>');

    expect(Page::query()->whereIn('key', ['about', 'contact', 'privacy-policy', 'terms', 'return-policy'])->where('is_system', true)->count())->toBe(5);
});

it('prefers the page builder text over the dashboard text', function (): void {
    Setting::set('page.about', 'Old text');
    Page::query()->where('key', 'about')->sole()->sections()->sole()->update(['data' => ['body' => '<p>New</p>', 'body_ar' => '<p>جديد</p>']]);

    $this->getJson('/api/v1/pages/about')->assertJsonPath('data.sections.0.data.body', '<p>New</p>');
});

it('refreshes static pages when the dashboard text changes', function (): void {
    Setting::set('page.about', 'Before');
    $this->getJson('/api/v1/pages/about')->assertJsonPath('data.sections.0.data.body', '<p>Before</p>');

    Setting::set('page.about', 'After');

    $this->getJson('/api/v1/pages/about')->assertJsonPath('data.sections.0.data.body', '<p>After</p>');
});

it('drops a static page section with no text anywhere', function (): void {
    $this->getJson('/api/v1/pages/privacy-policy')->assertOk()->assertJsonCount(0, 'data.sections');
});
