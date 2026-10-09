<?php

declare(strict_types=1);

/*
 * BD8 (owner decision 2026-10-10): a product that still has units on premises or on order
 * can't be deleted — they'd keep counting in inventory value with no product to sell them
 * under. Write off, return or sell them first.
 */

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;

require_once __DIR__.'/../FinanceAudit/FinanceAuditSetup.php';

beforeEach(fn () => finSetUp($this));

it('refuses to delete a product that still has stock, and changes nothing', function (): void {
    $before = finSnapshot($this, $this->admin);

    $this->actingAs($this->admin)->delete("/admin/products/{$this->phone->id}")->assertSessionHas('error');

    expect($this->phone->refresh()->trashed())->toBeFalse();
    finExpectChanges($before, finSnapshot($this, $this->admin), []);
});

it('refuses while units are reserved, damaged or on a sent purchase order', function (string $how): void {
    match ($how) {
        'reserved' => finBuy($this, $this->buyer, [[$this->mug, 1]]),
        'damaged' => finAdjust($this, $this->mug, 1, 'damaged', 'Cracked')->assertSessionHasNoErrors(),
        'incoming' => (function (): void {
            finAdjust($this, $this->mug, 1, 'lost', 'Stolen')->assertSessionHasNoErrors();
            $this->actingAs($this->admin)->post('/admin/purchase-orders', [
                'supplier_id' => Supplier::factory()->create()->id, 'warehouse_id' => $this->main->id,
                'items' => [['product_id' => $this->mug->id, 'quantity' => 3, 'unit_cost' => 5]],
            ])->assertSessionHasNoErrors();
            $this->actingAs($this->admin)->post('/admin/purchase-orders/'.PurchaseOrder::query()->latest('id')->value('id').'/send')->assertSessionHasNoErrors();
        })(),
    };
    expect((int) $this->mug->stockLevels()->sum('quantity'))->toBe(0);

    $this->actingAs($this->admin)->delete("/admin/products/{$this->mug->id}")->assertSessionHas('error');

    expect($this->mug->refresh()->trashed())->toBeFalse();
})->with(['reserved', 'damaged', 'incoming']);

it('deletes once the stock is gone', function (): void {
    finAdjust($this, $this->mug, 1, 'lost', 'Stolen')->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->delete("/admin/products/{$this->mug->id}")->assertSessionHas('success');

    expect($this->mug->refresh()->trashed())->toBeTrue();
});

it('bulk delete skips products with stock and says which', function (): void {
    $empty = Product::factory()->create(['sku' => 'EMPTY']);

    $this->actingAs($this->admin)->post('/admin/products/bulk', ['action' => 'delete', 'ids' => [$this->phone->id, $empty->id]])
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'Fin Phone'));

    expect($this->phone->refresh()->trashed())->toBeFalse()
        ->and($empty->refresh()->trashed())->toBeTrue();
});
