<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Cart\MergeGuestCart;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    /**
     * Register a new customer account and return an API token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
            'is_active' => true,
        ]);

        $user->assignRole('customer');

        event(new Registered($user));

        return response()->json([
            'token' => $user->createToken($validated['device_name'])->plainTextToken,
            'user' => $this->userPayload($user),
        ], 201);
    }

    /**
     * Exchange credentials for a personal access token.
     */
    public function login(LoginRequest $request, MergeGuestCart $mergeGuestCart): JsonResponse
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
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Revoke the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Revoke every token (e.g. "log out of all devices").
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'All sessions revoked.']);
    }

    /**
     * The authenticated user's profile.
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    /**
     * Update profile fields.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $request->user()->update($validated);

        return response()->json(['user' => $this->userPayload($request->user()->refresh())]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'locale' => $user->locale,
            'email_verified' => $user->email_verified_at !== null,
        ];
    }
}
