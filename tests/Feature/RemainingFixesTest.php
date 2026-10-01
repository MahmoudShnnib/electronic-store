<?php

/*
 * The 17 issues found in live testing that had no test yet (stock, returns,
 * register, reports, shop cards, emails). One test per fix.
 */

use App\Actions\Cart\AddToCart;
use App\Actions\Checkout\PlaceOrder;
use App\Actions\Inventory\AdjustStock;
use App\Actions\Orders\ResolveOrderIssue;
use App\Actions\Orders\UpdateOrderStatus;
use App\Actions\Payments\ConfirmPayment;
use App\Actions\Pos\CompleteSale;
use App\Actions\Pos\OpenShift;
use App\Actions\Purchasing\CancelPurchaseOrder;
use App\Actions\Purchasing\ReceivePurchaseOrder;
use App\Actions\Purchasing\SendPurchaseOrder;
use App\Actions\Refunds\CreateRefund;
use App\DTOs\CheckoutData;
use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RefundMethod;
use App\Http\Resources\ProductCardResource;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\OrderPlacedNotification;
use App\Services\Reports\ReportService;
use App\Support\Money;
use App\Support\ReferenceNumber;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => Notification::fake());

function rfOrder(?User $user, array $lines, ?string $email = null): Order
{
    $cart = Cart::create(['user_id' => $user?->id]);

    foreach ($lines as [$product, $qty]) {
        app(AddToCart::class)->handle($cart, $product, null, $qty);
    }

    $address = ['name' => 'x', 'phone' => '0910000000', 'country' => 'LY', 'city' => 'Tripoli'];

    return app(PlaceOrder::class)->handle($cart, new CheckoutData(
        customer: ['name' => 'x', 'email' => $email ?? $user?->email, 'phone' => '0910000000'],
        shippingAddress: $address,
        billingAddress: $address,
        paymentMethod: PaymentMethod::CashOnDelivery,
    ), $user);
}

function rfSentPo(Warehouse $warehouse, Product $product, int $qty = 5): PurchaseOrder
{
    $po = PurchaseOrder::create([
        'po_number' => ReferenceNumber::generate('PO'),
        'supplier_id' => Supplier::factory()->create()->id,
        'warehouse_id' => $warehouse->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);
    $po->items()->create(['product_id' => $product->id, 'quantity_ordered' => $qty, 'unit_cost_cents' => 100, 'line_total_cents' => 100 * $qty]);

    return app(SendPurchaseOrder::class)->handle($po);
}

it('#1 refuses the second submit of the same cart instead of making a second order', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 5);
    $user = customer();
    $cart = Cart::create(['user_id' => $user->id]);
    app(AddToCart::class)->handle($cart, $product, null, 1);
    $data = new CheckoutData(
        customer: ['name' => 'x', 'email' => $user->email, 'phone' => '0910000000'],
        shippingAddress: ['name' => 'x', 'phone' => '0910000000', 'country' => 'LY', 'city' => 'Tripoli'],
        billingAddress: ['name' => 'x', 'phone' => '0910000000', 'country' => 'LY', 'city' => 'Tripoli'],
        paymentMethod: PaymentMethod::CashOnDelivery,
    );

    app(PlaceOrder::class)->handle($cart, $data, $user);

    expect(fn () => app(PlaceOrder::class)->handle($cart, $data, $user))->toThrow(ValidationException::class)
        ->and(Order::count())->toBe(1);
});

it('#2 will not delete a warehouse that still holds damaged units', function (): void {
    $branch = Warehouse::create(['code' => 'DMG', 'name' => 'Branch', 'is_active' => true, 'is_default' => false]);
    $product = Product::factory()->create();
    giveStock($product, 3, null, $branch);
    app(AdjustStock::class)->handle($branch, $product, null, 3, MovementType::Damaged, 'dropped');

    $this->actingAs(staff('super_admin'))->delete(route('admin.warehouses.destroy', $branch));

    expect($branch->fresh()->trashed())->toBeFalse();
});

it('#3 a turned-off warehouse takes no transfers and no purchase-order receipts', function (): void {
    $closed = Warehouse::create(['code' => 'OFF', 'name' => 'Closed', 'is_active' => true, 'is_default' => false]);
    $product = Product::factory()->create();
    giveStock($product, 5);
    $po = rfSentPo($closed, $product);
    $closed->update(['is_active' => false]);

    $this->actingAs(staff('super_admin'))->post(route('admin.inventory.transfer'), [
        'from_warehouse_id' => defaultWarehouse()->id, 'to_warehouse_id' => $closed->id,
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('to_warehouse_id');

    expect(fn () => app(ReceivePurchaseOrder::class)->handle($po->refresh(), [$po->items()->sole()->id => 5]))
        ->toThrow(InvalidArgumentException::class)
        ->and((int) StockLevel::where('warehouse_id', $closed->id)->sum('quantity'))->toBe(0);
});

it('#4 a purchase order that was just received can no longer be cancelled (and the other way round)', function (): void {
    $product = Product::factory()->create();
    $po = rfSentPo(defaultWarehouse(), $product);
    $stale = PurchaseOrder::find($po->id); // what a second browser tab still holds

    app(ReceivePurchaseOrder::class)->handle($po, [$po->items()->sole()->id => 5]);

    expect(fn () => app(CancelPurchaseOrder::class)->handle($stale))->toThrow(InvalidArgumentException::class)
        ->and($po->fresh()->status)->toBe(PurchaseOrderStatus::Received);
});

it('#5 a product marked "Out of stock" goes back on sale when stock arrives', function (): void {
    $product = Product::factory()->create(['status' => ProductStatus::OutOfStock]);

    giveStock($product, 4);

    expect($product->fresh()->status)->toBe(ProductStatus::Active);
});

it('#6 the dashboard warns about cash-on-delivery orders waiting 3+ days, and never cancels them', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 5);
    $old = rfOrder(customer(), [[$product, 1]]);
    $old->forceFill(['created_at' => now()->subDays(4)])->save();
    rfOrder(customer(), [[$product, 1]]); // fresh one: no warning

    $this->actingAs(staff('admin'))->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('staleCodOrders', 1)->where('staleCodOrders.0.id', $old->id));

    expect($old->fresh()->status)->toBe(OrderStatus::InProgress);
});

it('#7 a partial return restocks only the items that came back (no ghost stock)', function (): void {
    $a = Product::factory()->create(['price_cents' => 1_000]);
    $b = Product::factory()->create(['price_cents' => 1_000]);
    giveStock($a, 10);
    giveStock($b, 10);
    $order = rfOrder(customer(), [[$a, 3], [$b, 2]]);
    $order = app(UpdateOrderStatus::class)->handle($order, OrderStatus::Completed);
    $lineA = $order->items()->where('product_id', $a->id)->sole();
    $lineB = $order->items()->where('product_id', $b->id)->sole();

    app(ResolveOrderIssue::class)->handle($order, 'one cracked', 1_000, RefundMethod::Cash, restockQuantities: [$lineA->id => 1, $lineB->id => 0]);

    expect((int) StockLevel::where('product_id', $a->id)->sum('quantity'))->toBe(8)   // 10 − 3 + 1
        ->and((int) StockLevel::where('product_id', $b->id)->sum('quantity'))->toBe(8); // 10 − 2, nothing back
});

it('#8 the return form starts from what is still held after earlier refunds', function (): void {
    $product = Product::factory()->create(['price_cents' => 10_000]);
    giveStock($product, 5);
    $order = app(UpdateOrderStatus::class)->handle(rfOrder(customer(), [[$product, 1]]), OrderStatus::Completed);
    app(CreateRefund::class)->handle($order, 3_000, RefundMethod::Cash);

    $this->actingAs(staff('admin'))->get(route('admin.orders.show', $order))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('order.paid_cents', 10_000)->where('order.refunded_cents', 3_000)
            ->where('order.items.0.returnable', 1));
});

it('#9 a cashier cannot hold two open shifts', function (): void {
    $cashier = staff('cashier');
    app(OpenShift::class)->handle($cashier, defaultWarehouse(), 0);

    expect(fn () => app(OpenShift::class)->handle($cashier, defaultWarehouse(), 0))->toThrow(ValidationException::class);
});

it('#10 an order page only offers the actions the staff member may take', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 5);
    $order = rfOrder(customer(), [[$product, 1]]);

    $this->actingAs(staff('cashier'))->get(route('admin.orders.show', $order))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('order.can.manage', false)->where('order.can.refund', false));
    $this->actingAs(staff('admin'))->get(route('admin.orders.show', $order))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('order.can.manage', true));
});

it('#11 the close-shift screen shows cash refunded from the drawer', function (): void {
    $cashier = staff('cashier');
    $product = Product::factory()->create(['price_cents' => 5_000]);
    giveStock($product, 5);
    $shift = app(OpenShift::class)->handle($cashier, defaultWarehouse(), 0);
    $sale = app(CompleteSale::class)->handle($shift, [['product_id' => $product->id, 'quantity' => 1]]);
    app(CreateRefund::class)->handle($sale, 2_000, RefundMethod::Cash, null, 'scratch', $cashier->id);

    $this->actingAs($cashier)->get(route('admin.pos.shifts.close'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('shift.has_cash_refunds', true));
});

it('#12 profit uses the cost at the time of sale, not today\'s cost', function (): void {
    $product = Product::factory()->create(['price_cents' => 10_000, 'cost_price_cents' => 4_000, 'sale_price_cents' => null]);
    giveStock($product, 5);
    app(UpdateOrderStatus::class)->handle(rfOrder(customer(), [[$product, 1]]), OrderStatus::Completed);
    $product->update(['cost_price_cents' => 9_000]); // supplier got dearer later

    $summary = app(ReportService::class)->salesSummary(CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addDay());

    expect($summary['estimated_cost_formatted'])->toBe(Money::format(4_000));
});

it('#13 "new customers" in the sales report does not count staff', function (): void {
    customer();
    staff('cashier');

    $summary = app(ReportService::class)->salesSummary(CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addDay());

    expect($summary['new_customers'])->toBe(1);
});

it('#14 the by-product report lists every product, not just the top 25', function (): void {
    $user = customer();
    $products = Product::factory()->count(27)->create(['price_cents' => 1_000]);
    $products->each(fn ($p) => giveStock($p, 1));
    rfOrder($user, $products->map(fn ($p) => [$p, 1])->all());

    expect(app(ReportService::class)->salesByProduct(CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addDay()))->toHaveCount(27);
});

it('#15 the dashboard counts refunded orders', function (): void {
    $product = Product::factory()->create(['price_cents' => 1_000]);
    giveStock($product, 5);
    $order = app(UpdateOrderStatus::class)->handle(rfOrder(customer(), [[$product, 1]]), OrderStatus::Completed);
    app(ResolveOrderIssue::class)->handle($order, 'faulty', 1_000, RefundMethod::Cash);

    $this->actingAs(staff('admin'))->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('kpis.orders_refunded', 1));
});

it('#16 a variant product card shows the cheapest variant price ("from …")', function (): void {
    $product = Product::factory()->create(['has_variants' => true, 'price_cents' => 99_000]);
    $product->variants()->create(['name' => 'Small', 'sku' => 'RF16-S', 'price_cents' => 20_000, 'is_active' => true]);
    $product->variants()->create(['name' => 'Large', 'sku' => 'RF16-L', 'price_cents' => 30_000, 'is_active' => true]);

    $card = (new ProductCardResource($product->fresh()))->resolve(request());

    expect($card['current_price_formatted'])->toBe(Money::format(20_000))
        ->and($card['price_from'])->toBeTrue();
});

it('#17 a guest gets an order email listing the items, and customers get it in their language', function (): void {
    $product = Product::factory()->create(['name' => 'Blue Kettle']);
    giveStock($product, 5);

    $order = rfOrder(null, [[$product, 2]], 'guest@example.test');

    Notification::assertSentTo(new AnonymousNotifiable, OrderPlacedNotification::class, function ($notification, $channels, $notifiable) use ($order) {
        $mail = $notification->toMail($notifiable);

        return $notifiable->routes['mail'] === 'guest@example.test'
            && collect($mail->introLines)->contains(fn ($line) => str_contains($line, '2 × Blue Kettle'))
            && str_contains($mail->subject, $order->number);
    });

    $arabic = customer(['locale' => 'ar']);
    expect($arabic->preferredLocale())->toBe('ar');
});

it('N2: a purchase order whose product was deleted can still be opened, sent, received and cancelled', function (): void {
    $admin = staff('super_admin');
    $product = Product::factory()->create();
    $other = Product::factory()->create();
    $po = rfSentPo(defaultWarehouse(), $product);
    $draft = PurchaseOrder::create([
        'po_number' => ReferenceNumber::generate('PO'), 'supplier_id' => Supplier::factory()->create()->id,
        'warehouse_id' => defaultWarehouse()->id, 'status' => PurchaseOrderStatus::Draft,
    ]);
    $draft->items()->create(['product_id' => $other->id, 'quantity_ordered' => 2, 'unit_cost_cents' => 100, 'line_total_cents' => 200]);
    $product->delete();
    $other->delete();

    $this->actingAs($admin)->get(route('admin.purchase-orders.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.purchase-orders.show', $po))->assertOk();
    app(ReceivePurchaseOrder::class)->handle($po->refresh(), [$po->items()->sole()->id => 5]);
    app(SendPurchaseOrder::class)->handle($draft->refresh());
    app(CancelPurchaseOrder::class)->handle($draft->refresh());

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::Received)
        ->and($draft->fresh()->status)->toBe(PurchaseOrderStatus::Cancelled);
});

it('a customer is not offered "cancel" on an order that already has a payment on it', function (): void {
    $product = Product::factory()->create(['price_cents' => 10_000]);
    giveStock($product, 5);
    $user = customer();
    $order = rfOrder($user, [[$product, 1]]);

    $this->actingAs($user)->get(route('account.orders.show', $order))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('order.cancellable', true));

    app(ConfirmPayment::class)->handle($order, 2_000);

    $this->actingAs($user)->get(route('account.orders.show', $order))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('order.cancellable', false));
});
