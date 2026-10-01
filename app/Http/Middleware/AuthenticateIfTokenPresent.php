<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * For API routes open to guests and customers alike (cart, checkout): resolve
 * the user through Sanctum, so a request carrying a Bearer token acts as that
 * customer while one without stays a guest.
 */
final class AuthenticateIfTokenPresent
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('sanctum');

        return $next($request);
    }
}
