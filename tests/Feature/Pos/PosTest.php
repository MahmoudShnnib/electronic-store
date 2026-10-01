<?php

use App\Actions\Pos\CloseShift;
use App\Actions\Pos\CompleteSale;
use App\Actions\Pos\OpenShift;
use App\Enums\CashierShiftStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\TreasuryAccount;
use App\Enums\TreasuryDirection;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

function cashier(): User
{
    return staff('cashier');
}

function openShiftFor(User $user, int $floatCents = 0, ?Warehouse $warehouse = null): CashierShift
{
    return app(OpenShift::class)->handle($user, $warehouse ?? defaultWarehouse(), $floatCents);
}

/** @return list<array{product_id: int, quantity: int}> */
function line(Product $product, int $quantity = 1): array
{
    return [['product_id' => $product->id, 'quantity' => $quantity]];
}

// -- Shifts -------------------------------------------------------------------

it('opens a shift against a warehouse with an opening float', function (): void {
    $shift = openShiftFor(cashier(), 50_000);

    expect($shift->status)->toBe(CashierShiftStatus::Open)
        ->and($shift->opening_float_cents)->toBe(50_000)
        ->and($shift->warehouse_id)->toBe(defaultWarehouse()->id);
});

it('refuses a second open shift for the same cashier', function (): void {
    $user = cashier();
    openShiftFor($user);

    app(OpenShift::class)->handle($user, defaultWarehouse());
})->throws(ValidationException::class);

it('refuses to open a shift on an inactive warehouse', function (): void {
    $warehouse = Warehouse::query()->create(['name' => 'Closed branch', 'code' => 'OLD', 'is_active' => false]);

    app(OpenShift::class)->handle(cashier(), $warehouse);
})->throws(ValidationException::class);

// -- Selling ------------------------------------------------------------------

it('completes a cash sale: stock leaves, the order is paid and the drawer grows', function (): void {
    $product = Product::factory()->create(['price_cents' => 25_000]);
    giveStock($product, 5);

    $shift = openShiftFor(cashier(), 10_000);

    $order = app(CompleteSale::class)->handle($shift, line($product, 2));

    expect($order->status)->toBe(OrderStatus::Completed)
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->payment_method)->toBe(PaymentMethod::Cash)
        ->and($order->placed_via)->toBe('pos')
        ->and($order->cashier_shift_id)->toBe($shift->id)
        ->and($order->total_cents)->toBe(50_000);

    $stock = StockLevel::query()->where('product_id', $product->id)->sole();

    // Sold, not reserved: the goods left with the customer.
    expect($stock->quantity)->toBe(3)
        ->and($stock->reserved)->toBe(0);

    // Opening float plus the sale.
    expect($shift->refresh()->expectedCashCents())->toBe(60_000);
});

it('sells from the shift warehouse even when another holds more stock', function (): void {
    $branch = Warehouse::query()->create(['name' => 'Branch', 'code' => 'BR1', 'is_active' => true]);

    $product = Product::factory()->create(['price_cents' => 10_000]);
    giveStock($product, 2, null, $branch);
    giveStock($product, 50, null, defaultWarehouse());

    $shift = openShiftFor(cashier(), 0, $branch);

    $order = app(CompleteSale::class)->handle($shift, line($product, 1));

    expect($order->items()->sole()->warehouse_id)->toBe($branch->id)
        ->and(StockLevel::query()->where('warehouse_id', $branch->id)->sole()->quantity)->toBe(1)
        ->and(StockLevel::query()->where('warehouse_id', defaultWarehouse()->id)->sole()->quantity)->toBe(50);
});

it('posts cash takings into the treasury cash account', function (): void {
    $product = Product::factory()->create(['price_cents' => 30_000]);
    giveStock($product, 3);

    $order = app(CompleteSale::class)->handle(openShiftFor(cashier()), line($product));

    $movement = TreasuryTransaction::query()->where('order_id', $order->id)->sole();

    expect($movement->account)->toBe(TreasuryAccount::Cash)
        ->and($movement->direction)->toBe(TreasuryDirection::In)
        ->and($movement->amount_cents)->toBe(30_000);
});

it('banks a counter transfer against the bank account, not the drawer', function (): void {
    $product = Product::factory()->create(['price_cents' => 40_000]);
    giveStock($product, 2);

    $shift = openShiftFor(cashier(), 5_000);

    $order = app(CompleteSale::class)->handle($shift, line($product), PaymentMethod::BankTransfer);

    expect(TreasuryTransaction::query()->where('order_id', $order->id)->sole()->account)
        ->toBe(TreasuryAccount::Bank)
        // The drawer only ever holds the float — the transfer never touched it.
        ->and($shift->refresh()->expectedCashCents())->toBe(5_000);
});

it('applies a manual discount to the total without touching line prices', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_000]);
    giveStock($product, 2);

    $order = app(CompleteSale::class)->handle(
        openShiftFor(cashier()),
        line($product),
        manualDiscountCents: 15_000,
    );

    expect($order->subtotal_cents)->toBe(100_000)
        ->and($order->discount_cents)->toBe(15_000)
        ->and($order->total_cents)->toBe(85_000)
        ->and($order->items()->sole()->unit_price_cents)->toBe(100_000)
        // The line carries its share, so product revenue adds up to the order.
        ->and($order->items()->sole()->line_total_cents)->toBe(85_000);
});

it('refuses a discount larger than the total', function (): void {
    $product = Product::factory()->create(['price_cents' => 10_000]);
    giveStock($product, 2);

    app(CompleteSale::class)->handle(openShiftFor(cashier()), line($product), manualDiscountCents: 999_999);
})->throws(ValidationException::class);

it('refuses to sell more than the shift warehouse holds', function (): void {
    $product = Product::factory()->create(['price_cents' => 10_000]);
    giveStock($product, 1);

    app(CompleteSale::class)->handle(openShiftFor(cashier()), line($product, 5));
})->throws(ValidationException::class);

it('refuses to sell on a closed shift', function (): void {
    $product = Product::factory()->create(['price_cents' => 10_000]);
    giveStock($product, 5);

    $shift = openShiftFor(cashier());
    app(CloseShift::class)->handle($shift, 0);

    app(CompleteSale::class)->handle($shift->refresh(), line($product));
})->throws(ValidationException::class);

// -- Closing ------------------------------------------------------------------

it('closes a balanced drawer without touching the treasury', function (): void {
    $product = Product::factory()->create(['price_cents' => 20_000]);
    giveStock($product, 5);

    $shift = openShiftFor(cashier(), 10_000);
    app(CompleteSale::class)->handle($shift, line($product));

    $before = TreasuryTransaction::query()->count();
    $shift = app(CloseShift::class)->handle($shift->refresh(), 30_000);

    expect($shift->status)->toBe(CashierShiftStatus::Closed)
        ->and($shift->expected_cash_cents)->toBe(30_000)
        ->and($shift->counted_cash_cents)->toBe(30_000)
        ->and($shift->difference_cents)->toBe(0)
        ->and(TreasuryTransaction::query()->count())->toBe($before);
});

it('books a shortfall in the drawer as money leaving the treasury', function (): void {
    $shift = openShiftFor(cashier(), 20_000);

    $shift = app(CloseShift::class)->handle($shift, 17_500);

    expect($shift->difference_cents)->toBe(-2_500);

    $variance = TreasuryTransaction::query()->where('source_type', $shift->getMorphClass())->sole();

    expect($variance->direction)->toBe(TreasuryDirection::Out)
        ->and($variance->amount_cents)->toBe(2_500);
});

it('refuses to close an already closed shift', function (): void {
    $shift = openShiftFor(cashier());
    app(CloseShift::class)->handle($shift, 0);

    app(CloseShift::class)->handle($shift->refresh(), 0);
})->throws(ValidationException::class);

// -- HTTP ---------------------------------------------------------------------

it('walks a cashier from opening a drawer to a printed receipt', function (): void {
    $product = Product::factory()->create(['price_cents' => 15_000]);
    giveStock($product, 4);

    $user = cashier();

    $this->actingAs($user)
        ->post(route('admin.pos.shifts.store'), [
            'warehouse_id' => defaultWarehouse()->id,
            'opening_float' => 100,
        ])
        ->assertRedirect(route('admin.pos.register'));

    $this->actingAs($user)->get(route('admin.pos.register'))->assertOk();

    $this->actingAs($user)
        ->post(route('admin.pos.sales.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => 'cash',
        ])
        ->assertRedirect();

    $order = Order::query()->where('placed_via', 'pos')->sole();

    $this->actingAs($user)->get(route('admin.pos.receipt', $order))->assertOk();

    $this->actingAs($user)
        ->post(route('admin.pos.shifts.close'), ['counted_cash' => 310])
        ->assertRedirect(route('admin.pos.register'));

    expect(CashierShift::query()->sole()->status)->toBe(CashierShiftStatus::Closed);
});

it('drops a keyed-in discount from a cashier who lacks pos.discount', function (): void {
    $product = Product::factory()->create(['price_cents' => 50_000]);
    giveStock($product, 3);

    $user = cashier();
    openShiftFor($user);

    expect($user->can('pos.discount'))->toBeFalse();

    $this->actingAs($user)->post(route('admin.pos.sales.store'), [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'payment_method' => 'cash',
        'discount' => 40,
    ])->assertRedirect();

    expect(Order::query()->sole()->total_cents)->toBe(50_000);
});

it('renders each register screen with its own Inertia component', function (): void {
    $product = Product::factory()->create(['price_cents' => 12_000]);
    giveStock($product, 3);

    $user = cashier();

    // Without a drawer the register hands back the open-shift screen instead.
    $this->actingAs($user)
        ->get(route('admin.pos.register'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/pos/OpenShift')
            ->has('warehouses', 1));

    $shift = openShiftFor($user, 5_000);

    $this->actingAs($user)
        ->get(route('admin.pos.register'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/pos/Register')
            ->where('shift.warehouse.id', defaultWarehouse()->id)
            ->has('paymentMethods', 2)
            ->has('products'));

    $order = app(CompleteSale::class)->handle($shift, line($product));

    $this->actingAs($user)
        ->get(route('admin.pos.receipt', $order))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/pos/Receipt')
            ->where('order.number', $order->number)
            ->has('order.items', 1));

    $this->actingAs($user)
        ->get(route('admin.pos.shifts.close'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/pos/CloseShift')
            ->where('shift.expected_cash_cents', 17_000));

    $this->actingAs(staff('order_manager'))
        ->get(route('admin.pos.shifts.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/pos/Shifts')
            ->has('shifts.data', 1));
});

it('re-reads the shelf on every visit, so the figure follows what the other till sold', function (): void {
    $product = Product::factory()->create(['price_cents' => 10_000]);
    giveStock($product, 2);

    $user = cashier();
    openShiftFor($user);

    $shelf = fn () => $this->actingAs($user)->get(route('admin.pos.register'));

    $shelf()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('products.0.id', $product->id)
        ->where('products.0.available', 2));

    // The till in the next room takes one of the two.
    app(CompleteSale::class)->handle(openShiftFor(cashier()), line($product));

    // Nothing is cached between visits, which is what makes the register's
    // ten-second partial reload a live figure rather than a repeat of itself.
    $shelf()->assertInertia(fn (AssertableInertia $page) => $page->where('products.0.available', 1));

    app(CompleteSale::class)->handle(openShiftFor(cashier()), line($product));

    // Sold out is still shipped, as a zero. Hiding the tile is the register's
    // job: the product has to survive the trip so the grid can bring it back
    // the moment a delivery lands, without a full page load.
    $shelf()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('products', 1)
        ->where('products.0.available', 0));
});

it('hands the register a picture for every tile it can find one for', function (): void {
    $plain = Product::factory()->create();
    $plain->images()->create(['path' => 'products/plain.jpg', 'is_primary' => true, 'position' => 0]);
    giveStock($plain, 2);

    // A variant with no shot of its own falls back to the product's.
    $withVariants = Product::factory()->create(['has_variants' => true]);
    $withVariants->images()->create(['path' => 'products/parent.jpg', 'is_primary' => true, 'position' => 0]);
    $own = $withVariants->variants()->create(['name' => 'Red', 'sku' => 'V-RED', 'price_cents' => 5_000, 'image_path' => 'products/red.jpg', 'is_active' => true]);
    $inherits = $withVariants->variants()->create(['name' => 'Blue', 'sku' => 'V-BLUE', 'price_cents' => 5_000, 'is_active' => true]);

    // A product with no image at all must still reach the grid, just blank.
    $bare = Product::factory()->create();
    giveStock($bare, 1);

    $user = cashier();
    openShiftFor($user);

    $products = collect(
        $this->actingAs($user)->get(route('admin.pos.register'))->viewData('page')['props']['products']
    )->keyBy('id');

    expect($products[$plain->id]['image'])->toBe('/storage/products/plain.jpg')
        ->and($products[$bare->id]['image'])->toBeNull();

    $variants = collect($products[$withVariants->id]['variants'])->keyBy('id');

    expect($variants[$own->id]['image'])->toBe('/storage/products/red.jpg')
        ->and($variants[$inherits->id]['image'])->toBe('/storage/products/parent.jpg');
});

it('offers only the categories it can actually sell from', function (): void {
    $sellable = Category::factory()->create(['name' => 'Consoles']);
    $empty = Category::factory()->create(['name' => 'Empty shelf']);

    $product = Product::factory()->create(['category_id' => $sellable->id]);
    giveStock($product, 2);

    // A draft product keeps its category off the chip row entirely.
    Product::factory()->create(['category_id' => $empty->id, 'status' => ProductStatus::Draft]);

    $user = cashier();
    openShiftFor($user);

    $props = $this->actingAs($user)->get(route('admin.pos.register'))->viewData('page')['props'];

    expect(collect($props['categories'])->pluck('name')->all())->toBe(['Consoles'])
        ->and($props['categories'][0]['products_count'])->toBe(1)
        ->and(collect($props['products'])->firstWhere('id', $product->id)['category_id'])->toBe($sellable->id);
});

it('never offers cash on delivery at the register', function (): void {
    $product = Product::factory()->create(['price_cents' => 10_000]);
    giveStock($product, 2);

    $user = cashier();
    openShiftFor($user);

    $this->actingAs($user)
        ->post(route('admin.pos.sales.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cod',
        ])
        ->assertSessionHasErrors('payment_method');
});

it('keeps cash off the storefront checkout', function (): void {
    // The register's payment method must not leak into the shop: checkout is
    // gated on config, not on the enum's case list.
    expect(config('store.payment_methods'))->not->toContain('cash');
});

it('keeps the register away from staff without pos.use', function (): void {
    $this->actingAs(staff('inventory_manager'))
        ->get(route('admin.pos.register'))
        ->assertForbidden();
});
