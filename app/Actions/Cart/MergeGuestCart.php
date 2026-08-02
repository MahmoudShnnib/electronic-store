<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * After login, fold the guest cookie cart into the user's cart.
 * Quantities merge (capped by the per-item maximum); the guest cart is deleted.
 */
final class MergeGuestCart
{
    public function handle(?string $guestToken, User $user): ?Cart
    {
        $userCart = Cart::query()->where('user_id', $user->id)->latest('id')->first();

        if ($guestToken === null || $guestToken === '') {
            return $userCart;
        }

        $guestCart = Cart::query()->whereNull('user_id')->where('session_token', $guestToken)->first();

        if ($guestCart === null) {
            return $userCart;
        }

        if ($userCart === null) {
            $guestCart->update(['user_id' => $user->id, 'session_token' => null]);

            return $guestCart;
        }

        return DB::transaction(function () use ($guestCart, $userCart): Cart {
            $max = (int) config('store.max_quantity_per_item');

            foreach ($guestCart->items as $guestItem) {
                $existing = $userCart->items()
                    ->where('product_id', $guestItem->product_id)
                    ->where('product_variant_id', $guestItem->product_variant_id)
                    ->first();

                if ($existing !== null) {
                    $existing->update([
                        'quantity' => min($max, $existing->quantity + $guestItem->quantity),
                    ]);
                } else {
                    $userCart->items()->create([
                        'product_id' => $guestItem->product_id,
                        'product_variant_id' => $guestItem->product_variant_id,
                        'quantity' => min($max, $guestItem->quantity),
                    ]);
                }
            }

            if ($userCart->coupon_id === null && $guestCart->coupon_id !== null) {
                $userCart->update(['coupon_id' => $guestCart->coupon_id]);
            }

            $guestCart->delete();

            return $userCart->refresh();
        });
    }
}
