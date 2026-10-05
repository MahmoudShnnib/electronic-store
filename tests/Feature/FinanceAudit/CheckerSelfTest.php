<?php

declare(strict_types=1);

/*
 * The invariant checker must actually catch drift: corrupt one place by hand (test DB
 * only) and expect the matching rule to fail.
 */

use App\Models\Order;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/FinanceAuditSetup.php';

beforeEach(fn () => finSetUp($this));

it('passes on a consistent shop and flags each kind of drift', function (string $rule, Closure $corrupt): void {
    $order = finBuy($this, $this->buyer, [[$this->phone, 2]]);
    expect(auditFailures())->toBe([]);

    $corrupt($order);

    expect(array_keys(auditFailures()))->toContain($rule);
})->with([
    'shelf off the ledger' => ['S1', fn () => DB::table('stock_levels')->increment('quantity')],
    'negative stock' => ['S3', fn () => DB::table('stock_levels')->update(['damaged' => -1])],
    'damaged off the ledger' => ['S9', fn () => DB::table('stock_levels')->increment('damaged')],
    'reservation drift' => ['S4', fn () => DB::table('stock_levels')->increment('reserved')],
    'line total wrong' => ['O1', fn (Order $o) => DB::table('order_items')->where('order_id', $o->id)->increment('line_total_cents')],
    'order total wrong' => ['O2', fn (Order $o) => DB::table('orders')->where('id', $o->id)->increment('total_cents')],
    'payment without treasury leg' => ['M5', fn (Order $o) => DB::table('payments')->insert([
        'order_id' => $o->id, 'method' => 'cod', 'status' => 'confirmed', 'amount_cents' => 100, 'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ])],
    'payment status lies' => ['M2', fn (Order $o) => DB::table('orders')->where('id', $o->id)->update(['payment_status' => 'paid'])],
    'cancelled without stock back' => ['S7', fn (Order $o) => DB::table('orders')->where('id', $o->id)->update(['status' => 'cancelled', 'cancelled_at' => now()])],
    'manual movement without reason' => ['A1', fn () => DB::table('inventory_movements')->where('type', 'initial')->update(['reason' => null])],
]);
