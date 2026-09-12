<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\TreasuryTransaction;
use App\Support\Money;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

function manualOrderPayload(Product $product, int $quantity = 2): array
{
    return [
        'customer' => ['name' => 'Salem Ali', 'phone' => '0911234567', 'email' => 'salem@example.test'],
        'shipping_address' => ['city' => 'Tripoli', 'area' => 'Hay Andalus'],
        'payment_method' => 'cod',
        'source' => 'whatsapp',
        'customer_note' => 'Ordered on WhatsApp',
        'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
    ];
}

it('creates an order from the admin form and reserves the stock', function (): void {
    $product = Product::factory()->create(['price_cents' => 120_00]);
    giveStock($product, 10);

    $this->actingAs(staff('order_manager'))
        ->post(route('admin.orders.store'), manualOrderPayload($product))
        ->assertRedirect();

    $order = Order::query()->sole();
    $stock = StockLevel::query()->sole();

    expect($order->status)->toBe(OrderStatus::InProgress)
        ->and($order->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($order->placed_via)->toBe('whatsapp')
        ->and($order->total_cents)->toBe(240_00)
        ->and($order->customer['name'])->toBe('Salem Ali')
        ->and($order->shipping_address['area'])->toBe('Hay Andalus')
        // Same reservation behaviour as a storefront order.
        ->and($stock->quantity)->toBe(8)
        ->and($stock->reserved)->toBe(2)
        // Nothing is collected until the order is completed.
        ->and(TreasuryTransaction::query()->count())->toBe(0);
});

it('prices the lines server-side rather than trusting the form', function (): void {
    $product = Product::factory()->create(['price_cents' => 120_00, 'sale_price_cents' => 90_00]);
    giveStock($product, 10);

    $this->actingAs(staff('order_manager'))
        ->post(route('admin.orders.store'), manualOrderPayload($product, 1))
        ->assertRedirect();

    expect(Order::query()->sole()->total_cents)->toBe(90_00);
});

it('banks the money when the manual order is completed', function (): void {
    $product = Product::factory()->create(['price_cents' => 120_00]);
    giveStock($product, 10);

    $this->actingAs(staff('order_manager'))->post(route('admin.orders.store'), manualOrderPayload($product, 1));

    $order = Order::query()->sole();

    $this->actingAs(staff('order_manager'))
        ->post(route('admin.orders.status', $order), ['status' => 'completed'])
        ->assertRedirect();

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and(TreasuryTransaction::query()->sole()->amount_cents)->toBe(120_00);
});

it('refuses to oversell from the admin form', function (): void {
    $product = Product::factory()->create(['price_cents' => 120_00]);
    giveStock($product, 1);

    $this->actingAs(staff('order_manager'))
        ->post(route('admin.orders.store'), manualOrderPayload($product, 5))
        ->assertSessionHasErrors();

    expect(Order::query()->count())->toBe(0);
});

it('rejects a form with no lines', function (): void {
    $this->actingAs(staff('order_manager'))
        ->post(route('admin.orders.store'), [
            'customer' => ['name' => 'Salem', 'phone' => '0911234567'],
            'shipping_address' => ['city' => 'Tripoli', 'area' => 'Hay Andalus'],
            'payment_method' => 'cod',
            'items' => [],
        ])
        ->assertSessionHasErrors('items');
});

it('keeps the form away from staff without orders.manage', function (): void {
    $this->actingAs(staff('inventory_manager'))
        ->get(route('admin.orders.create'))
        ->assertForbidden();
});

it('leaves no scaffolding cart behind', function (): void {
    $product = Product::factory()->create(['price_cents' => 120_00]);
    giveStock($product, 10);

    $this->actingAs(staff('order_manager'))->post(route('admin.orders.store'), manualOrderPayload($product, 1));

    expect(Cart::query()->count())->toBe(0);
});

it('prints a delivery slip carrying the recipient, the items and the cash to collect', function (): void {
    $product = Product::factory()->create(['price_cents' => 120_00, 'name' => 'USB-C Cable']);
    giveStock($product, 10);

    $this->actingAs(staff('order_manager'))
        ->post(route('admin.orders.store'), manualOrderPayload($product));

    $order = Order::query()->sole();

    $this->actingAs(staff('order_manager'))
        ->get(route('admin.orders.slip', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/orders/DeliverySlip')
            ->where('order.recipient.name', 'Salem Ali')
            ->where('order.recipient.phone', '0911234567')
            ->where('order.recipient.address', ['Hay Andalus', 'Tripoli'])
            ->where('order.items.0.name', 'USB-C Cable')
            ->where('order.items.0.quantity', 2)
            // Unpaid COD: the driver comes back with the full total. No LTR
            // isolate — the slip prints on the 80mm head, like a receipt.
            ->where('order.collect_formatted', Money::format($order->total_cents, $order->currency, isolate: false))
            ->where('order.customer_note', 'Ordered on WhatsApp'));
});
