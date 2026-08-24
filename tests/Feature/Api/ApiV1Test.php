<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('registers and returns a bearer token', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Mobile User',
        'email' => 'mobile@test.dev',
        'password' => 'secret-password-1',
        'device_name' => 'iphone',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

    expect(User::query()->where('email', 'mobile@test.dev')->sole()->hasRole('customer'))->toBeTrue();
});

it('logs in with valid credentials and rejects invalid ones', function (): void {
    $user = customer(['password' => 'secret-password-1']);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong',
        'device_name' => 'test',
    ])->assertUnprocessable();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'secret-password-1',
        'device_name' => 'test',
    ])->assertOk()->assertJsonStructure(['token']);
});

it('rejects deactivated accounts at login', function (): void {
    $user = customer(['password' => 'secret-password-1', 'is_active' => false]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'secret-password-1',
        'device_name' => 'test',
    ])->assertUnprocessable();
});

it('lists products through API resources with pagination', function (): void {
    Product::factory()->count(3)->create();
    Product::factory()->draft()->create(); // hidden

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'name', 'slug', 'current_price', 'currency', 'in_stock']],
            'links', 'meta',
        ]);
});

it('runs a full guest cart → checkout flow with a cart token', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_00]);
    giveStock($product, 5);

    $cart = $this->postJson('/api/v1/cart/items', [
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $cart->assertCreated();
    $token = $cart->json('cart_token');
    expect($token)->not->toBeNull();

    $checkout = $this->withHeader('X-Cart-Token', $token)->postJson('/api/v1/checkout', [
        'customer' => ['name' => 'Guest', 'email' => 'guest@test.dev', 'phone' => '0912345678'],
        'shipping_address' => ['country' => 'LY', 'city' => 'Tripoli', 'area' => 'Downtown'],
        'payment_method' => 'cod',
        'idempotency_key' => 'api-guest-1',
    ]);

    $checkout->assertCreated()
        ->assertJsonPath('data.total', 200)
        ->assertJsonStructure(['payment' => ['status', 'instructions']]);

    expect(Order::query()->sole()->placed_via)->toBe('api');
});

it('replays checkout idempotently', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_00]);
    giveStock($product, 5);
    $user = customer();

    Sanctum::actingAs($user);

    $payload = [
        'customer' => ['name' => 'U', 'email' => $user->email, 'phone' => '0912345678'],
        'shipping_address' => ['country' => 'LY', 'city' => 'Tripoli', 'area' => 'Downtown'],
        'payment_method' => 'cod',
        'idempotency_key' => 'api-idem-1',
    ];

    $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertCreated();
    $first = $this->postJson('/api/v1/checkout', $payload)->assertCreated();
    $second = $this->postJson('/api/v1/checkout', $payload)->assertCreated();

    expect($second->json('data.number'))->toBe($first->json('data.number'))
        ->and(Order::query()->count())->toBe(1);
});

it('scopes orders to the authenticated user (no IDOR)', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 5);

    $owner = customer();
    Sanctum::actingAs($owner);
    $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
    $this->postJson('/api/v1/checkout', [
        'customer' => ['name' => 'O', 'email' => $owner->email, 'phone' => '0912345678'],
        'shipping_address' => ['country' => 'LY', 'city' => 'Tripoli', 'area' => 'Downtown'],
        'payment_method' => 'cod',
    ])->assertCreated();

    $order = Order::query()->sole();

    Sanctum::actingAs(customer());

    $this->getJson("/api/v1/orders/{$order->id}")->assertForbidden();
    $this->getJson('/api/v1/orders')->assertOk()->assertJsonCount(0, 'data');
});

it('requires authentication for account endpoints', function (): void {
    $this->getJson('/api/v1/orders')->assertUnauthorized();
    $this->getJson('/api/v1/wishlist')->assertUnauthorized();
    $this->getJson('/api/v1/addresses')->assertUnauthorized();
});

it('manages addresses over the API', function (): void {
    $user = customer();
    Sanctum::actingAs($user);

    $created = $this->postJson('/api/v1/addresses', [
        'name' => 'Home', 'phone' => '091', 'country' => 'LY',
        'city' => 'Tripoli', 'area' => 'Andalus', 'is_default_shipping' => true,
    ])->assertCreated();

    $id = $created->json('data.id');

    $this->patchJson("/api/v1/addresses/{$id}", [
        'name' => 'Home 2', 'phone' => '092', 'country' => 'LY',
        'city' => 'Benghazi', 'area' => 'Salmani',
    ])->assertOk()->assertJsonPath('data.city', 'Benghazi');

    // Another user cannot touch it.
    Sanctum::actingAs(customer());
    $this->deleteJson("/api/v1/addresses/{$id}")->assertForbidden();
});

it('toggles wishlist items', function (): void {
    $user = customer();
    $product = Product::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/wishlist/{$product->id}")->assertCreated();
    $this->getJson('/api/v1/wishlist')->assertOk()->assertJsonCount(1, 'data');
    $this->deleteJson("/api/v1/wishlist/{$product->id}")->assertOk();
    $this->getJson('/api/v1/wishlist')->assertOk()->assertJsonCount(0, 'data');
});

it('revokes tokens on logout', function (): void {
    $user = customer(['password' => 'secret-password-1']);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email, 'password' => 'secret-password-1', 'device_name' => 'd1',
    ])->json('token');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    // The sanctum guard caches the resolved user within a single app instance.
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/user')
        ->assertUnauthorized();
});
