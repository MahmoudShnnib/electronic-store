<?php

/*
 * A stocked plain product can gain several variants in one save; staff say how
 * the shelf units split between them, warehouse by warehouse.
 */

use App\Actions\Cart\AddToCart;
use App\Actions\Checkout\PlaceOrder;
use App\Actions\Orders\CancelOrder;
use App\DTOs\CheckoutData;
use App\Enums\PaymentMethod;
use App\Models\Cart;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

beforeEach(fn () => Notification::fake());

function splitSave($test, Product $product, array $split): TestResponse
{
    return $test->actingAs(staff('inventory_manager'))->put(route('admin.products.update', $product), [
        'name' => $product->name, 'sku' => $product->sku, 'category_id' => $product->category_id,
        'cost_price' => 5, 'price' => 10, 'status' => 'active',
        'variants' => [
            ['name' => 'Red', 'sku' => 'SPLIT-RED', 'price' => 10],
            ['name' => 'Blue', 'sku' => 'SPLIT-BLUE', 'price' => 10],
        ],
        'stock_split' => $split,
    ]);
}

function variantQty(Product $product, string $sku, ?Warehouse $warehouse = null): int
{
    $variant = $product->variants()->where('sku', $sku)->sole();

    return (int) StockLevel::query()
        ->where('product_variant_id', $variant->id)
        ->when($warehouse !== null, fn ($q) => $q->where('warehouse_id', $warehouse->id))
        ->sum('quantity');
}

/** Every stock row still equals the sum of its ledger movements. */
function ledgerMatches(Product $product): bool
{
    return StockLevel::query()->where('product_id', $product->id)->get()->every(fn (StockLevel $row) => $row->quantity === (int) InventoryMovement::query()
        ->where('warehouse_id', $row->warehouse_id)
        ->where('product_id', $row->product_id)
        ->where('product_variant_id', $row->product_variant_id)
        ->sum('quantity_change'));
}

it('splits the shelf stock between several new variants as staff typed it', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 10);
    $wh = defaultWarehouse()->id;

    splitSave($this, $product, [
        ['warehouse_id' => $wh, 'sku' => 'SPLIT-RED', 'quantity' => 6],
        ['warehouse_id' => $wh, 'sku' => 'SPLIT-BLUE', 'quantity' => 4],
    ])->assertSessionHasNoErrors();

    $product->refresh();
    expect($product->has_variants)->toBeTrue()
        ->and(variantQty($product, 'SPLIT-RED'))->toBe(6)
        ->and(variantQty($product, 'SPLIT-BLUE'))->toBe(4)
        ->and((int) StockLevel::where('product_id', $product->id)->whereNull('product_variant_id')->sum('quantity'))->toBe(0)
        ->and(ledgerMatches($product))->toBeTrue();
});

it('splits per warehouse', function (): void {
    $product = Product::factory()->create();
    $branch = Warehouse::create(['code' => 'SPL', 'name' => 'Branch', 'is_active' => true, 'is_default' => false]);
    giveStock($product, 5);
    giveStock($product, 3, null, $branch);

    splitSave($this, $product, [
        ['warehouse_id' => defaultWarehouse()->id, 'sku' => 'SPLIT-RED', 'quantity' => 5],
        ['warehouse_id' => $branch->id, 'sku' => 'SPLIT-RED', 'quantity' => 1],
        ['warehouse_id' => $branch->id, 'sku' => 'SPLIT-BLUE', 'quantity' => 2],
    ])->assertSessionHasNoErrors();

    $product->refresh();
    expect(variantQty($product, 'SPLIT-RED', defaultWarehouse()))->toBe(5)
        ->and(variantQty($product, 'SPLIT-RED', $branch))->toBe(1)
        ->and(variantQty($product, 'SPLIT-BLUE', $branch))->toBe(2)
        ->and(ledgerMatches($product))->toBeTrue();
});

it('refuses a split that does not add up, and changes nothing', function (array $split): void {
    $product = Product::factory()->create();
    giveStock($product, 10);

    splitSave($this, $product, $split)->assertSessionHasErrors('stock_split');

    $product->refresh();
    expect($product->has_variants)->toBeFalse()
        ->and($product->variants()->count())->toBe(0)
        ->and((int) StockLevel::where('product_id', $product->id)->whereNull('product_variant_id')->sum('quantity'))->toBe(10);
})->with([
    'too few' => [fn () => [['warehouse_id' => defaultWarehouse()->id, 'sku' => 'SPLIT-RED', 'quantity' => 6], ['warehouse_id' => defaultWarehouse()->id, 'sku' => 'SPLIT-BLUE', 'quantity' => 3]]],
    'too many' => [fn () => [['warehouse_id' => defaultWarehouse()->id, 'sku' => 'SPLIT-RED', 'quantity' => 8], ['warehouse_id' => defaultWarehouse()->id, 'sku' => 'SPLIT-BLUE', 'quantity' => 3]]],
    'unknown variant' => [fn () => [['warehouse_id' => defaultWarehouse()->id, 'sku' => 'NOPE', 'quantity' => 10]]],
    'missing' => [fn () => []],
]);

it('puts reserved units and open orders on the first variant, so cancelling still works', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 10);
    $user = customer();
    $cart = Cart::create(['user_id' => $user->id]);
    app(AddToCart::class)->handle($cart, $product, null, 2);
    $order = app(PlaceOrder::class)->handle($cart, new CheckoutData(
        customer: ['name' => 'x', 'email' => $user->email, 'phone' => '0910000000'],
        shippingAddress: ['name' => 'x', 'phone' => '0910000000', 'country' => 'LY', 'city' => 'Tripoli'],
        billingAddress: ['name' => 'x', 'phone' => '0910000000', 'country' => 'LY', 'city' => 'Tripoli'],
        paymentMethod: PaymentMethod::CashOnDelivery,
    ), $user); // 8 on the shelf, 2 reserved

    $wh = defaultWarehouse()->id;
    splitSave($this, $product, [
        ['warehouse_id' => $wh, 'sku' => 'SPLIT-RED', 'quantity' => 3],
        ['warehouse_id' => $wh, 'sku' => 'SPLIT-BLUE', 'quantity' => 5],
    ])->assertSessionHasNoErrors();

    $product->refresh();
    $red = $product->variants()->where('sku', 'SPLIT-RED')->sole();
    expect(StockLevel::where('product_variant_id', $red->id)->sole()->reserved)->toBe(2)
        ->and($order->items()->sole()->product_variant_id)->toBe($red->id);

    app(CancelOrder::class)->handle($order->refresh(), 'changed mind');

    expect(variantQty($product, 'SPLIT-RED'))->toBe(5)
        ->and(variantQty($product, 'SPLIT-BLUE'))->toBe(5)
        ->and(ledgerMatches($product))->toBeTrue();
});

it('still lets a single new variant take everything without a split', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 7);

    $this->actingAs(staff('inventory_manager'))->put(route('admin.products.update', $product), [
        'name' => $product->name, 'sku' => $product->sku, 'category_id' => $product->category_id,
        'cost_price' => 5, 'price' => 10, 'status' => 'active',
        'variants' => [['name' => 'Red', 'sku' => 'SPLIT-RED', 'price' => 10]],
    ])->assertSessionHasNoErrors();

    expect(variantQty($product->refresh(), 'SPLIT-RED'))->toBe(7);
});

it('hands the edit form the shelf stock it needs to offer the split', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 4);

    $this->actingAs(staff('inventory_manager'))->get(route('admin.products.edit', $product))
        ->assertInertia(fn ($page) => $page->where('plainStock.0.quantity', 4)->where('plainStock.0.warehouse_id', defaultWarehouse()->id));
});
