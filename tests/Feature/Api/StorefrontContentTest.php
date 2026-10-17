<?php

use App\Enums\PageSectionType;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;

it('serves the boot payload with a content version', function (): void {
    $this->getJson('/api/v1/storefront')
        ->assertOk()
        ->assertJsonPath('data.currency.code', 'LYD')
        ->assertJsonPath('data.pages.0.key', 'home')
        ->assertJsonStructure(['data' => [
            'version', 'locales', 'default_locale', 'contact', 'social_links',
            'payment_methods', 'cities', 'nav_categories', 'pages',
        ]]);
});

it('ships a home page that reproduces the current layout', function (): void {
    $home = Page::query()->where('key', 'home')->sole();

    expect($home->is_system)->toBeTrue()
        ->and($home->sections()->pluck('type')->first())->toBe(PageSectionType::Hero);
});

it('resolves product carousels and drops empty ones', function (): void {
    Product::factory()->count(2)->create(['is_featured' => true]);

    $types = collect($this->getJson('/api/v1/pages/home')->assertOk()->json('data.sections'));
    $featured = $types->firstWhere('data.source', 'featured');

    expect($featured['data']['products'])->toHaveCount(2)
        ->and($featured['data']['link'])->toBe(['featured' => '1'])
        // Nothing is on sale, so that carousel is not served at all.
        ->and($types->firstWhere('data.source', 'on_sale'))->toBeNull();
});

it('keeps the admin order for a manual carousel', function (): void {
    [$first, $second] = Product::factory()->count(2)->create();
    $page = Page::query()->create(['slug' => 'picks', 'title' => 'Picks']);
    $page->sections()->create([
        'type' => PageSectionType::ProductCarousel,
        'data' => ['source' => 'manual', 'product_ids' => [$second->id, $first->id]],
    ]);

    $this->getJson('/api/v1/pages/picks')
        ->assertOk()
        ->assertJsonPath('data.sections.0.data.products.0.id', $second->id)
        ->assertJsonPath('data.sections.0.data.products.1.id', $first->id);
});

it('localises product cards from the locale parameter', function (): void {
    Product::factory()->create(['is_featured' => true, 'name' => 'Mouse', 'name_ar' => 'فأرة']);

    $card = fn (string $locale) => collect($this->getJson("/api/v1/pages/home?locale={$locale}")->json('data.sections'))
        ->firstWhere('data.source', 'featured')['data']['products'][0]['name'];

    expect($card('ar'))->toBe('فأرة')
        ->and($card('en'))->toBe('Mouse');
});

it('hides unpublished pages and inactive sections', function (): void {
    $page = Page::query()->create(['slug' => 'draft', 'title' => 'Draft', 'is_published' => false]);
    $this->getJson('/api/v1/pages/draft')->assertNotFound();

    $page->update(['is_published' => true]);
    $page->sections()->create(['type' => PageSectionType::RichText, 'is_active' => false, 'data' => ['body' => '<p>x</p>']]);

    $this->getJson('/api/v1/pages/draft')->assertOk()->assertJsonCount(0, 'data.sections');
});

it('serves content edits immediately despite the cache', function (): void {
    $page = Page::query()->create(['slug' => 'story', 'title' => 'Story']);
    $this->getJson('/api/v1/pages/story')->assertJsonCount(0, 'data.sections');

    $page->sections()->create(['type' => PageSectionType::RichText, 'data' => ['body' => '<p>Hi</p>']]);

    $this->getJson('/api/v1/pages/story')->assertJsonPath('data.sections.0.data.body', '<p>Hi</p>');
});

it('refreshes product sections when the catalogue changes', function (): void {
    $product = Product::factory()->create(['is_featured' => true]);
    $featured = fn () => collect($this->getJson('/api/v1/pages/home')->json('data.sections'))->firstWhere('data.source', 'featured');

    expect($featured())->not->toBeNull();

    $product->update(['is_featured' => false]);

    expect($featured())->toBeNull();
});

it('allows the storefront origin through CORS', function (): void {
    $this->withHeaders(['Origin' => 'http://localhost:5173'])
        ->getJson('/api/v1/storefront')
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
});

it('does not resolve a category carousel without a category', function (): void {
    Category::factory()->create();
    $page = Page::query()->create(['slug' => 'cat', 'title' => 'Cat']);
    $page->sections()->create(['type' => PageSectionType::ProductCarousel, 'data' => ['source' => 'category']]);

    $this->getJson('/api/v1/pages/cat')->assertOk()->assertJsonCount(0, 'data.sections');
});
