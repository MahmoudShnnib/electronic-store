<?php

declare(strict_types=1);

/*
 * Finance audit H9, H11, H12: price changes after a sale, cancelling unpaid and paid orders.
 * (H10 "edit an order" has no feature to exercise — see audit/TEST_LOG.md.)
 */

use App\Actions\Catalog\SaveProduct;
use App\Models\PriceHistory;

require_once __DIR__.'/FinanceAuditSetup.php';

beforeEach(fn () => finSetUp($this));

it('H9: changing a price leaves past orders, revenue and profit exactly as sold', function (): void {
    $done = finBuy($this, $this->buyer, [[$this->phone, 1]]);
    finComplete($this, $done);
    $open = finBuy($this, customer(), [[$this->phone, 1]]);
    $before = finSnapshot($this, $this->admin);

    app(SaveProduct::class)->handle($this->phone, ['price_cents' => 120_000, 'cost_price_cents' => 80_000], userId: $this->admin->id);

    finExpectChanges($before, finSnapshot($this, $this->admin), [
        'product.PHONE.price' => 120_000,
        'product.PHONE.cost' => 80_000,
        // 9 phones on hand (8 on the shelf + 1 reserved) are valued at today's prices; sales keep their snapshots.
        'report.valuation.cost_value_formatted' => finFmt(9 * 80_000 + 50 * 2_000 + 500),
        'report.valuation.retail_value_formatted' => finFmt(9 * 120_000 + 50 * 5_000 + 2_000),
    ]);
    expect(PriceHistory::query()->where('product_id', $this->phone->id)->count())->toBe(2); // regular + cost

    // The open order is still collected at the price it was sold at.
    finComplete($this, $open);
    expect((int) $open->refresh()->payments()->sum('amount_cents'))->toBe(100_000)
        ->and(auditFailures())->toBe([]);
});

it('H11: cancelling an unpaid order puts the stock back and takes it out of revenue', function (): void {
    $order = finBuy($this, $this->buyer, [[$this->phone, 2]]);
    $before = finSnapshot($this, $this->admin);

    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/status", ['status' => 'cancelled', 'comment' => 'Customer changed mind'])
        ->assertSessionHasNoErrors();

    finExpectChanges($before, finSnapshot($this, $this->admin), [
        'stock.PHONE@MAIN.quantity' => 10,
        'stock.PHONE@MAIN.reserved' => 0,
        'store.PHONE.available' => 10,
        'ledger.order_cancellation.rows' => 1,
        'ledger.order_cancellation.units' => 2,
        "order.{$order->number}.status" => 'cancelled',
        "adminorder.{$order->number}.outstanding" => finFmt(0),
        "report.invoice.{$order->number}.outstanding" => finFmt(0),
        'report.summary.orders' => 0,
        'report.summary.revenue_formatted' => finFmt(0),
        'report.summary.avg_order_formatted' => finFmt(0),
        'report.summary.estimated_cost_formatted' => finFmt(0),
        'report.summary.estimated_profit_formatted' => finFmt(0),
        'report.invoices.invoices' => 0,
        'report.invoices.invoiced_formatted' => finFmt(0),
        'report.invoices.outstanding_formatted' => finFmt(0),
        'report.product.PHONE.units' => null,
        'report.product.PHONE.revenue' => null,
        'report.status.in_progress.orders' => null,
        'report.status.in_progress.revenue' => null,
        'report.status.cancelled.orders' => 1,
        'report.status.cancelled.revenue' => finFmt(200_000),
        'report.method.cod.orders' => null,
        'report.method.cod.revenue' => null,
        'dash.revenue_today' => finFmt(0),
        'dash.revenue_month' => finFmt(0),
        'dash.orders_in_progress' => 0,
        'dash.orders_cancelled' => 1,
        'dash.best.Fin Phone.sold' => null,
        'dash.best.Fin Phone.revenue' => null,
    ]);
});

it('H12: a paid order cannot be cancelled until the money is refunded, then cancels cleanly', function (): void {
    // Storefront only offers COD (config store.payment_methods); the customer pays up front in cash.
    $order = finBuy($this, $this->buyer, [[$this->phone, 1]]);
    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/payments", ['amount' => 1000, 'reference' => 'Paid at pickup point'])->assertSessionHasNoErrors();
    $paid = finSnapshot($this, $this->admin);

    // Cancelling while the shop holds the money is refused and changes nothing.
    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/status", ['status' => 'cancelled'])->assertSessionHasErrors();
    $this->actingAs($this->buyer)->post("/account/orders/{$order->id}/cancel")->assertSessionHasErrors();
    finExpectChanges($paid, finSnapshot($this, $this->admin), []);

    // Refund first, then cancel.
    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/refunds", ['amount' => 1000, 'method' => 'cash', 'reason' => 'Cancelled by customer'])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/status", ['status' => 'cancelled'])->assertSessionHasNoErrors();

    finExpectChanges($paid, finSnapshot($this, $this->admin), [
        'stock.PHONE@MAIN.quantity' => 10,
        'stock.PHONE@MAIN.reserved' => 0,
        'store.PHONE.available' => 10,
        'ledger.order_cancellation.rows' => 1,
        'ledger.order_cancellation.units' => 1,
        "order.{$order->number}.status" => 'cancelled',
        "order.{$order->number}.payment_status" => 'refunded',
        "adminorder.{$order->number}.refunded" => finFmt(100_000),
        'money.refunds.rows' => 1,
        'money.refunds.sum' => 100_000,
        'treasury.cash' => 0,
        'treasury.rows' => 2,
        'report.summary.orders' => 0,
        'report.summary.revenue_formatted' => finFmt(0),
        'report.summary.avg_order_formatted' => finFmt(0),
        'report.summary.estimated_cost_formatted' => finFmt(0),
        'report.summary.estimated_profit_formatted' => finFmt(0),
        'report.summary.refunded_formatted' => finFmt(100_000),
        'report.invoices.invoices' => 0,
        'report.invoices.invoiced_formatted' => finFmt(0),
        'report.invoices.paid_formatted' => finFmt(0),
        'report.product.PHONE.units' => null,
        'report.product.PHONE.revenue' => null,
        'report.status.in_progress.orders' => null,
        'report.status.in_progress.revenue' => null,
        'report.status.cancelled.orders' => 1,
        'report.status.cancelled.revenue' => finFmt(0),
        'report.method.cod.orders' => null,
        'report.method.cod.revenue' => null,
        'dash.revenue_today' => finFmt(0),
        'dash.revenue_month' => finFmt(0),
        'dash.orders_in_progress' => 0,
        'dash.orders_cancelled' => 1,
        'dash.best.Fin Phone.sold' => null,
        'dash.best.Fin Phone.revenue' => null,
    ]);
});
