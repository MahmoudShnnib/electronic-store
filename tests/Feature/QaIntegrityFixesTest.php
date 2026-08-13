<?php

declare(strict_types=1);

use App\Actions\Inventory\AdjustStock;
use App\Actions\Purchasing\CancelPurchaseOrder;
use App\Actions\Purchasing\SendPurchaseOrder;
use App\Actions\Returns\CreateRefund;
use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RefundMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\ReferenceNumber;
use Illuminate\Validation\ValidationException;

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
        ->and(\App\Models\InventoryMovement::query()->where('type', MovementType::PurchaseCancelled)->count())->toBe(1);
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
    $cart = (new \App\Models\Cart)->forceFill(['user_id' => $user->id]);
    $cart->save();
    $cart->items()->create(['product_id' => $product->id, 'quantity' => 2]);

    $order = app(\App\Actions\Checkout\PlaceOrder::class)->handle(
        $cart,
        new \App\DTOs\CheckoutData(
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

    app(\App\Actions\Catalog\SaveProduct::class)->handle($product, [], [[
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

    $return = \App\Models\ProductReturn::create([
        'rma_number' => ReferenceNumber::generate('RMA'),
        'order_id' => $order->id, 'user_id' => $user->id,
        'status' => 'closed', 'reason' => 'defective',
    ]);
    $return->items()->create(['order_item_id' => $item->id, 'quantity' => 2]);

    expect($item->returnedQuantity())->toBe(2);
});
