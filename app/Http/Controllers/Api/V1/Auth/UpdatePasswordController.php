<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;

/**
 * Changes the signed-in customer's password. Other devices are signed out;
 * the token making this request keeps working.
 */
final class UpdatePasswordController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = $request->user();
        $user->update(['password' => $validated['password']]);
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        return response()->json(['message' => __('Your password has been changed.')]);
    }
}
