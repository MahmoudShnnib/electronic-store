<?php

use App\Models\Product;

it('blocks customers from the admin area', function (): void {
    $this->actingAs(customer())->get('/admin')->assertForbidden();
});

it('blocks guests from the admin area', function (): void {
    $this->get('/admin')->assertRedirect(route('login'));
});

it('lets each staff role reach only its own sections', function (): void {
    $inventory = staff('inventory_manager');
    $orders = staff('order_manager');

    // Inventory manager: products yes, orders no, users no.
    $this->actingAs($inventory)->get(route('admin.products.index'))->assertOk();
    $this->actingAs($inventory)->get(route('admin.inventory.index'))->assertOk();
    $this->actingAs($inventory)->get(route('admin.orders.index'))->assertForbidden();
    $this->actingAs($inventory)->get(route('admin.users.index'))->assertForbidden();

    // Order manager: orders yes, products no.
    $this->actingAs($orders)->get(route('admin.orders.index'))->assertOk();
    $this->actingAs($orders)->get(route('admin.returns.index'))->assertOk();
    $this->actingAs($orders)->get(route('admin.products.index'))->assertForbidden();
});

it('gives super admin access to everything', function (): void {
    $admin = staff('super_admin');

    foreach (['admin.dashboard', 'admin.products.index', 'admin.orders.index', 'admin.users.index', 'admin.roles.index', 'admin.audit-logs.index', 'admin.settings.index'] as $route) {
        $this->actingAs($admin)->get(route($route))->assertOk();
    }
});

it('blocks deactivated accounts immediately', function (): void {
    $user = customer(['is_active' => false]);

    $this->actingAs($user)->get('/account/orders')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('enforces product permissions on write endpoints', function (): void {
    $orderManager = staff('order_manager');
    $product = Product::factory()->create();

    $this->actingAs($orderManager)
        ->delete(route('admin.products.destroy', $product))
        ->assertForbidden();

    expect($product->refresh()->deleted_at)->toBeNull();
});
