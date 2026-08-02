<?php

use App\Actions\Inventory\AdjustStock;
use App\Actions\Inventory\InsufficientStockException;
use App\Actions\Inventory\TransferStock;
use App\Enums\MovementType;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Warehouse;

it('creates a ledger entry for every stock adjustment', function (): void {
    $product = Product::factory()->create();

    giveStock($product, 10);

    $movement = InventoryMovement::query()->sole();

    expect($movement->type)->toBe(MovementType::Initial)
        ->and($movement->quantity_before)->toBe(0)
        ->and($movement->quantity_change)->toBe(10)
        ->and($movement->quantity_after)->toBe(10);

    $stock = StockLevel::query()->sole();
    expect($stock->quantity)->toBe(10);
});

it('keeps the ledger consistent across multiple adjustments', function (): void {
    $product = Product::factory()->create();

    giveStock($product, 10);

    app(AdjustStock::class)->handle(defaultWarehouse(), $product, null, -3, MovementType::ManualDecrease, 'Shrinkage');

    $last = InventoryMovement::query()->latest('id')->first();

    expect($last->quantity_before)->toBe(10)
        ->and($last->quantity_after)->toBe(7)
        ->and(StockLevel::query()->sole()->quantity)->toBe(7);
});

it('refuses adjustments that would make stock negative', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 2);

    app(AdjustStock::class)->handle(defaultWarehouse(), $product, null, -5, MovementType::ManualDecrease, 'Too much');
})->throws(InsufficientStockException::class);

it('transfers stock between warehouses with paired ledger entries', function (): void {
    $product = Product::factory()->create();
    $from = defaultWarehouse();
    $to = Warehouse::factory()->create();

    giveStock($product, 10, warehouse: $from);

    app(TransferStock::class)->handle($from, $to, [
        ['product_id' => $product->id, 'product_variant_id' => null, 'quantity' => 4],
    ]);

    expect(StockLevel::query()->where('warehouse_id', $from->id)->sole()->quantity)->toBe(6)
        ->and(StockLevel::query()->where('warehouse_id', $to->id)->sole()->quantity)->toBe(4)
        ->and(InventoryMovement::query()->where('type', MovementType::TransferOut)->count())->toBe(1)
        ->and(InventoryMovement::query()->where('type', MovementType::TransferIn)->count())->toBe(1);
});

it('refuses transfers exceeding available stock', function (): void {
    $product = Product::factory()->create();
    $from = defaultWarehouse();
    $to = Warehouse::factory()->create();

    giveStock($product, 2, warehouse: $from);

    app(TransferStock::class)->handle($from, $to, [
        ['product_id' => $product->id, 'product_variant_id' => null, 'quantity' => 5],
    ]);
})->throws(InsufficientStockException::class);
