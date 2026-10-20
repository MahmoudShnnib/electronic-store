<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

/**
 * Sets a new password from the emailed link. Every existing API token is
 * revoked — whoever knew the old password is signed out everywhere — and the
 * customer signs in again with the new one.
 */
final class ResetPasswordController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            [...$validated, 'email' => mb_strtolower($validated['email'])],
            function (User $user, string $password): void {
                // The model casts `password` as hashed.
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PasswordReset) {
            // An unknown email gets the same answer as a bad token, so this
            // endpoint can't be used to find out who has an account.
            throw ValidationException::withMessages([
                'email' => __($status === Password::ResetThrottled ? $status : Password::InvalidToken),
            ]);
        }

        return response()->json(['message' => __($status)]);
    }
}
