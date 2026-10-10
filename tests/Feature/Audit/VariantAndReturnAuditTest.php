<?php

/*
 * Audit 2026-10-01 — reproductions of critical findings. Each test asserts the
 * CORRECT behaviour, so a failing test here is a live bug.
 */

use App\Actions\Cart\AddToCart;
use App\Actions\Checkout\PlaceOrder;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\ResolveOrderIssue;
use App\Actions\Orders\UpdateOrderStatus;
use App\Actions\Purchasing\ReceivePurchaseOrder;
use App\Actions\Purchasing\SendPurchaseOrder;
use App\DTOs\CheckoutData;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RefundMethod;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ReferenceNumber;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Notification::fake());

function auditOrder(User $user, Product $product, int $qty, ?ProductVariant $variant = null): Order
{
    $cart = Cart::create(['user_id' => $user->id]);
    app(AddToCart::class)->handle($cart, $product, $variant, $qty);

    return app(PlaceOrder::class)->handle($cart, new CheckoutData(
        customer: ['name' => $user->name, 'email' => $user->email, 'phone' => '091'],
        shippingAddress: ['name' => $user->name, 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        billingAddress: ['name' => $user->name, 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        paymentMethod: PaymentMethod::CashOnDelivery,
    ), $user);
}

function putProduct($test, Product $product, array $variants): void
{
    $test->actingAs(staff('inventory_manager'))->put(route('admin.products.update', $product), [
        'name' => $product->name, 'sku' => $product->sku, 'category_id' => $product->category_id,
        'cost_price' => 10, 'price' => 20, 'status' => 'active',
        'variants' => $variants,
    ])->assertSessionHasNoErrors();
}

it('A1: cancels an open order placed BEFORE the product got variants', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 10);
    $order = auditOrder(customer(), $product, 2);

    putProduct($this, $product, [['name' => 'Red', 'sku' => 'A1-RED', 'price' => 20]]);

    app(CancelOrder::class)->handle($order->refresh(), 'customer changed mind');

    // All 10 units must be sellable again somewhere the storefront reads.
    $variant = $product->refresh()->variants()->sole();
    expect((int) StockLevel::where('product_variant_id', $variant->id)->sum('quantity'))->toBe(10);
});

it('A2: cancels an open order whose variant was removed from the product form', function (): void {
    $product = Product::factory()->create(['has_variants' => true]);
    $red = $product->variants()->create(['name' => 'Red', 'sku' => 'A2-RED', 'price_cents' => 2_000, 'is_active' => true]);
    $blue = $product->variants()->create(['name' => 'Blue', 'sku' => 'A2-BLUE', 'price_cents' => 2_000, 'is_active' => true]);
    giveStock($product, 5, $red);
    $order = auditOrder(customer(), $product, 1, $red);

    // Admin drops "Red" from the form, keeps "Blue".
    putProduct($this, $product, [['id' => $blue->id, 'name' => 'Blue', 'sku' => 'A2-BLUE', 'price' => 20]]);

    $cancelled = app(CancelOrder::class)->handle($order->refresh(), 'out of red');
    expect($cancelled->status)->toBe(OrderStatus::Cancelled);
});

it('A3: removing a variant that still holds stock does not leave phantom "in stock" on the storefront', function (): void {
    $product = Product::factory()->create(['has_variants' => true]);
    $red = $product->variants()->create(['name' => 'Red', 'sku' => 'A3-RED', 'price_cents' => 2_000, 'is_active' => true]);
    $blue = $product->variants()->create(['name' => 'Blue', 'sku' => 'A3-BLUE', 'price_cents' => 2_000, 'is_active' => true]);
    giveStock($product, 7, $red);

    putProduct($this, $product, [['id' => $blue->id, 'name' => 'Blue', 'sku' => 'A3-BLUE', 'price' => 20]]);

    // Only Blue is sellable and Blue has 0 — the grid must not say 7 in stock.
    expect(Product::query()->withStock()->find($product->id)->availableQuantity())->toBe(0);
});

it('A4: a deactivated variant already sitting in a cart cannot be checked out', function (): void {
    $product = Product::factory()->create(['has_variants' => true]);
    $red = $product->variants()->create(['name' => 'Red', 'sku' => 'A4-RED', 'price_cents' => 2_000, 'is_active' => true]);
    giveStock($product, 5, $red);
    $user = customer();
    $cart = Cart::create(['user_id' => $user->id]);
    app(AddToCart::class)->handle($cart, $product, $red, 1);

    $red->update(['is_active' => false]);

    expect(fn () => app(PlaceOrder::class)->handle($cart, new CheckoutData(
        customer: ['name' => 'x', 'email' => $user->email, 'phone' => '091'],
        shippingAddress: ['name' => 'x', 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        billingAddress: ['name' => 'x', 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        paymentMethod: PaymentMethod::CashOnDelivery,
    ), $user))->toThrow(ValidationException::class);
});

it('A5: a refund that fails validation does not restock the goods anyway', function (): void {
    $product = Product::factory()->create(['price_cents' => 1_000]);
    giveStock($product, 10);
    $order = auditOrder(customer(), $product, 2);
    $order = app(UpdateOrderStatus::class)->handle($order, OrderStatus::Completed); // collects 20.000

    try {
        app(ResolveOrderIssue::class)->handle($order, 'broken', refundCents: 999_999, method: RefundMethod::Cash, restock: true);
    } catch (ValidationException) {
        // expected: refund exceeds what was paid
    }

    expect(StockLevel::query()->sole()->quantity)->toBe(8); // still 8 — nothing came back
});

it('A6: logging a problem twice on the same order does not restock twice', function (): void {
    $product = Product::factory()->create(['price_cents' => 1_000]);
    giveStock($product, 10);
    $order = auditOrder(customer(), $product, 2);
    $order = app(UpdateOrderStatus::class)->handle($order, OrderStatus::Completed);

    // A return is whole and refunded in full (2026-10-10); a second one finds nothing left to take back.
    app(ResolveOrderIssue::class)->handle($order, 'scratched', refundCents: $order->total_cents, method: RefundMethod::Cash, restock: true);
    expect(fn () => app(ResolveOrderIssue::class)->handle($order->refresh(), 'scratched again', refundCents: $order->total_cents, method: RefundMethod::Cash, restock: true))
        ->toThrow(ValidationException::class);

    // 2 units sold, at most 2 can ever come back.
    expect(StockLevel::query()->sole()->quantity)->toBeLessThanOrEqual(10);
});

it('A7: returning a completed order whose variant was later removed does not crash', function (): void {
    $product = Product::factory()->create(['has_variants' => true, 'price_cents' => 1_000]);
    $red = $product->variants()->create(['name' => 'Red', 'sku' => 'A7-RED', 'price_cents' => 1_000, 'is_active' => true]);
    $blue = $product->variants()->create(['name' => 'Blue', 'sku' => 'A7-BLUE', 'price_cents' => 1_000, 'is_active' => true]);
    giveStock($product, 5, $red);
    $order = auditOrder(customer(), $product, 1, $red);
    $order = app(UpdateOrderStatus::class)->handle($order, OrderStatus::Completed);

    putProduct($this, $product, [['id' => $blue->id, 'name' => 'Blue', 'sku' => 'A7-BLUE', 'price' => 10]]);

    $result = app(ResolveOrderIssue::class)->handle($order->refresh(), 'defective', refundCents: 1_000, method: RefundMethod::Cash, restock: true);
    expect($result->status)->toBe(OrderStatus::Refunded);
});

it('A8: receives a purchase order that was sent BEFORE the product got variants', function (): void {
    $product = Product::factory()->create();
    $po = PurchaseOrder::create([
        'po_number' => ReferenceNumber::generate('PO'),
        'supplier_id' => Supplier::factory()->create()->id,
        'warehouse_id' => defaultWarehouse()->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);
    $item = $po->items()->create(['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost_cents' => 1_000, 'line_total_cents' => 10_000]);
    app(SendPurchaseOrder::class)->handle($po);

    putProduct($this, $product, [['name' => 'Red', 'sku' => 'A8-RED', 'price' => 20]]);

    // Should either land on a variant or refuse with a validation message — never a 500.
    expect(fn () => app(ReceivePurchaseOrder::class)->handle($po->refresh(), [$item->id => 10]))
        ->not->toThrow(RuntimeException::class);
});

it('A9: a purchase order cannot pair a product with ANOTHER product\'s variant', function (): void {
    $phone = Product::factory()->create();
    $case = Product::factory()->create(['has_variants' => true]);
    $caseRed = $case->variants()->create(['name' => 'Red', 'sku' => 'A9-RED', 'price_cents' => 1_000, 'is_active' => true]);

    $this->actingAs(staff('admin'))->post(route('admin.purchase-orders.store'), [
        'supplier_id' => Supplier::factory()->create()->id,
        'warehouse_id' => defaultWarehouse()->id,
        'items' => [['product_id' => $phone->id, 'product_variant_id' => $caseRed->id, 'quantity' => 5, 'unit_cost' => 10]],
    ])->assertSessionHasErrors();
});

it('A10: a purchase order for a variant product must name a variant', function (): void {
    $case = Product::factory()->create(['has_variants' => true]);
    $case->variants()->create(['name' => 'Red', 'sku' => 'A10-RED', 'price_cents' => 1_000, 'is_active' => true]);

    $this->actingAs(staff('admin'))->post(route('admin.purchase-orders.store'), [
        'supplier_id' => Supplier::factory()->create()->id,
        'warehouse_id' => defaultWarehouse()->id,
        'items' => [['product_id' => $case->id, 'quantity' => 5, 'unit_cost' => 10]],
    ])->assertSessionHasErrors();
});
