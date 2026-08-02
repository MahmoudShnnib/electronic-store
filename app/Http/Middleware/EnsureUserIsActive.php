<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deactivated accounts are signed out immediately (web) or rejected (API).
 */
final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Strict false check: an instance that was never hydrated with the
        // column (attribute absent → null) must not be treated as deactivated.
        if ($user !== null && $user->is_active === false) {
            if ($request->expectsJson()) {
                abort(403, 'Your account has been deactivated.');
            }

            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been deactivated.',
            ]);
        }

        return $next($request);
    }
}
