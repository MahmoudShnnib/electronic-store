<?php

declare(strict_types=1);

/*
 * Owner decision 2026-10-10: customers always pay the whole order at once. A recorded
 * payment must be exactly what is still owed — no deposits, no instalments.
 */

require_once __DIR__.'/../FinanceAudit/FinanceAuditSetup.php';

beforeEach(fn () => finSetUp($this));

it('refuses a part payment and changes nothing', function (float $amount): void {
    $order = finBuy($this, $this->buyer, [[$this->phone, 1]]);
    $before = finSnapshot($this, $this->admin);

    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/payments", ['amount' => $amount])->assertSessionHasErrors('amount');

    finExpectChanges($before, finSnapshot($this, $this->admin), []);
})->with(['deposit' => 300, 'one dirham short' => 999.99, 'over' => 1000.01]);

it('records the full amount, banks it, and marks the order paid', function (): void {
    $order = finBuy($this, $this->buyer, [[$this->phone, 1]]);

    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/payments", ['amount' => 1000, 'reference' => 'TRX-9'])->assertSessionHasNoErrors();

    expect($order->refresh()->payment_status->value)->toBe('paid')
        ->and((int) $order->payments()->sum('amount_cents'))->toBe(100_000)
        ->and(auditFailures())->toBe([]);
    // Nothing is owed any more: a second payment is refused.
    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/payments", ['amount' => 1000])->assertSessionHasErrors('amount');
});

it('tells the order page how much the one payment must be', function (): void {
    $order = finBuy($this, $this->buyer, [[$this->phone, 2]]);

    $props = $this->actingAs($this->admin)->get("/admin/orders/{$order->id}")->viewData('page')['props']['order'];

    expect($props['outstanding_cents'])->toBe(200_000);
});
