<?php

use App\Actions\Cart\AddToCart;
use App\Actions\Checkout\PlaceOrder;
use App\Actions\Orders\UpdateOrderStatus;
use App\DTOs\CheckoutData;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Cart;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

/** A confirmed order from a registered customer, so every report has data —
 *  including the customer report, whose users join once hid an ambiguous
 *  deleted_at column that only surfaced at runtime. */
function seedRevenueOrder(): void
{
    $product = Product::factory()->create(['price_cents' => 150_00]);
    giveStock($product, 5);

    $buyer = customer();
    $cart = Cart::create(['user_id' => $buyer->id]);
    app(AddToCart::class)->handle($cart, $product, null, 2);

    $order = app(PlaceOrder::class)->handle($cart, new CheckoutData(
        customer: ['name' => $buyer->name, 'email' => $buyer->email, 'phone' => '091'],
        shippingAddress: ['name' => $buyer->name, 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        billingAddress: ['name' => $buyer->name, 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        paymentMethod: PaymentMethod::CashOnDelivery,
    ), $buyer);

    app(UpdateOrderStatus::class)->handle($order, OrderStatus::Confirmed);
}

it('renders the sales report with a customer breakdown', function (): void {
    seedRevenueOrder();

    $this->actingAs(staff('super_admin'))
        ->get(route('admin.reports.sales'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/Sales')
            ->has('byCustomer', 1)
            ->where('summary.orders', 1));
});

it('renders the inventory report', function (): void {
    seedRevenueOrder();

    $this->actingAs(staff('super_admin'))
        ->get(route('admin.reports.inventory'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/reports/Inventory'));
});

it('exports the customer report as CSV', function (): void {
    seedRevenueOrder();

    $this->actingAs(staff('super_admin'))
        ->get(route('admin.reports.export', 'sales_by_customer'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('denies reports to roles without reports.view', function (): void {
    $this->actingAs(customer())
        ->get(route('admin.reports.sales'))
        ->assertForbidden();
});
