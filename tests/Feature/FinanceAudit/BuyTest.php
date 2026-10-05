<?php

declare(strict_types=1);

/*
 * Finance audit H1–H3: buying. Each scenario snapshots every money/stock place, buys
 * through the storefront routes, and asserts the exact set of places that changed.
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
        'report.summary.orders' => 1,
        'report.summary.revenue_formatted' => finFmt(100_000),
        'report.summary.avg_order_formatted' => finFmt(100_000),
        'report.summary.estimated_cost_formatted' => finFmt(70_000),
        'report.summary.estimated_profit_formatted' => finFmt(30_000),
        'report.invoices.invoices' => 1,
        'report.invoices.invoiced_formatted' => finFmt(100_000),
        'report.invoices.outstanding_formatted' => finFmt(100_000),
        'report.product.PHONE.units' => 1,
        'report.product.PHONE.revenue' => finFmt(100_000),
        'report.status.in_progress.orders' => 1,
        'report.status.in_progress.revenue' => finFmt(100_000),
        'report.method.cod.orders' => 1,
        'report.method.cod.revenue' => finFmt(100_000),
        'dash.revenue_today' => finFmt(100_000),
        'dash.revenue_month' => finFmt(100_000),
        'dash.orders_total' => 1,
        'dash.orders_in_progress' => 1,
        'dash.best.Fin Phone.sold' => 1,
        'dash.best.Fin Phone.revenue' => finFmt(100_000),
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
        'report.summary.orders' => 1,
        'report.summary.revenue_formatted' => finFmt($total),
        'report.summary.avg_order_formatted' => finFmt($total),
        'report.summary.estimated_cost_formatted' => finFmt($cost),
        'report.summary.estimated_profit_formatted' => finFmt($total - $cost),
        'report.invoices.invoices' => 1,
        'report.invoices.invoiced_formatted' => finFmt($total),
        'report.invoices.outstanding_formatted' => finFmt($total),
        'report.product.PHONE.units' => 2,
        'report.product.PHONE.revenue' => finFmt(200_000),
        'report.product.CABLE.units' => 3,
        'report.product.CABLE.revenue' => finFmt(15_000),
        'report.status.in_progress.orders' => 1,
        'report.status.in_progress.revenue' => finFmt($total),
        'report.method.cod.orders' => 1,
        'report.method.cod.revenue' => finFmt($total),
        'dash.revenue_today' => finFmt($total),
        'dash.revenue_month' => finFmt($total),
        'dash.orders_total' => 1,
        'dash.orders_in_progress' => 1,
        'dash.best.Fin Phone.sold' => 2,
        'dash.best.Fin Phone.revenue' => finFmt(200_000),
        'dash.best.Fin Cable.sold' => 3,
        'dash.best.Fin Cable.revenue' => finFmt(15_000),
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
        'report.summary.orders' => 1,
        'report.summary.revenue_formatted' => finFmt(2_000),
        'report.summary.avg_order_formatted' => finFmt(2_000),
        'report.summary.estimated_cost_formatted' => finFmt(500),
        'report.summary.estimated_profit_formatted' => finFmt(1_500),
        'report.invoices.invoices' => 1,
        'report.invoices.invoiced_formatted' => finFmt(2_000),
        'report.invoices.outstanding_formatted' => finFmt(2_000),
        'report.product.MUG.units' => 1,
        'report.product.MUG.revenue' => finFmt(2_000),
        'report.status.in_progress.orders' => 1,
        'report.status.in_progress.revenue' => finFmt(2_000),
        'report.method.cod.orders' => 1,
        'report.method.cod.revenue' => finFmt(2_000),
        'dash.revenue_today' => finFmt(2_000),
        'dash.revenue_month' => finFmt(2_000),
        'dash.orders_total' => 1,
        'dash.orders_in_progress' => 1,
        'dash.out_of_stock' => 1,
        'dash.low_stock' => 0, // was 1: an empty shelf is out of stock, not low
        'dash.best.Fin Mug.sold' => 1,
        'dash.best.Fin Mug.revenue' => finFmt(2_000),
    ]);

    // And a second customer can no longer put it in a cart.
    $this->actingAs(customer())->post('/cart/items', ['product_id' => $this->mug->id, 'quantity' => 1])
        ->assertSessionHasErrors('quantity');
});
