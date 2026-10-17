<?php

use App\Actions\Pos\CloseShift;
use App\Actions\Pos\CompleteSale;
use App\Actions\Pos\OpenShift;
use App\Enums\OrderStatus;
use App\Models\CashierShift;
use App\Models\Order;
use App\Models\Product;
use App\Models\Refund;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Warehouse;
use Inertia\Testing\AssertableInertia as Assert;

function csShift(User $user, int $float = 0, ?Warehouse $warehouse = null): CashierShift
{
    return app(OpenShift::class)->handle($user, $warehouse ?? defaultWarehouse(), $float);
}

function csSell(CashierShift $shift, Product $product, int $qty = 1, array $customer = []): Order
{
    return app(CompleteSale::class)->handle($shift, [['product_id' => $product->id, 'quantity' => $qty]], customer: $customer);
}

it('numbers counter receipts in one gapless run per branch', function (): void {
    $product = Product::factory()->create(['price_cents' => 1_000]);
    $branch = Warehouse::query()->create(['name' => 'Benghazi', 'code' => 'BNG', 'is_active' => true]);
    giveStock($product, 5);
    giveStock($product, 5, null, $branch);

    $main = csShift(staff('cashier'));
    $other = csShift(staff('cashier'), 0, $branch);

    expect(csSell($main, $product)->number)->toBe('POS-MAIN-000001')
        ->and(csSell($other, $product)->number)->toBe('POS-BNG-000001')
        ->and(csSell($main, $product)->number)->toBe('POS-MAIN-000002');
});

it('links a counter sale to a customer account by phone, and never creates one', function (): void {
    $product = Product::factory()->create(['price_cents' => 1_000]);
    giveStock($product, 5);
    $regular = customer();
    $regular->update(['phone' => '0912345678']);
    $shift = csShift(staff('cashier'));
    $users = User::query()->count();

    // Written another way at the till: same customer.
    $linked = csSell($shift, $product, 1, ['phone' => '+218 91 234 5678']);
    $walkIn = csSell($shift, $product, 1, ['phone' => '0920000000', 'name' => 'Ali']);

    expect($linked->user_id)->toBe($regular->id)
        ->and($linked->customer['name'])->toBe($regular->name)
        ->and($walkIn->user_id)->toBeNull()
        ->and($walkIn->customer['name'])->toBe('Ali')
        ->and(User::query()->count())->toBe($users);

    $this->actingAs(staff('cashier'))->getJson(route('admin.pos.customers.lookup', ['phone' => '091 234 5678']))
        ->assertJson(['customer' => ['id' => $regular->id, 'name' => $regular->name]]);
});

it('keeps counter sales off the orders list and on their own page', function (): void {
    $product = Product::factory()->create(['price_cents' => 1_000]);
    giveStock($product, 5);
    $cashier = staff('cashier');
    csSell(csShift($cashier), $product, 2);
    csSell(csShift(staff('cashier')), $product, 1);

    $this->actingAs(staff('admin'))->get(route('admin.orders.index'))
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));

    $this->actingAs(staff('admin'))->get(route('admin.pos.sales.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/pos/Sales')
            ->has('sales.data', 2)
            ->where('totals.sales', 2)
            ->where('sales.data.0.customer', null));

    // A cashier sees the receipts they rang up, nobody else's.
    $this->actingAs($cashier)->get(route('admin.pos.sales.index'))
        ->assertInertia(fn (Assert $page) => $page->has('sales.data', 1)->where('ownOnly', true));
});

it('takes a sale back at the till and pays it out of that drawer only', function (): void {
    $product = Product::factory()->create(['price_cents' => 10_000]);
    giveStock($product, 3);

    // Cashier A sells; manager B (with their own drawer) takes it back.
    $shiftA = csShift(staff('cashier'), 5_000);
    $sale = csSell($shiftA, $product);
    $manager = staff('order_manager');
    $shiftB = csShift($manager, 20_000);

    $this->actingAs($manager)->getJson(route('admin.pos.returns.lookup', ['number' => strtolower($sale->number)]))
        ->assertJson(['found' => true, 'sale' => ['id' => $sale->id, 'returnable' => true]]);

    $this->actingAs($manager)->post(route('admin.pos.returns.store'), ['order_id' => $sale->id, 'reason' => 'Changed mind', 'method' => 'cash'])
        ->assertSessionHasNoErrors();

    $refund = Refund::query()->sole();
    expect($sale->refresh()->status)->toBe(OrderStatus::Refunded)
        ->and($refund->cashier_shift_id)->toBe($shiftB->id)
        ->and(StockLevel::query()->where('product_id', $product->id)->sum('quantity'))->toBe(3)
        // One refund, one drawer: A still expects its float + the sale, B paid it out.
        ->and($shiftA->refresh()->expectedCashCents())->toBe(15_000)
        ->and($shiftB->refresh()->expectedCashCents())->toBe(10_000);

    // Counting each drawer at what it should hold books no phantom variance.
    expect(app(CloseShift::class)->handle($shiftA, 15_000)->difference_cents)->toBe(0)
        ->and(app(CloseShift::class)->handle($shiftB, 10_000)->difference_cents)->toBe(0);

    // A second return of the same receipt is refused.
    $this->actingAs($manager)->getJson(route('admin.pos.returns.lookup', ['number' => $sale->number]))
        ->assertJson(['found' => true, 'sale' => ['returnable' => false]]);
});

it('refuses a counter sale return from the back office, and a till return without a reason or the right', function (): void {
    $product = Product::factory()->create(['price_cents' => 10_000]);
    giveStock($product, 2);
    $cashier = staff('cashier');
    $sale = csSell(csShift($cashier), $product);
    $admin = staff('admin');

    $this->actingAs($admin)->post(route('admin.orders.issue', $sale), ['reason' => 'x', 'amount' => 100, 'method' => 'cash', 'restock' => true])
        ->assertSessionHas('error');
    expect($sale->refresh()->status)->toBe(OrderStatus::Completed);

    // A cashier without refunds.manage can't take returns at all.
    $this->actingAs($cashier)->post(route('admin.pos.returns.store'), ['order_id' => $sale->id, 'reason' => 'x'])->assertForbidden();

    csShift($admin);
    $this->actingAs($admin)->post(route('admin.pos.returns.store'), ['order_id' => $sale->id, 'reason' => ''])->assertSessionHasErrors('reason');
    expect(Refund::query()->count())->toBe(0);
});
