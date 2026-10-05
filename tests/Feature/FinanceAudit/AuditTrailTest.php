<?php

declare(strict_types=1);
use App\Models\TreasuryTransaction;

/*
 * Finance audit, invariant A2: every change to money says who and why. A manual cash or
 * bank movement without a reason would leave money moving with no explanation.
 */

require_once __DIR__.'/FinanceAuditSetup.php';

beforeEach(fn () => finSetUp($this));

it('refuses a manual treasury movement that gives no reason', function (?string $note): void {
    $before = finSnapshot($this, $this->admin);

    $this->actingAs($this->admin)->post('/admin/treasury', array_filter([
        'direction' => 'in', 'account' => 'cash', 'amount' => 50, 'note' => $note,
    ], fn ($v) => $v !== null))->assertSessionHasErrors('note');

    finExpectChanges($before, finSnapshot($this, $this->admin), []);
})->with(['missing' => [null], 'blank' => ['   ']]);

it('records a manual treasury movement with who and why', function (): void {
    $before = finSnapshot($this, $this->admin);

    $this->actingAs($this->admin)->post('/admin/treasury', ['direction' => 'in', 'account' => 'cash', 'amount' => 50, 'note' => 'Opening float'])
        ->assertSessionHasNoErrors();

    finExpectChanges($before, finSnapshot($this, $this->admin), ['treasury.cash' => 5_000, 'treasury.rows' => 1]);
    expect(TreasuryTransaction::query()->firstOrFail()->only(['created_by', 'note']))
        ->toBe(['created_by' => $this->admin->id, 'note' => 'Opening float']);
});
