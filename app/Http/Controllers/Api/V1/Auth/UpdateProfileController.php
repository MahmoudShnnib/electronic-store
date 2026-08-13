<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\V1\UserResource;
use Illuminate\Http\JsonResponse;

/** Updates the authenticated user's profile fields. */
final class UpdateProfileController extends Controller
{
    public function __invoke(UpdateProfileRequest $request): JsonResponse
    {
        $request->user()->update($request->validated());

        return response()->json(['user' => new UserResource($request->user()->refresh())]);
    }
}
