<?php

/*
 * QA harness: drives the shop and admin flows through their real routes, and
 * after every step runs qaHarmony() to prove every related table still agrees.
 * Each request is also checked for error handling: no 500s, a rejected action
 * must show an error and change nothing, a successful one must say so.
 *
 * Findings are appended to QA_REPORT as JSON lines; the test fails if any exist.
 */

use App\Actions\Treasury\RecordTreasuryMovement;
use App\Enums\TreasuryAccount;
use App\Enums\TreasuryDirection;
use App\Models\CashierShift;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Reports\ReportService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/../../Qa/Harmony.php';

const QA_TABLES = ['orders', 'order_items', 'payments', 'refunds', 'treasury_transactions', 'inventory_movements',
    'stock_levels', 'coupon_redemptions', 'purchase_orders', 'purchase_order_items', 'stock_transfers', 'cashier_shifts', 'order_status_histories'];

function qaReport(string $kind, string $step, string $detail): void
{
    $file = env('QA_REPORT', sys_get_temp_dir().'/qa-harmony.jsonl');
    file_put_contents($file, json_encode(['kind' => $kind, 'step' => $step, 'detail' => $detail], JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
    $GLOBALS['qaFindings'][] = "[$kind] $step: $detail";
}

/** A fingerprint of every business table, to prove a rejected action wrote nothing. */
function qaFingerprint(): array
{
    return collect(QA_TABLES)->mapWithKeys(fn ($t) => [$t => md5(json_encode(DB::table($t)->orderBy('id')->get()))])->all();
}

function qaHarmonyAfter(string $step): void
{
    foreach (qaHarmony() as $v) {
        qaReport('harmony', $step, $v);
    }
}

/**
 * Sends one request the way the UI would, then judges the response.
 * $expect: 'ok' (must succeed and say so) or 'reject' (must show an error and change nothing).
 */
function qaSend($test, User $as, string $step, string $method, string $uri, array $data = [], string $expect = 'ok')
{
    $before = qaFingerprint();
    session()->forget(['success', 'error', 'errors', 'justSold', '_flash']);

    $res = $test->actingAs($as)->from('/qa-origin')->{$method}($uri, $data);
    $status = $res->getStatusCode();
    $errors = session('errors')?->getBag('default')->all() ?? [];
    $flashError = session('error');
    $shown = $errors !== [] || $flashError !== null || in_array($status, [403, 404, 409, 422], true);

    if ($status >= 500) {
        qaReport('crash', $step, "HTTP $status: ".($res->exception ? get_class($res->exception).': '.$res->exception->getMessage() : 'no exception'));
    } elseif ($expect === 'ok') {
        if ($shown) {
            qaReport('unexpected-rejection', $step, json_encode($errors ?: $flashError ?: "HTTP $status", JSON_UNESCAPED_UNICODE));
        } elseif (session('success') === null && session('justSold') === null && $status !== 200 && ! str_contains((string) $res->headers->get('Location'), '/confirmation/')) {
            qaReport('no-feedback', $step, "succeeded (HTTP $status) without a success message");
        }
    } else {
        if (! $shown) {
            qaReport('silent', $step, "should have been refused but returned HTTP $status with no error shown");
        }
        if (qaFingerprint() !== $before) {
            $changed = array_keys(array_diff_assoc(qaFingerprint(), $before));
            qaReport('partial-write', $step, 'refused action still changed: '.implode(', ', $changed));
        }
    }

    qaHarmonyAfter($step);

    return $res;
}

function qaCheckout(array $over = []): array
{
    return array_replace_recursive([
        'customer' => ['name' => 'QA Buyer', 'email' => 'qa-buyer@example.com', 'phone' => '0912345678'],
        'shipping_address' => ['city' => 'Tripoli', 'area' => 'Hay Al-Andalus'],
        'payment_method' => 'cod',
    ], $over);
}

function qaQty(Product $p, ?ProductVariant $v = null, ?Warehouse $w = null): int
{
    return (int) StockLevel::query()->where('product_id', $p->id)->where('product_variant_id', $v?->id)
        ->when($w, fn ($q) => $q->where('warehouse_id', $w->id))->sum('quantity');
}

beforeEach(function (): void {
    Notification::fake();
    $GLOBALS['qaFindings'] = [];

    $this->admin = staff('super_admin');
    $this->buyer = customer(['email' => 'qa-buyer@example.com']);
    $this->main = defaultWarehouse();
    $this->branch = Warehouse::factory()->create(['code' => 'BR2', 'name' => 'Branch', 'is_default' => false, 'is_active' => true]);

    $this->phone = Product::factory()->create(['sku' => 'QA-PHONE', 'price_cents' => 100_000, 'cost_price_cents' => 70_000, 'sale_price_cents' => null]);
    $this->cable = Product::factory()->create(['sku' => 'QA-CABLE', 'price_cents' => 5_000, 'cost_price_cents' => 2_000, 'sale_price_cents' => null]);
    $this->laptop = Product::factory()->withVariants()->create(['sku' => 'QA-LAPTOP', 'sale_price_cents' => null]);
    $this->lap16 = ProductVariant::factory()->create(['product_id' => $this->laptop->id, 'sku' => 'QA-LAP-16', 'price_cents' => 300_000, 'cost_price_cents' => 250_000]);
    $this->lap32 = ProductVariant::factory()->create(['product_id' => $this->laptop->id, 'sku' => 'QA-LAP-32', 'price_cents' => 400_000, 'cost_price_cents' => 320_000]);
    $this->coupon = Coupon::factory()->percentage()->create(['code' => 'QA10', 'percent' => 10, 'usage_limit' => 2]);

    // Initial stock through the admin screen, like staff would.
    $adj = fn ($p, $v, $q, $w = null) => qaSend($this, $this->admin, "setup stock {$p->sku}", 'post', '/admin/inventory/adjust', [
        'warehouse_id' => ($w ?? $this->main)->id, 'product_id' => $p->id, 'product_variant_id' => $v?->id,
        'quantity_change' => $q, 'type' => 'initial', 'reason' => 'QA opening stock',
    ]);
    $adj($this->phone, null, 10);
    $adj($this->cable, null, 50);
    $adj($this->laptop, $this->lap16, 3);
    $adj($this->laptop, $this->lap32, 2);
    $adj($this->phone, null, 4, $this->branch);
});

afterEach(function (): void {
    expect($GLOBALS['qaFindings'])->toBe([]);
});

it('keeps everything in step through web checkout, completion, partial return and refund', function (): void {
    $t = $this;
    qaSend($t, $t->buyer, 'cart: add 2 phones', 'post', '/cart/items', ['product_id' => $t->phone->id, 'quantity' => 2]);
    qaSend($t, $t->buyer, 'cart: add 3 cables', 'post', '/cart/items', ['product_id' => $t->cable->id, 'quantity' => 3]);
    qaSend($t, $t->buyer, 'cart: add laptop 16GB', 'post', '/cart/items', ['product_id' => $t->laptop->id, 'variant_id' => $t->lap16->id, 'quantity' => 1]);
    qaSend($t, $t->buyer, 'cart: apply QA10', 'post', '/cart/coupon', ['code' => 'QA10']);
    qaSend($t, $t->buyer, 'checkout COD', 'post', '/checkout', qaCheckout(['idempotency_key' => 'qa-1']));

    $order = Order::query()->latest('id')->firstOrFail();
    expect($order->discount_cents)->toBeGreaterThan(0);
    expect(qaQty($t->phone, null, $t->main))->toBe(8);

    qaSend($t, $t->buyer, 'checkout replay same key', 'post', '/checkout', qaCheckout(['idempotency_key' => 'qa-1']));
    expect(Order::count())->toBe(1);

    qaSend($t, $t->admin, 'admin: return before completion', 'post', "/admin/orders/{$order->id}/issue", ['reason' => 'x', 'amount' => 1, 'method' => 'cash'], 'reject');
    qaSend($t, $t->admin, 'admin: refund unpaid COD order', 'post', "/admin/orders/{$order->id}/refunds", ['amount' => 1, 'method' => 'cash'], 'reject');
    qaSend($t, $t->admin, 'admin: payment above total', 'post', "/admin/orders/{$order->id}/payments", ['amount' => $order->total_cents / 100 + 1], 'reject');
    qaSend($t, $t->admin, 'admin: complete COD order', 'post', "/admin/orders/{$order->id}/status", ['status' => 'completed']);

    $order->refresh();
    expect($order->payment_status->value)->toBe('paid');

    $phoneLine = $order->items()->where('product_id', $t->phone->id)->firstOrFail();
    qaSend($t, $t->admin, 'admin: cancel completed order', 'post', "/admin/orders/{$order->id}/status", ['status' => 'cancelled'], 'reject');
    qaSend($t, $t->admin, 'admin: refund more than paid', 'post', "/admin/orders/{$order->id}/refunds", ['amount' => $order->total_cents / 100 + 1, 'method' => 'cash'], 'reject');
    qaSend($t, $t->admin, 'admin: return 1 phone + partial refund', 'post', "/admin/orders/{$order->id}/issue", [
        'reason' => 'Screen cracked', 'amount' => 900, 'method' => 'cash', 'restock' => true, 'items' => [$phoneLine->id => 1],
    ]);
    expect(qaQty($t->phone, null, $t->main))->toBe(9);

    qaSend($t, $t->admin, 'admin: second return on refunded order', 'post', "/admin/orders/{$order->id}/issue", ['reason' => 'again', 'amount' => 0, 'method' => 'cash', 'restock' => true], 'reject');
    qaSend($t, $t->admin, 'admin: top-up refund on refunded order', 'post', "/admin/orders/{$order->id}/refunds", ['amount' => 50, 'method' => 'cash']);
});

it('keeps everything in step when a refund is issued straight on a completed order', function (): void {
    $t = $this;
    qaSend($t, $t->buyer, 'cart: add phone', 'post', '/cart/items', ['product_id' => $t->phone->id, 'quantity' => 1]);
    qaSend($t, $t->buyer, 'checkout', 'post', '/checkout', qaCheckout());
    $order = Order::query()->latest('id')->firstOrFail();
    qaSend($t, $t->admin, 'complete', 'post', "/admin/orders/{$order->id}/status", ['status' => 'completed']);
    // The refund screen on the order page, not the return button.
    qaSend($t, $t->admin, 'direct full refund on completed order', 'post', "/admin/orders/{$order->id}/refunds", ['amount' => 100, 'method' => 'cash']);
});

it('keeps everything in step for bank-transfer manual orders: deposit, refund, cancel', function (): void {
    $t = $this;
    qaSend($t, $t->admin, 'manual order (bank transfer, coupon)', 'post', '/admin/orders', [
        ...qaCheckout(['payment_method' => 'bank_transfer']),
        'items' => [['product_id' => $t->laptop->id, 'product_variant_id' => $t->lap32->id, 'quantity' => 1], ['product_id' => $t->cable->id, 'quantity' => 2]],
    ]);
    $order = Order::query()->latest('id')->firstOrFail();
    expect($order->user_id)->toBe($t->buyer->id);

    qaSend($t, $t->admin, 'complete before transfer confirmed', 'post', "/admin/orders/{$order->id}/status", ['status' => 'completed'], 'reject');
    qaSend($t, $t->admin, 'record 100 deposit', 'post', "/admin/orders/{$order->id}/payments", ['amount' => 100, 'reference' => 'TRX-1']);
    qaSend($t, $t->admin, 'cancel with deposit held', 'post', "/admin/orders/{$order->id}/status", ['status' => 'cancelled'], 'reject');
    qaSend($t, $t->buyer, 'customer cancels with deposit held', 'post', "/account/orders/{$order->id}/cancel", [], 'reject');
    qaSend($t, $t->admin, 'refund 40 of deposit', 'post', "/admin/orders/{$order->id}/refunds", ['amount' => 40, 'method' => 'bank_transfer']);
    qaSend($t, $t->admin, 'refund remaining 60', 'post', "/admin/orders/{$order->id}/refunds", ['amount' => 60, 'method' => 'bank_transfer']);
    qaSend($t, $t->admin, 'cancel after full refund', 'post', "/admin/orders/{$order->id}/status", ['status' => 'cancelled', 'comment' => 'Customer changed mind']);
    expect(qaQty($t->laptop, $t->lap32))->toBe(2);
    qaSend($t, $t->admin, 'payment on cancelled order', 'post', "/admin/orders/{$order->id}/payments", ['amount' => 10], 'reject');
});

it('keeps coupons, stock and customer cancellations in step', function (): void {
    $t = $this;
    $other = customer();
    $buy = function (User $u, string $label) use ($t) {
        qaSend($t, $u, "$label: add cable", 'post', '/cart/items', ['product_id' => $t->cable->id, 'quantity' => 2]);
        qaSend($t, $u, "$label: apply QA10", 'post', '/cart/coupon', ['code' => 'QA10']);
        qaSend($t, $u, "$label: checkout", 'post', '/checkout', qaCheckout(['customer' => ['email' => $u->email]]));

        return Order::query()->latest('id')->firstOrFail();
    };
    $first = $buy($t->buyer, 'buyer');
    $second = $buy($other, 'other');
    expect($t->coupon->refresh()->used_count)->toBe(2);

    $third = customer();
    qaSend($t, $third, 'third: add cable', 'post', '/cart/items', ['product_id' => $t->cable->id, 'quantity' => 1]);
    qaSend($t, $third, 'third: coupon over its limit', 'post', '/cart/coupon', ['code' => 'QA10'], 'reject');

    qaSend($t, $t->buyer, 'customer cancels someone else\'s order', 'post', "/account/orders/{$second->id}/cancel", [], 'reject');
    qaSend($t, $t->buyer, 'customer cancels own order', 'post', "/account/orders/{$first->id}/cancel");
    expect($t->coupon->refresh()->used_count)->toBe(1);
    qaSend($t, $third, 'third: coupon free again', 'post', '/cart/coupon', ['code' => 'QA10']);

    qaSend($t, $t->buyer, 'cart: more phones than in stock', 'post', '/cart/items', ['product_id' => $t->phone->id, 'quantity' => 50], 'reject');
    qaSend($t, $t->buyer, 'checkout empty cart', 'post', '/checkout', qaCheckout(), 'reject');

    // Item goes off sale while it sits in a cart.
    qaSend($t, $t->buyer, 'cart: add laptop 32GB', 'post', '/cart/items', ['product_id' => $t->laptop->id, 'variant_id' => $t->lap32->id, 'quantity' => 1]);
    $t->lap32->update(['is_active' => false]);
    qaSend($t, $t->buyer, 'checkout with disabled variant', 'post', '/checkout', qaCheckout(), 'reject');
});

it('keeps stock in step across warehouses, adjustments and transfers', function (): void {
    $t = $this;
    $adjust = fn (string $label, array $d, string $exp = 'ok') => qaSend($t, $t->admin, $label, 'post', '/admin/inventory/adjust', [
        'warehouse_id' => $t->main->id, 'product_id' => $t->phone->id, 'reason' => 'QA', ...$d,
    ], $exp);

    $adjust('write off 2 damaged', ['quantity_change' => 2, 'type' => 'damaged']);
    expect(StockLevel::where('product_id', $t->phone->id)->where('warehouse_id', $t->main->id)->value('damaged'))->toBe(2);
    $adjust('return 3 to supplier, from damaged', ['quantity_change' => 3, 'type' => 'supplier_return', 'from_damaged' => true]);
    $adjust('decrease more than on shelf', ['quantity_change' => -500, 'type' => 'manual_decrease'], 'reject');
    $adjust('correction below zero', ['quantity_change' => -500, 'type' => 'correction'], 'reject');
    $adjust('product-level stock on variant product', ['product_id' => $t->laptop->id, 'quantity_change' => 5, 'type' => 'manual_increase'], 'reject');

    $move = fn (string $label, array $d, string $exp = 'ok') => qaSend($t, $t->admin, $label, 'post', '/admin/inventory/transfer', [
        'from_warehouse_id' => $t->main->id, 'to_warehouse_id' => $t->branch->id, ...$d,
    ], $exp);
    $move('transfer 2 phones to branch', ['items' => [['product_id' => $t->phone->id, 'quantity' => 2]]]);
    $move('transfer more than available', ['items' => [['product_id' => $t->phone->id, 'quantity' => 999]]], 'reject');
    $move('transfer to same warehouse', ['to_warehouse_id' => $t->main->id, 'items' => [['product_id' => $t->phone->id, 'quantity' => 1]]], 'reject');
    $move('transfer variant product without variant', ['items' => [['product_id' => $t->laptop->id, 'quantity' => 1]]], 'reject');
    $move('transfer second line fails after first ok', ['items' => [['product_id' => $t->cable->id, 'quantity' => 1], ['product_id' => $t->phone->id, 'quantity' => 999]]], 'reject');

    // Main warehouse can't cover the whole line → ships from the branch, and cancelling must put it back there.
    $onMain = qaQty($t->phone, null, $t->main);
    qaSend($t, $t->buyer, 'cart: phones beyond main stock', 'post', '/cart/items', ['product_id' => $t->phone->id, 'quantity' => min(6, $onMain + 1)]);
    qaSend($t, $t->buyer, 'checkout from branch', 'post', '/checkout', qaCheckout());
    $order = Order::query()->latest('id')->firstOrFail();
    qaSend($t, $t->admin, 'cancel branch order', 'post', "/admin/orders/{$order->id}/status", ['status' => 'cancelled']);
});

it('keeps purchase orders and incoming stock in step', function (): void {
    $t = $this;
    $supplier = Supplier::factory()->create();
    qaSend($t, $t->admin, 'create PO', 'post', '/admin/purchase-orders', [
        'supplier_id' => $supplier->id, 'warehouse_id' => $t->main->id,
        'items' => [['product_id' => $t->phone->id, 'quantity' => 10, 'unit_cost' => 650], ['product_id' => $t->laptop->id, 'product_variant_id' => $t->lap16->id, 'quantity' => 4, 'unit_cost' => 2400]],
    ]);
    $po = PurchaseOrder::query()->latest('id')->with('items')->firstOrFail();
    qaSend($t, $t->admin, 'receive draft PO', 'post', "/admin/purchase-orders/{$po->id}/receive", ['quantities' => [$po->items[0]->id => 1]], 'reject');
    qaSend($t, $t->admin, 'send PO', 'post', "/admin/purchase-orders/{$po->id}/send");
    qaSend($t, $t->admin, 'send PO twice', 'post', "/admin/purchase-orders/{$po->id}/send", [], 'reject');
    qaSend($t, $t->admin, 'edit sent PO quantities', 'patch', "/admin/purchase-orders/{$po->id}", [
        'supplier_id' => $supplier->id, 'warehouse_id' => $t->main->id,
        'items' => [['product_id' => $t->phone->id, 'quantity' => 1, 'unit_cost' => 650]],
    ], 'reject');
    qaSend($t, $t->admin, 'receive more than ordered', 'post', "/admin/purchase-orders/{$po->id}/receive", ['quantities' => [$po->items[0]->id => 11]], 'reject');
    qaSend($t, $t->admin, 'receive 6 phones', 'post', "/admin/purchase-orders/{$po->id}/receive", ['quantities' => [$po->items[0]->id => 6]]);
    expect(qaQty($t->phone, null, $t->main))->toBe(16);
    qaSend($t, $t->admin, 'cancel partly received PO', 'post', "/admin/purchase-orders/{$po->id}/cancel");
    qaSend($t, $t->admin, 'receive on cancelled PO', 'post', "/admin/purchase-orders/{$po->id}/receive", ['quantities' => [$po->items[1]->id => 1]], 'reject');
    qaSend($t, $t->admin, 'delete supplier with POs', 'delete', "/admin/suppliers/{$supplier->id}", [], 'reject');
});

it('keeps the till, treasury and refunds in step through a POS shift', function (): void {
    $t = $this;
    qaSend($t, $t->admin, 'sell without shift', 'post', '/admin/pos/sales', ['items' => [['product_id' => $t->cable->id, 'quantity' => 1]], 'payment_method' => 'cash'], 'reject');
    qaSend($t, $t->admin, 'open shift at main, float 100', 'post', '/admin/pos/shifts', ['warehouse_id' => $t->main->id, 'opening_float' => 100]);
    qaSend($t, $t->admin, 'open second shift', 'post', '/admin/pos/shifts', ['warehouse_id' => $t->main->id, 'opening_float' => 0], 'reject');
    qaSend($t, $t->admin, 'cash sale 2 cables, 1 LYD off', 'post', '/admin/pos/sales', ['items' => [['product_id' => $t->cable->id, 'quantity' => 2]], 'payment_method' => 'cash', 'discount' => 1]);
    qaSend($t, $t->admin, 'cash sale phone', 'post', '/admin/pos/sales', ['items' => [['product_id' => $t->phone->id, 'quantity' => 1]], 'payment_method' => 'cash']);
    qaSend($t, $t->admin, 'sale beyond shelf stock', 'post', '/admin/pos/sales', ['items' => [['product_id' => $t->phone->id, 'quantity' => 100]], 'payment_method' => 'cash'], 'reject');
    qaSend($t, $t->admin, 'discount above total', 'post', '/admin/pos/sales', ['items' => [['product_id' => $t->cable->id, 'quantity' => 1]], 'payment_method' => 'cash', 'discount' => 999], 'reject');

    $sale = Order::query()->where('placed_via', 'pos')->latest('id')->firstOrFail();
    qaSend($t, $t->admin, 'return phone sold at till, cash back', 'post', "/admin/orders/{$sale->id}/issue", ['reason' => 'Changed mind', 'amount' => 100, 'method' => 'cash', 'restock' => true]);

    $shift = CashierShift::query()->latest('id')->firstOrFail();
    $expected = $shift->expectedCashCents();
    expect($expected)->toBe(10_000 + 9_900 + 100_000 - 10_000);
    qaSend($t, $t->admin, 'close shift 2 LYD short', 'post', '/admin/pos/shifts/close', ['counted_cash' => ($expected - 200) / 100]);
    qaSend($t, $t->admin, 'close shift twice', 'post', '/admin/pos/shifts/close', ['counted_cash' => 0], 'reject');
});

it('refuses treasury withdrawals the account cannot cover, and refunds too', function (): void {
    $t = $this;
    qaSend($t, $t->admin, 'deposit 50 cash', 'post', '/admin/treasury', ['direction' => 'in', 'account' => 'cash', 'amount' => 50]);
    qaSend($t, $t->admin, 'withdraw 80 cash', 'post', '/admin/treasury', ['direction' => 'out', 'account' => 'cash', 'amount' => 80], 'reject');

    // A bank-transfer order paid in, then the bank is emptied by hand, then refunded.
    qaSend($t, $t->admin, 'manual bank order', 'post', '/admin/orders', [...qaCheckout(['payment_method' => 'bank_transfer']), 'items' => [['product_id' => $t->cable->id, 'quantity' => 1]]]);
    $order = Order::query()->latest('id')->firstOrFail();
    qaSend($t, $t->admin, 'confirm transfer 5', 'post', "/admin/orders/{$order->id}/payments", ['amount' => 5]);
    qaSend($t, $t->admin, 'withdraw whole bank balance', 'post', '/admin/treasury', ['direction' => 'out', 'account' => 'bank', 'amount' => 5]);
    qaSend($t, $t->admin, 'refund from empty bank account', 'post', "/admin/orders/{$order->id}/refunds", ['amount' => 5, 'method' => 'bank_transfer'], 'reject');
});

it('validates money inputs instead of crashing', function (): void {
    $t = $this;
    qaSend($t, $t->buyer, 'cart: add phone', 'post', '/cart/items', ['product_id' => $t->phone->id, 'quantity' => 1]);
    qaSend($t, $t->buyer, 'checkout', 'post', '/checkout', qaCheckout());
    $order = Order::query()->latest('id')->firstOrFail();
    foreach (['abc', '-5', '0', '1e9', '0.0001', ''] as $bad) {
        qaSend($t, $t->admin, "payment amount '$bad'", 'post', "/admin/orders/{$order->id}/payments", ['amount' => $bad], 'reject');
    }
    qaSend($t, $t->admin, 'status unknown', 'post', "/admin/orders/{$order->id}/status", ['status' => 'shipped'], 'reject');
    qaSend($t, $t->admin, 'status refunded via status form', 'post', "/admin/orders/{$order->id}/status", ['status' => 'refunded'], 'reject');
    qaSend($t, $t->admin, 'adjust zero', 'post', '/admin/inventory/adjust', ['warehouse_id' => $t->main->id, 'product_id' => $t->phone->id, 'quantity_change' => 0, 'type' => 'manual_increase', 'reason' => 'x'], 'reject');
    qaSend($t, $t->admin, 'adjust into missing warehouse', 'post', '/admin/inventory/adjust', ['warehouse_id' => 99999, 'product_id' => $t->phone->id, 'quantity_change' => 1, 'type' => 'manual_increase', 'reason' => 'x'], 'reject');
    qaSend($t, $t->admin, 'adjust into inactive warehouse', 'post', '/admin/inventory/adjust', ['warehouse_id' => tap($t->branch)->update(['is_active' => false]) ? $t->branch->id : 0, 'product_id' => $t->phone->id, 'quantity_change' => 1, 'type' => 'manual_increase', 'reason' => 'x'], 'reject');
});

it('does not lose work when deleting things other records point at', function (): void {
    $t = $this;
    qaSend($t, $t->buyer, 'cart: add cable', 'post', '/cart/items', ['product_id' => $t->cable->id, 'quantity' => 1]);
    qaSend($t, $t->buyer, 'checkout', 'post', '/checkout', qaCheckout());
    qaSend($t, $t->admin, 'delete product with an open order', 'delete', "/admin/products/{$t->cable->id}");
    qaSend($t, $t->admin, 'delete warehouse holding stock', 'delete', "/admin/warehouses/{$t->branch->id}", [], 'reject');
    qaSend($t, $t->admin, 'delete default warehouse', 'delete', "/admin/warehouses/{$t->main->id}", [], 'reject');
    qaSend($t, $t->admin, 'delete used coupon', 'delete', "/admin/coupons/{$t->coupon->id}");
    $order = Order::query()->latest('id')->firstOrFail();
    qaSend($t, $t->admin, 'cancel order of deleted product', 'post', "/admin/orders/{$order->id}/status", ['status' => 'cancelled']);
});

it('treasury idempotency catch does not poison the surrounding transaction', function (): void {
    $t = $this;
    qaSend($t, $t->buyer, 'cart: add cable', 'post', '/cart/items', ['product_id' => $t->cable->id, 'quantity' => 1]);
    qaSend($t, $t->buyer, 'checkout', 'post', '/checkout', qaCheckout());
    qaSend($t, $t->admin, 'complete', 'post', '/admin/orders/'.Order::query()->latest('id')->value('id').'/status', ['status' => 'completed']);
    $payment = Payment::query()->firstOrFail();

    try {
        DB::transaction(function () use ($payment): void {
            $again = app(RecordTreasuryMovement::class)->handle(TreasuryDirection::In, TreasuryAccount::Cash, 5_000, $payment);
            expect($again)->toBeNull();
            DB::table('orders')->count(); // any later statement in the same transaction
        });
    } catch (Throwable $e) {
        qaReport('swallowed-error', 'RecordTreasuryMovement duplicate source', get_class($e).': '.strtok($e->getMessage(), "\n"));
    }
});

it('refuses LYD amounts it cannot store exactly, instead of rounding them', function (): void {
    $t = $this;
    qaSend($t, $t->buyer, 'cart: add phone', 'post', '/cart/items', ['product_id' => $t->phone->id, 'quantity' => 1]);
    qaSend($t, $t->buyer, 'checkout', 'post', '/checkout', qaCheckout());
    $order = Order::query()->latest('id')->firstOrFail();
    foreach (['12.345', '0.005', '1.999'] as $typed) {
        qaSend($t, $t->admin, "payment amount '$typed'", 'post', "/admin/orders/{$order->id}/payments", ['amount' => $typed], 'reject');
        qaSend($t, $t->admin, "treasury deposit '$typed'", 'post', '/admin/treasury', ['direction' => 'in', 'account' => 'cash', 'amount' => $typed], 'reject');
    }
    qaSend($t, $t->admin, "payment amount '12.34'", 'post', "/admin/orders/{$order->id}/payments", ['amount' => '12.34']);
});

it('agrees on revenue and cost across dashboard and reports after a partial return', function (): void {
    $t = $this;
    qaSend($t, $t->buyer, 'cart: add phone', 'post', '/cart/items', ['product_id' => $t->phone->id, 'quantity' => 1]);
    qaSend($t, $t->buyer, 'cart: add cable', 'post', '/cart/items', ['product_id' => $t->cable->id, 'quantity' => 1]);
    qaSend($t, $t->buyer, 'checkout', 'post', '/checkout', qaCheckout());
    $order = Order::query()->latest('id')->firstOrFail();
    qaSend($t, $t->admin, 'complete', 'post', "/admin/orders/{$order->id}/status", ['status' => 'completed']);
    $cableLine = $order->items()->where('product_id', $t->cable->id)->firstOrFail();
    qaSend($t, $t->admin, 'return the cable, refund 50', 'post', "/admin/orders/{$order->id}/issue", [
        'reason' => 'Cable faulty', 'amount' => 50, 'method' => 'cash', 'restock' => true, 'items' => [$cableLine->id => 1],
    ]);

    // Kept: 1,050 paid - 50 back = 1,000 revenue; cost of the phone only.
    $summary = app(ReportService::class)->salesSummary(now()->subDay()->toImmutable(), now()->addDay()->toImmutable());
    expect($summary['revenue_formatted'])->toContain('1,000.000')
        ->and($summary['estimated_cost_formatted'])->toContain('700.000');

    $t->actingAs($t->admin)->get('/admin')->assertInertia(fn ($page) => $page
        ->where('kpis.revenue_today', Money::format(100_000)));
});
