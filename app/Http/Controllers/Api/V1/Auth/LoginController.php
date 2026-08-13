<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Cart\MergeGuestCart;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Exchanges credentials for a personal access token. */
final class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, MergeGuestCart $mergeGuestCart): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->where('email', $validated['email'])->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'Your account has been deactivated.']);
        }

        $user->update(['last_login_at' => now()]);

        // Fold a guest cart (identified by X-Cart-Token) into the user's cart.
        $mergeGuestCart->handle($request->header('X-Cart-Token'), $user);

        return response()->json([
            'token' => $user->createToken($validated['device_name'])->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }
}
