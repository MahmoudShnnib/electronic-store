<?php

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use App\Notifications\StorefrontResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('emails a reset link that opens the storefront', function (): void {
    Notification::fake();
    config(['store.storefront_url' => 'https://shop.example.ly']);
    $user = customer(['email' => 'buyer@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'Buyer@Example.com'])->assertOk();

    Notification::assertSentTo($user, StorefrontResetPassword::class, function (StorefrontResetPassword $mail) use ($user): bool {
        $url = $mail->toMail($user)->actionUrl;

        return str_starts_with($url, 'https://shop.example.ly/reset-password?token=') && str_contains($url, 'email=buyer%40example.com');
    });
});

it('gives the same answer for an unknown email', function (): void {
    Notification::fake();

    $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => customer()->email])->json('message');
    $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk()->json('message');

    expect($unknown)->toBe($known);
    Notification::assertSentTimes(StorefrontResetPassword::class, 1);
});

it('resets the password with the emailed token and signs out every device', function (): void {
    $user = customer(['email' => 'buyer@example.com']);
    $user->createToken('old-phone');
    $token = Password::broker()->createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'buyer@example.com',
        'password' => 'new-secret-pass-1',
        'password_confirmation' => 'new-secret-pass-1',
    ])->assertOk();

    expect(Hash::check('new-secret-pass-1', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);

    $this->postJson('/api/v1/auth/login', ['email' => 'buyer@example.com', 'password' => 'new-secret-pass-1', 'device_name' => 'x'])->assertOk();
});

it('gives the same reset error for an unknown email as for a bad token', function (): void {
    $payload = ['token' => 'not-a-real-token', 'password' => 'new-secret-pass-1', 'password_confirmation' => 'new-secret-pass-1'];

    $known = $this->postJson('/api/v1/auth/reset-password', [...$payload, 'email' => customer()->email])->json('errors.email.0');
    $unknown = $this->postJson('/api/v1/auth/reset-password', [...$payload, 'email' => 'nobody@example.com'])->json('errors.email.0');

    expect($unknown)->toBe($known);
});

it('refuses a wrong or used reset token', function (): void {
    $user = customer();

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'new-secret-pass-1',
        'password_confirmation' => 'new-secret-pass-1',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('changes the password, keeping this device and signing out the others', function (): void {
    $user = customer(['password' => 'old-secret-pass-1']);
    $user->createToken('other-device');
    $current = $user->createToken('this-device')->plainTextToken;

    $this->withToken($current)->putJson('/api/v1/auth/password', [
        'current_password' => 'wrong',
        'password' => 'new-secret-pass-1',
        'password_confirmation' => 'new-secret-pass-1',
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

    $this->withToken($current)->putJson('/api/v1/auth/password', [
        'current_password' => 'old-secret-pass-1',
        'password' => 'new-secret-pass-1',
        'password_confirmation' => 'new-secret-pass-1',
    ])->assertOk();

    expect(Hash::check('new-secret-pass-1', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->pluck('name')->all())->toBe(['this-device']);
});

it('keeps a guest cart when the guest registers', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 5);
    $token = $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 2])->json('cart_token');

    $bearer = $this->withHeader('X-Cart-Token', $token)->postJson('/api/v1/auth/register', [
        'name' => 'New Buyer',
        'email' => 'new@example.com',
        'password' => 'secret-pass-123',
        'device_name' => 'phone',
    ])->assertCreated()->json('token');

    $user = User::query()->where('email', 'new@example.com')->sole();
    expect(Cart::query()->where('user_id', $user->id)->sole()->items()->sum('quantity'))->toBe(2);

    $this->withoutHeader('X-Cart-Token')->withToken($bearer)->getJson('/api/v1/cart')->assertJsonPath('data.items.0.quantity', 2);
});

it('treats emails as case-insensitive on register and sign-in', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Ali', 'email' => 'Ali@Example.com', 'password' => 'secret-pass-123', 'device_name' => 'x',
    ])->assertCreated();

    expect(User::query()->where('email', 'ali@example.com')->exists())->toBeTrue();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Ali again', 'email' => 'ali@example.com', 'password' => 'secret-pass-123', 'device_name' => 'x',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->postJson('/api/v1/auth/login', ['email' => 'ALI@example.com', 'password' => 'secret-pass-123', 'device_name' => 'x'])->assertOk();
});

it('signs in an older account whose stored email has capitals', function (): void {
    customer(['email' => 'Old@Example.com', 'password' => 'secret-pass-123']);

    $this->postJson('/api/v1/auth/login', ['email' => 'old@example.com', 'password' => 'secret-pass-123', 'device_name' => 'x'])->assertOk();
});

it('does not let newsletter sign-ups use up login attempts', function (): void {
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/newsletter', ['email' => "fan{$i}@example.com"]);
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'x', 'device_name' => 'x'])
        ->assertStatus(422);
});
