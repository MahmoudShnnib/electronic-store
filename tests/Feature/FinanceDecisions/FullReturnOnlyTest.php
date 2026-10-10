<?php

declare(strict_types=1);

/*
 * Owner decision 2026-10-10 (BD1/BD6): no part refunds. A completed order is refunded only
 * when everything comes back — every unit restocked and every dinar paid given back, in one
 * go. Money paid on an order that hasn't been delivered yet can be given back, also in full.
 */

require_once __DIR__.'/../FinanceAudit/FinanceAuditSetup.php';

beforeEach(function (): void {
    finSetUp($this);
    $this->order = finBuy($this, $this->buyer, [[$this->phone, 1], [$this->cable, 2]]);
    finComplete($this, $this->order);
    $this->issue = fn (array $data) => $this->actingAs($this->admin)->post("/admin/orders/{$this->order->id}/issue", [
        'reason' => 'Returned', 'method' => 'cash', ...$data,
    ]);
    $this->items = fn (int $phone, int $cable) => $this->order->items()->get()
        ->mapWithKeys(fn ($i) => [$i->id => $i->sku === 'PHONE' ? $phone : $cable])->all();
});

it('refuses a partial return or a partial refund and changes nothing', function (float $amount, bool $restock, ?array $units): void {
    $before = finSnapshot($this, $this->admin);

    ($this->issue)(['amount' => $amount, 'restock' => $restock, ...($units === null ? [] : ['items' => ($this->items)(...$units)])])
        ->assertSessionHasErrors();

    finExpectChanges($before, finSnapshot($this, $this->admin), []);
})->with([
    'one item of several back, refunded in part' => [50, true, [0, 1]],
    'everything back, refunded in part' => [500, true, [1, 2]],
    'one item back, refunded in full' => [1100, true, [1, 0]],
    'money back, goods kept' => [1100, false, null],
]);

it('refuses a refund on a completed order from the plain refund form: goods must come back', function (): void {
    $before = finSnapshot($this, $this->admin);

    $this->actingAs($this->admin)->post("/admin/orders/{$this->order->id}/refunds", ['amount' => 1100, 'method' => 'cash'])
        ->assertSessionHasErrors();

    finExpectChanges($before, finSnapshot($this, $this->admin), []);
});

it('takes a full return: every unit back, every dinar back, order refunded', function (): void {
    ($this->issue)(['amount' => 1100, 'restock' => true, 'items' => ($this->items)(1, 2)])->assertSessionHasNoErrors();

    $order = $this->order->refresh();
    expect($order->status->value)->toBe('refunded')
        ->and($order->payment_status->value)->toBe('refunded')
        ->and((int) $this->phone->stockLevels()->sum('quantity'))->toBe(10)
        ->and((int) $this->cable->stockLevels()->sum('quantity'))->toBe(50)
        ->and(auditFailures())->toBe([]);
    // Nothing left to give back.
    ($this->issue)(['amount' => 1, 'restock' => true, 'items' => ($this->items)(1, 2)])->assertSessionHasErrors();
});

it('a reason with no return just logs the problem', function (): void {
    $before = finSnapshot($this, $this->admin);

    ($this->issue)(['amount' => 0, 'restock' => false])->assertSessionHasNoErrors();

    finExpectChanges($before, finSnapshot($this, $this->admin), []);
    expect($this->order->statusHistories()->where('comment', 'Returned')->exists())->toBeTrue();
});

it('gives back a prepaid, undelivered order only in full', function (): void {
    $open = finBuy($this, customer(), [[$this->phone, 1]]);
    $this->actingAs($this->admin)->post("/admin/orders/{$open->id}/payments", ['amount' => 1000])->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->post("/admin/orders/{$open->id}/refunds", ['amount' => 400, 'method' => 'cash'])->assertSessionHasErrors('amount');
    $this->actingAs($this->admin)->post("/admin/orders/{$open->id}/refunds", ['amount' => 1000, 'method' => 'cash'])->assertSessionHasNoErrors();

    expect($open->refresh()->payment_status->value)->toBe('refunded')->and(auditFailures())->toBe([]);
});
