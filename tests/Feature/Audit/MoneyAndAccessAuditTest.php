<?php

/*
 * Audit 2026-10-01 — money / access reproductions. Each test asserts the
 * CORRECT behaviour, so a failing test here is a live bug.
 */

use App\Actions\Cart\AddToCart;
use App\Actions\Checkout\PlaceOrder;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\ResolveOrderIssue;
use App\Actions\Orders\UpdateOrderStatus;
use App\Actions\Payments\ConfirmPayment;
use App\Actions\Pos\CloseShift;
use App\Actions\Pos\CompleteSale;
use App\Actions\Pos\OpenShift;
use App\DTOs\CheckoutData;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefundMethod;
use App\Enums\TreasuryAccount;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\TreasuryTransaction;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Notification::fake());

function cashBalance(): int
{
    return (int) TreasuryTransaction::query()->where('account', TreasuryAccount::Cash)->get()
        ->sum(fn (TreasuryTransaction $t) => $t->signedAmountCents());
}

it('M1: a cash refund of a sale from an earlier (closed) shift leaves the treasury down by the refund only once', function (): void {
    $product = Product::factory()->create(['price_cents' => 10_000]);
    giveStock($product, 5);
    $cashier = staff('cashier');

    $shiftA = app(OpenShift::class)->handle($cashier, defaultWarehouse(), 0);
    $order = app(CompleteSale::class)->handle($shiftA, [['product_id' => $product->id, 'quantity' => 1]]);
    app(CloseShift::class)->handle($shiftA->refresh(), 10_000); // balanced

    $shiftB = app(OpenShift::class)->handle($cashier, defaultWarehouse(), 0);
    // Customer brings it back during shift B; cashier hands 100 LYD out of the drawer.
    // A delivered sale is refunded with its goods (whole returns, 2026-10-10).
    app(ResolveOrderIssue::class)->handle($order, 'faulty', 10_000, RefundMethod::Cash, userId: $cashier->id);
    // Drawer B opened with a 100 float and paid the refund out of it, so it now holds 0.
    $shiftB->update(['opening_float_cents' => 10_000]);
    app(CloseShift::class)->handle($shiftB->refresh(), 0);

    // Net effect on cash: +100 sale, -100 refund = 0. (Float is not treasury money.)
    expect(cashBalance())->toBe(0);
});

it('M2: marking an order "refunded" through the status dropdown moves money or is refused', function (): void {
    $product = Product::factory()->create(['price_cents' => 10_000]);
    giveStock($product, 5);
    $user = customer();
    $cart = Cart::create(['user_id' => $user->id]);
    app(AddToCart::class)->handle($cart, $product, null, 1);
    $order = app(PlaceOrder::class)->handle($cart, new CheckoutData(
        customer: ['name' => 'x', 'email' => $user->email, 'phone' => '091'],
        shippingAddress: ['name' => 'x', 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        billingAddress: ['name' => 'x', 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        paymentMethod: PaymentMethod::CashOnDelivery,
    ), $user);
    app(UpdateOrderStatus::class)->handle($order, OrderStatus::Completed); // paid 100 cash

    $this->actingAs(staff('order_manager'))
        ->post(route('admin.orders.status', $order), ['status' => 'refunded']);

    $order->refresh();
    $refundRows = $order->refunds()->count();
    // Either it was refused (still Completed) or a real refund exists.
    expect($order->status === OrderStatus::Completed || $refundRows > 0)->toBeTrue();
});

it('M3: a payment cannot be banked against a cancelled order', function (): void {
    $product = Product::factory()->create(['price_cents' => 10_000]);
    giveStock($product, 5);
    $user = customer();
    $cart = Cart::create(['user_id' => $user->id]);
    app(AddToCart::class)->handle($cart, $product, null, 1);
    $order = app(PlaceOrder::class)->handle($cart, new CheckoutData(
        customer: ['name' => 'x', 'email' => $user->email, 'phone' => '091'],
        shippingAddress: ['name' => 'x', 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        billingAddress: ['name' => 'x', 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        paymentMethod: PaymentMethod::CashOnDelivery,
    ), $user);
    app(CancelOrder::class)->handle($order, 'cancelled');

    expect(fn () => app(ConfirmPayment::class)->handle($order->refresh(), 10_000))
        ->toThrow(ValidationException::class);
});

it('M4: an admin cannot deactivate the super admin through the customer toggle', function (): void {
    $super = staff('super_admin');

    $this->actingAs(staff('admin'))->post(route('admin.customers.toggle-active', $super));

    expect($super->refresh()->is_active)->toBeTrue();
});

it('M5: a deactivated staff account is locked out of the admin', function (): void {
    $manager = staff('order_manager');
    $manager->update(['is_active' => false]);

    expect($this->actingAs($manager)->get(route('admin.orders.index'))->status())->not->toBe(200);
});

it('M6: a logged-in API customer (Bearer token) gets the order on their own account', function (): void {
    $product = Product::factory()->create(['price_cents' => 10_000, 'status' => 'active', 'published_at' => now()]);
    giveStock($product, 5);
    $user = customer();
    $token = $user->createToken('phone')->plainTextToken;

    $cartToken = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertSuccessful()->json('cart_token');

    // The app hands a logged-in customer a guest cart token; replay it as a client would.
    $r = $this->withHeader('Authorization', "Bearer {$token}")->withHeader('X-Cart-Token', (string) $cartToken)->postJson('/api/v1/checkout', [
        'customer' => ['name' => 'x', 'email' => $user->email, 'phone' => '0910000000'],
        'shipping_address' => ['name' => 'x', 'phone' => '0910000000', 'country' => 'LY', 'city' => 'Tripoli', 'area' => 'Downtown'],
        'payment_method' => 'cod',
    ]);

    $order = Order::query()->sole(); // the checkout did go through
    expect($order->user_id)->toBe($user->id);
});
