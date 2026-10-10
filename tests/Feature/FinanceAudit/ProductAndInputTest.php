<?php

declare(strict_types=1);

/*
 * Finance audit F8 (deleting a product that has orders) and F9 (invalid inputs anywhere
 * money or stock is typed in).
 */

use App\Actions\Catalog\SaveProduct;
use App\Models\Cart;

require_once __DIR__.'/FinanceAuditSetup.php';

beforeEach(fn () => finSetUp($this));

it('F8: deleting a product with past orders keeps orders, money and reports intact', function (): void {
    $done = finBuy($this, $this->buyer, [[$this->phone, 1]]);
    finComplete($this, $done);
    // BD8: a product can only be deleted once no unit is left, so the rest is written off first.
    finAdjust($this, $this->phone, 9, 'lost', 'Discontinued, units unaccounted for')->assertSessionHasNoErrors();
    $before = finSnapshot($this, $this->admin);

    $this->actingAs($this->admin)->delete("/admin/products/{$this->phone->id}")->assertSessionHasNoErrors();

    finExpectChanges($before, finSnapshot($this, $this->admin), [
        'product.PHONE.deleted' => true,
        'dash.products_total' => 2,
        'dash.products_active' => 2,
        'dash.out_of_stock' => 0, // a deleted product is no longer "on sale but out of stock"
    ]);

    // The finished order of the deleted product can still be returned and its pages render.
    finReturn($this, $done, 100_000, ['PHONE' => 1]);
    $after = finSnapshot($this, $this->admin);
    expect($after['stock.PHONE@MAIN.quantity'])->toBe(1)
        ->and($after['report.summary.revenue_formatted'])->toBe(finFmt(0))
        ->and($after['treasury.cash'])->toBe(0)
        ->and(auditFailures())->toBe([]);
    $this->actingAs($this->admin)->get("/admin/orders/{$done->id}")->assertOk();
    $this->actingAs($this->admin)->get('/admin/reports/sales')->assertOk();
});

it('F9: nonsense quantities are refused everywhere and change nothing', function (): void {
    $order = finBuy($this, $this->buyer, [[$this->cable, 1]]);
    $this->actingAs($this->buyer)->post('/cart/items', ['product_id' => $this->cable->id, 'quantity' => 1]);
    $item = Cart::query()->where('user_id', $this->buyer->id)->firstOrFail()->items()->firstOrFail();
    $this->actingAs($this->admin)->post('/admin/pos/shifts', ['warehouse_id' => $this->main->id, 'opening_float' => 0])->assertSessionHasNoErrors();
    $before = finSnapshot($this, $this->admin);

    foreach ([-1, 0, 1.5, 'abc', 999_999_999_999, ''] as $bad) {
        $this->actingAs($this->buyer)->post('/cart/items', ['product_id' => $this->cable->id, 'quantity' => $bad])->assertSessionHasErrors('quantity');
        $this->actingAs($this->admin)->post('/admin/orders', [
            ...finCheckoutPayload($this->buyer), 'items' => [['product_id' => $this->cable->id, 'quantity' => $bad]],
        ])->assertSessionHasErrors();
        $this->actingAs($this->admin)->post('/admin/pos/sales', [
            'items' => [['product_id' => $this->cable->id, 'quantity' => $bad]], 'payment_method' => 'cash',
        ])->assertSessionHasErrors();
        $this->actingAs($this->admin)->post('/admin/inventory/adjust', [
            'warehouse_id' => $this->main->id, 'product_id' => $this->cable->id, 'quantity_change' => $bad === -1 ? 1.5 : $bad,
            'type' => 'manual_increase', 'reason' => 'x',
        ])->assertSessionHasErrors('quantity_change');
    }
    foreach ([-1, 1.5, 'abc', 999_999_999_999] as $bad) {
        $this->actingAs($this->buyer)->patch("/cart/items/{$item->id}", ['quantity' => $bad])->assertSessionHasErrors('quantity');
    }
    foreach ([0, -1, 0.001, 'abc', 99_999_999_999] as $bad) {
        $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/payments", ['amount' => $bad])->assertSessionHasErrors('amount');
    }

    finExpectChanges($before, finSnapshot($this, $this->admin), []);
});

it('F9: a zero price and the largest allowed price flow through orders, money and reports exactly', function (): void {
    $free = finBuy($this, $this->buyer, [[tap($this->mug)->update(['price_cents' => 0]), 1]]);
    finComplete($this, $free);
    expect($free->refresh()->total_cents)->toBe(0)
        ->and($free->payment_status->value)->toBe('paid')
        ->and((int) $free->payments()->count())->toBe(0);

    // ProductRequest caps prices at 99,999,999 LYD.
    app(SaveProduct::class)->handle($this->phone, ['price_cents' => 9_999_999_900], userId: $this->admin->id);
    $big = finBuy($this, $this->buyer, [[$this->phone->refresh(), 10]]);
    finComplete($this, $big);
    $after = finSnapshot($this, $this->admin);

    expect($big->refresh()->total_cents)->toBe(99_999_999_000)
        ->and($after['treasury.cash'])->toBe(99_999_999_000)
        ->and($after['report.summary.revenue_formatted'])->toBe(finFmt(99_999_999_000))
        ->and($after['dash.revenue_today'])->toBe(finFmt(99_999_999_000))
        ->and(auditFailures())->toBe([]);
});
