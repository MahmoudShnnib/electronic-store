<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Finds (or lazily creates) the active cart: by user when authenticated,
 * by the signed guest cookie token otherwise. Web and API share this.
 */
final class ResolveCart
{
    public const GUEST_COOKIE = 'guest_cart_token';

    public function handle(Request $request, bool $create = true): ?Cart
    {
        $user = $request->user();

        if ($user instanceof User) {
            $cart = Cart::query()->where('user_id', $user->id)->latest('id')->first();

            if ($cart === null && $create) {
                $cart = Cart::create(['user_id' => $user->id]);
            }

            return $cart;
        }

        $token = (string) ($request->cookie(self::GUEST_COOKIE) ?? $request->header('X-Cart-Token', ''));

        if ($token !== '') {
            $cart = Cart::query()->whereNull('user_id')->where('session_token', $token)->first();

            if ($cart !== null) {
                return $cart;
            }
        }

        if (! $create) {
            return null;
        }

        $cart = Cart::create(['session_token' => Str::random(48)]);

        // The controller attaches the cookie to the response.
        return $cart;
    }
}
