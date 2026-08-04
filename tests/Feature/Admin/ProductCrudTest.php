<?php

use App\Enums\PriceType;
use App\Models\Category;
use App\Models\PriceHistory;
use App\Models\Product;
use Spatie\Activitylog\Models\Activity;

function productPayload(array $overrides = []): array
{
    return [
        'name' => 'Test Laptop Pro',
        'sku' => 'TLP-001',
        'category_id' => Category::factory()->create()->id,
        'cost_price' => 800,
        'price' => 1000,
        'status' => 'active',
        ...$overrides,
    ];
}

it('creates a product with generated slug and price history', function (): void {
    $admin = staff('inventory_manager');

    $this->actingAs($admin)
        ->post(route('admin.products.store'), productPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $product = Product::query()->sole();

    expect($product->slug)->toBe('test-laptop-pro')
        ->and($product->price_cents)->toBe(100000)
        ->and($product->cost_price_cents)->toBe(80000)
        ->and(PriceHistory::query()->where('price_type', PriceType::Regular)->count())->toBe(1);
});

it('records price history and audit trail on price change', function (): void {
    $admin = staff('inventory_manager');

    $this->actingAs($admin)->post(route('admin.products.store'), productPayload());
    $product = Product::query()->sole();

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), productPayload([
            'sku' => 'TLP-001',
            'price' => 900,
        ]))
        ->assertRedirect();

    $history = PriceHistory::query()->where('price_type', PriceType::Regular)->latest('id')->first();

    expect($product->refresh()->price_cents)->toBe(90000)
        ->and($history->old_price_cents)->toBe(100000)
        ->and($history->new_price_cents)->toBe(90000)
        ->and(Activity::query()->where('subject_type', Product::class)->exists())->toBeTrue();
});

it('manages variants through the product form', function (): void {
    $admin = staff('inventory_manager');

    $this->actingAs($admin)->post(route('admin.products.store'), productPayload([
        'variants' => [
            ['name' => '256GB', 'sku' => 'TLP-001-256', 'price' => 1000],
            ['name' => '512GB', 'sku' => 'TLP-001-512', 'price' => 1200],
        ],
    ]));

    $product = Product::query()->sole();

    expect($product->has_variants)->toBeTrue()
        ->and($product->variants)->toHaveCount(2);

    // Removing one variant soft-deletes it.
    $keep = $product->variants->first();
    $this->actingAs($admin)->put(route('admin.products.update', $product), productPayload([
        'variants' => [
            ['id' => $keep->id, 'name' => $keep->name, 'sku' => $keep->sku, 'price' => 1100],
        ],
    ]));

    expect($product->refresh()->variants()->count())->toBe(1)
        ->and($product->variants()->withTrashed()->count())->toBe(2);
});

it('soft deletes and restores products', function (): void {
    $admin = staff('inventory_manager');
    $product = Product::factory()->create();

    $this->actingAs($admin)->delete(route('admin.products.destroy', $product))->assertRedirect();
    expect(Product::query()->count())->toBe(0)
        ->and(Product::withTrashed()->count())->toBe(1);

    $this->actingAs($admin)->post(route('admin.products.restore', $product->id))->assertRedirect();
    expect(Product::query()->count())->toBe(1);
});

it('rejects duplicate SKUs', function (): void {
    $admin = staff('inventory_manager');
    Product::factory()->create(['sku' => 'DUPE-1']);

    $this->actingAs($admin)
        ->post(route('admin.products.store'), productPayload(['sku' => 'DUPE-1']))
        ->assertSessionHasErrors('sku');
});
