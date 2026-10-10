<?php

declare(strict_types=1);

/*
 * Finance audit snapshot: every place money or stock shows up (see audit/FINANCE_MAP.md),
 * flattened to "place => value" so a scenario can assert the exact set of places an action
 * changed — and that nothing else moved.
 *
 *   $before = finSnapshot($this, $admin);
 *   ...action...
 *   finExpectChanges($before, finSnapshot($this, $admin), ['stock.PHONE@MAIN.quantity' => 9, ...]);
 *
 * Money values are integer hundredths; report/dashboard figures are the formatted strings
 * the screens show (compare with finFmt()).
 */

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\User;
use App\Services\Admin\TreasuryQueryService;
use App\Services\Reports\ReportService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../../audit/invariants.php';

function finFmt(int $cents): string
{
    return Money::format($cents);
}

function finSnapshot($test, User $admin): array
{
    $s = [];

    // Stock, per product/variant sku @ warehouse code.
    foreach (StockLevel::query()->with(['product', 'variant', 'warehouse'])->get() as $l) {
        $k = 'stock.'.$l->product->sku.($l->variant ? '/'.$l->variant->sku : '').'@'.$l->warehouse->code;
        foreach (['quantity', 'reserved', 'damaged', 'incoming'] as $col) {
            $s["$k.$col"] = (int) $l->{$col};
        }
    }
    foreach (DB::table('inventory_movements')->selectRaw('type, COUNT(*) AS n, SUM(quantity_change) AS q')->groupBy('type')->get() as $m) {
        $s["ledger.{$m->type}.rows"] = (int) $m->n;
        $s["ledger.{$m->type}.units"] = (int) $m->q;
    }

    // Catalogue prices and what the storefront offers.
    foreach (Product::withTrashed()->get() as $p) {
        $s["product.{$p->sku}.price"] = (int) $p->price_cents;
        $s["product.{$p->sku}.sale"] = $p->sale_price_cents;
        $s["product.{$p->sku}.cost"] = (int) $p->cost_price_cents;
        $s["product.{$p->sku}.status"] = $p->status->value;
        $s["product.{$p->sku}.deleted"] = $p->trashed();
        if (! $p->has_variants) {
            $s["store.{$p->sku}.available"] = $p->availableQuantity();
        }
    }
    foreach (ProductVariant::withTrashed()->get() as $v) {
        $s["variant.{$v->sku}.price"] = (int) $v->price_cents;
        $s["store.{$v->sku}.available"] = max(0, (int) $v->stockLevels()->sellable()->max('quantity'));
    }

    // Orders and their line snapshots.
    foreach (Order::withTrashed()->with('items')->get() as $o) {
        $k = "order.{$o->number}";
        $s["$k.status"] = $o->status->value;
        $s["$k.payment_status"] = $o->payment_status->value;
        $s["$k.subtotal"] = $o->subtotal_cents;
        $s["$k.discount"] = $o->discount_cents;
        $s["$k.total"] = $o->total_cents;
        foreach ($o->items as $i) {
            $lk = "line.{$o->number}.{$i->sku}";
            $s["$lk.unit_price"] = $i->unit_price_cents;
            $s["$lk.qty"] = $i->quantity;
            $s["$lk.discount"] = $i->discount_cents;
            $s["$lk.line_total"] = $i->line_total_cents;
            $s["$lk.unit_cost"] = (int) $i->unit_cost_cents;
            $s["$lk.cost"] = (int) $i->cost_cents;
        }
        $props = $test->actingAs($admin)->get("/admin/orders/{$o->id}")->viewData('page')['props']['order'];
        $s["adminorder.{$o->number}.paid"] = $props['paid_formatted'];
        $s["adminorder.{$o->number}.refunded"] = $props['refunded_formatted'];
        $s["adminorder.{$o->number}.outstanding"] = $props['outstanding_formatted'];
    }

    // Money.
    $s['money.payments.rows'] = DB::table('payments')->where('status', 'confirmed')->count();
    $s['money.payments.sum'] = (int) DB::table('payments')->where('status', 'confirmed')->sum('amount_cents');
    $s['money.refunds.rows'] = DB::table('refunds')->where('status', 'completed')->count();
    $s['money.refunds.sum'] = (int) DB::table('refunds')->where('status', 'completed')->sum('amount_cents');
    $balances = app(TreasuryQueryService::class)->balances();
    $s['treasury.cash'] = $balances['cash'];
    $s['treasury.bank'] = $balances['bank'];
    $s['treasury.rows'] = DB::table('treasury_transactions')->count();
    foreach (Coupon::withTrashed()->get() as $c) {
        $s["coupon.{$c->code}.used"] = (int) $c->used_count;
    }

    // Reports, over a window around "now" (tests freeze time at noon).
    $reports = app(ReportService::class);
    $from = CarbonImmutable::now()->subDays(2);
    $to = CarbonImmutable::now()->addDays(2);
    foreach ($reports->salesSummary($from, $to) as $key => $value) {
        $s["report.summary.$key"] = $value;
    }
    $s['report.writeoffs.total'] = $reports->writeOffs($from, $to)['total_formatted'];
    foreach ($reports->invoiceSummary($from, $to) as $key => $value) {
        $s["report.invoices.$key"] = $value;
    }
    foreach ($reports->inventoryValuation() as $key => $value) {
        $s["report.valuation.$key"] = $value;
    }
    foreach ($reports->salesByProduct($from, $to) as $row) {
        $s["report.product.{$row['sku']}.units"] = $row['units'];
        $s["report.product.{$row['sku']}.revenue"] = $row['revenue_formatted'];
    }
    foreach ($reports->salesByStatus($from, $to) as $row) {
        $s["report.status.{$row['status']}.orders"] = $row['orders'];
        $s["report.status.{$row['status']}.revenue"] = $row['revenue_formatted'];
    }
    foreach ($reports->salesByPaymentMethod($from, $to) as $row) {
        $s["report.method.{$row['method']}.orders"] = $row['orders'];
        $s["report.method.{$row['method']}.revenue"] = $row['revenue_formatted'];
    }
    foreach ($reports->invoices($from, $to) as $row) {
        $s["report.invoice.{$row['number']}.paid"] = $row['paid_formatted'];
        $s["report.invoice.{$row['number']}.outstanding"] = $row['outstanding_formatted'];
    }

    // Dashboard.
    $dash = $test->actingAs($admin)->get('/admin')->viewData('page')['props'];
    foreach ($dash['kpis'] as $key => $value) {
        $s["dash.$key"] = $value;
    }
    foreach ($dash['bestSellers'] as $row) {
        $s["dash.best.{$row['name']}.sold"] = $row['sold'];
        $s["dash.best.{$row['name']}.revenue"] = $row['revenue_formatted'];
    }
    $s['dash.stale_cod'] = count($dash['staleCodOrders']);

    return $s;
}

/** [place => [before, after]] for every place whose value differs (missing = null). */
function finDiff(array $before, array $after): array
{
    $diff = [];
    foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $k) {
        if (($before[$k] ?? null) !== ($after[$k] ?? null)) {
            $diff[$k] = [$before[$k] ?? null, $after[$k] ?? null];
        }
    }
    ksort($diff);

    return $diff;
}

/**
 * Asserts the action changed exactly these places to exactly these values, and nothing
 * else — then that every invariant still holds.
 *
 * @param  array<string, mixed>  $expected  place => value after the action
 */
function finExpectChanges(array $before, array $after, array $expected): void
{
    $actual = array_map(fn ($pair) => $pair[1], finDiff($before, $after));
    ksort($expected);
    expect($actual)->toBe($expected);
    expect(auditFailures())->toBe([]);
}
