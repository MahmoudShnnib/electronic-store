<?php

use App\Actions\Cart\AddToCart;
use App\Actions\Checkout\PlaceOrder;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\UpdateOrderStatus;
use App\DTOs\CheckoutData;
use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Cart;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

function placedOrder(User $user, Product $product, int $quantity = 2): Order
{
    $cart = Cart::create(['user_id' => $user->id]);
    app(AddToCart::class)->handle($cart, $product, null, $quantity);

    return app(PlaceOrder::class)->handle($cart, new CheckoutData(
        customer: ['name' => $user->name, 'email' => $user->email, 'phone' => '091'],
        shippingAddress: ['name' => $user->name, 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        billingAddress: ['name' => $user->name, 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        paymentMethod: PaymentMethod::CashOnDelivery,
    ), $user);
}

beforeEach(function (): void {
    Notification::fake();
});

it('walks the happy path through the status state machine', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 10);
    $user = customer();
    $order = placedOrder($user, $product);

    expect($order->status)->toBe(OrderStatus::InProgress);

    $order = app(UpdateOrderStatus::class)->handle($order, OrderStatus::Completed);

    expect($order->status)->toBe(OrderStatus::Completed)
        ->and($order->statusHistories()->count())->toBe(2)
        ->and($order->completed_at)->not->toBeNull();
});

it('rejects invalid status transitions', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 10);
    $order = placedOrder(customer(), $product);

    // Money can only go back on an order that was finished first.
    app(UpdateOrderStatus::class)->handle($order, OrderStatus::Refunded);
})->throws(ValidationException::class);

it('clears the physical reservation when the order completes', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 10);
    $order = placedOrder(customer(), $product, 4);

    expect(StockLevel::query()->sole()->reserved)->toBe(4);

    app(UpdateOrderStatus::class)->handle($order, OrderStatus::Completed);

    $stock = StockLevel::query()->sole();
    expect($stock->reserved)->toBe(0)->and($stock->quantity)->toBe(6);
});

it('restores stock via the ledger when an order is cancelled', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 10);
    $order = placedOrder(customer(), $product, 3);

    $order = app(CancelOrder::class)->handle($order, 'Changed my mind');

    $stock = StockLevel::query()->sole();

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancelled_at)->not->toBeNull()
        ->and($stock->quantity)->toBe(10)
        ->and($stock->reserved)->toBe(0)
        ->and(InventoryMovement::query()->where('type', MovementType::OrderCancellation)->count())->toBe(1);
});

it('refuses to cancel a completed order', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 10);
    $order = placedOrder(customer(), $product);

    $order = app(UpdateOrderStatus::class)->handle($order, OrderStatus::Completed);

    app(CancelOrder::class)->handle($order);
})->throws(ValidationException::class);

it('lets a customer cancel their own pending order over HTTP but not others', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 10);

    $owner = customer();
    $stranger = customer();
    $order = placedOrder($owner, $product);

    $this->actingAs($stranger)
        ->post(route('account.orders.cancel', $order))
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('account.orders.cancel', $order))
        ->assertRedirect();

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled);
});
