<?php

declare(strict_types=1);

use App\Actions\Orders\CancelOrder;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\User;
use App\Support\Dates;
use App\Support\ReferenceNumber;
use Illuminate\Support\Carbon;

function orderFor(User $user, OrderStatus $status, ?string $couponCode = null): Order
{
    $address = ['name' => $user->name, 'phone' => '0910000000', 'country' => 'LY', 'city' => 'Tripoli', 'area' => 'Center'];

    return Order::create([
        'number' => ReferenceNumber::generate('ORD'),
        'user_id' => $user->id,
        'status' => $status,
        'payment_status' => PaymentStatus::Unpaid,
        'payment_method' => PaymentMethod::CashOnDelivery,
        'customer' => ['name' => $user->name, 'email' => $user->email],
        'shipping_address' => $address,
        'billing_address' => $address,
        'subtotal_cents' => 10_000,
        'total_cents' => 10_000,
        'discount_cents' => $couponCode !== null ? 1_000 : 0,
        'coupon_code' => $couponCode,
        'currency' => 'LYD',
        'placed_via' => 'web',
    ]);
}

// -- Web sign-up must tag the account as a customer --------------------------

it('assigns the customer role on web registration', function (): void {
    $this->post('/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'jane@example.com')->sole();

    expect($user->hasRole('customer'))->toBeTrue();
});

// -- Cancelling an order must release its coupon redemption ------------------

it('releases a coupon redemption and its usage count when an order is cancelled', function (): void {
    $user = customer();
    $coupon = Coupon::factory()->create(['used_count' => 1]);
    $order = orderFor($user, OrderStatus::Pending, $coupon->code);

    CouponRedemption::create([
        'coupon_id' => $coupon->id,
        'user_id' => $user->id,
        'order_id' => $order->id,
        'amount_cents' => 1_000,
    ]);

    app(CancelOrder::class)->handle($order);

    expect(CouponRedemption::where('order_id', $order->id)->exists())->toBeFalse()
        ->and($coupon->refresh()->used_count)->toBe(0);
});

it('never decrements a coupon usage count past zero', function (): void {
    $user = customer();
    $coupon = Coupon::factory()->create(['used_count' => 0]);
    $order = orderFor($user, OrderStatus::Pending, $coupon->code);

    CouponRedemption::create([
        'coupon_id' => $coupon->id,
        'user_id' => $user->id,
        'order_id' => $order->id,
        'amount_cents' => 1_000,
    ]);

    app(CancelOrder::class)->handle($order);

    expect($coupon->refresh()->used_count)->toBe(0);
});

// -- A fully-returned order must stop reporting itself as returnable ---------

it('reports an order as not returnable once every unit has been returned', function (): void {
    $user = customer();
    $order = orderFor($user, OrderStatus::Delivered);
    $item = $order->items()->create([
        'product_id' => Product::factory()->create()->id,
        'warehouse_id' => defaultWarehouse()->id,
        'product_name' => 'Thing', 'sku' => 'T-1',
        'unit_price_cents' => 1_000, 'quantity' => 2, 'line_total_cents' => 2_000,
    ]);

    expect($order->fresh()->isReturnable())->toBeTrue();

    $return = ProductReturn::create([
        'rma_number' => ReferenceNumber::generate('RMA'),
        'order_id' => $order->id, 'user_id' => $user->id,
        'status' => 'requested', 'reason' => 'defective',
    ]);
    $return->items()->create(['order_item_id' => $item->id, 'quantity' => 2]);

    expect($order->fresh()->isReturnable())->toBeFalse();
});

it('keeps a partially-returned order returnable', function (): void {
    $user = customer();
    $order = orderFor($user, OrderStatus::Delivered);
    $item = $order->items()->create([
        'product_id' => Product::factory()->create()->id,
        'warehouse_id' => defaultWarehouse()->id,
        'product_name' => 'Thing', 'sku' => 'T-1',
        'unit_price_cents' => 1_000, 'quantity' => 2, 'line_total_cents' => 2_000,
    ]);

    $return = ProductReturn::create([
        'rma_number' => ReferenceNumber::generate('RMA'),
        'order_id' => $order->id, 'user_id' => $user->id,
        'status' => 'requested', 'reason' => 'defective',
    ]);
    $return->items()->create(['order_item_id' => $item->id, 'quantity' => 1]);

    expect($order->fresh()->isReturnable())->toBeTrue();
});

// -- A return reason must be one of the known values --------------------------

it('rejects a return request with an invalid reason', function (): void {
    $user = customer();
    $order = orderFor($user, OrderStatus::Delivered);
    $item = $order->items()->create([
        'product_id' => Product::factory()->create()->id,
        'warehouse_id' => defaultWarehouse()->id,
        'product_name' => 'Thing', 'sku' => 'T-1',
        'unit_price_cents' => 1_000, 'quantity' => 1, 'line_total_cents' => 1_000,
    ]);

    $this->actingAs($user)->post(route('account.returns.store', $order), [
        'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        'reason' => 'because_i_said_so',
    ])->assertSessionHasErrors('reason');
});

// -- Dates translate under the active locale ----------------------------------

it('translates day/month names and relative time under the Arabic locale', function (): void {
    app()->setLocale('en');
    $date = Carbon::parse('2026-08-23 14:58:00');

    expect(Dates::dayDateTime($date))->toBe('Sun, Aug 23, 2026 2:58 PM');

    app()->setLocale('ar');

    expect(Dates::dayDateTime($date))->not->toBe('Sun, Aug 23, 2026 2:58 PM')
        ->and(Dates::dayDateTime($date))->toContain('أغسطس')
        ->and(Dates::relative(now()->subHours(2)))->toContain('ساع');

    app()->setLocale('en');
});
