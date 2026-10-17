<?php

use App\Actions\Treasury\RecordTreasuryMovement;
use App\Enums\TreasuryAccount;
use App\Enums\TreasuryDirection;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Services\Admin\TreasuryQueryService;
use App\Support\Money;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../../../audit/invariants.php';

/** Creates, sends and fully or partly receives a PO through the admin screens. */
function supplierPo($test, Supplier $supplier, array $items, float $shipping = 0, float $discount = 0): PurchaseOrder
{
    $test->actingAs($test->admin)->post(route('admin.purchase-orders.store'), [
        'supplier_id' => $supplier->id, 'warehouse_id' => defaultWarehouse()->id,
        'shipping' => $shipping, 'discount' => $discount, 'items' => $items,
    ])->assertSessionHasNoErrors();
    $po = PurchaseOrder::query()->latest('id')->firstOrFail();
    $test->actingAs($test->admin)->post(route('admin.purchase-orders.send', $po))->assertSessionHasNoErrors();

    return $po->refresh();
}

function receivePo($test, PurchaseOrder $po, array $quantities): void
{
    $test->actingAs($test->admin)->post(route('admin.purchase-orders.receive', $po), ['quantities' => $quantities])->assertSessionHasNoErrors();
}

function batchTotal(): int
{
    return (int) StockBatch::query()->selectRaw('COALESCE(SUM(unit_cost_cents * quantity_received),0) AS t')->value('t');
}

beforeEach(function (): void {
    $this->admin = staff('admin');
    $this->supplier = Supplier::factory()->create(['company_name' => 'Mahmoud Trading']);
});

it('books every cent of shipping into the stock and the debt, however small the unit price', function (): void {
    $cable = Product::factory()->create(['cost_price_cents' => 40]);
    $po = supplierPo($this, $this->supplier, [['product_id' => $cable->id, 'quantity' => 1000, 'unit_cost' => 0.50]], shipping: 4);

    receivePo($this, $po, [$po->items()->first()->id => 1000]);

    // 500.00 of cables + 4.00 shipping. Rounding each unit used to book 500.00.
    expect(batchTotal())->toBe(50_400)
        ->and($this->supplier->balanceCents())->toBe(50_400)
        // 400 units carry the extra cent, 600 don't: 400 × 0.51 + 600 × 0.50.
        ->and(StockBatch::query()->orderByDesc('unit_cost_cents')->pluck('quantity_received', 'unit_cost_cents')->all())->toBe([51 => 400, 50 => 600]);
});

it('spreads a discount exactly, and partial receipts add up to the order total', function (): void {
    $a = Product::factory()->create();
    $b = Product::factory()->create();
    $po = supplierPo($this, $this->supplier, [
        ['product_id' => $a->id, 'quantity' => 1000, 'unit_cost' => 0.10],
        ['product_id' => $b->id, 'quantity' => 3, 'unit_cost' => 100],
    ], shipping: 0.10, discount: 4.99);
    [$lineA, $lineB] = $po->items()->orderBy('id')->get()->all();

    receivePo($this, $po, [$lineA->id => 333, $lineB->id => 1]);
    receivePo($this, $po, [$lineA->id => 667, $lineB->id => 2]);

    // 100.00 + 300.00 − 4.99 + 0.10, to the cent, in stock and in what we owe.
    expect($po->refresh()->total_cents)->toBe(39_511)
        ->and(batchTotal())->toBe(39_511)
        ->and($this->supplier->balanceCents())->toBe(39_511)
        ->and(SupplierTransaction::query()->where('type', 'purchase')->count())->toBe(2);
});

it('keeps an account for a supplier who never signs in: opening debt, credit, payment', function (): void {
    app(RecordTreasuryMovement::class)->handle(TreasuryDirection::In, TreasuryAccount::Cash, 100_000, note: 'Float', userId: $this->admin->id);

    // "We already owe Mahmoud 500" — from before the system, no order, no money moving.
    $this->actingAs($this->admin)->post(route('admin.suppliers.adjustments.store', $this->supplier), [
        'direction' => 'debt', 'amount' => 500, 'note' => 'Opening balance',
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('admin.suppliers.adjustments.store', $this->supplier), [
        'direction' => 'credit', 'amount' => 50, 'note' => '',
    ])->assertSessionHasErrors('note');
    $this->actingAs($this->admin)->post(route('admin.suppliers.adjustments.store', $this->supplier), [
        'direction' => 'credit', 'amount' => 50, 'note' => 'Credit note: two returned headsets',
    ])->assertSessionHasNoErrors();

    expect($this->supplier->balanceCents())->toBe(45_000)
        ->and(app(TreasuryQueryService::class)->balances()['cash'])->toBe(100_000);

    $this->actingAs($this->admin)->post(route('admin.suppliers.payments.store', $this->supplier), [
        'amount' => 300, 'account' => 'cash', 'reference' => 'Receipt 17',
    ])->assertSessionHasNoErrors();

    // One action, two ledgers: we owe 300 less, and 300 left the drawer.
    expect($this->supplier->balanceCents())->toBe(15_000)
        ->and(app(TreasuryQueryService::class)->balances()['cash'])->toBe(70_000)
        ->and(auditFailures())->not->toHaveKey('P1');

    // Money that isn't there can't be paid out.
    $this->actingAs($this->admin)->post(route('admin.suppliers.payments.store', $this->supplier), [
        'amount' => 800, 'account' => 'cash',
    ])->assertSessionHasErrors('amount');

    $this->actingAs($this->admin)->get(route('admin.suppliers.show', $this->supplier))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/suppliers/Show')
            ->where('totals.balance_cents', 15_000)
            ->has('transactions.data', 3)
            // Newest first, each with the balance it left: 500 − 50 − 300 = 150.
            ->where('transactions.data.0.type', 'payment')
            ->where('transactions.data.0.balance_after_formatted', Money::format(15_000))
            ->where('canRecord', true));

    $this->actingAs($this->admin)->get(route('admin.suppliers.index'))
        ->assertInertia(fn (Assert $page) => $page->where('suppliers.data.0.balance_cents', 15_000));
});

it('lets an inventory manager read a supplier account but not pay or write debts', function (): void {
    $manager = staff('inventory_manager');

    $this->actingAs($manager)->get(route('admin.suppliers.show', $this->supplier))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canRecord', false));
    $this->actingAs($manager)->post(route('admin.suppliers.payments.store', $this->supplier), ['amount' => 1, 'account' => 'cash'])->assertForbidden();
    $this->actingAs($manager)->post(route('admin.suppliers.adjustments.store', $this->supplier), ['direction' => 'debt', 'amount' => 1, 'note' => 'x'])->assertForbidden();
});
