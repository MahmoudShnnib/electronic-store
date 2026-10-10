<?php

use App\Actions\Catalog\SaveProduct;
use App\Models\Product;
use App\Models\StockLevel;

/**
 * A product with stock on the shelf can only gain several variants if staff
 * say where those units go (SaveProduct's stock split), so pass one when the
 * product already holds something.
 *
 * @param  list<array{warehouse_id: int, sku: string, quantity: int}>|null  $stockSplit
 */
function withVariants(Product $product, ?array $stockSplit = null): Product
{
    return app(SaveProduct::class)->handle($product, [], [
        ['name' => '128GB', 'sku' => 'VS-128', 'price_cents' => 100_000],
        ['name' => '256GB', 'sku' => 'VS-256', 'price_cents' => 120_000],
    ], stockSplit: $stockSplit);
}

it('gives every new variant a stock row so the inventory screen can list it', function (): void {
    defaultWarehouse();
    $product = Product::factory()->create();

    withVariants($product);

    $variantIds = $product->refresh()->variants->pluck('id');

    expect(StockLevel::query()->whereIn('product_variant_id', $variantIds)->count())->toBe(2);
});

it('keeps the drained product-level row out of the inventory list', function (): void {
    $warehouse = defaultWarehouse();
    $product = Product::factory()->create();
    giveStock($product, 20);

    withVariants($product, [
        ['warehouse_id' => $warehouse->id, 'sku' => 'VS-128', 'quantity' => 12],
        ['warehouse_id' => $warehouse->id, 'sku' => 'VS-256', 'quantity' => 8],
    ]);

    // The stock moved onto the variants as split; the row it came from is still
    // in the table but is empty and can never be topped up again.
    expect(StockLevel::query()->where('product_id', $product->id)->whereNull('product_variant_id')->value('quantity'))->toBe(0)
        ->and(StockLevel::query()->where('product_id', $product->id)->whereNotNull('product_variant_id')->sum('quantity'))->toEqual(20);

    $rows = collect(
        $this->actingAs(staff('inventory_manager'))
            ->get(route('admin.inventory.index'))
            ->viewData('page')['props']['levels']['data']
    );

    expect($rows->pluck('variant_id')->filter()->count())->toBe(2)
        ->and($rows->whereNull('variant_id'))->toBeEmpty();
});

it('still lists a product-level row that is holding something', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 20);

    $rows = collect(
        $this->actingAs(staff('inventory_manager'))
            ->get(route('admin.inventory.index'))
            ->viewData('page')['props']['levels']['data']
    );

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['quantity'])->toBe(20);
});

it('finds a variant by its own name and sku', function (): void {
    defaultWarehouse();
    $product = Product::factory()->create();
    withVariants($product);

    $rows = collect(
        $this->actingAs(staff('inventory_manager'))
            ->get(route('admin.inventory.index', ['q' => 'VS-256']))
            ->viewData('page')['props']['levels']['data']
    );

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['name'])->toContain('256GB');
});
