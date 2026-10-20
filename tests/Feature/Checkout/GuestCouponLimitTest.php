<?php

use App\Actions\Orders\CancelOrder;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Product;
use App\Support\CustomerIdentity;

/**
 * One guest checkout over the API with a coupon: a fresh cart, the coupon
 * applied, then checkout as the given customer. Returns the checkout response.
 */
function guestCouponCheckout(Product $product, Coupon $coupon, array $customer, string $key)
{
    $token = test()->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])->json('cart_token');

    test()->withHeader('X-Cart-Token', $token)->postJson('/api/v1/cart/coupon', ['code' => $coupon->code])->assertOk();

    return test()->withHeader('X-Cart-Token', $token)->postJson('/api/v1/checkout', [
        'customer' => ['name' => 'Guest', ...$customer],
        'shipping_address' => ['country' => 'LY', 'city' => 'Tripoli', 'area' => 'Downtown'],
        'payment_method' => 'cod',
        'idempotency_key' => $key,
    ]);
}

beforeEach(function (): void {
    $this->product = Product::factory()->create(['price_cents' => 100_00]);
    giveStock($this->product, 20);
    $this->coupon = Coupon::factory()->create(['value_cents' => 10_00, 'per_customer_limit' => 1]);
});

it('stops a guest reusing a once-per-customer coupon with the same phone', function (): void {
    guestCouponCheckout($this->product, $this->coupon, ['phone' => '0912345678'], 'g-1')->assertCreated();

    // Same person, number typed differently and no email: still recognised.
    guestCouponCheckout($this->product, $this->coupon, ['phone' => '+218 91 234 5678'], 'g-2')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('coupon');

    expect(Order::query()->count())->toBe(1);
});

it('stops a guest reusing it with the same email and a new phone', function (): void {
    guestCouponCheckout($this->product, $this->coupon, ['phone' => '0912345678', 'email' => 'Fan@Example.com'], 'g-1')->assertCreated();

    guestCouponCheckout($this->product, $this->coupon, ['phone' => '0921111111', 'email' => 'fan@example.com'], 'g-2')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('coupon');
});

it('stops a customer reusing it by checking out signed out', function (): void {
    $token = customer()->createToken('phone')->plainTextToken;

    // Signed in: the coupon's one use.
    $this->withToken($token)->postJson('/api/v1/cart/items', ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
    $this->withToken($token)->postJson('/api/v1/cart/coupon', ['code' => $this->coupon->code])->assertOk();
    $this->withToken($token)->postJson('/api/v1/checkout', [
        'customer' => ['name' => 'Buyer', 'phone' => '0912345678'],
        'shipping_address' => ['country' => 'LY', 'city' => 'Tripoli', 'area' => 'Downtown'],
        'payment_method' => 'cod',
        'idempotency_key' => 'signed-in-1',
    ])->assertCreated();

    // Same person, signed out.
    $this->withoutToken();
    $this->app['auth']->forgetGuards();

    guestCouponCheckout($this->product, $this->coupon, ['phone' => '0912345678'], 'g-1')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('coupon');
});

it('lets a different guest use the same coupon', function (): void {
    guestCouponCheckout($this->product, $this->coupon, ['phone' => '0912345678'], 'g-1')->assertCreated();
    guestCouponCheckout($this->product, $this->coupon, ['phone' => '0927654321'], 'g-2')->assertCreated();

    expect(CouponRedemption::query()->pluck('customer_phone')->sort()->values()->all())->toBe(['912345678', '927654321']);
});

it('gives the use back when the order is cancelled', function (): void {
    guestCouponCheckout($this->product, $this->coupon, ['phone' => '0912345678'], 'g-1')->assertCreated();

    app(CancelOrder::class)->handle(Order::query()->sole(), 'Customer changed their mind');

    guestCouponCheckout($this->product, $this->coupon, ['phone' => '0912345678'], 'g-2')->assertCreated();
});

it('normalises phone numbers to their last nine digits', function (string $input, ?string $key): void {
    expect(CustomerIdentity::phoneKey($input))->toBe($key);
})->with([
    ['0912345678', '912345678'],
    ['+218 91-234 5678', '912345678'],
    ['00218912345678', '912345678'],
    ['٠٩١٢٣٤٥٦٧٨', '912345678'],
    ['12345', null],
]);
