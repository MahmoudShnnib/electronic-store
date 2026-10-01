<?php

/*
 * Audit 2026-10-01 — deploy-blocker pass on stock: every admin action that
 * should move stock, plus the customer buying path. Each test asserts the
 * CORRECT behaviour; a failing test is a live bug.
 */

use App\Actions\Cart\AddToCart;
use App\Actions\Checkout\PlaceOrder;
use App\Actions\Inventory\AdjustStock;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\ResolveOrderIssue;
use App\Actions\Orders\UpdateOrderStatus;
use App\DTOs\CheckoutData;
use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefundMethod;
use App\Models\Cart;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Notification::fake());

function stkCheckout(User $user, Cart $cart)
{
    return app(PlaceOrder::class)->handle($cart, new CheckoutData(
        customer: ['name' => 'x', 'email' => $user->email, 'phone' => '091'],
        shippingAddress: ['name' => 'x', 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        billingAddress: ['name' => 'x', 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        paymentMethod: PaymentMethod::CashOnDelivery,
    ), $user);
}

function stkSellable(Product $product): int
{
    return (int) StockLevel::query()->where('product_id', $product->id)->sellable()->sum('quantity');
}

it('STK-1: the stock dialog\'s "-5" (negative = remove, as the hint says) removes 5', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 20);

    // Exactly what admin/inventory/Index.vue posts: default type manual_increase, signed number.
    $this->actingAs(staff('inventory_manager'))->post(route('admin.inventory.adjust'), [
        'warehouse_id' => defaultWarehouse()->id, 'product_id' => $product->id, 'product_variant_id' => null,
        'quantity_change' => -5, 'type' => 'manual_increase', 'reason' => 'count was 5 short',
    ]);

    expect(stkSellable($product))->toBe(15);
});

it('STK-2: "-2" on a variant product\'s leftover row in the dialog never crashes with a 500', function (): void {
    $product = Product::factory()->create(['has_variants' => true]);
    $product->variants()->create(['name' => 'Red', 'sku' => 'STK2-RED', 'price_cents' => 1_000, 'is_active' => true]);

    $response = $this->actingAs(staff('inventory_manager'))->post(route('admin.inventory.adjust'), [
        'warehouse_id' => defaultWarehouse()->id, 'product_id' => $product->id, 'product_variant_id' => null,
        'quantity_change' => -2, 'type' => 'manual_increase', 'reason' => 'drain',
    ]);

    expect($response->status())->toBeLessThan(500);
});

it('STK-3: removing EVERY variant from a product does not make its stock vanish', function (): void {
    $product = Product::factory()->create(['has_variants' => true]);
    $red = $product->variants()->create(['name' => 'Red', 'sku' => 'STK3-RED', 'price_cents' => 1_000, 'is_active' => true]);
    giveStock($product, 6, $red);

    $this->actingAs(staff('inventory_manager'))->put(route('admin.products.update', $product), [
        'name' => $product->name, 'sku' => $product->sku, 'category_id' => $product->category_id,
        'cost_price' => 10, 'price' => 20, 'status' => 'active', 'variants' => [],
    ]);

    // Either the save is refused, or the 6 units are still sellable as the plain product.
    $product->refresh();
    $stillVariant = $product->variants()->exists();
    $sellableNow = (int) StockLevel::query()->where('product_id', $product->id)->whereNull('product_variant_id')->sum('quantity');

    expect($stillVariant || $sellableNow === 6)->toBeTrue();
});

it('STK-4: writing off damaged units then returning those same units to the supplier does not remove them from sellable stock twice', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 10);

    app(AdjustStock::class)->handle(defaultWarehouse(), $product, null, 2, MovementType::Damaged, 'broken in transit');
    app(AdjustStock::class)->handle(defaultWarehouse(), $product, null, 2, MovementType::SupplierReturn, 'sent the 2 broken back', fromDamaged: true);

    $row = StockLevel::query()->sole();
    expect($row->quantity)->toBe(8)   // 10 − 2 broken; the supplier return is of the broken ones
        ->and($row->damaged)->toBe(0);
});

it('STK-5: a customer can buy the quantity the storefront advertises when stock is split across two warehouses', function (): void {
    $product = Product::factory()->create();
    $branch = Warehouse::create(['code' => 'BR1', 'name' => 'Branch', 'is_active' => true, 'is_default' => false]);
    giveStock($product, 3);
    giveStock($product, 3, null, $branch);

    // One order line ships from one warehouse, so 3 is the honest number.
    $advertised = Product::query()->withStock()->find($product->id)->availableQuantity();
    expect($advertised)->toBe(3);

    $user = customer();
    $cart = Cart::create(['user_id' => $user->id]);
    app(AddToCart::class)->handle($cart, $product, null, $advertised);
    stkCheckout($user, $cart);

    expect(stkSellable($product))->toBe(3);
});

it('STK-6: deleting a product that sits in an open order is refused (or the order can still be cancelled with stock returned)', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 5);
    $user = customer();
    $cart = Cart::create(['user_id' => $user->id]);
    app(AddToCart::class)->handle($cart, $product, null, 2);
    $order = stkCheckout($user, $cart);

    $this->actingAs(staff('admin'))->delete(route('admin.products.destroy', $product));

    if ($product->fresh()->trashed()) {
        app(CancelOrder::class)->handle($order, 'cancel');
        $row = StockLevel::query()->sole();
        expect($row->quantity)->toBe(5)->and($row->reserved)->toBe(0);
    } else {
        expect(true)->toBeTrue();
    }
});

it('STK-7: happy path — buying one variant only moves that variant, and completion clears its reservation', function (): void {
    $product = Product::factory()->create(['has_variants' => true]);
    $red = $product->variants()->create(['name' => 'Red', 'sku' => 'STK7-RED', 'price_cents' => 1_000, 'is_active' => true]);
    $blue = $product->variants()->create(['name' => 'Blue', 'sku' => 'STK7-BLUE', 'price_cents' => 1_000, 'is_active' => true]);
    giveStock($product, 4, $red);
    giveStock($product, 4, $blue);

    $user = customer();
    $cart = Cart::create(['user_id' => $user->id]);
    app(AddToCart::class)->handle($cart, $product, $red, 3);
    $order = stkCheckout($user, $cart);

    $redRow = StockLevel::where('product_variant_id', $red->id)->sole();
    expect($redRow->quantity)->toBe(1)->and($redRow->reserved)->toBe(3)
        ->and(StockLevel::where('product_variant_id', $blue->id)->sole()->quantity)->toBe(4);

    app(UpdateOrderStatus::class)->handle($order, OrderStatus::Completed);
    expect($redRow->refresh()->reserved)->toBe(0)->and($redRow->quantity)->toBe(1);
});

it('STK-8: a customer cannot buy more of a variant than that variant holds, even if a sibling has plenty', function (): void {
    $product = Product::factory()->create(['has_variants' => true]);
    $red = $product->variants()->create(['name' => 'Red', 'sku' => 'STK8-RED', 'price_cents' => 1_000, 'is_active' => true]);
    $blue = $product->variants()->create(['name' => 'Blue', 'sku' => 'STK8-BLUE', 'price_cents' => 1_000, 'is_active' => true]);
    giveStock($product, 1, $red);
    giveStock($product, 9, $blue);

    $cart = Cart::create(['user_id' => customer()->id]);
    expect(fn () => app(AddToCart::class)->handle($cart, $product, $red, 2))
        ->toThrow(ValidationException::class);
});

it('STK-9: the mobile API does not advertise a sold-out product as in stock', function (): void {
    $product = Product::factory()->create(['status' => 'active', 'published_at' => now()]);
    // No stock at all.

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.in_stock', false);
});

it('STK-10: deleting a warehouse that only has a draft PO does not break the whole Purchase Orders list', function (): void {
    $admin = staff('admin');
    $wh = Warehouse::create(['code' => 'TMP', 'name' => 'Temp', 'is_active' => true, 'is_default' => false]);
    $product = Product::factory()->create();
    $this->actingAs($admin)->post(route('admin.purchase-orders.store'), [
        'supplier_id' => Supplier::factory()->create()->id,
        'warehouse_id' => $wh->id,
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 10]],
    ]);

    $this->actingAs($admin)->delete(route('admin.warehouses.destroy', $wh));

    // Either the delete is refused, or the PO list still opens.
    $this->actingAs($admin)->get(route('admin.purchase-orders.index'))->assertOk();
});

it('STK-11: checkout accepts a Libyan mobile number typed with Arabic-Indic digits', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 5);
    $user = customer();
    $cart = Cart::create(['user_id' => $user->id]);
    app(AddToCart::class)->handle($cart, $product, null, 1);

    $this->actingAs($user)->post(route('checkout.store'), [
        'customer' => ['name' => 'x', 'email' => $user->email, 'phone' => '٠٩١٢٣٤٥٦٧٨'],
        'shipping_address' => ['city' => 'Tripoli', 'area' => 'Downtown'],
        'payment_method' => 'cod',
        'idempotency_key' => 'stk-11',
    ])->assertSessionHasNoErrors();
});

it('STK-12: a second staff member saving an old copy of the product form does not silently delete a variant someone else just added and stocked', function (): void {
    $product = Product::factory()->create(['has_variants' => true]);
    $red = $product->variants()->create(['name' => 'Red', 'sku' => 'STK12-RED', 'price_cents' => 2_000, 'is_active' => true]);

    // Staff A opens the edit form now (sees only Red).
    $staleVariants = [['id' => $red->id, 'name' => 'Red', 'sku' => 'STK12-RED', 'price' => 20]];

    // Staff B adds Green and receives 5 units of it.
    $green = $product->variants()->create(['name' => 'Green', 'sku' => 'STK12-GREEN', 'price_cents' => 2_000, 'is_active' => true]);
    giveStock($product, 5, $green);

    // Staff A saves the old form (e.g. only fixed a typo in the description).
    $this->actingAs(staff('inventory_manager'))->put(route('admin.products.update', $product), [
        'name' => $product->name, 'sku' => $product->sku, 'category_id' => $product->category_id,
        'cost_price' => 10, 'price' => 20, 'status' => 'active', 'variants' => $staleVariants,
        'known_variant_ids' => [$red->id], // what the form opened with
    ]);

    expect($green->fresh()->trashed())->toBeFalse();
});

it('STK-13: returning a completed order (nothing open) still works after the product later got variants', function (): void {
    $product = Product::factory()->create(['price_cents' => 1_000]);
    giveStock($product, 3);
    $user = customer();
    $cart = Cart::create(['user_id' => $user->id]);
    app(AddToCart::class)->handle($cart, $product, null, 1);
    $order = stkCheckout($user, $cart);
    $order = app(UpdateOrderStatus::class)->handle($order, OrderStatus::Completed);

    $this->actingAs(staff('inventory_manager'))->put(route('admin.products.update', $product), [
        'name' => $product->name, 'sku' => $product->sku, 'category_id' => $product->category_id,
        'cost_price' => 5, 'price' => 10, 'status' => 'active',
        'variants' => [['name' => 'Red', 'sku' => 'STK13-RED', 'price' => 10], ['name' => 'Blue', 'sku' => 'STK13-BLUE', 'price' => 10]],
    ]);

    expect(fn () => app(ResolveOrderIssue::class)->handle($order->refresh(), 'faulty', 0, RefundMethod::Cash, true))
        ->not->toThrow(RuntimeException::class);
});

it('STK-14: stock left on a removed variant can still be written off from the Stock page', function (): void {
    $product = Product::factory()->create(['has_variants' => true]);
    $red = $product->variants()->create(['name' => 'Red', 'sku' => 'STK14-RED', 'price_cents' => 1_000, 'is_active' => true]);
    $blue = $product->variants()->create(['name' => 'Blue', 'sku' => 'STK14-BLUE', 'price_cents' => 1_000, 'is_active' => true]);
    giveStock($product, 2, $red);
    $red->delete(); // what removing it from the product form does

    $this->actingAs(staff('inventory_manager'))->post(route('admin.inventory.adjust'), [
        'warehouse_id' => defaultWarehouse()->id, 'product_id' => $product->id, 'product_variant_id' => $red->id,
        'quantity_change' => 2, 'type' => 'manual_decrease', 'reason' => 'clear leftover',
    ]);

    expect(StockLevel::where('product_variant_id', $red->id)->sole()->quantity)->toBe(0);
});

it('STK-15: a newly added variant shows on the Stock page so staff can give it opening stock', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 4);
    $this->actingAs(staff('inventory_manager'))->put(route('admin.products.update', $product), [
        'name' => $product->name, 'sku' => $product->sku, 'category_id' => $product->category_id,
        'cost_price' => 5, 'price' => 10, 'status' => 'active',
        'variants' => [['name' => 'Red', 'sku' => 'STK15-RED', 'price' => 10]],
    ])->assertSessionHasNoErrors();
    // A stocked product gains variants one at a time (STK-16), so Blue comes second.
    $red = $product->refresh()->variants()->sole();
    $this->put(route('admin.products.update', $product), [
        'name' => $product->name, 'sku' => $product->sku, 'category_id' => $product->category_id,
        'cost_price' => 5, 'price' => 10, 'status' => 'active',
        'variants' => [['id' => $red->id, 'name' => 'Red', 'sku' => 'STK15-RED', 'price' => 10], ['name' => 'Blue', 'sku' => 'STK15-BLUE', 'price' => 10]],
    ])->assertSessionHasNoErrors();
    $blue = $product->refresh()->variants()->where('sku', 'STK15-BLUE')->sole();

    $this->actingAs(staff('inventory_manager'))->get(route('admin.inventory.index'))
        ->assertInertia(fn ($page) => $page->where('levels.data', fn ($rows) => collect($rows)->contains('variant_id', $blue->id)));
});

it('STK-16: converting a stocked product to variants does not silently put every unit on the first variant', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 10);

    $response = $this->actingAs(staff('inventory_manager'))->put(route('admin.products.update', $product), [
        'name' => $product->name, 'sku' => $product->sku, 'category_id' => $product->category_id,
        'cost_price' => 5, 'price' => 10, 'status' => 'active',
        'variants' => [['name' => 'Red', 'sku' => 'STK16-RED', 'price' => 10], ['name' => 'Blue', 'sku' => 'STK16-BLUE', 'price' => 10]],
    ]);

    // Correct: staff are asked how to split the 10 units (save refused until they do) — not 10 on Red, 0 on Blue.
    $red = $product->refresh()->variants()->where('sku', 'STK16-RED')->first();
    $redQty = $red ? (int) StockLevel::where('product_variant_id', $red->id)->sum('quantity') : 0;
    expect(session('errors') !== null || $redQty < 10)->toBeTrue();
});
