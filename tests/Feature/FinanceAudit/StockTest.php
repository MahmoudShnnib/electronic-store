<?php

declare(strict_types=1);

/*
 * Finance audit H7, H8, H13, F7: stock coming in and going out without a sale.
 */

use App\Models\Cart;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/FinanceAuditSetup.php';

beforeEach(fn () => finSetUp($this));

it('H7: adding stock raises the shelf, the ledger and the valuation — and nothing financial', function (): void {
    $before = finSnapshot($this, $this->admin);

    finAdjust($this, $this->phone, 5, 'manual_increase', 'Found in back room')->assertSessionHasNoErrors();

    finExpectChanges($before, finSnapshot($this, $this->admin), [
        'stock.PHONE@MAIN.quantity' => 15,
        'store.PHONE.available' => 15,
        'ledger.manual_increase.rows' => 1,
        'ledger.manual_increase.units' => 5,
        'report.valuation.units' => 66,
        'report.valuation.cost_value_formatted' => finFmt(15 * 70_000 + 50 * 2_000 + 500),
        'report.valuation.retail_value_formatted' => finFmt(15 * 100_000 + 50 * 5_000 + 2_000),
    ]);
});

it('H8: receiving stock while orders are pending leaves their reservations alone', function (): void {
    $pending = finBuy($this, $this->buyer, [[$this->phone, 2]]);
    $supplier = Supplier::factory()->create();
    $this->actingAs($this->admin)->post('/admin/purchase-orders', [
        'supplier_id' => $supplier->id, 'warehouse_id' => $this->main->id,
        'items' => [['product_id' => $this->phone->id, 'quantity' => 5, 'unit_cost' => 650]],
    ])->assertSessionHasNoErrors();
    $po = PurchaseOrder::query()->latest('id')->with('items')->firstOrFail();
    $this->actingAs($this->admin)->post("/admin/purchase-orders/{$po->id}/send")->assertSessionHasNoErrors();
    $before = finSnapshot($this, $this->admin);

    $this->actingAs($this->admin)->post("/admin/purchase-orders/{$po->id}/receive", ['quantities' => [$po->items[0]->id => 5]])
        ->assertSessionHasNoErrors();

    finExpectChanges($before, finSnapshot($this, $this->admin), [
        'stock.PHONE@MAIN.quantity' => 13,
        'stock.PHONE@MAIN.incoming' => 0,
        'store.PHONE.available' => 13,
        'ledger.purchase_receipt.rows' => 1,
        'ledger.purchase_receipt.units' => 5,
        // 13 on the shelf + 2 reserved = 15 phones on hand: the 10 older ones at 700, the
        // 5 just received at the 650 paid on this PO (FIFO batches, BD2).
        'report.valuation.units' => 66,
        'report.valuation.cost_value_formatted' => finFmt(10 * 70_000 + 5 * 65_000 + 50 * 2_000 + 500),
        'report.valuation.retail_value_formatted' => finFmt(15 * 100_000 + 50 * 5_000 + 2_000),
    ]);

    // The pending order still completes against its own reservation.
    $before = finSnapshot($this, $this->admin);
    finComplete($this, $pending);
    $after = finSnapshot($this, $this->admin);
    expect($after['stock.PHONE@MAIN.reserved'])->toBe(0)
        ->and($after['stock.PHONE@MAIN.quantity'])->toBe($before['stock.PHONE@MAIN.quantity'])
        ->and(auditFailures())->toBe([]);
});

it('H13: a damaged write-off removes sellable stock and value, but is not a sale or refund', function (): void {
    $before = finSnapshot($this, $this->admin);

    finAdjust($this, $this->phone, 2, 'damaged', 'Dropped by courier')->assertSessionHasNoErrors();

    finExpectChanges($before, finSnapshot($this, $this->admin), [
        'stock.PHONE@MAIN.quantity' => 8,
        'stock.PHONE@MAIN.damaged' => 2,
        'store.PHONE.available' => 8,
        'ledger.damaged.rows' => 1,
        'ledger.damaged.units' => -2,
        'report.writeoffs.total' => finFmt(2 * 70_000), // what the damaged phones cost (FIFO)
        'report.valuation.units' => 59,
        'report.valuation.cost_value_formatted' => finFmt(8 * 70_000 + 50 * 2_000 + 500),
        'report.valuation.retail_value_formatted' => finFmt(8 * 100_000 + 50 * 5_000 + 2_000),
    ]);
});

it('H13: a stolen unit is written off as a manual decrease with its reason', function (): void {
    $before = finSnapshot($this, $this->admin);

    finAdjust($this, $this->phone, 1, 'manual_decrease', 'Stolen from display')->assertSessionHasNoErrors();

    finExpectChanges($before, finSnapshot($this, $this->admin), [
        'stock.PHONE@MAIN.quantity' => 9,
        'store.PHONE.available' => 9,
        'ledger.manual_decrease.rows' => 1,
        'ledger.manual_decrease.units' => -1,
        'report.writeoffs.total' => finFmt(70_000),
        'report.valuation.units' => 60,
        'report.valuation.cost_value_formatted' => finFmt(9 * 70_000 + 50 * 2_000 + 500),
        'report.valuation.retail_value_formatted' => finFmt(9 * 100_000 + 50 * 5_000 + 2_000),
    ]);
    expect(InventoryMovement::query()->where('type', 'manual_decrease')->value('reason'))->toBe('Stolen from display');
});

it('H13: writing off the unit sitting in a cart makes that checkout fail cleanly', function (): void {
    $this->actingAs($this->buyer)->post('/cart/items', ['product_id' => $this->mug->id, 'quantity' => 1])->assertSessionHasNoErrors();
    $before = finSnapshot($this, $this->admin);

    finAdjust($this, $this->mug, 1, 'damaged', 'Cracked on the shelf')->assertSessionHasNoErrors();

    $afterWriteOff = finSnapshot($this, $this->admin);
    finExpectChanges($before, $afterWriteOff, [
        'stock.MUG@MAIN.quantity' => 0,
        'stock.MUG@MAIN.damaged' => 1,
        'store.MUG.available' => 0,
        'ledger.damaged.rows' => 1,
        'ledger.damaged.units' => -1,
        'report.writeoffs.total' => finFmt(500),
        'report.valuation.units' => 60,
        'report.valuation.cost_value_formatted' => finFmt(10 * 70_000 + 50 * 2_000),
        'report.valuation.retail_value_formatted' => finFmt(10 * 100_000 + 50 * 5_000),
        'dash.out_of_stock' => 1,
        'dash.low_stock' => 0,
    ]);

    $this->actingAs($this->buyer)->post('/checkout', finCheckoutPayload($this->buyer))->assertSessionHasErrors('cart');

    finExpectChanges($afterWriteOff, finSnapshot($this, $this->admin), []);
    expect(Cart::query()->where('user_id', $this->buyer->id)->first()?->items()->count())->toBe(1); // still there for the customer to remove
});

it('F7: writing off more than is on the shelf is refused and changes nothing', function (string $type, int $qty, array $extra): void {
    finBuy($this, $this->buyer, [[$this->phone, 8]]); // 2 left on the shelf, 8 reserved for the customer
    $before = finSnapshot($this, $this->admin);

    finAdjust($this, $this->phone, $qty, $type, 'Too many', $extra)->assertSessionHasErrors();

    finExpectChanges($before, finSnapshot($this, $this->admin), []);
})->with([
    'damaged beyond stock' => ['damaged', 3, []],
    'stolen beyond stock' => ['manual_decrease', 3, []],
    'negative increase beyond stock' => ['manual_increase', -3, []],
    'correction below zero' => ['correction', -3, []],
    'supplier return beyond stock' => ['supplier_return', 3, []],
    'supplier return from damaged with none damaged' => ['supplier_return', 3, ['from_damaged' => true]],
]);

it('H13: damaged units sent back to the supplier leave a ledger record of how many', function (): void {
    finAdjust($this, $this->phone, 2, 'damaged', 'Dead on arrival')->assertSessionHasNoErrors();
    $before = finSnapshot($this, $this->admin);

    finAdjust($this, $this->phone, 2, 'supplier_return', 'Sent back to supplier', ['from_damaged' => true])->assertSessionHasNoErrors();

    finExpectChanges($before, finSnapshot($this, $this->admin), [
        'stock.PHONE@MAIN.damaged' => 0,
        'ledger.supplier_return.rows' => 1,
        'ledger.supplier_return.units' => 0, // the shelf didn't move: the units came out of damaged
    ]);
    // Who, when, why — and how many: the damaged count must be reconstructible from the ledger.
    $row = InventoryMovement::query()->where('type', 'supplier_return')->firstOrFail();
    expect($row->reason)->toBe('Sent back to supplier')
        ->and($row->user_id)->toBe($this->admin->id)
        ->and((int) DB::table('inventory_movements')->where('product_id', $this->phone->id)->sum('damaged_change'))
        ->toBe(0);
    expect((int) DB::table('inventory_movements')->where('id', $row->id)->value('damaged_change'))->toBe(-2);
});
