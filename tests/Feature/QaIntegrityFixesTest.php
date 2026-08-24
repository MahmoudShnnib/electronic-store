<?php

declare(strict_types=1);

use App\Actions\Cart\AddToCart;
use App\Actions\Catalog\SaveProduct;
use App\Actions\Checkout\PlaceOrder;
use App\Actions\Inventory\AdjustStock;
use App\Actions\Purchasing\CancelPurchaseOrder;
use App\Actions\Purchasing\SendPurchaseOrder;
use App\Actions\Returns\CreateRefund;
use App\DTOs\CheckoutData;
use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RefundMethod;
use App\Models\Cart;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\PurchaseOrder;
use App\Models\SpecificationAttribute;
use App\Models\SpecificationGroup;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\ReferenceNumber;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

function unpaidOrder(User $user, int $totalCents = 10_000): Order
{
    $address = ['name' => $user->name, 'phone' => '0910000000', 'country' => 'LY', 'city' => 'Tripoli', 'area' => 'Center'];

    return Order::create([
        'number' => ReferenceNumber::generate('ORD'),
        'user_id' => $user->id,
        'status' => 'pending',
        'payment_status' => PaymentStatus::Unpaid,
        'payment_method' => PaymentMethod::CashOnDelivery,
        'customer' => ['name' => $user->name, 'email' => $user->email],
        'shipping_address' => $address,
        'billing_address' => $address,
        'subtotal_cents' => $totalCents,
        'total_cents' => $totalCents,
        'currency' => 'LYD',
        'placed_via' => 'web',
    ]);
}

// -- A9: the catalog grid must read stock, not status -------------------------

it('shows an active product with an empty shelf as out of stock', function (): void {
    $stocked = Product::factory()->create(['name' => 'Has stock']);
    giveStock($stocked, 3);

    Product::factory()->create(['name' => 'Empty shelf']);

    // Nothing in the app ever writes ProductStatus::OutOfStock, so a status
    // check alone showed "Add to cart" on a product nobody could buy.
    $this->get(route('catalog.index', ['q' => 'Has stock']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('products.data.0.in_stock', true));

    $this->get(route('catalog.index', ['q' => 'Empty shelf']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('products.data.0.in_stock', false));

    $this->get(route('catalog.index', ['in_stock' => 1]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 1));
});

// -- A2: a variant sale must end when the product's sale window ends ----------

it('stops charging a variant sale price once the product sale window has ended', function (): void {
    $product = Product::factory()->create([
        'price_cents' => 100_000,
        'sale_price_cents' => 80_000,
        'sale_ends_at' => now()->subDay(),
    ]);

    $variant = $product->variants()->create([
        'name' => 'Expired sale',
        'sku' => 'VAR-EXPIRED',
        'price_cents' => 120_000,
        'sale_price_cents' => 90_000,
        'is_active' => true,
    ]);

    $cart = Cart::create(['session_token' => 'sale-window-test']);
    $item = $cart->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
    ]);

    // Variants carry no schedule of their own; they follow the parent product.
    expect($variant->saleIsActive())->toBeFalse()
        ->and($item->unitPriceCents())->toBe(120_000);

    $product->update(['sale_ends_at' => now()->addDay()]);

    expect($variant->refresh()->saleIsActive())->toBeTrue()
        ->and($item->refresh()->unitPriceCents())->toBe(90_000);
});

// -- B31: refunds capped by what was actually paid ---------------------------

it('refuses to refund an order with no confirmed payment', function (): void {
    $order = unpaidOrder(customer());

    app(CreateRefund::class)->handle($order, 10_000, RefundMethod::Cash);
})->throws(ValidationException::class);

it('caps refunds at the amount actually paid, not the order total', function (): void {
    $order = unpaidOrder(customer(), 10_000);
    $order->payments()->create([
        'amount_cents' => 4_000, 'method' => 'cash', 'status' => 'confirmed',
    ]);

    expect(fn () => app(CreateRefund::class)->handle($order, 5_000, RefundMethod::Cash))
        ->toThrow(ValidationException::class);

    app(CreateRefund::class)->handle($order, 4_000, RefundMethod::Cash);

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Refunded);
});

// -- B30: cancelling a sent PO releases its incoming stock -------------------

it('releases booked incoming stock when a sent PO is cancelled', function (): void {
    $product = Product::factory()->create();

    $po = PurchaseOrder::create([
        'po_number' => ReferenceNumber::generate('PO'),
        'supplier_id' => Supplier::factory()->create()->id,
        'warehouse_id' => defaultWarehouse()->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);
    $po->items()->create([
        'product_id' => $product->id,
        'quantity_ordered' => 7,
        'unit_cost_cents' => 1_000,
        'line_total_cents' => 7_000,
    ]);

    app(SendPurchaseOrder::class)->handle($po);
    expect(StockLevel::query()->sole()->incoming)->toBe(7);

    app(CancelPurchaseOrder::class)->handle($po->refresh());

    expect(StockLevel::query()->sole()->incoming)->toBe(0)
        ->and($po->refresh()->status)->toBe(PurchaseOrderStatus::Cancelled)
        ->and(InventoryMovement::query()->where('type', MovementType::PurchaseCancelled)->count())->toBe(1);
});

// -- A7: adjustment direction comes from the movement type -------------------

it('treats a positive quantity as a decrease for decrease-type adjustments', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 10);

    // Omar's repro: choose "damaged" and type a positive number.
    app(AdjustStock::class)->handle(
        defaultWarehouse(), $product, null, 4, MovementType::Damaged, 'QA test',
    );

    $stock = StockLevel::query()->sole();

    expect($stock->quantity)->toBe(6)
        ->and($stock->damaged)->toBe(4);
});

// -- B8: checkout sells from any warehouse holding the stock -----------------

it('fulfils an order from a secondary warehouse when the default is empty', function (): void {
    $product = Product::factory()->create(['price_cents' => 5_000]);
    $secondary = Warehouse::factory()->create(['is_default' => false, 'is_active' => true]);
    giveStock($product, 5, warehouse: $secondary);

    $user = customer();
    $cart = (new Cart)->forceFill(['user_id' => $user->id]);
    $cart->save();
    $cart->items()->create(['product_id' => $product->id, 'quantity' => 2]);

    $order = app(PlaceOrder::class)->handle(
        $cart,
        new CheckoutData(
            customer: ['name' => $user->name, 'email' => $user->email, 'phone' => '0910000000'],
            shippingAddress: ['name' => $user->name, 'phone' => '0910000000', 'country' => 'LY', 'city' => 'Tripoli', 'area' => 'Center'],
            billingAddress: ['name' => $user->name, 'phone' => '0910000000', 'country' => 'LY', 'city' => 'Tripoli', 'area' => 'Center'],
            paymentMethod: PaymentMethod::CashOnDelivery,
            customerNote: null,
            idempotencyKey: null,
        ),
        $user,
        'web',
    );

    expect($order->items()->sole()->warehouse_id)->toBe($secondary->id)
        ->and(StockLevel::query()->where('warehouse_id', $secondary->id)->sole()->quantity)->toBe(3);
});

// -- B22/B23: product-level stock follows the first variants -----------------

it('moves product-level stock onto the first variant when variants are added', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 12);

    app(SaveProduct::class)->handle($product, [], [[
        'name' => 'Red', 'sku' => 'VAR-RED-1', 'price_cents' => 5_000, 'cost_price_cents' => 0,
    ]]);

    $variant = $product->refresh()->variants()->sole();

    expect((int) $product->stockLevels()->whereNull('product_variant_id')->sum('quantity'))->toBe(0)
        ->and((int) $product->stockLevels()->where('product_variant_id', $variant->id)->sum('quantity'))->toBe(12);
});

// -- B9: a closed return keeps its quantities returned -----------------------

it('does not free return quantities when a return is closed', function (): void {
    $user = customer();
    $order = unpaidOrder($user);
    $item = $order->items()->create([
        'product_id' => Product::factory()->create()->id,
        'warehouse_id' => defaultWarehouse()->id,
        'product_name' => 'Thing', 'sku' => 'T-1',
        'unit_price_cents' => 1_000, 'quantity' => 2, 'line_total_cents' => 2_000,
    ]);

    $return = ProductReturn::create([
        'rma_number' => ReferenceNumber::generate('RMA'),
        'order_id' => $order->id, 'user_id' => $user->id,
        'status' => 'closed', 'reason' => 'defective',
    ]);
    $return->items()->create(['order_item_id' => $item->id, 'quantity' => 2]);

    expect($item->returnedQuantity())->toBe(2);
});

// -- B8 residual: split-warehouse stock is not summed across warehouses ------

it('caps cart availability at the largest single warehouse, not the sum across warehouses', function (): void {
    $product = Product::factory()->create();
    $secondary = Warehouse::factory()->create(['is_default' => false, 'is_active' => true]);
    giveStock($product, 3, warehouse: defaultWarehouse());
    giveStock($product, 3, warehouse: $secondary);

    // 3 + 3 must not read as "6 available" — no single order ships from two
    // warehouses (PlaceOrder::warehouseFor picks exactly one).
    app(AddToCart::class)->assertQuantityAvailable($product, null, 4);
})->throws(ValidationException::class);

it('excludes stock sitting in an inactive warehouse from cart availability', function (): void {
    $product = Product::factory()->create();
    $inactive = Warehouse::factory()->create(['is_default' => false, 'is_active' => false]);
    giveStock($product, 5, warehouse: $inactive);

    app(AddToCart::class)->assertQuantityAvailable($product, null, 1);
})->throws(ValidationException::class);

// -- B23 residual: a variant product can no longer gain product-level stock --

it('refuses to add product-level stock once a product has variants', function (): void {
    $product = Product::factory()->create();

    app(SaveProduct::class)->handle($product, [], [[
        'name' => 'Red', 'sku' => 'VAR-RED-2', 'price_cents' => 5_000, 'cost_price_cents' => 0,
    ]]);

    giveStock($product->refresh(), 10);
})->throws(RuntimeException::class);

it('still allows draining an existing orphaned product-level stock row', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 8);

    app(SaveProduct::class)->handle($product, [], [[
        'name' => 'Red', 'sku' => 'VAR-RED-3', 'price_cents' => 5_000, 'cost_price_cents' => 0,
    ]]);
    $product->refresh();

    // The migration only moves quantity > 0 rows; force one back to simulate a
    // pre-fix orphan sitting alongside variant stock.
    $product->stockLevels()->whereNull('product_variant_id')->update(['quantity' => 4]);

    app(AdjustStock::class)->handle(
        defaultWarehouse(), $product, null, -4, MovementType::ManualDecrease, 'Draining orphan',
    );

    expect((int) $product->stockLevels()->whereNull('product_variant_id')->sum('quantity'))->toBe(0);
});

// -- Specifications: category-change no longer silently drops data -----------

it('keeps a specification value when the form no longer submits its attribute', function (): void {
    $admin = staff('inventory_manager');
    $group = SpecificationGroup::create(['name' => 'Display']);
    $mobile = Category::factory()->create(['name' => 'Mobile']);
    $pc = Category::factory()->create(['name' => 'PC']);

    $screenSize = SpecificationAttribute::create([
        'specification_group_id' => $group->id, 'name' => 'Screen size', 'type' => 'number',
    ]);
    $screenSize->categories()->sync([$mobile->id]);

    $product = Product::factory()->create(['category_id' => $mobile->id]);
    $product->specificationValues()->create([
        'specification_attribute_id' => $screenSize->id, 'value' => '6.1',
    ]);

    // Recategorize to PC and submit with no specifications at all — Screen size
    // is out of scope now, so the form never sends it.
    $this->actingAs($admin)->put(route('admin.products.update', $product), [
        'name' => $product->name, 'sku' => $product->sku, 'category_id' => $pc->id,
        'cost_price' => 10, 'price' => 20, 'status' => 'active',
    ])->assertSessionHasNoErrors();

    expect($product->specificationValues()->where('specification_attribute_id', $screenSize->id)->exists())->toBeTrue();
});

it('rejects a specification value for an attribute out of the submitted category scope', function (): void {
    $admin = staff('inventory_manager');
    $group = SpecificationGroup::create(['name' => 'Display']);
    $mobile = Category::factory()->create(['name' => 'Mobile']);
    $pc = Category::factory()->create(['name' => 'PC']);

    $screenSize = SpecificationAttribute::create([
        'specification_group_id' => $group->id, 'name' => 'Screen size', 'type' => 'number',
    ]);
    $screenSize->categories()->sync([$mobile->id]);

    $product = Product::factory()->create(['category_id' => $pc->id]);

    $this->actingAs($admin)->put(route('admin.products.update', $product), [
        'name' => $product->name, 'sku' => $product->sku, 'category_id' => $pc->id,
        'cost_price' => 10, 'price' => 20, 'status' => 'active',
        'specifications' => [$screenSize->id => ['value' => '6.1']],
    ])->assertSessionHasErrors(["specifications.{$screenSize->id}.value"]);
});

// -- API price sort now honours the sale window ------------------------------

it('sorts the API catalog by the effective price, ignoring an expired sale', function (): void {
    $cheapButExpired = Product::factory()->create([
        'price_cents' => 10_000, 'sale_price_cents' => 1_000,
        'sale_starts_at' => now()->subDays(10), 'sale_ends_at' => now()->subDay(),
        'status' => 'active', 'published_at' => now(),
    ]);
    $trulyCheaper = Product::factory()->create([
        'price_cents' => 5_000, 'sale_price_cents' => null,
        'status' => 'active', 'published_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/products?sort=price_asc');

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect(array_search($trulyCheaper->id, $ids, true))
        ->toBeLessThan(array_search($cheapButExpired->id, $ids, true));
});
