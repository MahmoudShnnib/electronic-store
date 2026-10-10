<?php

declare(strict_types=1);

/*
 * BD7 (owner decision 2026-10-10): cancelling an order no longer deletes its coupon use.
 * The record stays as history, marked released, and stops counting against the limits.
 */

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;

require_once __DIR__.'/../FinanceAudit/FinanceAuditSetup.php';

beforeEach(fn () => finSetUp($this));

it('keeps the coupon use when the order is cancelled, marked released, and frees the use', function (): void {
    $coupon = Coupon::factory()->percentage()->create(['code' => 'ONCE', 'percent' => 10, 'usage_limit' => 1, 'per_customer_limit' => 1]);
    $this->actingAs($this->buyer)->post('/cart/items', ['product_id' => $this->cable->id, 'quantity' => 2]);
    $this->actingAs($this->buyer)->post('/cart/coupon', ['code' => 'ONCE'])->assertSessionHasNoErrors();
    $this->actingAs($this->buyer)->post('/checkout', finCheckoutPayload($this->buyer))->assertSessionHasNoErrors();
    $order = Order::query()->latest('id')->firstOrFail();

    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/status", ['status' => 'cancelled'])->assertSessionHasNoErrors();

    $redemption = CouponRedemption::query()->where('order_id', $order->id)->first();
    expect($redemption)->not->toBeNull()
        ->and($redemption->released_at)->not->toBeNull()
        ->and($coupon->refresh()->used_count)->toBe(0)
        ->and(auditFailures())->toBe([]);

    // The same customer can use it again: a released use doesn't count against per-customer limits.
    $this->actingAs($this->buyer)->post('/cart/items', ['product_id' => $this->cable->id, 'quantity' => 1]);
    $this->actingAs($this->buyer)->post('/cart/coupon', ['code' => 'ONCE'])->assertSessionHasNoErrors();
    $this->actingAs($this->buyer)->post('/checkout', finCheckoutPayload($this->buyer))->assertSessionHasNoErrors();
    expect($coupon->refresh()->used_count)->toBe(1)
        ->and(CouponRedemption::query()->where('coupon_id', $coupon->id)->count())->toBe(2)
        ->and(auditFailures())->toBe([]);

    // The coupon history shows both, the cancelled one marked released.
    $row = collect($this->actingAs($this->admin)->get('/admin/coupons')->viewData('page')['props']['coupons']['data'])->firstWhere('code', 'ONCE');
    expect($row['redemptions_count'])->toBe(1)
        ->and(collect($row['redemptions'])->pluck('released')->sort()->values()->all())->toBe([false, true]);
});
