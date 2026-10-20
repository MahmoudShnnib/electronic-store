<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\StorefrontResetPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * Emails a reset link that opens the storefront. The answer is the same
 * whether or not the email has an account, so the form can't be used to find
 * out who shops here.
 */
final class ForgotPasswordController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);

        Password::broker()->sendResetLink(
            ['email' => mb_strtolower($validated['email'])],
            fn (User $user, string $token) => $user->notify(new StorefrontResetPassword($token)),
        );

        return response()->json(['message' => __('If an account exists for that email, we have sent a link to reset the password.')]);
    }
}
