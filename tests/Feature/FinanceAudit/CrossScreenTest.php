<?php

declare(strict_types=1);

/*
 * Finance audit X1/X2: two screens that read the same money must agree.
 */

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Reports\ReportService;

require_once __DIR__.'/FinanceAuditSetup.php';

beforeEach(fn () => finSetUp($this));

it('X1: a variant without its own cost is valued at the product cost, like the sale-time cost snapshot', function (): void {
    $laptop = Product::factory()->withVariants()->create(['sku' => 'LAPTOP', 'name' => 'Fin Laptop', 'price_cents' => 300_000, 'cost_price_cents' => 200_000, 'sale_price_cents' => null]);
    // Saved from the product form without a cost: SaveProduct stores 0.
    $v = ProductVariant::factory()->create(['product_id' => $laptop->id, 'sku' => 'LAPTOP-16', 'price_cents' => 300_000, 'cost_price_cents' => 0, 'is_active' => true]);
    $this->actingAs($this->admin)->post('/admin/inventory/adjust', [
        'warehouse_id' => $this->main->id, 'product_id' => $laptop->id, 'product_variant_id' => $v->id, 'quantity_change' => 2, 'type' => 'initial', 'reason' => 'Opening stock',
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->buyer)->post('/cart/items', ['product_id' => $laptop->id, 'variant_id' => $v->id, 'quantity' => 1])->assertSessionHasNoErrors();
    $this->actingAs($this->buyer)->post('/checkout', finCheckoutPayload($this->buyer))->assertSessionHasNoErrors();
    $line = OrderItem::query()->where('product_variant_id', $v->id)->firstOrFail();

    // The sale recorded the unit at the product's cost …
    expect((int) $line->unit_cost_cents)->toBe(200_000);
    // … so the 2 laptops on hand (1 shelf + 1 reserved) are worth 2 × 2,000 at cost, on top of the fixtures.
    expect(app(ReportService::class)->inventoryValuation()['cost_value_formatted'])
        ->toBe(finFmt(2 * 200_000 + 10 * 70_000 + 50 * 2_000 + 500));
});
