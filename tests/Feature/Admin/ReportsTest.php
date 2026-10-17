<?php

use App\Actions\Cart\AddToCart;
use App\Actions\Checkout\PlaceOrder;
use App\Actions\Orders\UpdateOrderStatus;
use App\DTOs\CheckoutData;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

/** A confirmed order from a registered customer, so every report has data —
 *  including the customer report, whose users join once hid an ambiguous
 *  deleted_at column that only surfaced at runtime. */
function seedRevenueOrder(bool $complete = true): Order
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

    return $complete
        ? app(UpdateOrderStatus::class)->handle($order, OrderStatus::Completed)
        : $order;
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

it('renders the invoice report with paid and outstanding amounts', function (): void {
    $order = seedRevenueOrder(complete: false); // 2 × 150.00 = 300.00

    $order->payments()->create([
        'method' => 'cod',
        'status' => 'confirmed',
        'amount_cents' => 100_00,
    ]);

    $this->actingAs(staff('super_admin'))
        ->get(route('admin.reports.invoices'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/Invoices')
            ->where('summary.invoices', 1)
            ->has('invoices', 1)
            ->where('invoices.0.number', $order->number)
            ->where('invoices.0.outstanding_cents', 200_00));
});

it('exports every report without errors', function (): void {
    seedRevenueOrder();

    $reports = [
        'sales_by_day', 'sales_by_product', 'sales_by_category', 'sales_by_brand',
        'sales_by_customer', 'invoices', 'inventory_valuation', 'low_stock',
        'movements', 'refunds',
    ];

    $this->actingAs(staff('super_admin'));

    foreach ($reports as $report) {
        $this->get(route('admin.reports.export', $report))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
});

it('denies reports to roles without reports.view', function (): void {
    $this->actingAs(customer())
        ->get(route('admin.reports.sales'))
        ->assertForbidden();
});

it('keeps products without a brand in the brand table so it adds up to revenue', function (): void {
    seedRevenueOrder();
    Product::query()->update(['brand_id' => null]);

    $this->actingAs(staff('super_admin'))
        ->get(route('admin.reports.sales'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('byBrand', 1)
            ->where('byBrand.0.name', 'No brand')
            ->where('byBrand.0.units', 2)
            ->where('byBrand.0.revenue', 300));
});

it('filters the orders list by the shop\'s local day, like the reports', function (): void {
    $order = seedRevenueOrder(false);
    // 23:30 UTC on the 11th is 01:30 on the 12th in Tripoli.
    $order->forceFill(['created_at' => '2026-10-11 23:30:00'])->save();

    $list = fn (string $day) => $this->actingAs(staff('super_admin'))
        ->get(route('admin.orders.index', ['from' => $day, 'to' => $day]));

    $list('2026-10-12')->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
    $list('2026-10-11')->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
});

it('echoes the chosen range back as the same local dates, so Apply does not creep a day back', function (): void {
    $this->actingAs(staff('super_admin'))
        ->get(route('admin.reports.sales', ['from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertInertia(fn (Assert $page) => $page->where('filters', ['from' => '2026-10-01', 'to' => '2026-10-31']));

    $this->actingAs(staff('super_admin'))
        ->get(route('admin.reports.invoices', ['from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertInertia(fn (Assert $page) => $page->where('filters', ['from' => '2026-10-01', 'to' => '2026-10-31']));
});
