<?php

declare(strict_types=1);

use App\Actions\Cart\ApplyCoupon;
use App\Actions\Cart\CalculateCartTotals;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

function cartWith(array $linesSpec): Cart
{
    $cart = (new Cart)->forceFill(['user_id' => customer()->id]);
    $cart->save();

    foreach ($linesSpec as [$product, $quantity]) {
        $cart->items()->create(['product_id' => $product->id, 'quantity' => $quantity]);
    }

    return $cart;
}

// -- ×2: scoped coupon only discounts the eligible lines ---------------------

it('does not discount lines a product-limited coupon excludes', function (): void {
    $inScope = Product::factory()->create(['price_cents' => 10_000]);
    $outOfScope = Product::factory()->create(['price_cents' => 10_000]);

    $coupon = Coupon::factory()->create([
        'type' => 'fixed', 'value_cents' => 5_000,
        'applies_to' => 'products', 'applies_to_ids' => [$inScope->id],
        'is_active' => true,
    ]);

    $cart = cartWith([[$inScope, 1], [$outOfScope, 1]]);
    $cart->update(['coupon_id' => $coupon->id]);

    $totals = app(CalculateCartTotals::class)->handle($cart->refresh());

    $excludedLine = collect($totals->lines)->firstWhere(fn ($l) => $l->product->id === $outOfScope->id);
    $discountedLine = collect($totals->lines)->firstWhere(fn ($l) => $l->product->id === $inScope->id);

    expect($totals->discountCents)->toBe(5_000)
        // The out-of-scope line keeps its full price, untouched by the coupon.
        ->and($excludedLine->lineDiscountCents)->toBe(0)
        ->and($excludedLine->lineTotalCents)->toBe(10_000)
        ->and($discountedLine->lineDiscountCents)->toBe(5_000);
});

// -- B4: minimum order enforced when the code is applied ---------------------

it('rejects a coupon below its minimum order at apply time', function (): void {
    $product = Product::factory()->create(['price_cents' => 2_000]);
    $coupon = Coupon::factory()->create([
        'type' => 'fixed', 'value_cents' => 500,
        'min_order_cents' => 10_000, 'is_active' => true,
    ]);

    $cart = cartWith([[$product, 1]]);

    app(ApplyCoupon::class)->handle($cart, $coupon->code);
})->throws(ValidationException::class);

// -- B4: a deleted coupon's code can be used again ---------------------------

it('allows recreating a coupon with a deleted coupon\'s code', function (): void {
    $old = Coupon::factory()->create(['code' => 'SUMMER25']);
    $old->delete();

    $admin = staff('super_admin');

    $this->actingAs($admin)->post(route('admin.coupons.store'), [
        'code' => 'SUMMER25',
        'type' => 'percentage',
        'percent' => 25,
        'applies_to' => 'all',
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect(Coupon::query()->where('code', 'SUMMER25')->count())->toBe(1);
});
