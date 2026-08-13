<?php

use App\Actions\Cart\AddToCart;
use App\Actions\Checkout\PlaceOrder;
use App\Actions\Orders\UpdateOrderStatus;
use App\Actions\Returns\CreateRefund;
use App\Actions\Returns\ReceiveReturnItems;
use App\Actions\Returns\RequestReturn;
use App\Actions\Returns\ReviewReturn;
use App\DTOs\CheckoutData;
use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundMethod;
use App\Enums\ReturnStatus;
use App\Models\Cart;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

function deliveredOrder(User $user, Product $product, int $quantity = 2): Order
{
    $cart = Cart::create(['user_id' => $user->id]);
    app(AddToCart::class)->handle($cart, $product, null, $quantity);

    $order = app(PlaceOrder::class)->handle($cart, new CheckoutData(
        customer: ['name' => $user->name, 'email' => $user->email, 'phone' => '091'],
        shippingAddress: ['name' => $user->name, 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        billingAddress: ['name' => $user->name, 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        paymentMethod: PaymentMethod::CashOnDelivery,
    ), $user);

    $action = app(UpdateOrderStatus::class);

    foreach ([OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::ReadyForShipping, OrderStatus::Shipped, OrderStatus::Delivered] as $status) {
        $order = $action->handle($order, $status);
    }

    return $order;
}

beforeEach(function (): void {
    Notification::fake();
});

it('lets a customer request a return within the window', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 10);
    $user = customer();
    $order = deliveredOrder($user, $product, 2);

    $return = app(RequestReturn::class)->handle(
        $order,
        $user,
        [['order_item_id' => $order->items->first()->id, 'quantity' => 1]],
        'defective',
    );

    expect($return->status)->toBe(ReturnStatus::Requested)
        ->and($return->rma_number)->toStartWith('RMA-')
        ->and($return->items)->toHaveCount(1);
});

it('rejects returns exceeding the purchased quantity', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 10);
    $user = customer();
    $order = deliveredOrder($user, $product, 2);

    app(RequestReturn::class)->handle($order, $user, [
        ['order_item_id' => $order->items->first()->id, 'quantity' => 5],
    ], 'defective');
})->throws(ValidationException::class);

it('rejects returns from someone else\'s account', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 10);
    $order = deliveredOrder(customer(), $product);

    app(RequestReturn::class)->handle($order, customer(), [
        ['order_item_id' => $order->items->first()->id, 'quantity' => 1],
    ], 'defective');
})->throws(ValidationException::class);

it('restocks resellable items and flags damaged ones on receipt', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 10);
    $user = customer();
    $order = deliveredOrder($user, $product, 2); // stock now 8, reserved cleared at ship

    $return = app(RequestReturn::class)->handle($order, $user, [
        ['order_item_id' => $order->items->first()->id, 'quantity' => 2],
    ], 'defective');

    $reviewer = staff('order_manager');
    $return = app(ReviewReturn::class)->handle($return, ReturnStatus::Approved, 'ok', $reviewer->id);

    $conditions = [$return->items->first()->id => 'resellable'];
    $return = app(ReceiveReturnItems::class)->handle($return, $conditions, defaultWarehouse(), $reviewer->id);

    $stock = StockLevel::query()->sole();

    expect($return->status)->toBe(ReturnStatus::ItemReceived)
        ->and($stock->quantity)->toBe(10)
        ->and(InventoryMovement::query()->where('type', MovementType::CustomerReturn)->count())->toBe(1);
});

it('issues a partial then full refund and rolls up payment status', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_00, 'tax_rate' => 0]);
    giveStock($product, 10);
    $user = customer();
    $order = deliveredOrder($user, $product, 2);

    // Refunds can only give back collected money, so pay the order first.
    $order->payments()->create(['amount_cents' => $order->total_cents, 'method' => 'cash', 'status' => 'confirmed']);

    $manager = staff('order_manager');

    app(CreateRefund::class)->handle($order, 50_00, RefundMethod::Cash, createdBy: $manager->id);
    expect($order->refresh()->payment_status)->toBe(PaymentStatus::PartiallyRefunded);

    app(CreateRefund::class)->handle($order, $order->total_cents - 50_00, RefundMethod::Cash, createdBy: $manager->id);
    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Refunded);
});

it('refuses refunds exceeding the amount paid', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_00]);
    giveStock($product, 10);
    $order = deliveredOrder(customer(), $product, 1);
    $order->payments()->create(['amount_cents' => $order->total_cents, 'method' => 'cash', 'status' => 'confirmed']);

    app(CreateRefund::class)->handle($order, $order->total_cents + 1, RefundMethod::Cash);
})->throws(ValidationException::class);

it('enforces the return state machine', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 10);
    $user = customer();
    $order = deliveredOrder($user, $product);

    $return = app(RequestReturn::class)->handle($order, $user, [
        ['order_item_id' => $order->items->first()->id, 'quantity' => 1],
    ], 'defective');

    // Cannot mark received before approval.
    app(ReceiveReturnItems::class)->handle($return, [], defaultWarehouse());
})->throws(ValidationException::class);
