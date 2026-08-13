<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as GoogleUser;

/**
 * Resolves a Google account to a local user: match by google_id first, then
 * link an existing account by email, otherwise create a fresh customer.
 * Google has already verified the email, so new accounts skip verification.
 */
final class LoginWithGoogle
{
    public function handle(GoogleUser $google): User
    {
        return DB::transaction(function () use ($google): User {
            $user = User::query()->where('google_id', $google->getId())->first()
                ?? User::query()->where('email', $google->getEmail())->first();

            if ($user === null) {
                $user = User::create([
                    'name' => $google->getName() ?: ($google->getNickname() ?: 'Customer'),
                    'email' => $google->getEmail(),
                    // Social-only accounts still satisfy the NOT NULL password
                    // column; the random value is never usable for login.
                    'password' => Str::password(40),
                    'is_active' => true,
                ]);

                $user->markEmailAsVerified();
                $user->assignRole('customer');
            }

            if (! $user->is_active) {
                throw ValidationException::withMessages([
                    'email' => __('Your account has been deactivated.'),
                ]);
            }

            $user->forceFill([
                'google_id' => $google->getId(),
                'last_login_at' => now(),
            ])->save();

            return $user;
        });
    }
}
