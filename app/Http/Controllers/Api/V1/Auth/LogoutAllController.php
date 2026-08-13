<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Revokes every token the user holds — "log out of all devices". */
final class LogoutAllController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'All sessions revoked.']);
    }
}
