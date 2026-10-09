<?php

declare(strict_types=1);

/*
 * Finance audit H12 (deposit variant): money taken up front and given back on an order
 * that is still open — what is owed, and what revenue is booked, before and after it completes.
 */

require_once __DIR__.'/FinanceAuditSetup.php';

beforeEach(fn () => finSetUp($this));

it('H12: after a deposit is refunded on an open order, every screen shows the full total owed again', function (): void {
    $order = finBuy($this, $this->buyer, [[$this->phone, 1]]);
    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/payments", ['amount' => 300])->assertSessionHasNoErrors();
    $before = finSnapshot($this, $this->admin);

    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/refunds", ['amount' => 300, 'method' => 'cash', 'reason' => 'Deposit returned'])
        ->assertSessionHasNoErrors();

    finExpectChanges($before, finSnapshot($this, $this->admin), [
        "order.{$order->number}.payment_status" => 'refunded',
        "adminorder.{$order->number}.refunded" => finFmt(30_000),
        // What completing the order will now collect (UpdateOrderStatus::settleOutstanding).
        "adminorder.{$order->number}.outstanding" => finFmt(100_000),
        "report.invoice.{$order->number}.outstanding" => finFmt(100_000),
        'report.invoices.outstanding_formatted' => finFmt(100_000),
        'money.refunds.rows' => 1,
        'money.refunds.sum' => 30_000,
        'treasury.cash' => 0,
        'treasury.rows' => 2,
        // The sale itself is unchanged: still 1,000 booked, still 1,000 to collect.
        'report.summary.refunded_formatted' => finFmt(30_000),
    ]);

    // Completing collects exactly that, and the order is booked at its full 1,000.
    $before = finSnapshot($this, $this->admin);
    finComplete($this, $order);
    $after = finSnapshot($this, $this->admin);
    expect((int) $order->refresh()->payments()->sum('amount_cents'))->toBe(130_000)
        ->and($after["order.{$order->number}.payment_status"])->toBe('paid')
        ->and($after['report.summary.revenue_formatted'])->toBe(finFmt(100_000))
        ->and($after['report.status.completed.revenue'])->toBe(finFmt(100_000))
        ->and($after['dash.revenue_today'])->toBe(finFmt(100_000))
        ->and($after['treasury.cash'])->toBe(100_000)
        ->and($after["adminorder.{$order->number}.outstanding"])->toBe(finFmt(0))
        ->and($before['report.summary.revenue_formatted'])->toBe(finFmt(100_000));

    // Known conflict, flagged in audit/MORNING_REVIEW.md: rules M3 and M4 as written say a
    // completed order never carries refunds and was paid exactly its total. A deposit refunded
    // before completion breaks both wordings although the money is right. Every other rule holds.
    expect(array_keys(auditFailures()))->toBe(['M3', 'M4']);
});
