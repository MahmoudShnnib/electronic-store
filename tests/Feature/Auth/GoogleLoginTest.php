<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

function fakeGoogleUser(string $id = 'google-123', string $email = 'g@example.com', string $name = 'Google Person'): GoogleUser
{
    return (new GoogleUser)->setRaw(['email_verified' => true]) // Google always reports this for its accounts
        ->map([
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ]);
}

function mockGoogleCallback(GoogleUser $user): void
{
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($user);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

test('the google redirect route sends the user to google', function () {
    config(['services.google.client_id' => 'test', 'services.google.client_secret' => 'test']);

    $this->get(route('auth.google'))
        ->assertRedirect()
        ->assertRedirectContains('accounts.google.com');
});

test('a new google account creates a verified customer and logs in', function () {
    mockGoogleCallback(fakeGoogleUser());

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'g@example.com')->firstOrFail();

    expect($user->google_id)->toBe('google-123')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->hasRole('customer'))->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

test('an existing account with the same email is linked, not duplicated', function () {
    $existing = User::factory()->create(['email' => 'g@example.com']);
    $existing->assignRole('customer');

    mockGoogleCallback(fakeGoogleUser());

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard', absolute: false));

    expect(User::query()->where('email', 'g@example.com')->count())->toBe(1)
        ->and($existing->fresh()->google_id)->toBe('google-123');

    $this->assertAuthenticatedAs($existing);
});

test('a deactivated account cannot sign in with google', function () {
    $user = User::factory()->create(['email' => 'g@example.com', 'is_active' => false]);
    $user->assignRole('customer');

    mockGoogleCallback(fakeGoogleUser());

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a cancelled google consent returns to login with an error', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andThrow(new RuntimeException('state mismatch'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});
