<?php

declare(strict_types=1);

/*
 * Finance audit H4–H6: refunds. The order is bought on the storefront and completed by
 * staff (cash collected), then returned through the admin return form.
 */

use App\Models\Order;

require_once __DIR__.'/FinanceAuditSetup.php';

beforeEach(fn () => finSetUp($this));

function finComplete($test, Order $order): void
{
    $test->actingAs($test->admin)->post("/admin/orders/{$order->id}/status", ['status' => 'completed'])->assertSessionHasNoErrors();
}

/** The admin return form: refund an amount (LYD) and put the listed units back on the shelf. */
function finReturn($test, Order $order, int $refundCents, array $unitsBySku): void
{
    $items = $order->items()->get()->mapWithKeys(fn ($i) => [$i->id => $unitsBySku[$i->sku] ?? 0])->all();
    $test->actingAs($test->admin)->post("/admin/orders/{$order->id}/issue", [
        'reason' => 'Customer returned goods', 'amount' => $refundCents / 100, 'method' => 'cash', 'restock' => true, 'items' => $items,
    ])->assertSessionHasNoErrors();
}

it('H4: a full refund gives the money back, restocks, and nets the sale out everywhere', function (): void {
    $order = finBuy($this, $this->buyer, [[$this->phone, 1]]);
    finComplete($this, $order);
    $before = finSnapshot($this, $this->admin);

    finReturn($this, $order, 100_000, ['PHONE' => 1]);

    finExpectChanges($before, finSnapshot($this, $this->admin), [
        'stock.PHONE@MAIN.quantity' => 10,
        'store.PHONE.available' => 10,
        // The returned unit is back on hand: 10 phones + 50 cables + 1 mug.
        'report.valuation.units' => 61,
        'report.valuation.cost_value_formatted' => finFmt(10 * 70_000 + 50 * 2_000 + 500),
        'report.valuation.retail_value_formatted' => finFmt(10 * 100_000 + 50 * 5_000 + 2_000),
        'ledger.customer_return.rows' => 1,
        'ledger.customer_return.units' => 1,
        "order.{$order->number}.status" => 'refunded',
        "order.{$order->number}.payment_status" => 'refunded',
        "adminorder.{$order->number}.refunded" => finFmt(100_000),
        'money.refunds.rows' => 1,
        'money.refunds.sum' => 100_000,
        'treasury.cash' => 0,
        'treasury.rows' => 2,
        'report.summary.revenue_formatted' => finFmt(0),
        'report.summary.avg_order_formatted' => finFmt(0),
        'report.summary.estimated_cost_formatted' => finFmt(0),
        'report.summary.estimated_profit_formatted' => finFmt(0),
        'report.summary.refunded_formatted' => finFmt(100_000),
        // Refunded orders are left out of the per-product breakdown (ReportService::revenueItems).
        'report.product.PHONE.units' => null,
        'report.product.PHONE.revenue' => null,
        'report.status.completed.orders' => null,
        'report.status.completed.revenue' => null,
        'report.status.refunded.orders' => 1,
        'report.status.refunded.revenue' => finFmt(0),
        'report.method.cod.revenue' => finFmt(0),
        'dash.revenue_today' => finFmt(0),
        'dash.revenue_month' => finFmt(0),
        'dash.orders_completed' => 0,
        'dash.orders_refunded' => 1,
        // A product that all came back is not a best seller: same rule as the report.
        'dash.best.Fin Phone.sold' => null,
        'dash.best.Fin Phone.revenue' => null,
    ]);
});

it('H5: a partial refund of one item of several only nets out that item', function (): void {
    $order = finBuy($this, $this->buyer, [[$this->phone, 1], [$this->cable, 2]]);
    finComplete($this, $order);
    $before = finSnapshot($this, $this->admin);

    finReturn($this, $order, 5_000, ['CABLE' => 1]);

    finExpectChanges($before, finSnapshot($this, $this->admin), [
        'stock.CABLE@MAIN.quantity' => 49,
        'store.CABLE.available' => 49,
        // 9 phones + 49 cables + 1 mug on hand.
        'report.valuation.units' => 59,
        'report.valuation.cost_value_formatted' => finFmt(9 * 70_000 + 49 * 2_000 + 500),
        'report.valuation.retail_value_formatted' => finFmt(9 * 100_000 + 49 * 5_000 + 2_000),
        'ledger.customer_return.rows' => 1,
        'ledger.customer_return.units' => 1,
        "order.{$order->number}.status" => 'refunded',
        "order.{$order->number}.payment_status" => 'partially_refunded',
        "adminorder.{$order->number}.refunded" => finFmt(5_000),
        'money.refunds.rows' => 1,
        'money.refunds.sum' => 5_000,
        'treasury.cash' => 105_000,
        'treasury.rows' => 2,
        'report.summary.revenue_formatted' => finFmt(105_000),
        'report.summary.avg_order_formatted' => finFmt(105_000),
        'report.summary.estimated_cost_formatted' => finFmt(72_000),
        'report.summary.estimated_profit_formatted' => finFmt(33_000),
        'report.summary.refunded_formatted' => finFmt(5_000),
        // 🔴 Business decision (MORNING_REVIEW): the per-product breakdown drops the whole
        // order once any refund lands, so the phone and the kept cable vanish from it.
        'report.product.PHONE.units' => null,
        'report.product.PHONE.revenue' => null,
        'report.product.CABLE.units' => null,
        'report.product.CABLE.revenue' => null,
        'report.status.completed.orders' => null,
        'report.status.completed.revenue' => null,
        'report.status.refunded.orders' => 1,
        'report.status.refunded.revenue' => finFmt(105_000),
        'report.method.cod.revenue' => finFmt(105_000),
        'dash.revenue_today' => finFmt(105_000),
        'dash.revenue_month' => finFmt(105_000),
        'dash.orders_completed' => 0,
        'dash.orders_refunded' => 1,
        // Same rule as the report's product breakdown.
        'dash.best.Fin Phone.sold' => null,
        'dash.best.Fin Phone.revenue' => null,
        'dash.best.Fin Cable.sold' => null,
        'dash.best.Fin Cable.revenue' => null,
    ]);
});

it('H6: a refunded unit can be bought again and the refunded order stays as it was', function (): void {
    $first = finBuy($this, $this->buyer, [[$this->mug, 1]]);
    finComplete($this, $first);
    finReturn($this, $first, 2_000, ['MUG' => 1]);
    $before = finSnapshot($this, $this->admin);

    $other = customer(['email' => 'fin-rebuy@example.com']);
    $second = finBuy($this, $other, [[$this->mug, 1]]);

    finExpectChanges($before, finSnapshot($this, $this->admin), finNewOrderPlaces($second, [['MUG', 2_000, 1, 500]]) + [
        'stock.MUG@MAIN.quantity' => 0,
        'stock.MUG@MAIN.reserved' => 1,
        'store.MUG.available' => 0,
        'ledger.customer_order.rows' => 2,
        'ledger.customer_order.units' => -2,
        'report.summary.orders' => 2,
        'report.summary.revenue_formatted' => finFmt(2_000),
        'report.summary.avg_order_formatted' => finFmt(1_000),
        'report.summary.estimated_cost_formatted' => finFmt(500),
        'report.summary.estimated_profit_formatted' => finFmt(1_500),
        'report.summary.new_customers' => 2,
        'report.invoices.invoices' => 2,
        'report.invoices.invoiced_formatted' => finFmt(4_000),
        'report.invoices.outstanding_formatted' => finFmt(2_000),
        'report.product.MUG.units' => 1,
        'report.product.MUG.revenue' => finFmt(2_000),
        'report.status.in_progress.orders' => 1,
        'report.status.in_progress.revenue' => finFmt(2_000),
        'report.method.cod.orders' => 2,
        'report.method.cod.revenue' => finFmt(2_000),
        'dash.revenue_today' => finFmt(2_000),
        'dash.revenue_month' => finFmt(2_000),
        'dash.orders_total' => 2,
        'dash.orders_in_progress' => 1,
        'dash.customers_total' => 2,
        'dash.out_of_stock' => 1,
        'dash.low_stock' => 0,
        'dash.best.Fin Mug.sold' => 1,
        'dash.best.Fin Mug.revenue' => finFmt(2_000),
    ]);
});
