<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Admin\AdminAttention;
use App\Support\ReferenceNumber;
use Inertia\Testing\AssertableInertia as Assert;

/** A website order waiting to be fulfilled, by default cash on delivery to Tripoli. */
function workflowOrder(array $attributes = []): Order
{
    $address = ['name' => 'Ali', 'phone' => '0912345678', 'country' => 'LY', 'city' => 'Tripoli', 'area' => 'Center'];

    return Order::create([
        'number' => ReferenceNumber::generate('ORD'),
        'status' => OrderStatus::InProgress,
        'payment_status' => PaymentStatus::Unpaid,
        'payment_method' => PaymentMethod::CashOnDelivery,
        'customer' => ['name' => 'Ali', 'email' => 'ali@example.com', 'phone' => '0912345678'],
        'shipping_address' => $address,
        'billing_address' => $address,
        'subtotal_cents' => 10_000,
        'total_cents' => 10_000,
        'currency' => 'LYD',
        'placed_via' => 'web',
        ...$attributes,
    ]);
}

it('lists orders with phone, city and payment method, and filters by them', function (): void {
    workflowOrder();
    workflowOrder(['payment_method' => PaymentMethod::BankTransfer, 'shipping_address' => ['phone' => '0921111111', 'city' => 'Benghazi']]);

    $this->actingAs(staff('super_admin'))
        ->get(route('admin.orders.index', ['city' => 'Benghazi']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.phone', '0921111111')
            ->where('orders.data.0.city', 'Benghazi')
            ->where('orders.data.0.can_complete', true)
            ->where('can.manage', true));

    $this->get(route('admin.orders.index', ['payment_method' => 'cod']))
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.payment_method_value', 'cod'));
});

it('shows delivery cities in Arabic for an Arabic admin', function (): void {
    workflowOrder();
    $admin = staff('super_admin');
    $admin->update(['locale' => 'ar']);

    $this->actingAs($admin)
        ->get(route('admin.orders.index'))
        ->assertInertia(fn (Assert $page) => $page->where('orders.data.0.city', 'طرابلس'));
});

it('completes several in-progress orders at once and skips the rest', function (): void {
    $first = workflowOrder();
    $second = workflowOrder();
    $done = workflowOrder(['status' => OrderStatus::Completed, 'payment_status' => PaymentStatus::Paid]);

    $this->actingAs(staff('super_admin'))
        ->post(route('admin.orders.bulk-complete'), ['ids' => [$first->id, $second->id, $done->id]])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($first->fresh()->status)->toBe(OrderStatus::Completed)
        ->and($second->fresh()->status)->toBe(OrderStatus::Completed)
        // Completing books the cash the driver collected, like the single button.
        ->and($first->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('keeps bulk complete to staff who manage orders', function (): void {
    $order = workflowOrder();

    $this->actingAs(staff('cashier'))
        ->post(route('admin.orders.bulk-complete'), ['ids' => [$order->id]])
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::InProgress);
});

it('prints the slips of several orders in the order they were picked', function (): void {
    $first = workflowOrder();
    $second = workflowOrder();

    $this->actingAs(staff('super_admin'))
        ->get(route('admin.orders.slips', ['ids' => [$second->id, $first->id]]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/orders/DeliverySlips')
            ->has('orders', 2)
            ->where('orders.0.number', $second->number)
            ->where('orders.1.number', $first->number));
});

it('counts only bank transfers as waiting for payment', function (): void {
    workflowOrder();
    workflowOrder(['payment_method' => PaymentMethod::BankTransfer]);

    $item = collect(app(AdminAttention::class)->for(staff('super_admin')))->firstWhere('key', 'awaiting_payment');

    expect($item['count'])->toBe(1)
        ->and($item['href'])->toContain('payment_method=bank_transfer');
});

it('drafts a purchase order from one supplier\'s low stock', function (): void {
    $supplier = Supplier::factory()->create();
    $low = Product::factory()->create(['supplier_id' => $supplier->id, 'low_stock_threshold' => 5, 'cost_price_cents' => 2_500]);
    giveStock($low, 2);
    $plenty = Product::factory()->create(['supplier_id' => $supplier->id, 'low_stock_threshold' => 5]);
    giveStock($plenty, 50);
    $otherSupplier = Product::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'low_stock_threshold' => 5]);
    giveStock($otherSupplier, 1);

    $admin = staff('super_admin');

    $this->actingAs($admin)
        ->get(route('admin.inventory.index', ['filter' => 'low']))
        ->assertInertia(fn (Assert $page) => $page->has('reorder', 2));

    $this->actingAs($admin)
        ->get(route('admin.purchase-orders.create', ['reorder' => 1, 'supplier' => $supplier->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('draft.supplier_id', $supplier->id)
            ->where('draft.warehouse_id', defaultWarehouse()->id)
            ->has('draft.items', 1)
            ->where('draft.items.0.product_id', $low->id)
            // Back up to twice the alert level: 5 × 2 − 2 on hand.
            ->where('draft.items.0.quantity', 8)
            ->where('draft.items.0.unit_cost', 25));
});

it('offers the reorder only to staff who can create purchase orders', function (): void {
    $product = Product::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'low_stock_threshold' => 5]);
    giveStock($product, 1);

    $this->actingAs(staff('cashier'))
        ->get(route('admin.inventory.index', ['filter' => 'low']))
        ->assertInertia(fn (Assert $page) => $page->where('reorder', []));
});

it('hides product cost prices from cashiers', function (): void {
    Product::factory()->create(['cost_price_cents' => 12_345]);

    $this->actingAs(staff('cashier'))
        ->get(route('admin.products.index'))
        ->assertInertia(fn (Assert $page) => $page->where('products.data.0.cost_price_formatted', null));

    $this->actingAs(staff('super_admin'))
        ->get(route('admin.products.index'))
        ->assertInertia(fn (Assert $page) => $page->whereType('products.data.0.cost_price_formatted', 'string'));
});
