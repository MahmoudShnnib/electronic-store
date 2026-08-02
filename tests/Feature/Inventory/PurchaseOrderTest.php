<?php

use App\Actions\Purchasing\ReceivePurchaseOrder;
use App\Actions\Purchasing\SendPurchaseOrder;
use App\Enums\MovementType;
use App\Enums\PurchaseOrderStatus;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Support\ReferenceNumber;

function makePo(Product $product, int $quantity = 10): PurchaseOrder
{
    $po = PurchaseOrder::create([
        'po_number' => ReferenceNumber::generate('PO'),
        'supplier_id' => Supplier::factory()->create()->id,
        'warehouse_id' => defaultWarehouse()->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    $po->items()->create([
        'product_id' => $product->id,
        'quantity_ordered' => $quantity,
        'unit_cost_cents' => 10_000,
        'line_total_cents' => 10_000 * $quantity,
    ]);

    return $po;
}

it('marks ordered quantities as incoming when a PO is sent', function (): void {
    $product = Product::factory()->create();
    $po = makePo($product, 10);

    app(SendPurchaseOrder::class)->handle($po);

    expect($po->refresh()->status)->toBe(PurchaseOrderStatus::Sent)
        ->and(StockLevel::query()->sole()->incoming)->toBe(10);
});

it('receives a purchase order fully and creates purchase_receipt movements', function (): void {
    $product = Product::factory()->create();
    $po = makePo($product, 10);
    app(SendPurchaseOrder::class)->handle($po);

    app(ReceivePurchaseOrder::class)->handle($po, [$po->items->first()->id => 10]);

    $stock = StockLevel::query()->sole();

    expect($po->refresh()->status)->toBe(PurchaseOrderStatus::Received)
        ->and($stock->quantity)->toBe(10)
        ->and($stock->incoming)->toBe(0)
        ->and(InventoryMovement::query()->where('type', MovementType::PurchaseReceipt)->count())->toBe(1);
});

it('supports partial receiving', function (): void {
    $product = Product::factory()->create();
    $po = makePo($product, 10);
    app(SendPurchaseOrder::class)->handle($po);

    app(ReceivePurchaseOrder::class)->handle($po, [$po->items->first()->id => 4]);

    expect($po->refresh()->status)->toBe(PurchaseOrderStatus::PartiallyReceived)
        ->and(StockLevel::query()->sole()->quantity)->toBe(4)
        ->and($po->items->first()->outstandingQuantity())->toBe(6);

    app(ReceivePurchaseOrder::class)->handle($po, [$po->items()->first()->id => 6]);

    expect($po->refresh()->status)->toBe(PurchaseOrderStatus::Received);
});

it('rejects receiving more than outstanding', function (): void {
    $product = Product::factory()->create();
    $po = makePo($product, 5);
    app(SendPurchaseOrder::class)->handle($po);

    app(ReceivePurchaseOrder::class)->handle($po, [$po->items->first()->id => 6]);
})->throws(InvalidArgumentException::class);
