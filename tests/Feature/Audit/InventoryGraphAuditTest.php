<?php

/*
 * Audit 2026-10-01 — inventory ledger integrity across every writer.
 * Each test asserts the CORRECT business behaviour, so a failing test is a live bug.
 */

use App\Actions\Cart\AddToCart;
use App\Actions\Checkout\PlaceOrder;
use App\Actions\Inventory\AdjustStock;
use App\Actions\Inventory\TransferStock;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\CreateManualOrder;
use App\Actions\Orders\UpdateOrderStatus;
use App\Actions\Pos\CompleteSale;
use App\Actions\Pos\OpenShift;
use App\Actions\Purchasing\CancelPurchaseOrder;
use App\Actions\Purchasing\ReceivePurchaseOrder;
use App\Actions\Purchasing\SavePurchaseOrder;
use App\Actions\Purchasing\SendPurchaseOrder;
use App\DTOs\CheckoutData;
use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Reports\ReportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Notification::fake());

/** Every violation of the four ledger invariants, as readable strings. */
function invViolations(): array
{
    $bad = [];

    foreach (StockLevel::query()->orderBy('id')->get() as $s) {
        $key = "wh{$s->warehouse_id}/p{$s->product_id}/v".($s->product_variant_id ?? '-');
        $match = fn ($q, string $prefix) => $q
            ->where("{$prefix}warehouse_id", $s->warehouse_id)
            ->where("{$prefix}product_id", $s->product_id)
            ->whereRaw("{$prefix}product_variant_id IS NOT DISTINCT FROM ?", [$s->product_variant_id]);

        $ledger = (int) $match(DB::table('inventory_movements'), '')->sum('quantity_change');
        if ($ledger !== $s->quantity) {
            $bad[] = "$key quantity {$s->quantity} != ledger {$ledger}";
        }

        $reserved = (int) $match(DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', OrderStatus::InProgress->value)
            ->whereNull('orders.deleted_at'), 'order_items.')->sum('order_items.quantity');
        if ($reserved !== $s->reserved) {
            $bad[] = "$key reserved {$s->reserved} != open orders {$reserved}";
        }

        $incoming = (int) DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->whereIn('purchase_orders.status', ['sent', 'partially_received'])
            ->whereNull('purchase_orders.deleted_at')
            ->where('purchase_orders.warehouse_id', $s->warehouse_id)
            ->where('purchase_order_items.product_id', $s->product_id)
            ->whereRaw('purchase_order_items.product_variant_id IS NOT DISTINCT FROM ?', [$s->product_variant_id])
            ->sum(DB::raw('GREATEST(quantity_ordered - quantity_received, 0)'));
        if ($incoming !== $s->incoming) {
            $bad[] = "$key incoming {$s->incoming} != outstanding POs {$incoming}";
        }

        foreach (['quantity', 'reserved', 'damaged', 'incoming'] as $col) {
            if ($s->{$col} < 0) {
                $bad[] = "$key negative {$col} {$s->{$col}}";
            }
        }

        // quantity_before/after must chain and end on the row's quantity.
        $prev = 0;
        foreach ($match(DB::table('inventory_movements'), '')->orderBy('id')->get() as $m) {
            if ((int) $m->quantity_before !== $prev || (int) $m->quantity_after !== $m->quantity_before + $m->quantity_change) {
                $bad[] = "$key movement {$m->id} chain broken (before {$m->quantity_before}, expected {$prev})";
            }
            $prev = (int) $m->quantity_after;
        }
        if ($prev !== $s->quantity) {
            $bad[] = "$key last quantity_after {$prev} != row {$s->quantity}";
        }
    }

    return $bad;
}

function invCheckout(): CheckoutData
{
    return new CheckoutData(
        customer: ['name' => 'Inv Buyer', 'email' => 'inv@example.test', 'phone' => '091'],
        shippingAddress: ['name' => 'Inv Buyer', 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        billingAddress: ['name' => 'Inv Buyer', 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        paymentMethod: PaymentMethod::CashOnDelivery,
    );
}

function invOrder(Product $product, int $qty, ?ProductVariant $variant = null, ?User $user = null): Order
{
    $user ??= customer();
    $cart = Cart::create(['user_id' => $user->id]);
    app(AddToCart::class)->handle($cart, $product, $variant, $qty);

    return app(PlaceOrder::class)->handle($cart->refresh(), invCheckout(), $user);
}

function invPo(Warehouse $warehouse, array $lines): PurchaseOrder
{
    return app(SavePurchaseOrder::class)->handle(null, [
        'supplier_id' => Supplier::factory()->create()->id,
        'warehouse_id' => $warehouse->id,
        'items' => array_map(fn ($l) => [
            'product_id' => $l[0]->id, 'product_variant_id' => $l[2]?->id ?? null, 'quantity' => $l[1], 'unit_cost' => 5,
        ], $lines),
    ], staff('admin')->id);
}

function invQty(Product $product, ?Warehouse $warehouse = null, ?ProductVariant $variant = null): StockLevel
{
    return StockLevel::query()
        ->where('warehouse_id', ($warehouse ?? defaultWarehouse())->id)
        ->where('product_id', $product->id)
        ->where('product_variant_id', $variant?->id)
        ->firstOrFail();
}

/* ------------------------------------------------------------------ flows */

it('INV-1: checkout then complete keeps the ledger consistent', function (): void {
    $p = Product::factory()->create();
    giveStock($p, 10);
    $keep = invOrder($p, 2);
    $done = invOrder($p, 3);
    app(UpdateOrderStatus::class)->handle($done->refresh(), OrderStatus::Completed);

    expect(invQty($p)->quantity)->toBe(5)->and(invQty($p)->reserved)->toBe(2)
        ->and(invViolations())->toBe([]);
});

it('INV-2: checkout then cancel returns stock and the reservation', function (): void {
    $p = Product::factory()->create();
    giveStock($p, 10);
    app(CancelOrder::class)->handle(invOrder($p, 4)->refresh(), 'changed mind');

    expect(invQty($p)->quantity)->toBe(10)->and(invQty($p)->reserved)->toBe(0)
        ->and(invViolations())->toBe([]);
});

it('INV-3: a POS sale leaves no reservation behind', function (): void {
    $p = Product::factory()->create();
    giveStock($p, 10);
    $cashier = staff('cashier');
    $shift = app(OpenShift::class)->handle($cashier, defaultWarehouse());
    $this->actingAs($cashier);
    $order = app(CompleteSale::class)->handle($shift, [['product_id' => $p->id, 'quantity' => 2]]);

    expect($order->status)->toBe(OrderStatus::Completed)
        ->and(invQty($p)->quantity)->toBe(8)->and(invQty($p)->reserved)->toBe(0)
        ->and(invViolations())->toBe([]);
});

it('INV-4: a manual order then cancel keeps the ledger consistent', function (): void {
    $p = Product::factory()->create();
    giveStock($p, 6);
    $order = app(CreateManualOrder::class)->handle([['product_id' => $p->id, 'quantity' => 6]], invCheckout());
    expect(invQty($p)->reserved)->toBe(6);
    app(CancelOrder::class)->handle($order->refresh());

    expect(invQty($p)->quantity)->toBe(6)->and(invViolations())->toBe([]);
});

it('INV-5: cancelling an order after the source warehouse was emptied by a transfer restores the source', function (): void {
    $p = Product::factory()->create();
    $wh2 = Warehouse::factory()->create();
    giveStock($p, 10);
    $order = invOrder($p, 4);
    app(TransferStock::class)->handle(defaultWarehouse(), $wh2, [['product_id' => $p->id, 'product_variant_id' => null, 'quantity' => 6]]);
    app(CancelOrder::class)->handle($order->refresh());

    expect(invQty($p)->quantity)->toBe(4)->and(invQty($p, $wh2)->quantity)->toBe(6)
        ->and(invViolations())->toBe([]);
});

it('INV-6: PO send, partial receive, cancel drops incoming to zero', function (): void {
    $p = Product::factory()->create();
    $po = invPo(defaultWarehouse(), [[$p, 10]]);
    app(SendPurchaseOrder::class)->handle($po->refresh());
    expect(invQty($p)->incoming)->toBe(10);
    app(ReceivePurchaseOrder::class)->handle($po->refresh(), [$po->items()->sole()->id => 4]);
    app(CancelPurchaseOrder::class)->handle($po->refresh());

    expect(invQty($p)->quantity)->toBe(4)->and(invQty($p)->incoming)->toBe(0)
        ->and(invViolations())->toBe([]);
});

it('INV-7: a sent PO cannot be edited and its incoming stays put', function (): void {
    $p = Product::factory()->create();
    $po = invPo(defaultWarehouse(), [[$p, 10]]);
    app(SendPurchaseOrder::class)->handle($po->refresh());

    $this->actingAs(staff('super_admin'))->patch(route('admin.purchase-orders.update', $po), [
        'supplier_id' => $po->supplier_id, 'warehouse_id' => $po->warehouse_id,
        'items' => [['product_id' => $p->id, 'quantity' => 99, 'unit_cost' => 5]],
    ]);

    expect($po->items()->sole()->quantity_ordered)->toBe(10)->and(invViolations())->toBe([]);
});

it('INV-8: receiving into a warehouse deactivated after sending is refused cleanly, and works once it is active again', function (): void {
    $p = Product::factory()->create();
    $wh2 = Warehouse::factory()->create();
    $po = invPo($wh2, [[$p, 5]]);
    app(SendPurchaseOrder::class)->handle($po->refresh());
    $wh2->update(['is_active' => false]);

    expect(fn () => app(ReceivePurchaseOrder::class)->handle($po->refresh(), [$po->items()->sole()->id => 5]))
        ->toThrow(InvalidArgumentException::class)
        ->and(invViolations())->toBe([]);

    $wh2->update(['is_active' => true]);
    app(ReceivePurchaseOrder::class)->handle($po->refresh(), [$po->items()->sole()->id => 5]);

    expect(invQty($p, $wh2)->quantity)->toBe(5)->and(invViolations())->toBe([]);
});

it('INV-9: open orders reserved in a warehouse that is then deactivated still complete/cancel cleanly', function (): void {
    $p = Product::factory()->create();
    giveStock($p, 10);
    $a = invOrder($p, 2);
    $b = invOrder($p, 3);
    defaultWarehouse()->update(['is_active' => false]);
    app(UpdateOrderStatus::class)->handle($a->refresh(), OrderStatus::Completed);
    app(CancelOrder::class)->handle($b->refresh());

    expect(invQty($p)->quantity)->toBe(8)->and(invQty($p)->reserved)->toBe(0)
        ->and(invViolations())->toBe([]);
});

it('INV-10: checkout never ships from a deactivated default warehouse', function (): void {
    $p = Product::factory()->create();
    $main = defaultWarehouse();
    $wh2 = Warehouse::factory()->create();
    giveStock($p, 10, null, $main);
    giveStock($p, 3, null, $wh2);

    // Admin deactivates MAIN (still the default) through the normal form.
    $this->actingAs(staff('super_admin'))->patch(route('admin.warehouses.update', $main), [
        'name' => $main->name, 'code' => $main->code, 'is_default' => true, 'is_active' => false,
    ])->assertSessionHasNoErrors();

    $user = customer();
    $cart = Cart::create(['user_id' => $user->id]);
    app(AddToCart::class)->handle($cart, $p, null, 3); // storefront: 3 sellable in WH2
    app(AdjustStock::class)->handle($wh2, $p, null, 1, MovementType::ManualDecrease, 'sold at counter');

    try {
        $order = app(PlaceOrder::class)->handle($cart->refresh(), invCheckout(), $user);
        expect($order->items()->sole()->warehouse->is_active)->toBeTrue('order shipped from an INACTIVE warehouse');
    } catch (ValidationException) {
        expect(true)->toBeTrue(); // correct: nothing active can cover the line
    }
});

it('INV-11: the default warehouse cannot be left inactive', function (): void {
    $main = defaultWarehouse();
    Warehouse::factory()->create();

    $this->actingAs(staff('super_admin'))->patch(route('admin.warehouses.update', $main), [
        'name' => $main->name, 'code' => $main->code, 'is_default' => true, 'is_active' => false,
    ]);

    expect(Warehouse::default()->is_active)->toBeTrue();
});

it('INV-12: changing the default warehouse leaves exactly one default and checkout follows it', function (): void {
    $p = Product::factory()->create();
    $wh2 = Warehouse::factory()->create();
    giveStock($p, 5);
    giveStock($p, 5, null, $wh2);

    $this->actingAs(staff('super_admin'))->patch(route('admin.warehouses.update', $wh2), [
        'name' => $wh2->name, 'code' => $wh2->code, 'is_default' => true, 'is_active' => true,
    ])->assertSessionHasNoErrors();

    $order = invOrder($p, 2);
    expect(Warehouse::where('is_default', true)->count())->toBe(1)
        ->and($order->items()->sole()->warehouse_id)->toBe($wh2->id)
        ->and(invViolations())->toBe([]);
});

it('INV-13: deleting a warehouse with history keeps the movement ledger readable', function (): void {
    $p = Product::factory()->create();
    $wh2 = Warehouse::factory()->create();
    giveStock($p, 4, null, $wh2);
    app(TransferStock::class)->handle($wh2, defaultWarehouse(), [['product_id' => $p->id, 'product_variant_id' => null, 'quantity' => 4]]);
    $admin = staff('super_admin');

    $this->actingAs($admin)->delete(route('admin.warehouses.destroy', $wh2));
    expect($wh2->refresh()->trashed())->toBeTrue();
    $this->actingAs($admin)->get(route('admin.inventory.movements'))->assertOk();
    $this->actingAs($admin)->get(route('admin.inventory.index'))->assertOk();
    expect(invViolations())->toBe([]);
});

it('INV-14: a draft PO whose warehouse was deleted cannot crash on send', function (): void {
    $p = Product::factory()->create();
    $wh2 = Warehouse::factory()->create();
    $po = invPo($wh2, [[$p, 5]]);
    $admin = staff('super_admin');

    $this->actingAs($admin)->delete(route('admin.warehouses.destroy', $wh2)); // nothing on hand → allowed
    $response = $this->actingAs($admin)->post(route('admin.purchase-orders.send', $po));

    expect($response->status())->not->toBe(500)->and(invViolations())->toBe([]);
});

it('INV-15: a supplier with purchase-order history cannot be deleted', function (): void {
    $p = Product::factory()->create();
    $po = invPo(defaultWarehouse(), [[$p, 1]]);
    $this->actingAs(staff('super_admin'))->delete(route('admin.suppliers.destroy', $po->supplier_id));

    expect(Supplier::find($po->supplier_id))->not->toBeNull();
});

it('INV-16: every manual adjustment type moves quantity in the right direction and damaged is counted once', function (): void {
    $p = Product::factory()->create();
    giveStock($p, 20);
    $adj = app(AdjustStock::class);
    $adj->handle(defaultWarehouse(), $p, null, 5, MovementType::ManualIncrease, 'x');   // 25
    $adj->handle(defaultWarehouse(), $p, null, 3, MovementType::ManualDecrease, 'x');   // 22
    $adj->handle(defaultWarehouse(), $p, null, 2, MovementType::Damaged, 'x');          // 20, damaged 2
    $adj->handle(defaultWarehouse(), $p, null, -1, MovementType::Correction, 'x');      // 19
    $adj->handle(defaultWarehouse(), $p, null, 4, MovementType::SupplierReturn, 'x');   // 15

    $row = invQty($p);
    expect($row->quantity)->toBe(15)->and($row->damaged)->toBe(2)
        ->and(app(ReportService::class)->inventoryValuation()['units'])->toBe(15)
        ->and(invViolations())->toBe([]);
});

it('INV-17: a transfer larger than available is refused and writes nothing', function (): void {
    $p = Product::factory()->create();
    $wh2 = Warehouse::factory()->create();
    giveStock($p, 5);

    $this->actingAs(staff('super_admin'))->post(route('admin.inventory.transfer'), [
        'from_warehouse_id' => defaultWarehouse()->id, 'to_warehouse_id' => $wh2->id,
        'items' => [['product_id' => $p->id, 'quantity' => 6]],
    ])->assertSessionHasErrors('items');

    expect(DB::table('stock_transfers')->count())->toBe(0)->and(invQty($p)->quantity)->toBe(5)
        ->and(invViolations())->toBe([]);
});

it('INV-18: the same product twice in one transfer cannot overdraw the source', function (): void {
    $p = Product::factory()->create();
    $wh2 = Warehouse::factory()->create();
    giveStock($p, 10);

    $this->actingAs(staff('super_admin'))->post(route('admin.inventory.transfer'), [
        'from_warehouse_id' => defaultWarehouse()->id, 'to_warehouse_id' => $wh2->id,
        'items' => [['product_id' => $p->id, 'quantity' => 6], ['product_id' => $p->id, 'quantity' => 6]],
    ]);

    expect(invQty($p)->quantity)->toBe(10)->and(invViolations())->toBe([]);
});

it('INV-19: a transfer of a trashed variant is rejected cleanly (no 500, no product-level move)', function (): void {
    $p = Product::factory()->withVariants()->create();
    $red = $p->variants()->create(['name' => 'Red', 'sku' => 'INV19-R', 'price_cents' => 1000, 'is_active' => true]);
    $wh2 = Warehouse::factory()->create();
    giveStock($p, 5, $red);
    $red->delete();

    $response = $this->actingAs(staff('super_admin'))->post(route('admin.inventory.transfer'), [
        'from_warehouse_id' => defaultWarehouse()->id, 'to_warehouse_id' => $wh2->id,
        'items' => [['product_id' => $p->id, 'product_variant_id' => $red->id, 'quantity' => 2]],
    ]);

    expect($response->status())->toBe(302)->and(invViolations())->toBe([]);
    $response->assertSessionHasErrors();
});

it('INV-20: a transfer into an inactive warehouse keeps the ledger consistent', function (): void {
    $p = Product::factory()->create();
    $wh2 = Warehouse::factory()->create(['is_active' => false]);
    giveStock($p, 5);
    app(TransferStock::class)->handle(defaultWarehouse(), $wh2, [['product_id' => $p->id, 'product_variant_id' => null, 'quantity' => 5]]);

    expect(invViolations())->toBe([]);
});

it('INV-21: a stock adjustment refuses a variant that belongs to a different product', function (): void {
    $a = Product::factory()->create();
    $b = Product::factory()->withVariants()->create();
    $bRed = $b->variants()->create(['name' => 'Red', 'sku' => 'INV21-R', 'price_cents' => 1000, 'is_active' => true]);

    $this->actingAs(staff('super_admin'))->post(route('admin.inventory.adjust'), [
        'warehouse_id' => defaultWarehouse()->id, 'product_id' => $a->id, 'product_variant_id' => $bRed->id,
        'quantity_change' => 7, 'type' => 'manual_increase', 'reason' => 'typo',
    ]);

    // The storefront reads variant stock by variant id only: a (product A, variant B)
    // row is phantom stock for B that checkout can never actually take.
    expect(StockLevel::where('product_id', $a->id)->where('product_variant_id', $bRed->id)->exists())->toBeFalse();
});

it('INV-22: a transfer refuses a variant that belongs to a different product', function (): void {
    $a = Product::factory()->create();
    $b = Product::factory()->withVariants()->create();
    $bRed = $b->variants()->create(['name' => 'Red', 'sku' => 'INV22-R', 'price_cents' => 1000, 'is_active' => true]);
    $wh2 = Warehouse::factory()->create();
    giveStock($a, 5);
    // Seed a mismatched row the same way INV-21 does, then try to move it.
    $this->actingAs(staff('super_admin'))->post(route('admin.inventory.transfer'), [
        'from_warehouse_id' => defaultWarehouse()->id, 'to_warehouse_id' => $wh2->id,
        'items' => [['product_id' => $a->id, 'product_variant_id' => $bRed->id, 'quantity' => 1]],
    ])->assertSessionHasErrors();

    expect(StockLevel::where('product_id', $a->id)->where('product_variant_id', $bRed->id)->exists())->toBeFalse();
});

it('INV-23: adjusting a trashed variant does not silently hit product-level stock', function (): void {
    $p = Product::factory()->create();
    giveStock($p, 10); // product-level stock, then variants appear and one is trashed
    $red = ProductVariant::create(['product_id' => $p->id, 'name' => 'Red', 'sku' => 'INV23-R', 'price_cents' => 1000, 'is_active' => true]);
    $red->delete();

    $this->actingAs(staff('super_admin'))->post(route('admin.inventory.adjust'), [
        'warehouse_id' => defaultWarehouse()->id, 'product_id' => $p->id, 'product_variant_id' => $red->id,
        'quantity_change' => 3, 'type' => 'manual_decrease', 'reason' => 'broken red ones',
    ]);

    expect(invQty($p)->quantity)->toBe(10);
});

it('INV-24: cancelling an open order after its product was trashed still returns stock and the reservation', function (): void {
    $p = Product::factory()->create();
    giveStock($p, 10);
    $order = invOrder($p, 4);
    $this->actingAs(staff('super_admin'))->delete(route('admin.products.destroy', $p));
    expect($p->refresh()->trashed())->toBeTrue();

    app(CancelOrder::class)->handle($order->refresh());

    expect(invQty($p)->reserved)->toBe(0)->and(invQty($p)->quantity)->toBe(10)
        ->and(invViolations())->toBe([]);
});

it('INV-25: a sent PO can still be cancelled after its product was trashed (incoming released)', function (): void {
    $p = Product::factory()->create();
    $po = invPo(defaultWarehouse(), [[$p, 8]]);
    app(SendPurchaseOrder::class)->handle($po->refresh());
    $p->delete();

    try {
        app(CancelPurchaseOrder::class)->handle(PurchaseOrder::find($po->id));
    } catch (Throwable $e) {
        $this->fail('Cancel PO crashed: '.$e::class.' '.$e->getMessage());
    }

    expect(invQty($p)->incoming)->toBe(0);
});

it('INV-26: dashboard out-of-stock does not flag a product that is sellable in another warehouse', function (): void {
    $p = Product::factory()->create();
    $wh2 = Warehouse::factory()->create();
    giveStock($p, 10);
    app(TransferStock::class)->handle(defaultWarehouse(), $wh2, [['product_id' => $p->id, 'product_variant_id' => null, 'quantity' => 10]]);
    expect($p->refresh()->availableQuantity())->toBe(10); // storefront: in stock

    $this->actingAs(staff('super_admin'))->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('kpis.out_of_stock', 0));
});

it('INV-27: dashboard out-of-stock counts a product whose only stock sits in an inactive warehouse', function (): void {
    $p = Product::factory()->create();
    $wh2 = Warehouse::factory()->create();
    giveStock($p, 10, null, $wh2);
    $wh2->update(['is_active' => false]);
    expect($p->refresh()->availableQuantity())->toBe(0); // storefront: out of stock

    $this->actingAs(staff('super_admin'))->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('kpis.out_of_stock', 1));
});

it('INV-28: interleaved checkouts, transfer, PO receipt and cancels keep the full ledger chain intact', function (): void {
    $p = Product::factory()->create();
    $wh2 = Warehouse::factory()->create();
    giveStock($p, 10);
    $o1 = invOrder($p, 3);
    $po = invPo(defaultWarehouse(), [[$p, 6]]);
    app(SendPurchaseOrder::class)->handle($po->refresh());
    $o2 = invOrder($p, 2);
    app(TransferStock::class)->handle(defaultWarehouse(), $wh2, [['product_id' => $p->id, 'product_variant_id' => null, 'quantity' => 4]]);
    app(ReceivePurchaseOrder::class)->handle($po->refresh(), [$po->items()->sole()->id => 6]);
    app(CancelOrder::class)->handle($o1->refresh());
    app(UpdateOrderStatus::class)->handle($o2->refresh(), OrderStatus::Completed);
    invOrder($p, 4);

    expect(invViolations())->toBe([]);
});
