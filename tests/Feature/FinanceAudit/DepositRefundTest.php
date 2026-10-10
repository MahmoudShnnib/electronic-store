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
        // Paid, the phone counted as sold; refunded, it is the shop's stock again (FIFO cost).
        'report.valuation.units' => 61,
        'report.valuation.cost_value_formatted' => finFmt(10 * 70_000 + 50 * 2_000 + 500),
        'report.valuation.retail_value_formatted' => finFmt(10 * 100_000 + 50 * 5_000 + 2_000),
        // Revenue is money received: the prepayment booked the sale, the refund takes it out.
        'report.summary.revenue_formatted' => finFmt(0),
        'report.summary.avg_order_formatted' => finFmt(0),
        'report.summary.estimated_cost_formatted' => finFmt(0),
        'report.summary.estimated_profit_formatted' => finFmt(0),
        'report.method.cod.revenue' => finFmt(0),
        'report.product.PHONE.units' => null,
        'report.product.PHONE.revenue' => null,
        'dash.revenue_today' => finFmt(0),
        'dash.revenue_month' => finFmt(0),
        'dash.best.Fin Phone.sold' => null,
        'dash.best.Fin Phone.revenue' => null,
    ]);

    // With the money back, the order can be cancelled: stock returns, nothing is owed.
    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/status", ['status' => 'cancelled'])->assertSessionHasNoErrors();
    $after = finSnapshot($this, $this->admin);
    expect($after['stock.PHONE@MAIN.quantity'])->toBe(10)
        ->and($after["adminorder.{$order->number}.outstanding"])->toBe(finFmt(0))
        ->and($after['treasury.cash'])->toBe(0)
        ->and(auditFailures())->toBe([]);
});
