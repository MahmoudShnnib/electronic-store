<?php

/*
 * POS audit — each test asserts the CORRECT behaviour; a failing test is a
 * confirmed bug. Nothing here touches the print routes, and the printer is
 * forced off and bound to a tripwire so no byte can reach a real device.
 */

use App\Actions\Orders\ResolveOrderIssue;
use App\Actions\Pos\CloseShift;
use App\Actions\Pos\CompleteSale;
use App\Actions\Pos\OpenShift;
use App\Actions\Refunds\CreateRefund;
use App\Enums\CouponType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\RefundMethod;
use App\Enums\TreasuryAccount;
use App\Models\CashierShift;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Pos\ReceiptPrinter;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    config(['pos.printer.driver' => null]);
    app()->bind(ReceiptPrinter::class, fn () => throw new RuntimeException('Audit tests must never print.'));
});

function pgaShift(User $user, int $float = 0, ?Warehouse $warehouse = null): CashierShift
{
    return app(OpenShift::class)->handle($user, $warehouse ?? defaultWarehouse(), $float);
}

function pgaSale(CashierShift $shift, array $lines, PaymentMethod $method = PaymentMethod::Cash, ?string $coupon = null, int $discount = 0): Order
{
    return app(CompleteSale::class)->handle($shift, $lines, $method, [], $coupon, $discount);
}

function pgaBranch(string $code): Warehouse
{
    return Warehouse::query()->create(['name' => "Branch {$code}", 'code' => $code, 'is_active' => true]);
}

// -- Confirmed-bug probes -----------------------------------------------------

it('POS-1: the counter can sell more than 10 of one item when the shelf holds them', function (): void {
    $cable = Product::factory()->create(['price_cents' => 500]);
    giveStock($cable, 50);

    $order = pgaSale(pgaShift(staff('cashier')), [['product_id' => $cable->id, 'quantity' => 12]]);

    expect($order->total_cents)->toBe(6_000);
});

it('POS-2: the register catalogue carries each variant\'s own barcode so it can be scanned', function (): void {
    $cashier = staff('cashier');
    $product = Product::factory()->withVariants()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'barcode' => '6291041500213']);
    giveStock($product, 3, $variant);
    pgaShift($cashier);

    $this->actingAs($cashier)->get(route('admin.pos.register'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.0.variants.0.barcode', '6291041500213'));
});

it('POS-3: the receipt arithmetic adds up when a coupon is used (lines - discount = total)', function (): void {
    $cashier = staff('cashier');
    $a = Product::factory()->create(['price_cents' => 10_000]);
    $b = Product::factory()->create(['price_cents' => 30_000]);
    giveStock($a, 5);
    giveStock($b, 5);
    Coupon::factory()->create(['code' => 'TAKE40', 'type' => CouponType::Fixed, 'value_cents' => 4_000]);

    $order = pgaSale(pgaShift($cashier), [
        ['product_id' => $a->id, 'quantity' => 1],
        ['product_id' => $b->id, 'quantity' => 1],
    ], coupon: 'TAKE40');

    // The slip prints each line, then "Discount (TAKE40) -40.000", then the total:
    // so the printed lines must be the pre-discount amounts (100 + 300 - 40 = 360).
    $fmt = fn (int $cents) => Money::format($cents, $order->currency, isolate: false);
    expect($order->total_cents)->toBe(36_000);
    $this->actingAs($cashier)->get(route('admin.pos.receipt', $order))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('order.items.0.line_total_formatted', $fmt(10_000))
            ->where('order.items.1.line_total_formatted', $fmt(30_000))
            ->where('order.discount_formatted', $fmt(4_000))
            ->where('order.total_formatted', $fmt(36_000)));
});

it('POS-4: a shift whose warehouse was deactivated after opening can no longer sell from it', function (): void {
    $product = Product::factory()->create(['price_cents' => 1_000]);
    $branch = pgaBranch('BR1');
    giveStock($product, 5, null, $branch);
    giveStock($product, 5); // default warehouse still active and stocked

    $shift = pgaShift(staff('cashier'), 0, $branch);
    $branch->update(['is_active' => false]);

    expect(fn () => pgaSale($shift, [['product_id' => $product->id, 'quantity' => 1]]))
        ->toThrow(ValidationException::class);
});

it('POS-5: a fully discounted counter sale is not left "unpaid"', function (): void {
    $product = Product::factory()->create(['price_cents' => 2_000]);
    giveStock($product, 2);

    $order = pgaSale(pgaShift(staff('order_manager')), [['product_id' => $product->id, 'quantity' => 1]], discount: 2_000);

    expect($order->total_cents)->toBe(0)
        ->and($order->payment_status)->not->toBe(PaymentStatus::Unpaid);
});

// -- Checked & OK (expected to pass) -----------------------------------------

it('POS-6: a discount posted straight to the endpoint by a cashier without pos.discount is dropped', function (): void {
    $cashier = staff('cashier');
    $product = Product::factory()->create(['price_cents' => 10_000]);
    giveStock($product, 2);
    pgaShift($cashier);

    $this->actingAs($cashier)->post(route('admin.pos.sales.store'), [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'payment_method' => 'cash',
        'discount' => 99.99,
    ])->assertRedirect();

    expect(Order::query()->sole()->total_cents)->toBe(10_000);
});

it('POS-7: a cashier whose pos.use is revoked mid-shift cannot ring another sale', function (): void {
    $cashier = staff('cashier');
    $product = Product::factory()->create();
    giveStock($product, 2);
    pgaShift($cashier);
    $cashier->syncRoles([]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($cashier->fresh())->post(route('admin.pos.sales.store'), [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'payment_method' => 'cash',
    ])->assertForbidden();
});

it('POS-8: bank-transfer sales never enter the drawer and bank to the bank account', function (): void {
    $product = Product::factory()->create(['price_cents' => 10_000]);
    giveStock($product, 3);
    $shift = pgaShift(staff('order_manager'), 5_000);

    pgaSale($shift, [['product_id' => $product->id, 'quantity' => 1]], PaymentMethod::BankTransfer, discount: 1_000);

    expect($shift->refresh()->expectedCashCents())->toBe(5_000)
        ->and(TreasuryTransaction::query()->where('account', TreasuryAccount::Bank)->sum('amount_cents'))->toEqual(9_000);
});

it('POS-9: a partial cash refund during the shift lowers the expected drawer exactly once', function (): void {
    $product = Product::factory()->create(['price_cents' => 10_000]);
    giveStock($product, 3);
    $shift = pgaShift(staff('cashier'), 5_000);
    $order = pgaSale($shift, [['product_id' => $product->id, 'quantity' => 2]]);

    app(CreateRefund::class)->handle($order, 3_000, RefundMethod::Cash);

    $closed = app(CloseShift::class)->handle($shift, 22_000);
    expect($closed->expected_cash_cents)->toBe(22_000)->and($closed->difference_cents)->toBe(0);
});

it('POS-10: a counter return restocks the shift warehouse, not the default one', function (): void {
    $product = Product::factory()->create();
    $branch = pgaBranch('BR2');
    giveStock($product, 1, null, $branch);
    $order = pgaSale(pgaShift(staff('cashier'), 0, $branch), [['product_id' => $product->id, 'quantity' => 1]]);

    app(ResolveOrderIssue::class)->handle($order->refresh(), 'Wrong item', 0, RefundMethod::Cash, true);

    expect(StockLevel::query()->where('warehouse_id', $branch->id)->where('product_id', $product->id)->value('quantity'))->toBe(1);
});

it('POS-11: the last unit cannot be sold twice from one warehouse by two tills', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 1);
    $a = pgaShift(staff('cashier'));
    $b = pgaShift(staff('cashier'));

    pgaSale($a, [['product_id' => $product->id, 'quantity' => 1]]);

    expect(fn () => pgaSale($b, [['product_id' => $product->id, 'quantity' => 1]]))->toThrow(ValidationException::class);
});

it('POS-12: register stock is per-warehouse and price shown equals price charged across a sale window', function (): void {
    $cashier = staff('cashier');
    $branch = pgaBranch('BR3');
    $product = Product::factory()->create([
        'name' => 'AAA Charger', 'price_cents' => 10_000, 'sale_price_cents' => 8_000, 'sale_ends_at' => now()->subDay(),
    ]);
    giveStock($product, 7);
    giveStock($product, 2, null, $branch);
    $shift = pgaShift($cashier, 0, $branch);

    $this->actingAs($cashier)->get(route('admin.pos.register'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.0.available', 2)
            ->where('products.0.price', Product::find($product->id)->currentPriceCents() / 100));

    expect(pgaSale($shift, [['product_id' => $product->id, 'quantity' => 1]])->total_cents)->toBe(10_000);
});

it('POS-13: draft/discontinued products and inactive/trashed variants are not offered or sellable', function (): void {
    $cashier = staff('cashier');
    $draft = Product::factory()->draft()->create(['name' => 'Draft thing']);
    $gone = Product::factory()->create(['name' => 'Gone thing', 'status' => ProductStatus::Discontinued]);
    giveStock($draft, 2);
    giveStock($gone, 2);
    $vp = Product::factory()->withVariants()->create(['name' => 'Variant thing']);
    $off = ProductVariant::factory()->create(['product_id' => $vp->id, 'is_active' => false]);
    $trashed = ProductVariant::factory()->create(['product_id' => $vp->id]);
    giveStock($vp, 2, $trashed);
    $trashed->delete();
    $shift = pgaShift($cashier);

    $this->actingAs($cashier)->get(route('admin.pos.register'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products', 1)
            ->has('products.0.variants', 0));

    expect(fn () => pgaSale($shift, [['product_id' => $gone->id, 'quantity' => 1]]))->toThrow(ValidationException::class)
        ->and(fn () => pgaSale($shift, [['product_id' => $vp->id, 'product_variant_id' => $off->id, 'quantity' => 1]]))->toThrow(ValidationException::class);
});
