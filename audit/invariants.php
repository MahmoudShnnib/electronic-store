<?php

declare(strict_types=1);

/*
 * Finance & inventory invariant checker — see audit/INVARIANTS.md for the rules.
 *
 * READ-ONLY: only SELECTs. auditInvariants() returns [rule id => [violation, ...]] for
 * every rule (an empty list means PASS). Used by audit/check_invariants.php (CLI, against
 * a real database) and by the audit scenario tests (tests/Feature/Audit/Finance).
 */

use App\Models\CashierShift;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Refund;
use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const AUDIT_RULES = [
    'S1' => 'Ledger = shelf (stock_levels.quantity = Σ movements, chained)',
    'S2' => 'Movement signs match their type',
    'S3' => 'No negative quantity / reserved / damaged / incoming',
    'S4' => 'reserved = open-order units',
    'S5' => 'incoming = open-PO units still owed',
    'S6' => 'Every ledger key has a stock row',
    'S7' => 'Order stock legs (sold / cancelled) match the order lines',
    'S8' => 'No unit returned twice; returns only on completed/refunded orders',
    'S9' => 'damaged = Σ ledger damaged_change (every damaged unit in/out is recorded)',
    'S10' => 'FIFO batches = shelf (Σ stock_batches.quantity_remaining = max(0, quantity))',
    'S11' => 'Order line cost slices add up to its quantity and its cost',
    'O1' => 'Line arithmetic (unit × qty − discount = line total, sane values)',
    'O2' => 'Order arithmetic (subtotal, discount, total)',
    'O4' => 'Status history and timestamps match the status',
    'M1' => 'Refund caps (0 ≤ refunded ≤ paid, 0 ≤ net paid ≤ total)',
    'M2' => 'payment_status matches the money',
    'M3' => 'Closed orders are settled; cancelled orders hold no money',
    'M4' => 'Net revenue of closed orders = payments − refunds',
    'M5' => 'Treasury leg for every payment and refund; no orphan legs',
    'M6' => 'Treasury balances ≥ 0',
    'M7' => 'Order-linked treasury net = payments − cash/bank refunds',
    'A1' => 'Stock movements carry who/why (manual) or a reference (automatic)',
    'A2' => 'Payments, refunds and manual treasury legs carry who',
    'A3' => 'Audit log entries for payments, refunds and status changes',
    'A4' => 'No financial row points at a missing order',
    'W1' => 'Write-offs never touch orders, payments, refunds or treasury',
    'C1' => 'Coupon usage = redemptions, within limit, none held by cancelled orders',
];

function auditInvariants(): array
{
    $v = array_fill_keys(array_keys(AUDIT_RULES), []);
    $key = fn ($r) => "w{$r->warehouse_id}/p{$r->product_id}/v".($r->product_variant_id ?? '-');
    $orderMorph = (new Order)->getMorphClass();

    // ── Stock ────────────────────────────────────────────────────────────────
    $movements = DB::table('inventory_movements')->orderBy('id')->get();
    $byKey = $movements->groupBy($key);
    $levels = DB::table('stock_levels')->get();
    $levelKeys = [];
    // Added by migration 2026_10_05_100000; a database that hasn't run it skips S9.
    $hasDamagedChange = Schema::hasColumn('inventory_movements', 'damaged_change');
    if (! $hasDamagedChange) {
        $v['S9'][] = 'SKIPPED: inventory_movements.damaged_change missing — run php artisan migrate';
    }
    // Added by migration 2026_10_10_110000 (FIFO costing); skipped until it has run.
    $hasBatches = Schema::hasTable('stock_batches');
    if (! $hasBatches) {
        $v['S10'][] = 'SKIPPED: stock_batches missing — run php artisan migrate';
        $v['S11'][] = 'SKIPPED: order_items.cost_layers missing — run php artisan migrate';
    }
    $batchUnits = $hasBatches
        ? DB::table('stock_batches')->groupBy('stock_level_id')->selectRaw('stock_level_id, SUM(quantity_remaining) AS q')->pluck('q', 'stock_level_id')
        : collect();

    $openUnits = DB::table('order_items')
        ->join('orders', 'orders.id', '=', 'order_items.order_id')
        ->where('orders.status', 'in_progress')->whereNull('orders.deleted_at')
        ->selectRaw('order_items.warehouse_id, order_items.product_id, order_items.product_variant_id, SUM(order_items.quantity) AS q')
        ->groupBy('order_items.warehouse_id', 'order_items.product_id', 'order_items.product_variant_id')
        ->get()->keyBy($key);
    $owed = DB::table('purchase_order_items')
        ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
        ->whereIn('purchase_orders.status', ['sent', 'partially_received'])->whereNull('purchase_orders.deleted_at')
        ->selectRaw('purchase_orders.warehouse_id, purchase_order_items.product_id, purchase_order_items.product_variant_id, SUM(quantity_ordered - quantity_received) AS q')
        ->groupBy('purchase_orders.warehouse_id', 'purchase_order_items.product_id', 'purchase_order_items.product_variant_id')
        ->get()->keyBy($key);

    foreach ($levels as $s) {
        $k = $key($s);
        $levelKeys[$k] = true;
        $movs = $byKey->get($k, collect());
        $sum = (int) $movs->sum('quantity_change');
        if ($sum !== (int) $s->quantity) {
            $v['S1'][] = "$k: stock_levels.quantity={$s->quantity}, ledger sums to $sum";
        }
        $prev = 0;
        foreach ($movs as $m) {
            if ((int) $m->quantity_before !== $prev) {
                $v['S1'][] = "$k: movement #{$m->id} ({$m->type}) starts at {$m->quantity_before}, previous ended at $prev";
            }
            if ((int) $m->quantity_before + (int) $m->quantity_change !== (int) $m->quantity_after) {
                $v['S1'][] = "$k: movement #{$m->id} before + change != after";
            }
            $prev = (int) $m->quantity_after;
        }
        foreach (['quantity', 'reserved', 'damaged', 'incoming'] as $col) {
            if ((int) $s->{$col} < 0) {
                $v['S3'][] = "$k: $col is {$s->{$col}}";
            }
        }
        if ($hasDamagedChange && (int) $movs->sum('damaged_change') !== (int) $s->damaged) {
            $v['S9'][] = "$k: damaged={$s->damaged}, ledger damaged_change sums to ".(int) $movs->sum('damaged_change');
        }
        $inBatches = (int) ($batchUnits[$s->id] ?? 0);
        if ($hasBatches && $inBatches !== max(0, (int) $s->quantity)) {
            $v['S10'][] = "$k: quantity={$s->quantity}, FIFO batches hold $inBatches";
        }
        $expR = (int) ($openUnits->get($k)->q ?? 0);
        if ((int) $s->reserved !== $expR) {
            $v['S4'][] = "$k: reserved={$s->reserved}, open orders hold $expR";
        }
        $expI = (int) ($owed->get($k)->q ?? 0);
        if ((int) $s->incoming !== $expI) {
            $v['S5'][] = "$k: incoming={$s->incoming}, open POs still owe $expI";
        }
    }
    foreach ($byKey->keys() as $k) {
        if (! isset($levelKeys[$k])) {
            $v['S6'][] = "$k: movements exist but no stock_levels row";
        }
    }

    $out = ['customer_order', 'damaged', 'lost', 'manual_decrease', 'supplier_return', 'transfer_out'];
    $in = ['initial', 'purchase_receipt', 'order_cancellation', 'customer_return', 'manual_increase', 'transfer_in'];
    $zeroOk = ['purchase_incoming', 'purchase_cancelled', 'correction', 'variant_split', 'supplier_return'];
    foreach ($movements as $m) {
        $c = (int) $m->quantity_change;
        if ((in_array($m->type, $out, true) && $c > 0) || (in_array($m->type, $in, true) && $c < 0)
            || (in_array($m->type, ['purchase_incoming', 'purchase_cancelled'], true) && $c !== 0)
            || ($c === 0 && ! in_array($m->type, $zeroOk, true))) {
            $v['S2'][] = "movement #{$m->id}: type {$m->type} with change $c";
        }
    }

    // Per-order stock legs, summed per order (lines can be re-pointed to a variant).
    $legs = $movements->where('reference_type', $orderMorph)->groupBy('reference_id');
    $orders = DB::table('orders')->get()->keyBy('id');
    $itemsByOrder = DB::table('order_items')->orderBy('id')->get()->groupBy('order_id');
    foreach ($orders as $o) {
        $sold = (int) $itemsByOrder->get($o->id, collect())->sum('quantity');
        $mine = $legs->get($o->id, collect());
        $placed = -(int) $mine->where('type', 'customer_order')->sum('quantity_change');
        $cancelled = (int) $mine->where('type', 'order_cancellation')->sum('quantity_change');
        $returned = (int) $mine->where('type', 'customer_return')->sum('quantity_change');
        if ($placed !== $sold) {
            $v['S7'][] = "order {$o->number}: sold $sold units, ledger took $placed";
        }
        $expCancel = $o->status === 'cancelled' ? $sold : 0;
        if ($cancelled !== $expCancel) {
            $v['S7'][] = "order {$o->number} [{$o->status}]: cancellation put back $cancelled, expected $expCancel";
        }
        if ($returned > $sold) {
            $v['S8'][] = "order {$o->number}: $returned units returned of $sold sold";
        }
        if ($returned > 0 && ! in_array($o->status, ['completed', 'refunded'], true)) {
            $v['S8'][] = "order {$o->number} [{$o->status}]: has $returned returned units";
        }
    }
    foreach ($legs->keys() as $id) {
        if (! $orders->has($id)) {
            $v['A4'][] = "movements reference missing order #$id";
        }
    }

    // ── Orders & money ───────────────────────────────────────────────────────
    $payments = DB::table('payments')->where('status', 'confirmed')->get();
    $refunds = DB::table('refunds')->where('status', 'completed')->get();
    $payByOrder = $payments->groupBy('order_id');
    $refByOrder = $refunds->groupBy('order_id');
    $payTreasury = DB::table('treasury_transactions')->where('source_type', (new Payment)->getMorphClass())->get()->groupBy('source_id');
    $refTreasury = DB::table('treasury_transactions')->where('source_type', (new Refund)->getMorphClass())->get()->groupBy('source_id');
    $events = DB::table('activity_log')->where('subject_type', $orderMorph)
        ->selectRaw('subject_id, event, COUNT(*) AS n')->groupBy('subject_id', 'event')->get()->groupBy('subject_id');
    $closedPaid = 0;
    $closedRefunded = 0;
    $closedNetRevenue = 0;

    foreach ($orders as $o) {
        if ($o->deleted_at !== null) {
            continue;
        }
        $n = "order {$o->number} [{$o->status}/{$o->payment_status}]";
        $items = $itemsByOrder->get($o->id, collect());

        foreach ($items as $i) {
            if ((int) $i->quantity < 1 || (int) $i->unit_price_cents < 0 || (int) $i->line_total_cents < 0
                || (int) $i->unit_price_cents * (int) $i->quantity - (int) $i->discount_cents !== (int) $i->line_total_cents) {
                $v['O1'][] = "$n: line #{$i->id} {$i->unit_price_cents} × {$i->quantity} − {$i->discount_cents} != {$i->line_total_cents}";
            }
            if ($hasBatches) {
                $layers = json_decode((string) $i->cost_layers, true) ?: [];
                $units = array_sum(array_column($layers, 'qty'));
                $cost = array_sum(array_map(fn ($l) => $l['cost'] * $l['qty'], $layers));
                if ($units !== (int) $i->quantity || $cost !== (int) $i->cost_cents) {
                    $v['S11'][] = "$n: line #{$i->id} slices hold $units units costing $cost, line has {$i->quantity} costing {$i->cost_cents}";
                }
            }
        }
        $sub = (int) $items->sum(fn ($i) => $i->unit_price_cents * $i->quantity);
        if ($items->isEmpty()) {
            $v['O2'][] = "$n: has no lines";
        }
        if ($sub !== (int) $o->subtotal_cents) {
            $v['O2'][] = "$n: subtotal {$o->subtotal_cents} != Σ unit × qty $sub";
        }
        if ((int) $items->sum('discount_cents') !== (int) $o->discount_cents) {
            $v['O2'][] = "$n: discount {$o->discount_cents} != Σ line discounts ".$items->sum('discount_cents');
        }
        if ((int) $items->sum('line_total_cents') !== (int) $o->total_cents
            || (int) $o->subtotal_cents - (int) $o->discount_cents + (int) $o->tax_cents !== (int) $o->total_cents
            || (int) $o->total_cents < 0) {
            $v['O2'][] = "$n: total {$o->total_cents} != Σ line totals ".$items->sum('line_total_cents').' / subtotal − discount + tax';
        }

        $hist = DB::table('order_status_histories')->where('order_id', $o->id)->orderBy('id')->get();
        $prev = null;
        foreach ($hist as $idx => $h) {
            if (($idx === 0 && $h->from_status !== null) || ($idx > 0 && $h->from_status !== $prev)) {
                $v['O4'][] = "$n: history row #{$h->id} goes {$h->from_status}→{$h->to_status} after $prev";
            }
            $prev = $h->to_status;
        }
        if ($prev !== $o->status) {
            $v['O4'][] = "$n: history ends at ".($prev ?? 'nothing');
        }
        if (($o->status === 'completed' && $o->completed_at === null) || ($o->status === 'cancelled' && $o->cancelled_at === null)) {
            $v['O4'][] = "$n: missing completed_at/cancelled_at";
        }

        $gross = (int) $payByOrder->get($o->id, collect())->sum('amount_cents');
        $refunded = (int) $refByOrder->get($o->id, collect())->sum('amount_cents');
        $net = $gross - $refunded;
        if ($refunded < 0 || $refunded > $gross || $net < 0 || $net > (int) $o->total_cents) {
            $v['M1'][] = "$n: paid $gross, refunded $refunded, total {$o->total_cents}";
        }
        // A deposit given back and then the full total collected is simply paid.
        $expected = match (true) {
            $refunded > 0 && $refunded >= $gross => 'refunded',
            $net > 0 && $net >= (int) $o->total_cents => 'paid',
            $refunded > 0 => 'partially_refunded',
            $net === 0 => (int) $o->total_cents === 0 && $o->status === 'completed' ? 'paid' : 'unpaid',
            default => 'partially_paid',
        };
        if ($o->payment_status !== $expected) {
            $v['M2'][] = "$n: money says $expected (paid $gross, refunded $refunded)";
        }
        if ($o->status === 'completed' && $refunded === 0 && $net !== (int) $o->total_cents) {
            $v['M3'][] = "$n: completed but collected $net of {$o->total_cents}";
        }
        if ($o->status === 'completed' && $refunded > 0) {
            $v['M3'][] = "$n: completed with $refunded refunded (should be refunded status)";
        }
        if ($o->status === 'refunded' && $refunded === 0) {
            $v['M3'][] = "$n: refunded status with no refund";
        }
        if ($o->status === 'cancelled' && $net !== 0) {
            $v['M3'][] = "$n: cancelled but holds $net";
        }
        if (in_array($o->status, ['completed', 'refunded'], true)) {
            if ($gross !== (int) $o->total_cents) {
                $v['M4'][] = "$n: closed order was paid $gross of its total {$o->total_cents}";
            }
            $closedPaid += $gross;
            $closedRefunded += $refunded;
            $closedNetRevenue += (int) $o->total_cents - $refunded;
        }

        $ev = collect($events->get($o->id, collect()))->pluck('n', 'event');
        if ((int) ($ev['payment_confirmed'] ?? 0) !== $payByOrder->get($o->id, collect())->count()) {
            $v['A3'][] = "$n: ".$payByOrder->get($o->id, collect())->count().' payments, '.(int) ($ev['payment_confirmed'] ?? 0).' payment log entries';
        }
        if ((int) ($ev['refund_created'] ?? 0) !== $refByOrder->get($o->id, collect())->count()) {
            $v['A3'][] = "$n: ".$refByOrder->get($o->id, collect())->count().' refunds, '.(int) ($ev['refund_created'] ?? 0).' refund log entries';
        }
        $transitions = $hist->filter(fn ($h) => $h->from_status !== null && $h->from_status !== $h->to_status)->count();
        $logged = (int) ($ev['order_status_changed'] ?? 0) + (int) ($ev['order_cancelled'] ?? 0);
        if ($logged !== $transitions) {
            $v['A3'][] = "$n: $transitions status changes, $logged logged";
        }
    }
    if ($closedPaid - $closedRefunded !== $closedNetRevenue) {
        $v['M4'][] = "closed orders: paid $closedPaid − refunded $closedRefunded != net revenue $closedNetRevenue";
    }

    foreach ($payments as $p) {
        $leg = $payTreasury->get($p->id, collect());
        $acct = in_array($p->method, ['cod', 'cash'], true) ? 'cash' : 'bank';
        if ($leg->count() !== 1 || (int) $leg->first()->amount_cents !== (int) $p->amount_cents
            || $leg->first()->direction !== 'in' || $leg->first()->account !== $acct) {
            $v['M5'][] = "payment #{$p->id} ({$p->amount_cents}, order #{$p->order_id}) has no single matching IN on $acct";
        }
        if ($p->confirmed_by === null || $p->confirmed_at === null) {
            $v['A2'][] = "payment #{$p->id}: no confirmed_by/confirmed_at";
        }
        if (! $orders->has($p->order_id)) {
            $v['A4'][] = "payment #{$p->id}: order #{$p->order_id} missing";
        }
    }
    foreach ($refunds as $r) {
        if ($r->created_by === null) {
            $v['A2'][] = "refund #{$r->id}: no created_by";
        }
        if (! $orders->has($r->order_id)) {
            $v['A4'][] = "refund #{$r->id}: order #{$r->order_id} missing";
        }
        if ($r->method === 'store_credit') {
            continue;
        }
        $leg = $refTreasury->get($r->id, collect());
        $acct = $r->method === 'cash' ? 'cash' : 'bank';
        if ($leg->count() !== 1 || (int) $leg->first()->amount_cents !== (int) $r->amount_cents
            || $leg->first()->direction !== 'out' || $leg->first()->account !== $acct) {
            $v['M5'][] = "refund #{$r->id} ({$r->amount_cents}, order #{$r->order_id}) has no single matching OUT on $acct";
        }
    }

    // ── Treasury ─────────────────────────────────────────────────────────────
    $sources = [
        (new Payment)->getMorphClass() => 'payments',
        (new Refund)->getMorphClass() => 'refunds',
        (new CashierShift)->getMorphClass() => 'cashier_shifts',
    ];
    $treasury = DB::table('treasury_transactions')->get();
    foreach ($treasury as $t) {
        if ($t->source_type === null) {
            if ($t->created_by === null || trim((string) $t->note) === '') {
                $v['A2'][] = "treasury #{$t->id}: manual leg without created_by/note";
            }

            continue;
        }
        $table = $sources[$t->source_type] ?? null;
        if ($table === null || ! DB::table($table)->where('id', $t->source_id)->exists()) {
            $v['M5'][] = "treasury #{$t->id}: source {$t->source_type} #{$t->source_id} missing";
        }
        if ($t->order_id !== null && ! $orders->has($t->order_id)) {
            $v['A4'][] = "treasury #{$t->id}: order #{$t->order_id} missing";
        }
    }
    foreach ($treasury->groupBy('account') as $account => $rows) {
        $bal = (int) $rows->sum(fn ($t) => $t->direction === 'in' ? $t->amount_cents : -$t->amount_cents);
        if ($bal < 0) {
            $v['M6'][] = "$account balance is $bal";
        }
    }
    $orderLinked = (int) $treasury->whereNotNull('order_id')->whereIn('source_type', [(new Payment)->getMorphClass(), (new Refund)->getMorphClass()])
        ->sum(fn ($t) => $t->direction === 'in' ? $t->amount_cents : -$t->amount_cents);
    $expectedLinked = (int) $payments->sum('amount_cents') - (int) $refunds->where('method', '!=', 'store_credit')->sum('amount_cents');
    if ($orderLinked !== $expectedLinked) {
        $v['M7'][] = "order-linked treasury net $orderLinked != payments − refunds $expectedLinked";
    }

    // ── Audit trail on stock, write-offs ─────────────────────────────────────
    $manual = ['initial', 'manual_increase', 'manual_decrease', 'damaged', 'lost', 'correction', 'variant_split', 'supplier_return'];
    $refMorphs = [$orderMorph, (new PurchaseOrder)->getMorphClass(), (new StockTransfer)->getMorphClass()];
    foreach ($movements as $m) {
        if (in_array($m->type, $manual, true)) {
            if (trim((string) $m->reason) === '' || $m->user_id === null) {
                $v['A1'][] = "movement #{$m->id} ({$m->type}): reason '".($m->reason ?? '')."', user ".($m->user_id ?? 'none');
            }
        } elseif (! in_array($m->reference_type, $refMorphs, true) || $m->reference_id === null) {
            $v['A1'][] = "movement #{$m->id} ({$m->type}): no order/PO/transfer reference";
        }
        if (in_array($m->type, ['damaged', 'lost', 'manual_decrease', 'supplier_return'], true) && $m->reference_type === $orderMorph) {
            $v['W1'][] = "write-off movement #{$m->id} references order #{$m->reference_id}";
        }
    }
    $writeOffMorph = (new InventoryMovement)->getMorphClass();
    foreach ($treasury->where('source_type', $writeOffMorph) as $t) {
        $v['W1'][] = "treasury #{$t->id} sourced from a stock movement";
    }

    // ── Coupons ──────────────────────────────────────────────────────────────
    foreach (DB::table('coupons')->get() as $c) {
        $red = DB::table('coupon_redemptions')->where('coupon_id', $c->id)
            ->when(Schema::hasColumn('coupon_redemptions', 'released_at'), fn ($q) => $q->whereNull('released_at'))
            ->count();
        if ($red !== (int) $c->used_count) {
            $v['C1'][] = "coupon {$c->code}: used_count {$c->used_count}, $red redemptions";
        }
        if ($c->usage_limit !== null && (int) $c->used_count > (int) $c->usage_limit) {
            $v['C1'][] = "coupon {$c->code}: used {$c->used_count}, limit {$c->usage_limit}";
        }
    }
    foreach (DB::table('coupon_redemptions')->join('orders', 'orders.id', '=', 'coupon_redemptions.order_id')
        ->where('orders.status', 'cancelled')
        ->when(Schema::hasColumn('coupon_redemptions', 'released_at'), fn ($q) => $q->whereNull('coupon_redemptions.released_at'))
        ->pluck('orders.number') as $num) {
        $v['C1'][] = "redemption still held by cancelled order $num";
    }

    return $v;
}

/** Only the failing rules, for test assertions: [rule => violations]. */
function auditFailures(): array
{
    return array_filter(auditInvariants());
}
