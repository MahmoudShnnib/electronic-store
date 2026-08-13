<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LoginWithGoogle;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/** "Sign in with Google": send the user to Google, then log in the callback. */
final class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, LoginWithGoogle $loginWithGoogle): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable) {
            // Denied consent, expired state, or a stale callback URL.
            return redirect()->route('login')
                ->withErrors(['email' => __('Google sign-in was cancelled. Please try again.')]);
        }

        try {
            $user = $loginWithGoogle->handle($google);
        } catch (ValidationException $e) {
            return redirect()->route('login')->withErrors($e->errors());
        }

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
