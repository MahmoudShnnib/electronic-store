<?php

declare(strict_types=1);

/*
 * BD2 (owner decision 2026-10-10): every arrival of stock keeps what was paid for it, sales
 * use up the oldest units first (FIFO), and profit uses what the sold units really cost.
 * Opening stock (finSetUp): PHONE 10 @ 700 LYD. Each test adds 5 newer phones @ 650.
 */

use App\Actions\Inventory\AdjustStock;
use App\Actions\Inventory\TransferStock;
use App\Enums\MovementType;
use App\Models\StockBatch;
use App\Models\StockLevel;
use App\Models\Warehouse;

require_once __DIR__.'/../FinanceAudit/FinanceAuditSetup.php';

beforeEach(function (): void {
    finSetUp($this);
    $this->travel(1)->days();
    app(AdjustStock::class)->handle($this->main, $this->phone, null, 5, MovementType::ManualIncrease, 'Bought at a better price', $this->admin->id, unitCostCents: 65_000);
    $this->travel(1)->days();
});

/** Units left per cost on a stock row, oldest batch first: [cost => units]. */
function fifoBatches($product, ?Warehouse $warehouse = null, $variant = null): array
{
    $level = StockLevel::query()->where('warehouse_id', ($warehouse ?? defaultWarehouse())->id)
        ->where('product_id', $product->id)->where('product_variant_id', $variant?->id)->sole();

    return StockBatch::query()->where('stock_level_id', $level->id)->where('quantity_remaining', '>', 0)
        ->orderBy('received_at')->orderBy('id')->get()
        ->map(fn (StockBatch $b) => [$b->unit_cost_cents => $b->quantity_remaining])->all();
}

it('sells the oldest units first and books what they really cost', function (): void {
    $before = finSnapshot($this, $this->admin);

    finComplete($this, finBuy($this, $this->buyer, [[$this->phone, 8]]));
    $order = finBuy($this, customer(), [[$this->phone, 4]]); // the last 2 old phones, then 2 new ones
    finComplete($this, $order);

    $line = $order->items()->sole();
    expect($line->cost_cents)->toBe(2 * 70_000 + 2 * 65_000)
        ->and($line->unit_cost_cents)->toBe(67_500)
        ->and(array_map(fn ($l) => [$l['cost'], $l['qty']], $line->cost_layers))->toBe([[70_000, 2], [65_000, 2]])
        ->and(fifoBatches($this->phone))->toBe([[65_000 => 3]]);

    $after = finSnapshot($this, $this->admin);
    expect($after['report.summary.estimated_cost_formatted'])->toBe(finFmt(10 * 70_000 + 2 * 65_000))
        ->and($after['report.summary.estimated_profit_formatted'])->toBe(finFmt(12 * 100_000 - 830_000))
        ->and($after['report.valuation.cost_value_formatted'])->toBe(finFmt(3 * 65_000 + 50 * 2_000 + 500))
        ->and($before['report.valuation.cost_value_formatted'])->toBe(finFmt(10 * 70_000 + 5 * 65_000 + 50 * 2_000 + 500))
        ->and(auditFailures())->toBe([]);
});

it('puts cancelled units back at their old cost and place, so they sell first again', function (): void {
    $order = finBuy($this, $this->buyer, [[$this->phone, 10]]);
    expect(fifoBatches($this->phone))->toBe([[65_000 => 5]]);

    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/status", ['status' => 'cancelled'])->assertSessionHasNoErrors();

    expect(fifoBatches($this->phone))->toBe([[70_000 => 10], [65_000 => 5]]);
    $next = finBuy($this, customer(), [[$this->phone, 1]]);
    expect($next->items()->sole()->cost_cents)->toBe(70_000)
        ->and(auditFailures())->toBe([]);
});

it('a whole return puts every unit back at what it cost and takes the cost out of profit', function (): void {
    finComplete($this, finBuy($this, $this->buyer, [[$this->phone, 8]]));
    $order = finBuy($this, customer(), [[$this->phone, 4]]);
    finComplete($this, $order);

    finReturn($this, $order, 400_000, ['PHONE' => 4]);

    $after = finSnapshot($this, $this->admin);
    expect(fifoBatches($this->phone))->toBe([[70_000 => 2], [65_000 => 3], [65_000 => 2]])
        ->and($after['report.summary.estimated_cost_formatted'])->toBe(finFmt(8 * 70_000))
        ->and($after['report.valuation.cost_value_formatted'])->toBe(finFmt(2 * 70_000 + 5 * 65_000 + 50 * 2_000 + 500))
        ->and(auditFailures())->toBe([]);
});

it('moves units between warehouses with their cost and age', function (): void {
    $branch = Warehouse::factory()->create(['is_active' => true]);

    app(TransferStock::class)->handle($this->main, $branch, [['product_id' => $this->phone->id, 'quantity' => 12]], userId: $this->admin->id);

    expect(fifoBatches($this->phone))->toBe([[65_000 => 3]])
        ->and(fifoBatches($this->phone, $branch))->toBe([[70_000 => 10], [65_000 => 2]])
        ->and(StockBatch::query()->where('unit_cost_cents', 70_000)->where('quantity_remaining', 10)->sole()->received_at->toDateString())->toBe('2026-10-05')
        ->and(finSnapshot($this, $this->admin)['report.valuation.cost_value_formatted'])->toBe(finFmt(10 * 70_000 + 5 * 65_000 + 50 * 2_000 + 500))
        ->and(auditFailures())->toBe([]);
});

it('writes off the oldest units first', function (): void {
    finAdjust($this, $this->phone, 3, 'lost', 'Stolen from the display')->assertSessionHasNoErrors();
    finAdjust($this, $this->phone, 8, 'damaged', 'Water damage')->assertSessionHasNoErrors();

    expect(fifoBatches($this->phone))->toBe([[65_000 => 4]])
        ->and(finSnapshot($this, $this->admin)['report.valuation.cost_value_formatted'])->toBe(finFmt(4 * 65_000 + 50 * 2_000 + 500))
        ->and(auditFailures())->toBe([]);
});

it('carries the costs onto the variants when a stocked product gains them', function (): void {
    $this->actingAs($this->admin)->put(route('admin.products.update', $this->phone), [
        'name' => $this->phone->name, 'sku' => $this->phone->sku, 'category_id' => $this->phone->category_id,
        'cost_price' => 700, 'price' => 1000, 'status' => 'active',
        'variants' => [
            ['name' => 'Red', 'sku' => 'PHONE-RED', 'price' => 1000],
            ['name' => 'Blue', 'sku' => 'PHONE-BLUE', 'price' => 1000],
        ],
        'stock_split' => [
            ['warehouse_id' => $this->main->id, 'sku' => 'PHONE-RED', 'quantity' => 8],
            ['warehouse_id' => $this->main->id, 'sku' => 'PHONE-BLUE', 'quantity' => 7],
        ],
    ])->assertSessionHasNoErrors();

    $red = $this->phone->variants()->where('sku', 'PHONE-RED')->sole();
    $blue = $this->phone->variants()->where('sku', 'PHONE-BLUE')->sole();
    expect(fifoBatches($this->phone, null, $red))->toBe([[70_000 => 8]])
        ->and(fifoBatches($this->phone, null, $blue))->toBe([[70_000 => 2], [65_000 => 5]])
        ->and(auditFailures())->toBe([]);
});

it('counts a paid order as sold even before delivery: its cost leaves stock value and enters profit', function (): void {
    $order = finBuy($this, $this->buyer, [[$this->phone, 1]]);
    $open = finSnapshot($this, $this->admin);

    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/payments", ['amount' => 1000])->assertSessionHasNoErrors();
    $paid = finSnapshot($this, $this->admin);
    finComplete($this, $order);
    $done = finSnapshot($this, $this->admin);

    $owned = fn (int $phones) => finFmt($phones + 50 * 2_000 + 500);
    expect($open['report.valuation.cost_value_formatted'])->toBe($owned(10 * 70_000 + 5 * 65_000))
        ->and($paid['report.valuation.cost_value_formatted'])->toBe($owned(9 * 70_000 + 5 * 65_000))
        ->and($paid['report.summary.estimated_cost_formatted'])->toBe(finFmt(70_000))
        ->and($done['report.valuation.cost_value_formatted'])->toBe($paid['report.valuation.cost_value_formatted'])
        ->and($done['report.summary.estimated_cost_formatted'])->toBe(finFmt(70_000))
        ->and(auditFailures())->toBe([]);
});

it('takes the unit cost typed in the stock dialog, and prefills it only for staff who can add stock', function (): void {
    finAdjust($this, $this->phone, 4, 'manual_increase', 'New delivery', ['unit_cost' => 612.5])->assertSessionHasNoErrors();
    finAdjust($this, $this->phone, 1, 'manual_increase', 'Bad cost', ['unit_cost' => -1])->assertSessionHasErrors('unit_cost');

    expect(fifoBatches($this->phone))->toBe([[70_000 => 10], [65_000 => 5], [61_250 => 4]])
        ->and(auditFailures())->toBe([]);

    $row = fn ($user) => collect($this->actingAs($user)->get('/admin/inventory')->viewData('page')['props']['levels']['data'])
        ->firstWhere('sku', 'PHONE');
    expect($row($this->admin)['unit_cost'])->toEqual(700)
        ->and($row(staff('cashier')))->not->toHaveKey('unit_cost');
});
