<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\HomepageController;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('public');
});

it('keeps the homepage page to staff who can manage settings', function (): void {
    $this->actingAs(staff('admin'))->get(route('admin.homepage.index'))->assertForbidden();
    $this->actingAs(staff('admin'))->post(route('admin.homepage.update'), [])->assertForbidden();

    $this->actingAs(staff('super_admin'))->get(route('admin.homepage.index'))->assertOk();
});

it('saves hero slides in order with their headline and artwork', function (): void {
    [$first, $second] = Product::factory()->count(2)->create();

    $this->actingAs(staff('super_admin'))
        ->post(route('admin.homepage.update'), [
            'hero' => [
                ['product_id' => $second->id, 'title' => 'Summer sale', 'title_ar' => 'تخفيضات الصيف', 'image' => UploadedFile::fake()->image('banner.jpg', 1200, 600)],
                ['product_id' => $first->id],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $hero = Setting::get(HomepageController::HERO_KEY);

    expect($hero)->toHaveCount(2)
        ->and($hero[0]['product_id'])->toBe($second->id)
        ->and($hero[0]['title'])->toBe('Summer sale')
        ->and($hero[0]['title_ar'])->toBe('تخفيضات الصيف')
        ->and($hero[0]['image_path'])->toStartWith('homepage/')
        ->and($hero[1]['product_id'])->toBe($first->id)
        ->and($hero[1]['image_path'])->toBeNull();

    Storage::disk('public')->assertExists($hero[0]['image_path']);
});

it('shows the curated hero on the storefront and skips products it cannot show', function (): void {
    $shown = Product::factory()->create(['name' => 'Shown phone']);
    $draft = Product::factory()->draft()->create();

    Setting::set(HomepageController::HERO_KEY, [
        ['product_id' => $draft->id, 'title' => null, 'title_ar' => null, 'image_path' => null],
        ['product_id' => $shown->id, 'title' => 'Big launch', 'title_ar' => null, 'image_path' => null],
    ]);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('heroSlides', 1)
            ->where('heroSlides.0.product.id', $shown->id)
            ->where('heroSlides.0.title', 'Big launch'));
});

it('leaves the hero and tiles automatic until something is curated', function (): void {
    Product::factory()->create(['is_featured' => true]);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('heroSlides', null)
            ->where('promoTiles', null));
});

it('points the promo tiles at the chosen categories with their own title', function (): void {
    $phones = Category::factory()->create(['name' => 'Phones']);
    $laptops = Category::factory()->create(['name' => 'Laptops']);
    Product::factory()->create(['category_id' => $phones->id]);
    Product::factory()->create(['category_id' => $laptops->id]);

    $this->actingAs(staff('super_admin'))
        ->post(route('admin.homepage.update'), [
            'tiles' => [
                ['category_id' => $laptops->id, 'title' => 'Back to school'],
                ['category_id' => $phones->id],
            ],
        ])
        ->assertSessionHasNoErrors();

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('promoTiles', 2)
            ->where('promoTiles.0.id', $laptops->id)
            ->where('promoTiles.0.title', 'Back to school')
            ->where('promoTiles.1.id', $phones->id));
});

it('rejects more slides than the hero holds and duplicate products', function (): void {
    $products = Product::factory()->count(9)->create();
    $admin = staff('super_admin');

    $this->actingAs($admin)
        ->post(route('admin.homepage.update'), ['hero' => $products->map(fn (Product $p) => ['product_id' => $p->id])->all()])
        ->assertSessionHasErrors('hero');

    $this->actingAs($admin)
        ->post(route('admin.homepage.update'), ['hero' => [['product_id' => $products[0]->id], ['product_id' => $products[0]->id]]])
        ->assertSessionHasErrors('hero.0.product_id');
});

it('ignores an image path the server did not store itself', function (): void {
    $product = Product::factory()->create();

    $this->actingAs(staff('super_admin'))
        ->post(route('admin.homepage.update'), [
            'hero' => [['product_id' => $product->id, 'image_path' => '../../.env']],
        ])
        ->assertSessionHasNoErrors();

    expect(Setting::get(HomepageController::HERO_KEY)[0]['image_path'])->toBeNull();
});

it('keeps an existing banner on resave and deletes it once replaced', function (): void {
    $product = Product::factory()->create();
    $admin = staff('super_admin');

    $this->actingAs($admin)->post(route('admin.homepage.update'), [
        'hero' => [['product_id' => $product->id, 'image' => UploadedFile::fake()->image('one.jpg')]],
    ]);
    $original = Setting::get(HomepageController::HERO_KEY)[0]['image_path'];

    // Resaving with the stored path keeps it.
    $this->actingAs($admin)->post(route('admin.homepage.update'), [
        'hero' => [['product_id' => $product->id, 'image_path' => $original]],
    ]);
    expect(Setting::get(HomepageController::HERO_KEY)[0]['image_path'])->toBe($original);
    Storage::disk('public')->assertExists($original);

    // Replacing it removes the old file.
    $this->actingAs($admin)->post(route('admin.homepage.update'), [
        'hero' => [['product_id' => $product->id, 'image' => UploadedFile::fake()->image('two.jpg')]],
    ]);
    Storage::disk('public')->assertMissing($original);
});

it('finds products for the slide picker by name or sku', function (): void {
    Product::factory()->create(['name' => 'Galaxy S25 Ultra', 'sku' => 'GAL-S25']);
    Product::factory()->create(['name' => 'ThinkPad X1']);

    $this->actingAs(staff('super_admin'))
        ->getJson(route('admin.homepage.products', ['q' => 'galaxy']))
        ->assertOk()
        ->assertJsonCount(1, 'products')
        ->assertJsonPath('products.0.sku', 'GAL-S25')
        ->assertJsonPath('products.0.visible', true);
});

it('saves social links, rejects non-urls, and shares only the filled ones', function (): void {
    $admin = staff('super_admin');

    $this->actingAs($admin)
        ->post(route('admin.settings.update'), ['settings' => ['social_links' => ['facebook' => 'not a url']]])
        ->assertSessionHasErrors('settings.social_links.facebook');

    $this->actingAs($admin)
        ->post(route('admin.settings.update'), ['settings' => ['social_links' => [
            'facebook' => 'https://facebook.com/store',
            'instagram' => null,
            'tiktok' => null,
            'youtube' => null,
        ]]])
        ->assertSessionHasNoErrors();

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('socialLinks', ['facebook' => 'https://facebook.com/store']));
});
