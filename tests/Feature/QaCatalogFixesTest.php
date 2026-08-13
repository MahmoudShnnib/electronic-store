<?php

declare(strict_types=1);

use App\Actions\Catalog\SaveProduct;
use App\Enums\ProductStatus;
use App\Models\Product;

// -- B26: publish date survives edits ----------------------------------------

it('keeps the original publish date when editing an active product', function (): void {
    $admin = staff('super_admin');
    $product = Product::factory()->create([
        'status' => ProductStatus::Active,
        'published_at' => now()->subDays(30),
    ]);
    $original = $product->published_at;

    $this->actingAs($admin)->put(route('admin.products.update', $product), [
        'name' => $product->name,
        'sku' => $product->sku,
        'category_id' => $product->category_id,
        'cost_price' => '10',
        'price' => '99',
        'status' => 'active',
    ])->assertSessionHasNoErrors();

    expect($product->refresh()->published_at->timestamp)->toBe($original->timestamp);
});

// -- B25: variant sale price must undercut its price -------------------------

it('rejects a variant sale price above its regular price', function (): void {
    $admin = staff('super_admin');
    $product = Product::factory()->create();

    $this->actingAs($admin)->put(route('admin.products.update', $product), [
        'name' => $product->name,
        'sku' => $product->sku,
        'category_id' => $product->category_id,
        'cost_price' => '10',
        'price' => '99',
        'status' => 'active',
        'variants' => [
            ['name' => 'Red', 'sku' => 'QA-RED', 'price' => '50', 'sale_price' => '60'],
        ],
    ])->assertSessionHasErrors('variants.0.sale_price');
});

// -- B12: duplicate variant SKU validates instead of crashing ----------------

it('rejects a variant SKU already used on another product', function (): void {
    $admin = staff('super_admin');
    $other = Product::factory()->create();
    $other->variants()->create(['name' => 'Blue', 'sku' => 'TAKEN-1', 'price_cents' => 1_000]);

    $product = Product::factory()->create();

    $this->actingAs($admin)->put(route('admin.products.update', $product), [
        'name' => $product->name,
        'sku' => $product->sku,
        'category_id' => $product->category_id,
        'cost_price' => '10',
        'price' => '99',
        'status' => 'active',
        'variants' => [
            ['name' => 'Blue', 'sku' => 'TAKEN-1', 'price' => '50'],
        ],
    ])->assertSessionHasErrors('variants.0.sku');
});

it('revives a soft-deleted variant when its SKU is re-added', function (): void {
    $product = Product::factory()->create();
    $variant = $product->variants()->create(['name' => 'Old', 'sku' => 'REVIVE-1', 'price_cents' => 1_000]);
    $variant->delete();

    app(SaveProduct::class)->handle($product, [], [[
        'name' => 'New again', 'sku' => 'REVIVE-1', 'price_cents' => 2_000, 'cost_price_cents' => 0,
    ]]);

    $revived = $product->variants()->sole();

    expect($revived->id)->toBe($variant->id)
        ->and($revived->name)->toBe('New again')
        ->and($revived->deleted_at)->toBeNull();
});

// -- B14: removing every variant persists ------------------------------------

it('deletes all variants when an empty list is submitted', function (): void {
    $admin = staff('super_admin');
    $product = Product::factory()->create();
    $product->variants()->create(['name' => 'Solo', 'sku' => 'SOLO-1', 'price_cents' => 1_000]);

    $this->actingAs($admin)->put(route('admin.products.update', $product), [
        'name' => $product->name,
        'sku' => $product->sku,
        'category_id' => $product->category_id,
        'cost_price' => '10',
        'price' => '99',
        'status' => 'active',
        'variants' => [],
    ])->assertSessionHasNoErrors();

    expect($product->refresh()->variants()->count())->toBe(0)
        ->and($product->has_variants)->toBeFalse();
});

// -- A6: price filter uses the live sale price -------------------------------

it('includes on-sale products whose sale price fits the max filter', function (): void {
    Product::factory()->create([
        'name' => 'Expensive on paper',
        'status' => ProductStatus::Active,
        'published_at' => now(),
        'price_cents' => 100_000,
        'sale_price_cents' => 30_000,
        'sale_starts_at' => now()->subDay(),
        'sale_ends_at' => now()->addDay(),
    ]);

    $this->get(route('catalog.index', ['max_price' => 500]))
        ->assertInertia(fn ($page) => $page
            ->component('storefront/Catalog')
            ->has('products.data', 1));
});
