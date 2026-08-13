<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\Coupon;
use Illuminate\Validation\ValidationException;

final class ApplyCoupon
{
    public function handle(Cart $cart, string $code, ?int $userId = null): Cart
    {
        $coupon = Coupon::query()->whereRaw('LOWER(code) = ?', [mb_strtolower(trim($code))])->first();

        if ($coupon === null || ! $coupon->isCurrentlyValid()) {
            throw ValidationException::withMessages(['coupon' => __('This coupon code is not valid.')]);
        }

        if ($userId !== null
            && $coupon->per_customer_limit !== null
            && $coupon->usesByUser($userId) >= $coupon->per_customer_limit) {
            throw ValidationException::withMessages(['coupon' => __('You have already used this coupon.')]);
        }

        // Enforce the minimum at apply time — accepting the code and then
        // silently zeroing the discount at checkout reads as a broken coupon.
        if ($coupon->min_order_cents !== null) {
            $subtotal = (int) $cart->items()->with(['product', 'variant'])->get()
                ->sum(fn ($item) => $item->unitPriceCents() * $item->quantity);

            if ($subtotal < $coupon->min_order_cents) {
                throw ValidationException::withMessages([
                    'coupon' => __('This coupon requires a minimum order of :min.', [
                        'min' => \App\Support\Money::format((int) $coupon->min_order_cents),
                    ]),
                ]);
            }
        }

        $cart->update(['coupon_id' => $coupon->id]);

        return $cart->refresh();
    }

    public function remove(Cart $cart): Cart
    {
        $cart->update(['coupon_id' => null]);

        return $cart->refresh();
    }
}
