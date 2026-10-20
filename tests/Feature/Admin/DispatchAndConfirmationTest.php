<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Product;
use App\Support\ReferenceNumber;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

/** A website cash-on-delivery order, in progress and unconfirmed unless told otherwise. */
function dispatchOrder(array $attributes = []): Order
{
    $address = ['name' => 'Ali', 'phone' => '0912345678', 'country' => 'LY', 'city' => 'Tripoli', 'area' => 'Center'];
    $createdAt = $attributes['created_at'] ?? null;
    unset($attributes['created_at']);

    $order = Order::create([
        'number' => ReferenceNumber::generate('ORD'),
        'status' => OrderStatus::InProgress,
        'payment_status' => PaymentStatus::Unpaid,
        'payment_method' => PaymentMethod::CashOnDelivery,
        'customer' => ['name' => 'Ali', 'phone' => '0912345678'],
        'shipping_address' => $address,
        'billing_address' => $address,
        'subtotal_cents' => 15_000,
        'total_cents' => 15_000,
        'currency' => 'LYD',
        'placed_via' => 'web',
        ...$attributes,
    ]);

    if ($createdAt !== null) {
        $order->forceFill(['created_at' => $createdAt])->save();
    }

    return $order;
}

function driver(array $attributes = []): Driver
{
    return Driver::query()->create(['name' => 'Salem', 'phone' => '0921234567', 'is_active' => true, ...$attributes]);
}

it('hands an order to a driver and takes it back', function (): void {
    $order = dispatchOrder();
    $salem = driver();
    $admin = staff('super_admin');

    $this->actingAs($admin)->post(route('admin.orders.dispatch', $order), ['driver_id' => $salem->id])->assertSessionHasNoErrors();

    expect($order->fresh())
        ->driver_id->toBe($salem->id)
        ->shipped_at->not->toBeNull()
        ->status->toBe(OrderStatus::InProgress);

    $this->actingAs($admin)->delete(route('admin.orders.dispatch.recall', $order))->assertSessionHasNoErrors();

    expect($order->fresh())->driver_id->toBeNull()->shipped_at->toBeNull();
    expect($order->statusHistories()->pluck('comment')->implode('|'))->toContain('Salem');
});

it('refuses to send out an order that is not in progress, or with an inactive driver', function (): void {
    $admin = staff('super_admin');

    $this->actingAs($admin)
        ->post(route('admin.orders.dispatch', dispatchOrder(['status' => OrderStatus::Completed])), ['driver_id' => driver()->id])
        ->assertSessionHasErrors('driver_id');

    $this->actingAs($admin)
        ->post(route('admin.orders.dispatch', dispatchOrder()), ['driver_id' => driver(['is_active' => false])->id])
        ->assertSessionHasErrors('driver_id');
});

it('loads a driver\'s run in one go and lists it on their manifest', function (): void {
    $first = dispatchOrder();
    $second = dispatchOrder(['payment_status' => PaymentStatus::Paid]);
    $salem = driver();
    $admin = staff('super_admin');

    $this->actingAs($admin)
        ->post(route('admin.orders.bulk-dispatch'), ['ids' => [$first->id, $second->id], 'driver_id' => $salem->id])
        ->assertSessionHas('success');

    $this->actingAs($admin)
        ->get(route('admin.drivers.manifest', $salem))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/drivers/Manifest')
            ->has('orders', 2)
            // Only the unpaid order is cash to collect.
            ->where('orders.1.collect_formatted', null)
            ->where('total_formatted', fn ($total) => str_contains($total, '150.000')));
});

it('stops a customer cancelling once the order is out with a driver', function (): void {
    $customer = customer();
    $product = Product::factory()->create();
    $order = dispatchOrder(['user_id' => $customer->id]);
    $order->items()->create([
        'product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku,
        'unit_price_cents' => 15_000, 'unit_cost_cents' => 1, 'cost_cents' => 1,
        'cost_layers' => [['cost' => 1, 'qty' => 1, 'received_at' => now()->toIso8601String()]],
        'quantity' => 1, 'line_total_cents' => 15_000,
    ]);
    $order->update(['driver_id' => driver()->id, 'shipped_at' => now()]);

    Sanctum::actingAs($customer);

    $this->getJson("/api/v1/orders/{$order->id}")
        ->assertJsonPath('data.cancellable', false)
        ->assertJsonPath('data.out_for_delivery', true);

    $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertForbidden();
});

it('records confirmation calls and confirms the order', function (): void {
    $order = dispatchOrder();
    $admin = staff('super_admin');

    $this->actingAs($admin)->post(route('admin.orders.calls.store', $order), ['outcome' => 'no_answer'])->assertSessionHasNoErrors();
    expect($order->fresh()->confirmed_at)->toBeNull();

    $this->actingAs($admin)->post(route('admin.orders.calls.store', $order), ['outcome' => 'confirmed', 'note' => 'Deliver after 5pm'])->assertSessionHasNoErrors();

    expect($order->fresh()->confirmed_at)->not->toBeNull()
        ->and($order->callAttempts()->count())->toBe(2);

    $this->actingAs($admin)
        ->get(route('admin.orders.index', ['confirmation' => 'unconfirmed']))
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
});

it('keeps calls and dispatch to staff who manage orders', function (): void {
    $order = dispatchOrder();

    $this->actingAs(staff('cashier'))->post(route('admin.orders.calls.store', $order), ['outcome' => 'confirmed'])->assertForbidden();
    $this->actingAs(staff('cashier'))->post(route('admin.orders.dispatch', $order), ['driver_id' => driver()->id])->assertForbidden();
});

it('cancels unconfirmed website orders after the limit and leaves the rest alone', function (): void {
    config(['store.unconfirmed_cancel_hours' => 72]);

    $stale = dispatchOrder(['created_at' => now()->subHours(73)]);
    $fresh = dispatchOrder(['created_at' => now()->subHours(10)]);
    $confirmed = dispatchOrder(['created_at' => now()->subHours(73), 'confirmed_at' => now()->subHours(70)]);
    $outWithDriver = dispatchOrder(['created_at' => now()->subHours(73), 'driver_id' => driver()->id, 'shipped_at' => now()->subHours(1)]);
    $phoneOrder = dispatchOrder(['created_at' => now()->subHours(73), 'placed_via' => 'whatsapp']);
    $bankTransfer = dispatchOrder(['created_at' => now()->subHours(73), 'payment_method' => PaymentMethod::BankTransfer]);

    Artisan::call('orders:cancel-unconfirmed');

    expect($stale->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($fresh->fresh()->status)->toBe(OrderStatus::InProgress)
        ->and($confirmed->fresh()->status)->toBe(OrderStatus::InProgress)
        ->and($outWithDriver->fresh()->status)->toBe(OrderStatus::InProgress)
        ->and($phoneOrder->fresh()->status)->toBe(OrderStatus::InProgress)
        ->and($bankTransfer->fresh()->status)->toBe(OrderStatus::InProgress);
});

it('can be switched off', function (): void {
    config(['store.unconfirmed_cancel_hours' => 0]);
    $stale = dispatchOrder(['created_at' => now()->subDays(10)]);

    Artisan::call('orders:cancel-unconfirmed');

    expect($stale->fresh()->status)->toBe(OrderStatus::InProgress);
});

it('manages drivers', function (): void {
    $admin = staff('super_admin');

    $this->actingAs($admin)->post(route('admin.drivers.store'), ['name' => 'Omar', 'phone' => '0911111111'])->assertSessionHasNoErrors();
    $omar = Driver::query()->sole();

    $this->actingAs($admin)->patch(route('admin.drivers.update', $omar), ['name' => 'Omar A.', 'is_active' => false])->assertSessionHasNoErrors();

    expect($omar->fresh())->name->toBe('Omar A.')->is_active->toBeFalse();

    $this->actingAs(staff('cashier'))->get(route('admin.drivers.index'))->assertForbidden();
});
