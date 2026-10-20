<?php

use App\Actions\Cart\AddToCart;
use App\Actions\Checkout\PlaceOrder;
use App\DTOs\CheckoutData;
use App\Enums\PaymentMethod;
use App\Enums\ReviewStatus;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
 * The admin shell's JSON endpoints: the command palette's record search and
 * the attention counts behind the dashboard tiles and sidebar badges.
 */

function shellOrder(User $customer, Product $product): Order
{
    $cart = Cart::create(['user_id' => $customer->id]);
    app(AddToCart::class)->handle($cart, $product, null, 1);

    $address = ['name' => 'x', 'phone' => '0910000000', 'country' => 'LY', 'city' => 'Tripoli'];

    return app(PlaceOrder::class)->handle($cart, new CheckoutData(
        customer: ['name' => $customer->name, 'email' => $customer->email, 'phone' => '0910000000'],
        shippingAddress: $address,
        billingAddress: $address,
        paymentMethod: PaymentMethod::CashOnDelivery,
    ), $customer);
}

/** @return array<string, int> */
function attentionCounts(array $items): array
{
    return collect($items)->pluck('count', 'key')->all();
}

it('finds orders, products and customers for staff allowed to open them', function (): void {
    $product = Product::factory()->create(['name' => 'Palette Phone', 'sku' => 'PAL-PHONE-1']);
    giveStock($product, 5);
    $buyer = customer(['name' => 'Huda Palette', 'email' => 'huda.palette@example.com']);
    $order = shellOrder($buyer, $product);

    $admin = staff('super_admin');

    $this->actingAs($admin)->getJson(route('admin.search', ['q' => $order->number]))
        ->assertOk()
        ->assertJsonPath('orders.0.id', $order->id)
        ->assertJsonPath('orders.0.url', route('admin.orders.show', $order));

    $this->actingAs($admin)->getJson(route('admin.search', ['q' => 'pal-phone']))
        ->assertOk()
        ->assertJsonPath('products.0.id', $product->id)
        ->assertJsonPath('products.0.url', route('admin.products.edit', $product));

    $this->actingAs($admin)->getJson(route('admin.search', ['q' => 'huda.palette']))
        ->assertOk()
        ->assertJsonPath('customers.0.id', $buyer->id)
        ->assertJsonPath('customers.0.url', route('admin.customers.show', $buyer));
});

it('leaves out the sections a role may not open', function (): void {
    Product::factory()->create(['name' => 'Scoped Item']);

    // Inventory managers see products, never orders or customers.
    $this->actingAs(staff('inventory_manager'))->getJson(route('admin.search', ['q' => 'scoped']))
        ->assertOk()
        ->assertJsonPath('orders', null)
        ->assertJsonPath('customers', null)
        ->assertJsonCount(1, 'products');
});

it('sends view-only staff to the filtered product list instead of the edit form', function (): void {
    $product = Product::factory()->create(['sku' => 'VIEW-ONLY-9']);

    // Cashiers hold products.view but not products.update.
    $this->actingAs(staff('cashier'))->getJson(route('admin.search', ['q' => 'view-only']))
        ->assertOk()
        ->assertJsonPath('products.0.url', route('admin.products.index', ['q' => $product->sku]));
});

it('does not search on a single character', function (): void {
    Product::factory()->create(['name' => 'A thing']);

    $this->actingAs(staff('super_admin'))->getJson(route('admin.search', ['q' => 'a']))
        ->assertOk()
        ->assertExactJson(['orders' => [], 'products' => [], 'customers' => []]);
});

it('keeps the shell endpoints for staff only', function (): void {
    $this->actingAs(customer())->getJson(route('admin.search', ['q' => 'abc']))->assertForbidden();
    $this->actingAs(customer())->getJson(route('admin.attention'))->assertForbidden();
});

it('turns guests away from the shell endpoints', function (): void {
    $this->getJson(route('admin.search', ['q' => 'abc']))->assertUnauthorized();
    $this->getJson(route('admin.attention'))->assertUnauthorized();
});

it('counts what needs attention, leaving counter sales out of the fulfilment queue', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 10);

    // A bank transfer still to confirm: "awaiting payment" leaves unpaid cash-on-delivery
    // orders out, since those are only paid when the driver comes back.
    shellOrder(customer(), $product)->forceFill(['payment_method' => PaymentMethod::BankTransfer])->save();
    shellOrder(customer(), $product)->forceFill(['placed_via' => 'pos'])->save();
    Review::create(['user_id' => customer()->id, 'product_id' => $product->id, 'rating' => 4, 'status' => ReviewStatus::Pending]);

    $counts = attentionCounts($this->actingAs(staff('super_admin'))->getJson(route('admin.attention'))->assertOk()->json('items'));

    expect($counts)->toHaveKeys(['orders_to_fulfil', 'awaiting_payment', 'low_stock', 'out_of_stock', 'pending_reviews', 'purchase_orders_open', 'open_shifts'])
        ->and($counts['orders_to_fulfil'])->toBe(1)
        ->and($counts['awaiting_payment'])->toBe(1)
        ->and($counts['pending_reviews'])->toBe(1);
});

it('only computes the attention items a role can act on', function (): void {
    $keys = array_keys(attentionCounts($this->actingAs(staff('cashier'))->getJson(route('admin.attention'))->json('items')));

    // orders.view, inventory.view and pos.shifts.view — no reviews, no purchasing.
    expect($keys)->toBe(['orders_to_fulfil', 'awaiting_payment', 'low_stock', 'out_of_stock', 'open_shifts']);
});

it('gives the dashboard the same attention items', function (): void {
    $this->actingAs(staff('order_manager'))->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('attention', 3)
            ->where('attention.0.key', 'orders_to_fulfil')
            ->where('attention.0.href', route('admin.orders.index', ['status' => 'in_progress'])));
});

it('shows each dashboard block only to staff allowed to open it', function (): void {
    $this->actingAs(staff('cashier'))
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can.reports', false)
            ->where('kpis.revenue_month', null)
            ->where('kpis.customers_total', null)
            ->where('revenueSeries', [])
            ->where('bestSellers', [])
            ->where('recentActivity', []));

    $this->actingAs(staff('inventory_manager'))
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('can.orders', false)
            ->where('kpis.orders_total', null)
            ->where('recentOrders', [])
            ->where('staleCodOrders', []));

    $this->actingAs(staff('super_admin'))
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('can.reports', true)
            ->whereType('kpis.revenue_month', 'string')
            ->whereType('kpis.orders_total', 'integer'));
});
