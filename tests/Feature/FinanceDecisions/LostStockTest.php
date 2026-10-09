<?php

declare(strict_types=1);

/*
 * BD3 (owner decision 2026-10-10): stolen or lost units get their own movement type,
 * so theft can be told apart from miscounts. A loss is a write-off: not a sale, not a refund.
 */

use App\Models\InventoryMovement;

require_once __DIR__.'/../FinanceAudit/FinanceAuditSetup.php';

beforeEach(fn () => finSetUp($this));

it('writes stolen units off as "lost" with who and why, and nothing financial moves', function (): void {
    $before = finSnapshot($this, $this->admin);

    finAdjust($this, $this->phone, 2, 'lost', 'Stolen from the display')->assertSessionHasNoErrors();

    finExpectChanges($before, finSnapshot($this, $this->admin), [
        'stock.PHONE@MAIN.quantity' => 8,
        'store.PHONE.available' => 8,
        'ledger.lost.rows' => 1,
        'ledger.lost.units' => -2,
        'report.valuation.units' => 59,
        'report.valuation.cost_value_formatted' => finFmt(8 * 70_000 + 50 * 2_000 + 500),
        'report.valuation.retail_value_formatted' => finFmt(8 * 100_000 + 50 * 5_000 + 2_000),
    ]);
    expect(InventoryMovement::query()->where('type', 'lost')->firstOrFail()->only(['reason', 'user_id', 'damaged_change']))
        ->toBe(['reason' => 'Stolen from the display', 'user_id' => $this->admin->id, 'damaged_change' => 0]);
});

it('treats a typed quantity as a decrease, whatever its sign', function (): void {
    finAdjust($this, $this->phone, -3, 'lost', 'Missing at count')->assertSessionHasNoErrors();

    expect(InventoryMovement::query()->where('type', 'lost')->value('quantity_change'))->toBe(-3);
});

it('refuses to lose more than is on the shelf', function (): void {
    $before = finSnapshot($this, $this->admin);

    finAdjust($this, $this->mug, 2, 'lost', 'Gone')->assertSessionHasErrors();

    finExpectChanges($before, finSnapshot($this, $this->admin), []);
});

it('offers "lost" in the stock dialog', function (): void {
    $types = $this->actingAs($this->admin)->get('/admin/inventory')->viewData('page')['props']['movementTypes'];

    expect(collect($types)->pluck('value'))->toContain('lost');
});
