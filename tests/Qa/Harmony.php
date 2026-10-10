<?php

declare(strict_types=1);

/*
 * QA harmony checker: reads the whole database and returns every place where
 * two resources that must agree do not. Used by the QA harness after each step,
 * and runnable standalone against the dev DB (tests/Qa/check-dev.php).
 */

use App\Models\CashierShift;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

function qaHarmony(): array
{
    $v = [];
    $key = fn ($r) => "w{$r->warehouse_id}/p{$r->product_id}/v".($r->product_variant_id ?? '-');

    // ── Stock: stock_levels vs the movement ledger, reservations, incoming POs
    $movements = DB::table('inventory_movements')->orderBy('id')->get()->groupBy($key);
    $reserved = DB::table('order_items')
        ->join('orders', 'orders.id', '=', 'order_items.order_id')
        ->where('orders.status', 'in_progress')->whereNull('orders.deleted_at')
        ->selectRaw('order_items.warehouse_id, order_items.product_id, order_items.product_variant_id, SUM(order_items.quantity) AS q')
        ->groupBy('order_items.warehouse_id', 'order_items.product_id', 'order_items.product_variant_id')
        ->get()->keyBy($key);
    $incoming = DB::table('purchase_order_items')
        ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
        ->whereIn('purchase_orders.status', ['sent', 'partially_received'])->whereNull('purchase_orders.deleted_at')
        ->selectRaw('purchase_orders.warehouse_id, purchase_order_items.product_id, purchase_order_items.product_variant_id, SUM(quantity_ordered - quantity_received) AS q')
        ->groupBy('purchase_orders.warehouse_id', 'purchase_order_items.product_id', 'purchase_order_items.product_variant_id')
        ->get()->keyBy($key);

    $levels = DB::table('stock_levels')->get();
    $batchUnits = DB::table('stock_batches')->groupBy('stock_level_id')->selectRaw('stock_level_id, SUM(quantity_remaining) AS q')->pluck('q', 'stock_level_id');
    $levelKeys = [];
    foreach ($levels as $s) {
        $k = $key($s);
        $levelKeys[$k] = true;
        $movs = $movements->get($k, collect());
        $sum = (int) $movs->sum('quantity_change');
        if ($sum !== (int) $s->quantity) {
            $v[] = "STOCK $k: stock_levels.quantity={$s->quantity} but ledger sums to $sum";
        }
        $prev = 0;
        foreach ($movs as $m) {
            if ((int) $m->quantity_before !== $prev) {
                $v[] = "LEDGER $k: movement #{$m->id} ({$m->type}) starts at {$m->quantity_before}, previous ended at $prev";
            }
            if ((int) $m->quantity_before + (int) $m->quantity_change !== (int) $m->quantity_after) {
                $v[] = "LEDGER $k: movement #{$m->id} before+change != after";
            }
            $prev = (int) $m->quantity_after;
        }
        if ((int) $s->quantity < 0) {
            $v[] = "STOCK $k: negative quantity {$s->quantity}";
        }
        if ((int) ($batchUnits[$s->id] ?? 0) !== max(0, (int) $s->quantity)) {
            $v[] = "BATCHES $k: stock_levels.quantity={$s->quantity} but FIFO batches hold ".(int) ($batchUnits[$s->id] ?? 0);
        }
        $expR = (int) ($reserved->get($k)->q ?? 0);
        if ((int) $s->reserved !== $expR) {
            $v[] = "RESERVED $k: stock_levels.reserved={$s->reserved} but open orders hold $expR";
        }
        $expI = (int) ($incoming->get($k)->q ?? 0);
        if ((int) $s->incoming !== $expI) {
            $v[] = "INCOMING $k: stock_levels.incoming={$s->incoming} but open POs still owe $expI";
        }
    }
    foreach ($movements->keys() as $k) {
        if (! isset($levelKeys[$k])) {
            $v[] = "LEDGER $k: movements exist but no stock_levels row";
        }
    }

    // ── Orders: arithmetic, money roll-ups, status history, treasury legs, audit log
    $payTreasury = DB::table('treasury_transactions')->where('source_type', (new Payment)->getMorphClass())->get()->groupBy('source_id');
    $refTreasury = DB::table('treasury_transactions')->where('source_type', (new Refund)->getMorphClass())->get()->groupBy('source_id');
    $orderMorph = (new Order)->getMorphClass();

    foreach (DB::table('orders')->whereNull('deleted_at')->get() as $o) {
        $n = "ORDER {$o->number} [{$o->status}/{$o->payment_status}]";
        $items = DB::table('order_items')->where('order_id', $o->id)->get();
        $sub = (int) $items->sum(fn ($i) => $i->unit_price_cents * $i->quantity);
        if ($sub !== (int) $o->subtotal_cents) {
            $v[] = "$n: subtotal {$o->subtotal_cents} != sum(unit*qty) $sub";
        }
        $lines = (int) $items->sum('line_total_cents');
        if ($lines !== (int) $o->total_cents) {
            $v[] = "$n: total {$o->total_cents} != sum(line totals) $lines";
        }
        if ((int) $o->subtotal_cents - (int) $o->discount_cents + (int) $o->tax_cents !== (int) $o->total_cents) {
            $v[] = "$n: subtotal - discount + tax != total";
        }
        if ((int) $items->sum('discount_cents') !== (int) $o->discount_cents) {
            $v[] = "$n: order discount {$o->discount_cents} != sum(line discounts) ".$items->sum('discount_cents');
        }
        foreach ($items as $i) {
            if ($i->warehouse_id === null) {
                $v[] = "$n: item #{$i->id} has no warehouse (stock can't be returned)";
            }
        }

        $payments = DB::table('payments')->where('order_id', $o->id)->where('status', 'confirmed')->get();
        $refunds = DB::table('refunds')->where('order_id', $o->id)->where('status', 'completed')->get();
        $gross = (int) $payments->sum('amount_cents');
        $refunded = (int) $refunds->sum('amount_cents');
        $net = $gross - $refunded;
        if ($net < 0 || $net > (int) $o->total_cents || $refunded > $gross) {
            $v[] = "$n: money out of range (paid $gross, refunded $refunded, total {$o->total_cents})";
        }
        $expected = match (true) {
            $refunded > 0 => $refunded >= $gross ? 'refunded' : 'partially_refunded',
            $net === 0 => (int) $o->total_cents === 0 && $o->status === 'completed' ? 'paid' : 'unpaid',
            $net >= (int) $o->total_cents => 'paid',
            default => 'partially_paid',
        };
        if ($o->payment_status !== $expected) {
            $v[] = "$n: payment_status is {$o->payment_status}, money says $expected (paid $gross, refunded $refunded)";
        }
        if ($o->status === 'completed' && $refunded === 0 && $net !== (int) $o->total_cents) {
            $v[] = "$n: completed but only $net of {$o->total_cents} collected";
        }
        if ($o->status === 'completed' && $refunded > 0) {
            $v[] = "$n: completed order has refunds ($refunded) but status was never moved to refunded";
        }
        if ($o->status === 'cancelled' && $net !== 0) {
            $v[] = "$n: cancelled but still holds $net of customer money";
        }
        if ($o->status === 'refunded' && $refunded === 0) {
            $v[] = "$n: status refunded but no refund recorded";
        }
        if ($o->status === 'completed' && $o->completed_at === null) {
            $v[] = "$n: completed without completed_at";
        }
        if ($o->status === 'cancelled' && $o->cancelled_at === null) {
            $v[] = "$n: cancelled without cancelled_at";
        }

        $hist = DB::table('order_status_histories')->where('order_id', $o->id)->orderBy('id')->get();
        if ($hist->isEmpty()) {
            $v[] = "$n: no status history";
        } else {
            if ($hist->first()->from_status !== null) {
                $v[] = "$n: first history row doesn't start from null";
            }
            $prev = null;
            foreach ($hist as $h) {
                if ($prev !== null && $h->from_status !== $prev) {
                    $v[] = "$n: history jumps ($prev then from {$h->from_status})";
                }
                $prev = $h->to_status;
            }
            if ($prev !== $o->status) {
                $v[] = "$n: last history says $prev, order says {$o->status}";
            }
        }

        foreach ($payments as $p) {
            $legs = $payTreasury->get($p->id, collect());
            $acct = in_array($p->method, ['cod', 'cash'], true) ? 'cash' : 'bank';
            if ($legs->count() !== 1 || (int) $legs->first()->amount_cents !== (int) $p->amount_cents
                || $legs->first()->direction !== 'in' || $legs->first()->account !== $acct) {
                $v[] = "$n: payment #{$p->id} ({$p->amount_cents}) has no matching treasury IN on $acct";
            }
        }
        foreach ($refunds as $r) {
            if ($r->method === 'store_credit') {
                continue;
            }
            $legs = $refTreasury->get($r->id, collect());
            $acct = $r->method === 'cash' ? 'cash' : 'bank';
            if ($legs->count() !== 1 || (int) $legs->first()->amount_cents !== (int) $r->amount_cents
                || $legs->first()->direction !== 'out' || $legs->first()->account !== $acct) {
                $v[] = "$n: refund #{$r->id} ({$r->amount_cents}) has no matching treasury OUT on $acct";
            }
        }

        $events = DB::table('activity_log')->where('subject_type', $orderMorph)->where('subject_id', $o->id)->pluck('event')->countBy();
        if (($events['payment_confirmed'] ?? 0) !== $payments->count()) {
            $v[] = "$n: {$payments->count()} payments but ".($events['payment_confirmed'] ?? 0).' payment audit entries';
        }
        if (($events['refund_created'] ?? 0) !== $refunds->count()) {
            $v[] = "$n: {$refunds->count()} refunds but ".($events['refund_created'] ?? 0).' refund audit entries';
        }
        $transitions = $hist->filter(fn ($h) => $h->from_status !== null && $h->from_status !== $h->to_status)->count();
        $logged = ($events['order_status_changed'] ?? 0) + ($events['order_cancelled'] ?? 0);
        if ($logged !== $transitions) {
            $v[] = "$n: $transitions status changes but $logged status audit entries";
        }
    }

    // ── Treasury: no orphan legs
    foreach (DB::table('treasury_transactions')->whereNotNull('source_type')->get() as $t) {
        $table = match ($t->source_type) {
            (new Payment)->getMorphClass() => 'payments',
            (new Refund)->getMorphClass() => 'refunds',
            (new CashierShift)->getMorphClass() => 'cashier_shifts',
            default => null,
        };
        if ($table === null) {
            $v[] = "TREASURY #{$t->id}: unknown source type {$t->source_type}";
        } elseif (! DB::table($table)->where('id', $t->source_id)->exists()) {
            $v[] = "TREASURY #{$t->id}: source {$table} #{$t->source_id} no longer exists";
        }
        if ($table === 'cashier_shifts') {
            $diff = (int) DB::table('cashier_shifts')->where('id', $t->source_id)->value('difference_cents');
            if (abs($diff) !== (int) $t->amount_cents || ($diff > 0 ? 'in' : 'out') !== $t->direction) {
                $v[] = "TREASURY #{$t->id}: shift variance leg {$t->direction} {$t->amount_cents} != shift difference $diff";
            }
        }
    }
    foreach (DB::table('treasury_transactions')->selectRaw('account, SUM(CASE WHEN direction = \'in\' THEN amount_cents ELSE -amount_cents END) AS bal')->groupBy('account')->get() as $b) {
        if ((int) $b->bal < 0) {
            $v[] = "TREASURY {$b->account}: balance is negative ({$b->bal})";
        }
    }

    // ── Shifts: a closed shift with a variance has exactly one treasury leg
    foreach (DB::table('cashier_shifts')->where('status', 'closed')->get() as $s) {
        $legs = DB::table('treasury_transactions')->where('source_type', (new CashierShift)->getMorphClass())->where('source_id', $s->id)->count();
        if (((int) $s->difference_cents !== 0) !== ($legs === 1) || $legs > 1) {
            $v[] = "SHIFT #{$s->id}: difference {$s->difference_cents} but $legs treasury legs";
        }
        if ((int) $s->counted_cash_cents - (int) $s->expected_cash_cents !== (int) $s->difference_cents) {
            $v[] = "SHIFT #{$s->id}: counted - expected != difference";
        }
    }

    // ── Coupons: used_count matches redemptions; no redemption on a cancelled order
    foreach (DB::table('coupons')->get() as $c) {
        // Released uses (cancelled orders) stay as history but don't count (BD7).
        $red = DB::table('coupon_redemptions')->where('coupon_id', $c->id)->whereNull('released_at')->count();
        if ($red !== (int) $c->used_count) {
            $v[] = "COUPON {$c->code}: used_count {$c->used_count} != $red redemptions";
        }
        if ($c->usage_limit !== null && (int) $c->used_count > (int) $c->usage_limit) {
            $v[] = "COUPON {$c->code}: used {$c->used_count} times, limit {$c->usage_limit}";
        }
    }
    foreach (DB::table('coupon_redemptions')->join('orders', 'orders.id', '=', 'coupon_redemptions.order_id')->where('orders.status', 'cancelled')->whereNull('coupon_redemptions.released_at')->pluck('orders.number') as $num) {
        $v[] = "COUPON: redemption still held by cancelled order $num";
    }

    // ── Purchase orders: header status follows the lines
    foreach (DB::table('purchase_orders')->whereNull('deleted_at')->whereIn('status', ['sent', 'partially_received', 'received'])->get() as $po) {
        $items = DB::table('purchase_order_items')->where('purchase_order_id', $po->id)->get();
        $owed = (int) $items->sum(fn ($i) => $i->quantity_ordered - $i->quantity_received);
        $got = (int) $items->sum('quantity_received');
        $exp = $owed === 0 ? 'received' : ($got > 0 ? 'partially_received' : 'sent');
        if ($exp !== $po->status) {
            $v[] = "PO {$po->po_number}: status {$po->status}, lines say $exp";
        }
        foreach ($items as $i) {
            if ($i->quantity_received > $i->quantity_ordered) {
                $v[] = "PO {$po->po_number}: line #{$i->id} received more than ordered";
            }
        }
        $receiptMovs = (int) DB::table('inventory_movements')->where('type', 'purchase_receipt')
            ->where('reference_type', (new PurchaseOrder)->getMorphClass())->where('reference_id', $po->id)->sum('quantity_change');
        if ($receiptMovs !== $got) {
            $v[] = "PO {$po->po_number}: lines say $got received, ledger shows $receiptMovs";
        }
    }

    // ── Products: "out of stock" label vs real stock
    foreach (DB::table('products')->whereNull('deleted_at')->where('status', 'out_of_stock')->get() as $p) {
        $q = (int) DB::table('stock_levels')->where('product_id', $p->id)->sum('quantity');
        if ($q > 0) {
            $v[] = "PRODUCT {$p->sku}: marked out_of_stock but has $q units";
        }
    }

    return $v;
}
