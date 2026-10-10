<?php

declare(strict_types=1);

/*
 * Finance audit F1, F2, F3, F5, F6: things going wrong. Every refused or failed action must
 * leave every money/stock place exactly as it was.
 */

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TreasuryTransaction;

require_once __DIR__.'/FinanceAuditSetup.php';

beforeEach(fn () => finSetUp($this));

it('F1: a payment that never arrives — the order is flagged, and cancelling it releases stock and books no revenue', function (): void {
    $order = finBuy($this, $this->buyer, [[$this->phone, 1]]);

    // A payment that "fails" here is one that's refused: zero or negative amounts change nothing.
    $before = finSnapshot($this, $this->admin);
    foreach ([0, -5, 0.001] as $amount) {
        $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/payments", ['amount' => $amount])->assertSessionHasErrors('amount');
    }
    finExpectChanges($before, finSnapshot($this, $this->admin), []);

    // No money after 3 days: the dashboard flags it; staff cancel it.
    $this->travel(4)->days();
    $stale = finSnapshot($this, $this->admin);
    expect($stale['dash.stale_cod'])->toBe(1);

    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/status", ['status' => 'cancelled', 'comment' => 'Never paid'])->assertSessionHasNoErrors();
    $after = finSnapshot($this, $this->admin);

    expect($after['stock.PHONE@MAIN.quantity'])->toBe(10)
        ->and($after['stock.PHONE@MAIN.reserved'])->toBe(0)
        ->and($after['dash.stale_cod'])->toBe(0)
        ->and($after['report.summary.revenue_formatted'])->toBe(finFmt(0))
        ->and($after['dash.revenue_month'])->toBe(finFmt(0))
        ->and($after['money.payments.rows'])->toBe(0)
        ->and($after['treasury.rows'])->toBe(0)
        ->and(auditFailures())->toBe([]);
});

it('F2: a refund the treasury cannot pay changes nothing — not the status, not the stock', function (): void {
    $order = finBuy($this, $this->buyer, [[$this->phone, 1]]);
    finComplete($this, $order);
    // The day's cash went to the bank by hand: the drawer no longer holds the 1,000.
    $this->actingAs($this->admin)->post('/admin/treasury', ['direction' => 'out', 'account' => 'cash', 'amount' => 900, 'note' => 'Deposited at bank'])
        ->assertSessionHasNoErrors();
    $before = finSnapshot($this, $this->admin);

    // Return form (refund + restock) and plain refund form both fail at the money step.
    $item = $order->items()->firstOrFail();
    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/issue", [
        'reason' => 'Faulty', 'amount' => 1000, 'method' => 'cash', 'restock' => true, 'items' => [$item->id => 1],
    ])->assertSessionHasErrors('amount');
    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/refunds", ['amount' => 1000, 'method' => 'cash'])->assertSessionHasErrors('amount');

    finExpectChanges($before, finSnapshot($this, $this->admin), []);
    expect($order->refresh()->status->value)->toBe('completed')
        ->and($order->statusHistories()->count())->toBe(2); // placed, completed — no half-logged return
});

it('F3: a crash halfway through checkout saves nothing anywhere and keeps the cart', function (): void {
    $this->actingAs($this->buyer)->post('/cart/items', ['product_id' => $this->phone->id, 'quantity' => 2]);
    $this->actingAs($this->buyer)->post('/cart/items', ['product_id' => $this->cable->id, 'quantity' => 3]);
    $before = finSnapshot($this, $this->admin);

    // The second order line blows up after the first line's stock was already taken.
    $lines = 0;
    OrderItem::creating(function () use (&$lines): void {
        if (++$lines === 2) {
            throw new RuntimeException('Simulated database failure');
        }
    });
    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($this->buyer)->post('/checkout', finCheckoutPayload($this->buyer)))->toThrow(RuntimeException::class);

    finExpectChanges($before, finSnapshot($this, $this->admin), []);
    expect(Order::count())->toBe(0)
        ->and(Cart::query()->where('user_id', $this->buyer->id)->first()->items()->count())->toBe(2);
});

it('F3: a crash while banking a payment leaves no payment and no treasury line', function (): void {
    $order = finBuy($this, $this->buyer, [[$this->phone, 1]]);
    $before = finSnapshot($this, $this->admin);

    TreasuryTransaction::creating(fn () => throw new RuntimeException('Simulated database failure'));
    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/status", ['status' => 'completed']))->toThrow(RuntimeException::class);

    finExpectChanges($before, finSnapshot($this, $this->admin), []);
});

it('F5: a double-clicked checkout places one order and takes stock once', function (): void {
    $this->actingAs($this->buyer)->post('/cart/items', ['product_id' => $this->phone->id, 'quantity' => 1]);
    $payload = finCheckoutPayload($this->buyer, ['idempotency_key' => 'click-1']);
    $this->actingAs($this->buyer)->post('/checkout', $payload)->assertSessionHasNoErrors();
    $once = finSnapshot($this, $this->admin);

    $this->actingAs($this->buyer)->post('/checkout', $payload); // same key: hands back the same order
    $this->actingAs($this->buyer)->post('/checkout', finCheckoutPayload($this->buyer))->assertSessionHasErrors('cart'); // no key: cart is already empty

    finExpectChanges($once, finSnapshot($this, $this->admin), []);
    expect(Order::count())->toBe(1);
});

it('F5: double-clicking "record payment", "complete" or the return form charges and moves stock once', function (): void {
    $order = finBuy($this, $this->buyer, [[$this->phone, 1]]);
    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/payments", ['amount' => 1000])->assertSessionHasNoErrors();
    $paid = finSnapshot($this, $this->admin);
    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/payments", ['amount' => 1000])->assertSessionHasErrors('amount');
    finExpectChanges($paid, finSnapshot($this, $this->admin), []);

    finComplete($this, $order);
    $done = finSnapshot($this, $this->admin);
    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/status", ['status' => 'completed'])->assertSessionHasErrors('status');
    finExpectChanges($done, finSnapshot($this, $this->admin), []);

    finReturn($this, $order, 100_000, ['PHONE' => 1]);
    $returned = finSnapshot($this, $this->admin);
    $item = $order->items()->firstOrFail();
    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/issue", [
        'reason' => 'Customer returned goods', 'amount' => 1000, 'method' => 'cash', 'restock' => true, 'items' => [$item->id => 1],
    ])->assertSessionHasErrors();
    finExpectChanges($returned, finSnapshot($this, $this->admin), []);
});

it('F6: refunds beyond what was paid, twice, on unpaid or cancelled orders are refused cleanly', function (): void {
    $paid = finBuy($this, $this->buyer, [[$this->phone, 1]]);
    finComplete($this, $paid);
    $unpaid = finBuy($this, customer(), [[$this->cable, 1]]);
    $cancelled = finBuy($this, customer(), [[$this->cable, 1]]);
    $this->actingAs($this->admin)->post("/admin/orders/{$cancelled->id}/status", ['status' => 'cancelled'])->assertSessionHasNoErrors();

    $before = finSnapshot($this, $this->admin);
    $refund = fn (Order $o, $amount) => $this->actingAs($this->admin)->post("/admin/orders/{$o->id}/refunds", ['amount' => $amount, 'method' => 'cash']);
    $refund($paid, 1000.01)->assertSessionHasErrors('amount');
    $refund($paid, -10)->assertSessionHasErrors('amount');
    $refund($paid, 0)->assertSessionHasErrors('amount');
    $refund($unpaid, 10)->assertSessionHasErrors('amount');
    $refund($cancelled, 10)->assertSessionHasErrors('amount');
    $this->actingAs($this->admin)->post("/admin/orders/{$paid->id}/refunds", ['amount' => 10, 'method' => 'store_credit'])->assertSessionHasErrors('method');
    finExpectChanges($before, finSnapshot($this, $this->admin), []);

    // Refund twice: a delivered order comes back whole once (return form); nothing is left after.
    $refund($paid, 1000)->assertSessionHasErrors('amount'); // refund form: goods must come back
    finReturn($this, $paid, 100_000, ['PHONE' => 1]);
    $afterFirst = finSnapshot($this, $this->admin);
    $refund($paid, 1000)->assertSessionHasErrors('amount');
    $this->actingAs($this->admin)->post("/admin/orders/{$paid->id}/issue", ['reason' => 'again', 'amount' => 1000, 'method' => 'cash', 'restock' => true])
        ->assertSessionHasErrors();
    finExpectChanges($afterFirst, finSnapshot($this, $this->admin), []);
    expect($paid->refresh()->payment_status->value)->toBe('refunded')
        ->and(auditFailures())->toBe([]);
});
