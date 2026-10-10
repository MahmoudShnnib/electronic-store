<?php

declare(strict_types=1);

/*
 * Finance audit H12 (prepaid variant): money taken up front and given back on an order
 * that is still open — what is owed afterwards, and that it then cancels cleanly.
 */

require_once __DIR__.'/FinanceAuditSetup.php';

beforeEach(fn () => finSetUp($this));

it('H12: a prepaid order given back before delivery owes its full total again, then cancels cleanly', function (): void {
    $order = finBuy($this, $this->buyer, [[$this->phone, 1]]);
    // Customers pay in full (owner decision 2026-10-10): a deposit is refused outright.
    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/payments", ['amount' => 300])->assertSessionHasErrors('amount');
    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/payments", ['amount' => 1000])->assertSessionHasNoErrors();
    $before = finSnapshot($this, $this->admin);

    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/refunds", ['amount' => 1000, 'method' => 'cash', 'reason' => 'Customer changed mind'])
        ->assertSessionHasNoErrors();

    finExpectChanges($before, finSnapshot($this, $this->admin), [
        "order.{$order->number}.payment_status" => 'refunded',
        "adminorder.{$order->number}.refunded" => finFmt(100_000),
        // What completing the order would now collect (UpdateOrderStatus::settleOutstanding).
        "adminorder.{$order->number}.outstanding" => finFmt(100_000),
        "report.invoice.{$order->number}.outstanding" => finFmt(100_000),
        'report.invoices.outstanding_formatted' => finFmt(100_000),
        'money.refunds.rows' => 1,
        'money.refunds.sum' => 100_000,
        'treasury.cash' => 0,
        'treasury.rows' => 2,
        'report.summary.refunded_formatted' => finFmt(100_000),
    ]);

    // With the money back, the order can be cancelled: stock returns, nothing is owed.
    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/status", ['status' => 'cancelled'])->assertSessionHasNoErrors();
    $after = finSnapshot($this, $this->admin);
    expect($after['stock.PHONE@MAIN.quantity'])->toBe(10)
        ->and($after["adminorder.{$order->number}.outstanding"])->toBe(finFmt(0))
        ->and($after['treasury.cash'])->toBe(0)
        ->and(auditFailures())->toBe([]);
});
