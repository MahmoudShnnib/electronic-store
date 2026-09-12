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
use Illuminate\Support\Facades\Mail;

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

it('sends, receives and cancels a multi-line purchase order over HTTP', function (): void {
    // Two lines on purpose: Eloquent only arms preventLazyLoading on models
    // hydrated as part of a collection of more than one, so a single-item PO
    // never reproduced the LazyLoadingViolation that 500'd all three actions.
    $po = makePo(Product::factory()->create(), 10);
    $po->items()->create([
        'product_id' => Product::factory()->create()->id,
        'quantity_ordered' => 5,
        'unit_cost_cents' => 20_000,
        'line_total_cents' => 100_000,
    ]);

    $line = $po->items()->orderBy('id')->first();
    $manager = staff('inventory_manager');

    $this->actingAs($manager)
        ->post(route('admin.purchase-orders.send', $po))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($manager)
        ->post(route('admin.purchase-orders.receive', $po), ['quantities' => [$line->id => 4]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(StockLevel::query()->where('product_id', $line->product_id)->sole()->quantity)->toBe(4);

    $this->actingAs($manager)
        ->post(route('admin.purchase-orders.cancel', $po))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($po->refresh()->status)->toBe(PurchaseOrderStatus::Cancelled)
        ->and(StockLevel::query()->sum('incoming'))->toBe(0);
});

it('rejects receiving more than outstanding', function (): void {
    $product = Product::factory()->create();
    $po = makePo($product, 5);
    app(SendPurchaseOrder::class)->handle($po);

    app(ReceivePurchaseOrder::class)->handle($po, [$po->items->first()->id => 6]);
})->throws(InvalidArgumentException::class);

// -- Sending records that the order was placed; nothing is mailed -------------

it('stamps when the order was sent and books the incoming stock', function (): void {
    Mail::fake();

    $po = makePo(Product::factory()->create(), 10);

    app(SendPurchaseOrder::class)->handle($po, staff('inventory_manager')->id);

    Mail::assertNothingQueued();

    expect($po->refresh()->status)->toBe(PurchaseOrderStatus::Sent)
        ->and($po->sent_at)->not->toBeNull()
        ->and(StockLevel::query()->sole()->incoming)->toBe(10);
});

it('refuses to send a purchase order that is already sent', function (): void {
    $po = makePo(Product::factory()->create(), 10);

    app(SendPurchaseOrder::class)->handle($po);
    app(SendPurchaseOrder::class)->handle($po->refresh());
})->throws(InvalidArgumentException::class);

it('renders the printable purchase order', function (): void {
    $po = makePo(Product::factory()->create(), 3);

    $this->actingAs(staff('inventory_manager'))
        ->get(route('admin.purchase-orders.print', $po))
        ->assertOk();
});
