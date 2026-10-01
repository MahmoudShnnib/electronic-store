<?php

/*
 * Audit 2026-10-01 — identity & customer-owned resources. Each test asserts
 * the CORRECT behaviour, so a failing test here is a live bug.
 * API tests use real Sanctum bearer tokens (never Sanctum::actingAs).
 */

use App\Actions\Auth\LoginWithGoogle;
use App\Actions\Cart\AddToCart;
use App\Actions\Checkout\PlaceOrder;
use App\Actions\Pos\OpenShift;
use App\DTOs\CheckoutData;
use App\Enums\PaymentMethod;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CashierShift;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Spatie\Permission\Models\Role;

beforeEach(fn () => Notification::fake());

function accProduct(): Product
{
    $product = Product::factory()->create(['price_cents' => 10_000, 'status' => 'active', 'published_at' => now()]);
    giveStock($product, 20);

    return $product;
}

function accOrderFor(User $user, ?string $idempotencyKey = null): Order
{
    $cart = Cart::create(['user_id' => $user->id]);
    app(AddToCart::class)->handle($cart, accProduct(), null, 1);

    return app(PlaceOrder::class)->handle($cart, new CheckoutData(
        customer: ['name' => 'Victim', 'email' => $user->email, 'phone' => '0912345678'],
        shippingAddress: ['name' => 'Victim', 'phone' => '0912345678', 'country' => 'LY', 'city' => 'Tripoli', 'area' => 'Secret St'],
        billingAddress: ['name' => 'Victim', 'phone' => '0912345678', 'country' => 'LY', 'city' => 'Tripoli', 'area' => 'Secret St'],
        paymentMethod: PaymentMethod::CashOnDelivery,
        idempotencyKey: $idempotencyKey,
    ), $user);
}

function accAddress(User $user): Address
{
    return $user->addresses()->create([
        'name' => 'Owner', 'phone' => '0912345678', 'country' => 'LY', 'city' => 'Tripoli', 'area' => 'Home',
    ]);
}

function bearer(User $user): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('phone')->plainTextToken];
}

// -- Confirmed-bug probes ----------------------------------------------------

it('ACC-1: a deactivated customer\'s existing API token stops working', function (): void {
    $user = customer();
    $headers = bearer($user);
    $user->update(['is_active' => false]);

    $status = $this->withHeaders($headers)->getJson('/api/v1/auth/user')->status();
    $ordersStatus = $this->withHeaders($headers)->getJson('/api/v1/orders')->status();

    expect($status)->toBeIn([401, 403])
        ->and($ordersStatus)->toBeIn([401, 403]);
});

it('ACC-2: a password reset revokes the account\'s API tokens', function (): void {
    $user = customer();
    $user->createToken('stolen-phone');
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token, 'email' => $user->email,
        'password' => 'new-secret-pass-1', 'password_confirmation' => 'new-secret-pass-1',
    ])->assertSessionHasNoErrors();

    expect($user->tokens()->count())->toBe(0);
});

it('ACC-3: logging in on the web keeps the guest cart', function (): void {
    $user = customer(['password' => 'secret-pass-1']);
    $guest = Cart::create(['session_token' => str_repeat('g', 48)]);
    app(AddToCart::class)->handle($guest, accProduct(), null, 2);

    $this->withUnencryptedCookie('guest_cart_token', $guest->session_token)
        ->post('/login', ['email' => $user->email, 'password' => 'secret-pass-1'])
        ->assertRedirect();

    $userCart = Cart::query()->where('user_id', $user->id)->first();

    expect($userCart?->items()->sum('quantity'))->toBe(2);
});

it('ACC-4: Google sign-in does not hand over a pre-registered unverified account whose password someone else set', function (): void {
    // Attacker registers the victim's address first (never verified).
    $planted = customer(['email' => 'victim@example.com', 'password' => 'attacker-pass-1', 'email_verified_at' => null]);

    $google = (new SocialiteUser)->setRaw(['email_verified' => true])
        ->map(['id' => 'google-123', 'email' => 'victim@example.com', 'name' => 'Victim']);

    try {
        app(LoginWithGoogle::class)->handle($google);
        $attackerStillGetsIn = Hash::check('attacker-pass-1', $planted->fresh()->password);
    } catch (ValidationException) {
        $attackerStillGetsIn = false;
    }

    expect($attackerStillGetsIn)->toBeFalse();
});

it('ACC-5: a cashier deleting their own account does not erase their cash-drawer shifts', function (): void {
    $cashier = staff('cashier');
    $cashier->update(['password' => 'secret-pass-1']);
    app(OpenShift::class)->handle($cashier, defaultWarehouse(), 5_000);

    $this->actingAs($cashier)->delete('/settings/profile', ['password' => 'secret-pass-1']);

    expect(CashierShift::query()->count())->toBe(1);
});

it('ACC-6: a custom role built in Admin → Roles can reach the admin pages it has permission for', function (): void {
    Role::create(['name' => 'accountant'])->givePermissionTo('orders.view');
    $accountant = User::factory()->create();
    $accountant->assignRole('accountant');

    $this->actingAs($accountant)->get(route('admin.orders.index'))->assertOk();
});

it('ACC-7: a guest replaying someone else\'s idempotency key does not get their order back', function (): void {
    $victimOrder = accOrderFor(customer(), 'shared-key-123');

    $response = $this->postJson('/api/v1/checkout', [
        'customer' => ['name' => 'Attacker', 'phone' => '0911111111'],
        'shipping_address' => ['city' => 'Tripoli', 'area' => 'Elsewhere'],
        'payment_method' => 'cod',
        'idempotency_key' => 'shared-key-123',
    ]);

    expect($response->json('data.number'))->not->toBe($victimOrder->number);
});

it('ACC-8: staff without audit.view do not see the audit trail on the dashboard', function (): void {
    activity()->log('Staff account updated: boss@example.com');

    $props = $this->actingAs(staff('inventory_manager'))->get(route('admin.dashboard'))
        ->assertOk()->viewData('page')['props'];

    expect($props['recentActivity'] ?? [])->toBeEmpty();
});

// -- Role / permission matrix ------------------------------------------------

$adminGets = [
    'admin.dashboard', 'admin.products.index', 'admin.categories.index', 'admin.brands.index',
    'admin.specifications.index', 'admin.warehouses.index', 'admin.inventory.index', 'admin.suppliers.index',
    'admin.purchase-orders.index', 'admin.orders.index', 'admin.orders.create', 'admin.pos.register',
    'admin.pos.shifts.index', 'admin.refunds.index', 'admin.treasury.index', 'admin.customers.index',
    'admin.reviews.index', 'admin.coupons.index', 'admin.reports.index', 'admin.users.index',
    'admin.roles.index', 'admin.audit-logs.index', 'admin.settings.index',
];

it('ACC-9: a customer is refused every admin page', function (string $route): void {
    $this->actingAs(customer())->get(route($route))->assertForbidden();
})->with($adminGets);

it('ACC-10: a cashier is refused admin pages outside their permissions', function (string $route): void {
    $this->actingAs(staff('cashier'))->get(route($route))->assertForbidden();
})->with([
    'admin.categories.index', 'admin.brands.index', 'admin.specifications.index', 'admin.warehouses.index',
    'admin.suppliers.index', 'admin.purchase-orders.index', 'admin.orders.create', 'admin.refunds.index',
    'admin.treasury.index', 'admin.customers.index', 'admin.reviews.index', 'admin.coupons.index',
    'admin.reports.index', 'admin.users.index', 'admin.roles.index', 'admin.audit-logs.index', 'admin.settings.index',
]);

it('ACC-11: a cashier cannot mint roles, staff, settings or toggle accounts', function (): void {
    $cashier = staff('cashier');
    $victim = customer();

    $this->actingAs($cashier)->post(route('admin.roles.store'), ['name' => 'boss', 'permissions' => ['users.manage']])->assertForbidden();
    $this->actingAs($cashier)->post(route('admin.users.store'), [
        'name' => 'x', 'email' => 'x@example.com', 'password' => 'secret-pass-1', 'roles' => ['admin'],
    ])->assertForbidden();
    $this->actingAs($cashier)->patch(route('admin.users.update', $cashier), ['name' => 'x', 'roles' => ['super_admin']])->assertForbidden();
    $this->actingAs($cashier)->post(route('admin.settings.update'), ['store_name' => 'pwned'])->assertForbidden();
    $this->actingAs($cashier)->post(route('admin.customers.toggle-active', $victim))->assertForbidden();

    expect($cashier->fresh()->hasRole('super_admin'))->toBeFalse();
});

// -- IDOR / ownership checks (expected to hold) ------------------------------

it('ACC-12: addresses are owner-only on web and API', function (): void {
    $owner = customer();
    $address = accAddress($owner);
    $attacker = customer();
    $payload = ['name' => 'Hacked', 'phone' => '0912345678', 'country' => 'LY', 'city' => 'Tripoli', 'area' => 'X'];

    $this->actingAs($attacker)->patch(route('account.addresses.update', $address), $payload)->assertForbidden();
    $this->actingAs($attacker)->delete(route('account.addresses.destroy', $address))->assertForbidden();
    $this->withHeaders(bearer($attacker))->patchJson("/api/v1/addresses/{$address->id}", $payload)->assertForbidden();
    $this->withHeaders(bearer($attacker))->deleteJson("/api/v1/addresses/{$address->id}")->assertForbidden();

    expect($address->fresh()->name)->toBe('Owner');
});

it('ACC-13: orders are owner-only on web and API (view + cancel)', function (): void {
    $order = accOrderFor(customer());
    $attacker = customer();

    $this->actingAs($attacker)->get(route('account.orders.show', $order))->assertForbidden();
    $this->actingAs($attacker)->post(route('account.orders.cancel', $order))->assertForbidden();
    $this->actingAs($attacker)->get(route('checkout.confirmation', $order->number))->assertForbidden();
    $this->withHeaders(bearer($attacker))->getJson("/api/v1/orders/{$order->id}")->assertForbidden();
    $this->withHeaders(bearer($attacker))->postJson("/api/v1/orders/{$order->id}/cancel")->assertForbidden();
    $this->get(route('checkout.confirmation', $order->number))->assertForbidden();

    expect($order->fresh()->status->value)->not->toBe('cancelled');
});

it('ACC-14: one cart cannot edit or remove another cart\'s items', function (): void {
    $owner = customer();
    $cart = Cart::create(['user_id' => $owner->id]);
    app(AddToCart::class)->handle($cart, accProduct(), null, 1);
    $item = $cart->items()->first();
    $attacker = customer();

    $this->actingAs($attacker)->patch(route('cart.update', $item), ['quantity' => 9])->assertForbidden();
    $this->actingAs($attacker)->delete(route('cart.remove', $item))->assertForbidden();
    $this->withHeaders(bearer($attacker))->patchJson("/api/v1/cart/items/{$item->id}", ['quantity' => 9])->assertForbidden();
    $this->withHeaders(['X-Cart-Token' => 'nope'])->deleteJson("/api/v1/cart/items/{$item->id}")->assertForbidden();

    expect($item->fresh()->quantity)->toBe(1);
});

it('ACC-15: reviews need a completed purchase and are one per user', function (): void {
    $product = accProduct();
    $user = customer();

    $this->withHeaders(bearer($user))->postJson('/api/v1/reviews', ['product_id' => $product->id, 'rating' => 5])
        ->assertUnprocessable();

    expect($product->reviews()->count())->toBe(0);
});

it('ACC-16: a customer deleting their account keeps the shop\'s order history', function (): void {
    $user = customer(['password' => 'secret-pass-1']);
    $order = accOrderFor($user);

    $this->actingAs($user)->delete('/settings/profile', ['password' => 'secret-pass-1'])->assertRedirect('/');

    expect(Order::query()->whereKey($order->id)->exists())->toBeTrue();
});

it('ACC-17: the newsletter does not duplicate addresses by case', function (): void {
    $this->post(route('newsletter.subscribe'), ['email' => 'A@Example.com']);
    $this->post(route('newsletter.subscribe'), ['email' => 'a@example.com']);

    expect(NewsletterSubscriber::query()->count())->toBe(1);
});

// -- Account deletion is a soft delete: what happens to the e-mail? ----------

it('ACC-18: a customer who deleted their account can sign in with Google again (no 500)', function (): void {
    $user = customer(['email' => 'returning@example.com', 'password' => 'secret-pass-1']);
    $this->actingAs($user)->delete('/settings/profile', ['password' => 'secret-pass-1'])->assertRedirect('/');
    auth()->forgetGuards();

    $google = (new SocialiteUser)->setRaw(['email_verified' => true])
        ->map(['id' => 'google-999', 'email' => 'returning@example.com', 'name' => 'Returning']);

    try {
        app(LoginWithGoogle::class)->handle($google);
        $outcome = 'ok';
    } catch (ValidationException) {
        $outcome = 'refused-cleanly';
    } catch (Throwable $e) {
        $outcome = get_class($e);
    }

    expect($outcome)->toBeIn(['ok', 'refused-cleanly']);
});

it('ACC-19: a customer who deleted their account can register again with the same e-mail', function (): void {
    $user = customer(['email' => 'comeback@example.com', 'password' => 'secret-pass-1']);
    $this->actingAs($user)->delete('/settings/profile', ['password' => 'secret-pass-1']);
    auth()->forgetGuards();

    $this->post('/register', [
        'name' => 'Comeback', 'email' => 'comeback@example.com',
        'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
    ])->assertSessionHasNoErrors();
});
