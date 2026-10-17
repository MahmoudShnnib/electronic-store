<?php

declare(strict_types=1);

use App\Enums\PageSectionType;
use App\Models\Category;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Product;
use App\Support\Content\ContentCache;
use App\Support\Content\HtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('public');
});

function home(): Page
{
    return Page::query()->where('key', 'home')->sole();
}

it('keeps the page builder to staff who can manage settings', function (): void {
    $home = home();
    $section = $home->sections()->first();

    $this->get(route('admin.pages.index'))->assertRedirect(route('login'));

    $admin = staff('admin');
    $this->actingAs($admin)->get(route('admin.pages.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.pages.edit', $home))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.pages.store'), ['title' => 'X', 'slug' => 'x'])->assertForbidden();
    $this->actingAs($admin)->patch(route('admin.pages.sections.toggle', [$home, $section]))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.pages.sections.reorder', $home), ['ids' => [$section->id]])->assertForbidden();
    $this->actingAs($admin)->post(route('admin.pages.images.store'), ['image' => UploadedFile::fake()->image('a.jpg')])->assertForbidden();

    $this->actingAs(staff('super_admin'))
        ->get(route('admin.pages.edit', $home))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/pages/Edit')
            ->where('page.key', 'home')
            ->has('sections', $home->sections()->count()));
});

it('creates, updates and deletes a custom page', function (): void {
    $admin = staff('super_admin');

    $this->actingAs($admin)
        ->post(route('admin.pages.store'), ['title' => 'Our story', 'title_ar' => 'قصتنا', 'slug' => 'our-story', 'is_published' => true])
        ->assertSessionHasNoErrors();

    $page = Page::query()->where('slug', 'our-story')->sole();
    expect($page->is_system)->toBeFalse()->and($page->title_ar)->toBe('قصتنا');

    $this->actingAs($admin)
        ->put(route('admin.pages.update', $page), ['title' => 'About', 'slug' => 'about-us', 'is_published' => false, 'seo' => ['description' => ' Who we are ']])
        ->assertSessionHasNoErrors();

    expect($page->fresh())
        ->slug->toBe('about-us')
        ->is_published->toBeFalse()
        ->seo->toBe(['description' => 'Who we are']);

    $this->actingAs($admin)->delete(route('admin.pages.destroy', $page))->assertRedirect(route('admin.pages.index'));
    expect(Page::query()->whereKey($page->id)->exists())->toBeFalse();
});

it('refuses slugs that are malformed, taken or shadow a system page', function (string $slug): void {
    Page::query()->create(['slug' => 'taken', 'title' => 'Taken']);

    $this->actingAs(staff('super_admin'))
        ->post(route('admin.pages.store'), ['title' => 'Page', 'slug' => $slug])
        ->assertSessionHasErrors('slug');
})->with(['home', 'taken', 'Has Spaces', '']);

it('never deletes or unpublishes a system page', function (): void {
    $admin = staff('super_admin');
    $home = home();

    $this->actingAs($admin)->delete(route('admin.pages.destroy', $home))->assertForbidden();
    expect(Page::query()->whereKey($home->id)->exists())->toBeTrue();

    $this->actingAs($admin)
        ->put(route('admin.pages.update', $home), ['title' => 'Start', 'is_published' => false])
        ->assertSessionHasNoErrors();

    expect($home->fresh())->title->toBe('Start')->is_published->toBeTrue()->key->toBe('home');

    $this->actingAs($admin)
        ->put(route('admin.pages.update', $home), ['title' => 'Start', 'slug' => 'start'])
        ->assertSessionHasErrors('slug');
});

it('edits a carousel and the content API reflects it', function (): void {
    [$first, $second] = Product::factory()->count(2)->create();
    $home = home();
    $carousel = $home->sections()->where('type', PageSectionType::ProductCarousel)->first();
    $versionBefore = ContentCache::version();

    $this->actingAs(staff('super_admin'))
        ->put(route('admin.pages.sections.update', [$home, $carousel]), ['data' => [
            'title' => ' Staff picks ', 'title_ar' => 'اختياراتنا', 'source' => 'manual',
            'product_ids' => [$second->id, $first->id], 'category_id' => 99, 'limit' => 6, 'junk' => 'x',
        ]])
        ->assertSessionHasNoErrors();

    expect($carousel->fresh()->data)->toBe([
        'title' => 'Staff picks', 'title_ar' => 'اختياراتنا', 'icon' => null, 'source' => 'manual',
        'product_ids' => [$second->id, $first->id], 'limit' => 6,
    ])->and(ContentCache::version())->not->toBe($versionBefore);

    $served = collect($this->getJson('/api/v1/pages/home')->json('data.sections'))->firstWhere('id', $carousel->id);

    expect($served['data']['title'])->toBe('Staff picks')
        ->and(array_column($served['data']['products'], 'id'))->toBe([$second->id, $first->id]);
});

it('validates each section type\'s data', function (string $type, array $data, array $errors): void {
    $this->actingAs(staff('super_admin'))
        ->post(route('admin.pages.sections.store', home()), ['type' => $type, 'data' => $data])
        ->assertSessionHasErrors($errors);
})->with([
    'carousel without source' => ['product_carousel', ['limit' => 6], ['data.source']],
    'carousel bad limit' => ['product_carousel', ['source' => 'featured', 'limit' => 500], ['data.limit']],
    'category carousel without category' => ['product_carousel', ['source' => 'category', 'limit' => 4], ['data.category_id']],
    'manual carousel without products' => ['product_carousel', ['source' => 'manual', 'limit' => 4], ['data.product_ids']],
    'hero with unknown product' => ['hero', ['slides' => [['product_id' => 999999]]], ['data.slides.0.product_id']],
    'too many tiles' => ['promo_tiles', ['tiles' => [['category_id' => 1], ['category_id' => 2], ['category_id' => 3]]], ['data.tiles']],
    'banner without image' => ['image_banner', ['link' => '/x'], ['data.image_path']],
    'banner with foreign image' => ['image_banner', ['image_path' => '../../.env'], ['data.image_path']],
    'banner with script link' => ['image_banner', ['image_path' => 'products/a.jpg', 'link' => 'javascript:alert(1)'], ['data.link', 'data.image_path']],
    'empty trust strip' => ['trust_strip', ['items' => []], ['data.items']],
    'unknown type' => ['marquee', [], ['type']],
]);

it('adds a section at the end with a banner uploaded first', function (): void {
    $admin = staff('super_admin');
    $home = home();

    $upload = $this->actingAs($admin)
        ->post(route('admin.pages.images.store'), ['image' => UploadedFile::fake()->image('banner.jpg', 1200, 400)])
        ->assertCreated()
        ->json();

    expect($upload['path'])->toStartWith('pages/');

    $this->actingAs($admin)
        ->post(route('admin.pages.sections.store', $home), ['type' => 'image_banner', 'data' => [
            'image_path' => $upload['path'], 'link' => '/categories/phones', 'title' => 'Phones',
        ]])
        ->assertSessionHasNoErrors();

    $banner = $home->sections()->get()->last();

    expect($banner->type)->toBe(PageSectionType::ImageBanner)
        ->and($banner->data['image_path'])->toBe($upload['path']);

    $served = collect($this->getJson('/api/v1/pages/home')->json('data.sections'))->last();
    expect($served['type'])->toBe('image_banner')->and($served['data']['image'])->toEndWith($upload['path']);

    // Replacing the artwork removes the old file.
    $next = $this->actingAs($admin)->post(route('admin.pages.images.store'), ['image' => UploadedFile::fake()->image('b.png')])->json('path');
    $this->actingAs($admin)
        ->put(route('admin.pages.sections.update', [$home, $banner]), ['data' => ['image_path' => $next]])
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing($upload['path']);
    Storage::disk('public')->assertExists($next);

    $this->actingAs($admin)->delete(route('admin.pages.sections.destroy', [$home, $banner]))->assertSessionHasNoErrors();
    Storage::disk('public')->assertMissing($next);
});

it('saves curated hero slides and promo tiles', function (): void {
    $product = Product::factory()->create();
    $category = Category::factory()->create();
    $home = home();
    $hero = $home->sections()->where('type', PageSectionType::Hero)->sole();
    $tiles = $home->sections()->where('type', PageSectionType::PromoTiles)->sole();
    $admin = staff('super_admin');

    $this->actingAs($admin)
        ->put(route('admin.pages.sections.update', [$home, $hero]), ['data' => ['slides' => [['product_id' => $product->id, 'title' => 'Launch', 'title_ar' => '']]]])
        ->assertSessionHasNoErrors();
    $this->actingAs($admin)
        ->put(route('admin.pages.sections.update', [$home, $tiles]), ['data' => ['tiles' => [['category_id' => $category->id]]]])
        ->assertSessionHasNoErrors();

    expect($hero->fresh()->data['slides'])->toBe([['product_id' => $product->id, 'title' => 'Launch', 'title_ar' => null, 'image_path' => null]])
        ->and($tiles->fresh()->data['tiles'][0]['category_id'])->toBe($category->id);
});

it('toggles a section off the served page and back', function (): void {
    $home = home();
    $rail = $home->sections()->where('type', PageSectionType::CategoryRail)->sole();
    $admin = staff('super_admin');
    $served = fn (): bool => collect($this->getJson('/api/v1/pages/home')->json('data.sections'))->contains('id', $rail->id);

    expect($served())->toBeTrue();

    $this->actingAs($admin)->patch(route('admin.pages.sections.toggle', [$home, $rail]))->assertSessionHasNoErrors();
    expect($rail->fresh()->is_active)->toBeFalse()->and($served())->toBeFalse();

    $this->actingAs($admin)->patch(route('admin.pages.sections.toggle', [$home, $rail]));
    expect($rail->fresh()->is_active)->toBeTrue()->and($served())->toBeTrue();
});

it('reorders sections and the served page follows', function (): void {
    $home = home();
    $ids = $home->sections()->pluck('id')->all();
    $reversed = array_reverse($ids);

    $this->actingAs(staff('super_admin'))
        ->post(route('admin.pages.sections.reorder', $home), ['ids' => $reversed])
        ->assertSessionHasNoErrors();

    expect($home->sections()->pluck('id')->all())->toBe($reversed);

    $servedIds = array_column($this->getJson('/api/v1/pages/home')->json('data.sections'), 'id');
    expect($servedIds)->toBe(array_values(array_intersect($reversed, $servedIds)))
        ->and($servedIds[0])->toBe($reversed[0]);
});

it('refuses a reorder that drops, duplicates or borrows a section', function (Closure $tamper): void {
    $home = home();
    $foreign = Page::query()->create(['slug' => 'other', 'title' => 'Other'])
        ->sections()->create(['type' => PageSectionType::RichText, 'data' => []]);
    $before = $home->sections()->pluck('id')->all();

    $this->actingAs(staff('super_admin'))
        ->post(route('admin.pages.sections.reorder', $home), ['ids' => $tamper($before, $foreign->id)])
        ->assertSessionHasErrors();

    expect($home->sections()->pluck('id')->all())->toBe($before);
})->with([
    'missing one' => [fn (array $ids): array => array_slice($ids, 1)],
    'foreign section' => [fn (array $ids, int $foreign): array => [...array_slice($ids, 1), $foreign]],
    'duplicate' => [fn (array $ids): array => [...$ids, $ids[0]]],
]);

it('will not touch a section through another page', function (): void {
    $other = Page::query()->create(['slug' => 'other', 'title' => 'Other']);
    $section = home()->sections()->first();

    $this->actingAs(staff('super_admin'))
        ->patch(route('admin.pages.sections.toggle', [$other, $section]))
        ->assertNotFound();
});

it('sanitises rich text', function (): void {
    $this->actingAs(staff('super_admin'))
        ->post(route('admin.pages.sections.store', home()), ['type' => 'rich_text', 'data' => [
            'body' => '<p onclick="x()">Hi <script>alert(1)</script><a href="javascript:alert(1)">bad</a> <a href="https://example.com" style="x">ok</a><span>kept</span></p>',
            'body_ar' => '   ',
        ]])
        ->assertSessionHasNoErrors();

    $data = PageSection::query()->where('type', PageSectionType::RichText)->latest('id')->first()->data;

    expect($data['body'])->toBe('<p>Hi <a>bad</a> <a href="https://example.com" rel="noopener nofollow">ok</a>kept</p>')
        ->and($data['body_ar'])->toBeNull();
});

it('keeps Arabic text intact when sanitising', function (): void {
    expect(HtmlSanitizer::clean('<h2>مرحبا</h2><p>بكم</p>'))->toBe('<h2>مرحبا</h2><p>بكم</p>');
});
