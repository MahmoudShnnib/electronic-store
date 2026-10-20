<?php

use App\Models\Order;
use App\Models\Product;

it('revokes every API token when an account is deactivated', function (): void {
    $user = customer();
    $user->createToken('phone');
    $user->createToken('tablet');

    $user->update(['is_active' => false]);

    expect($user->tokens()->count())->toBe(0);
});

it('keeps tokens when an account is edited but stays active', function (): void {
    $user = customer();
    $user->createToken('phone');

    $user->update(['name' => 'Renamed']);

    expect($user->tokens()->count())->toBe(1);
});

it('refuses cart and checkout for a deactivated account whose token survived', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 5);
    $user = customer();
    $token = $user->createToken('phone')->plainTextToken;

    // Deactivated behind the model's back (a direct DB edit), so the token is still there.
    $user->forceFill(['is_active' => false])->saveQuietly();

    $this->withToken($token)
        ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
        ->assertForbidden();

    $this->withToken($token)->getJson('/api/v1/cart')->assertForbidden();

    expect(Order::query()->count())->toBe(0);
});

it('still serves an active customer through the same routes', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 5);
    $token = customer()->createToken('phone')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
        ->assertCreated();
});
