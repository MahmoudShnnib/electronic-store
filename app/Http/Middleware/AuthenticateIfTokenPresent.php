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
 *
 * EnsureUserIsActive runs earlier in the api group, while the default guard
 * is still `web` and sees no user, so a deactivated customer's token has to be
 * refused here, once the token has been resolved.
 */
final class AuthenticateIfTokenPresent
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('sanctum');

        abort_if($request->user()?->is_active === false, 403, __('Your account has been deactivated.'));

        return $next($request);
    }
}
