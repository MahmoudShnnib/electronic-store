<?php

use App\Actions\Cart\AddToCart;
use App\Actions\Cart\ApplyCoupon;
use App\Actions\Cart\CalculateCartTotals;
use App\Actions\Checkout\PlaceOrder;
use App\DTOs\CheckoutData;
use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function cartFor(?User $user, Product $product, int $quantity = 1): Cart
{
    $cart = Cart::create($user !== null ? ['user_id' => $user->id] : ['session_token' => str()->random(48)]);

    app(AddToCart::class)->handle($cart, $product, null, $quantity);

    return $cart;
}

function checkoutData(array $overrides = []): CheckoutData
{
    $address = ['name' => 'Test Buyer', 'phone' => '0912345678', 'country' => 'LY', 'city' => 'Tripoli', 'area' => 'Hay Alandalus'];

    return new CheckoutData(...[
        'customer' => ['name' => 'Test Buyer', 'email' => 'buyer@test.dev', 'phone' => '0912345678'],
        'shippingAddress' => $address,
        'billingAddress' => $address,
        'paymentMethod' => PaymentMethod::CashOnDelivery,
        ...$overrides,
    ]);
}

it('recalculates all totals server-side', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_00]);
    giveStock($product, 5);

    $user = customer();
    $cart = cartFor($user, $product, 2);

    $totals = app(CalculateCartTotals::class)->handle($cart, $user->id);

    expect($totals->subtotalCents)->toBe(200_00)
        ->and($totals->totalCents)->toBe(200_00);
});

it('places an order, snapshots items, and reserves stock through the ledger', function (): void {
    $product = Product::factory()->create(['price_cents' => 150_00]);
    giveStock($product, 10);

    $user = customer();
    $cart = cartFor($user, $product, 3);

    $order = app(PlaceOrder::class)->handle($cart, checkoutData(), $user);

    $stock = StockLevel::query()->sole();

    expect($order->status)->toBe(OrderStatus::InProgress)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->product_name)->toBe($product->name)
        ->and($order->items->first()->sku)->toBe($product->sku)
        ->and($stock->quantity)->toBe(7)
        ->and($stock->reserved)->toBe(3)
        ->and(InventoryMovement::query()->where('type', MovementType::CustomerOrder)->count())->toBe(1)
        ->and($cart->items()->count())->toBe(0)
        ->and($order->statusHistories()->count())->toBe(1);
});

it('prevents overselling when two orders race for the same stock', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_00]);
    giveStock($product, 5);

    $alice = customer();
    $bob = customer();

    $aliceOrder = app(PlaceOrder::class)->handle(cartFor($alice, $product, 4), checkoutData(), $alice);
    expect($aliceOrder)->toBeInstanceOf(Order::class);

    // Only 1 left — Bob wants 3 and must be rejected, with nothing persisted.
    expect(fn () => app(PlaceOrder::class)->handle(cartFor($bob, $product, 3), checkoutData(), $bob))
        ->toThrow(ValidationException::class);

    expect(Order::query()->count())->toBe(1)
        ->and(StockLevel::query()->sole()->quantity)->toBe(1);
});

it('is idempotent per idempotency key', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_00]);
    giveStock($product, 10);

    $user = customer();
    $data = checkoutData(['idempotencyKey' => 'test-key-123']);

    $first = app(PlaceOrder::class)->handle(cartFor($user, $product, 1), $data, $user);
    $second = app(PlaceOrder::class)->handle(cartFor($user, $product, 1), $data, $user);

    expect($second->id)->toBe($first->id)
        ->and(Order::query()->count())->toBe(1);
});

it('applies a percentage coupon and records the redemption', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_00]);
    giveStock($product, 10);

    $coupon = Coupon::factory()->percentage(10)->create();

    $user = customer();
    $cart = cartFor($user, $product, 2);
    app(ApplyCoupon::class)->handle($cart, $coupon->code, $user->id);

    $order = app(PlaceOrder::class)->handle($cart->refresh(), checkoutData(), $user);

    expect($order->discount_cents)->toBe(20_00)
        ->and($order->coupon_code)->toBe($coupon->code)
        ->and($coupon->refresh()->used_count)->toBe(1)
        ->and($coupon->redemptions()->count())->toBe(1);
});

it('rejects expired coupons', function (): void {
    $coupon = Coupon::factory()->expired()->create();
    $product = Product::factory()->create();
    giveStock($product, 5);

    $user = customer();
    $cart = cartFor($user, $product, 1);

    app(ApplyCoupon::class)->handle($cart, $coupon->code, $user->id);
})->throws(ValidationException::class);

it('enforces per-customer coupon limits', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_00]);
    giveStock($product, 10);

    $coupon = Coupon::factory()->create(['per_customer_limit' => 1, 'value_cents' => 5_00]);
    $user = customer();

    $cart = cartFor($user, $product, 1);
    app(ApplyCoupon::class)->handle($cart, $coupon->code, $user->id);
    app(PlaceOrder::class)->handle($cart->refresh(), checkoutData(), $user);

    $cart2 = cartFor($user, $product, 1);

    app(ApplyCoupon::class)->handle($cart2, $coupon->code, $user->id);
})->throws(ValidationException::class);

it('accepts a checkout with the merged contact/delivery form and no email', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_00]);
    giveStock($product, 5);

    $this->actingAs(customer());
    $this->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    $this->post('/checkout', [
        'customer' => ['name' => 'Guest Buyer', 'email' => '', 'phone' => '0911111111'],
        'shipping_address' => ['country' => 'LY', 'city' => 'Benghazi', 'area' => 'Alsalmani'],
        'payment_method' => 'cod',
        'customer_note' => 'Call before delivery',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $order = Order::query()->sole();

    expect($order->shipping_address)->toEqualCanonicalizing([
        'name' => 'Guest Buyer',
        'phone' => '0911111111',
        'country' => 'LY',
        'city' => 'Benghazi',
        'area' => 'Alsalmani',
    ])
        ->and($order->billing_address)->toBe($order->shipping_address)
        ->and($order->customer_note)->toBe('Call before delivery')
        ->and($order->total_cents)->toBe(100_00);
});

it('rejects a delivery city outside the configured list', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_00]);
    giveStock($product, 5);

    $this->actingAs(customer());
    $this->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    $this->post('/checkout', [
        'customer' => ['name' => 'Guest Buyer', 'email' => '', 'phone' => '0911111111'],
        'shipping_address' => ['country' => 'LY', 'city' => 'Cairo', 'area' => ''],
        'payment_method' => 'cod',
    ])->assertSessionHasErrors(['shipping_address.city', 'shipping_address.area']);
});
