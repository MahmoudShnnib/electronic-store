<?php

declare(strict_types=1);

/*
 * Finance audit H1–H3: buying. Each scenario snapshots every money/stock place, buys
 * through the storefront routes, and asserts the exact set of places that changed.
 *
 * Revenue is money received (owner decision 2026-10-10): an unpaid cash-on-delivery
 * order reserves stock and is invoiced, but books no revenue, cost or best-seller
 * units until the money arrives.
 */

require_once __DIR__.'/FinanceAuditSetup.php';

beforeEach(fn () => finSetUp($this));

it('H1: buying one item reserves it and books it everywhere, nothing else moves', function (): void {
    $before = finSnapshot($this, $this->admin);

    $order = finBuy($this, $this->buyer, [[$this->phone, 1]]);

    finExpectChanges($before, finSnapshot($this, $this->admin), finNewOrderPlaces($order, [['PHONE', 100_000, 1, 70_000]]) + [
        'stock.PHONE@MAIN.quantity' => 9,
        'stock.PHONE@MAIN.reserved' => 1,
        'store.PHONE.available' => 9,
        'ledger.customer_order.rows' => 1,
        'ledger.customer_order.units' => -1,
        'report.invoices.invoices' => 1,
        'report.invoices.invoiced_formatted' => finFmt(100_000),
        'report.invoices.outstanding_formatted' => finFmt(100_000),
        'report.status.in_progress.orders' => 1,
        'report.status.in_progress.revenue' => finFmt(100_000),
        'dash.orders_total' => 1,
        'dash.orders_in_progress' => 1,
    ]);
});

it('H2: buying several items books every line', function (): void {
    $before = finSnapshot($this, $this->admin);

    $order = finBuy($this, $this->buyer, [[$this->phone, 2], [$this->cable, 3]]);

    $total = 2 * 100_000 + 3 * 5_000;
    $cost = 2 * 70_000 + 3 * 2_000;
    finExpectChanges($before, finSnapshot($this, $this->admin), finNewOrderPlaces($order, [['PHONE', 100_000, 2, 70_000], ['CABLE', 5_000, 3, 2_000]]) + [
        'stock.PHONE@MAIN.quantity' => 8,
        'stock.PHONE@MAIN.reserved' => 2,
        'stock.CABLE@MAIN.quantity' => 47,
        'stock.CABLE@MAIN.reserved' => 3,
        'store.PHONE.available' => 8,
        'store.CABLE.available' => 47,
        'ledger.customer_order.rows' => 2,
        'ledger.customer_order.units' => -5,
        'report.invoices.invoices' => 1,
        'report.invoices.invoiced_formatted' => finFmt($total),
        'report.invoices.outstanding_formatted' => finFmt($total),
        'report.status.in_progress.orders' => 1,
        'report.status.in_progress.revenue' => finFmt($total),
        'dash.orders_total' => 1,
        'dash.orders_in_progress' => 1,
    ]);
});

it('H3: buying the last unit empties the shelf and the storefront stops offering it', function (): void {
    $before = finSnapshot($this, $this->admin);

    $order = finBuy($this, $this->buyer, [[$this->mug, 1]]);

    finExpectChanges($before, finSnapshot($this, $this->admin), finNewOrderPlaces($order, [['MUG', 2_000, 1, 500]]) + [
        'stock.MUG@MAIN.quantity' => 0,
        'stock.MUG@MAIN.reserved' => 1,
        'store.MUG.available' => 0,
        'ledger.customer_order.rows' => 1,
        'ledger.customer_order.units' => -1,
        'report.invoices.invoices' => 1,
        'report.invoices.invoiced_formatted' => finFmt(2_000),
        'report.invoices.outstanding_formatted' => finFmt(2_000),
        'report.status.in_progress.orders' => 1,
        'report.status.in_progress.revenue' => finFmt(2_000),
        'dash.orders_total' => 1,
        'dash.orders_in_progress' => 1,
        'dash.out_of_stock' => 1,
        'dash.low_stock' => 0, // was 1: an empty shelf is out of stock, not low
    ]);

    // And a second customer can no longer put it in a cart.
    $this->actingAs(customer())->post('/cart/items', ['product_id' => $this->mug->id, 'quantity' => 1])
        ->assertSessionHasErrors('quantity');
});
