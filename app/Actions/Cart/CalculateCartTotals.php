<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\DTOs\CartLine;
use App\DTOs\CartTotals;
use App\Enums\CouponType;
use App\Models\Cart;
use App\Models\Coupon;

/**
 * The single source of truth for pricing. Web checkout, the admin, and the
 * API all call this — client-side numbers are never trusted.
 */
final class CalculateCartTotals
{
    public function handle(Cart $cart, ?int $userId = null): CartTotals
    {
        $cart->loadMissing(['items.product.images', 'items.variant.product', 'coupon']);

        $lines = [];
        $subtotal = 0;

        foreach ($cart->items as $item) {
            if ($item->product === null) {
                continue;
            }

            $unit = $item->unitPriceCents();
            $lineSubtotal = $unit * $item->quantity;
            $subtotal += $lineSubtotal;

            $lines[] = [
                'item' => $item,
                'unit' => $unit,
                'subtotal' => $lineSubtotal,
            ];
        }

        [$discount, $coupon, $couponError] = $this->resolveCoupon($cart->coupon, $subtotal, $lines, $userId);

        // Distribute the discount proportionally across the lines the coupon
        // actually covers. A product-limited coupon must not shave money off
        // items it doesn't apply to.
        $eligible = array_map(fn (array $line) => $coupon !== null && $this->lineIsEligible($coupon, $line), $lines);
        $eligibleSubtotal = array_sum(array_map(
            fn (array $line, bool $ok) => $ok ? $line['subtotal'] : 0,
            $lines,
            $eligible,
        ));
        $lastEligibleIndex = array_key_last(array_filter($eligible));

        $builtLines = [];
        $remainingDiscount = $discount;

        foreach ($lines as $index => $line) {
            $lineDiscount = 0;

            if ($eligible[$index] && $eligibleSubtotal > 0) {
                $lineDiscount = $index === $lastEligibleIndex
                    ? $remainingDiscount
                    : intdiv($line['subtotal'] * $discount, $eligibleSubtotal);
            }

            $remainingDiscount -= $lineDiscount;

            $builtLines[] = new CartLine(
                product: $line['item']->product,
                variant: $line['item']->variant,
                quantity: $line['item']->quantity,
                unitPriceCents: $line['unit'],
                lineSubtotalCents: $line['subtotal'],
                lineDiscountCents: $lineDiscount,
                lineTotalCents: max(0, $line['subtotal'] - $lineDiscount),
                cartItemId: $line['item']->id,
            );
        }

        return new CartTotals(
            lines: $builtLines,
            subtotalCents: $subtotal,
            discountCents: $discount,
            totalCents: max(0, $subtotal - $discount),
            coupon: $coupon,
            couponError: $couponError,
        );
    }

    /**
     * @return array{0: int, 1: ?Coupon, 2: ?string}
     */
    private function resolveCoupon(?Coupon $coupon, int $subtotal, array $lines, ?int $userId): array
    {
        if ($coupon === null) {
            return [0, null, null];
        }

        if ($coupon->trashed()) {
            return [0, $coupon, __('This coupon is no longer available.')];
        }

        if (! $coupon->isCurrentlyValid()) {
            return [0, $coupon, __('This coupon is no longer valid.')];
        }

        if ($userId !== null
            && $coupon->per_customer_limit !== null
            && $coupon->usesByUser($userId) >= $coupon->per_customer_limit) {
            return [0, $coupon, __('You have already used this coupon the maximum number of times.')];
        }

        if ($coupon->min_order_cents !== null && $subtotal < $coupon->min_order_cents) {
            return [0, $coupon, __("The order subtotal does not meet this coupon's minimum.")];
        }

        $eligible = $this->eligibleSubtotal($coupon, $lines);

        if ($eligible === 0) {
            return [0, $coupon, __('This coupon does not apply to any item in your cart.')];
        }

        $discount = match ($coupon->type) {
            CouponType::Fixed => min((int) $coupon->value_cents, $eligible),
            CouponType::Percentage => (int) round($eligible * ((float) $coupon->percent) / 100),
        };

        if ($coupon->max_discount_cents !== null) {
            $discount = min($discount, $coupon->max_discount_cents);
        }

        return [$discount, $coupon, null];
    }

    /** Subtotal of the lines the coupon's product/category/brand scope covers. */
    private function eligibleSubtotal(Coupon $coupon, array $lines): int
    {
        return array_sum(array_map(
            fn (array $line): int => $this->lineIsEligible($coupon, $line) ? $line['subtotal'] : 0,
            $lines,
        ));
    }

    /** Whether one cart line falls inside the coupon's scope. */
    private function lineIsEligible(Coupon $coupon, array $line): bool
    {
        if ($coupon->applies_to === 'all') {
            return true;
        }

        $ids = array_map('intval', $coupon->applies_to_ids ?? []);
        $product = $line['item']->product;

        return match ($coupon->applies_to) {
            'products' => in_array($product->id, $ids, true),
            'categories' => in_array($product->category_id, $ids, true),
            'brands' => $product->brand_id !== null && in_array($product->brand_id, $ids, true),
            default => false,
        };
    }
}
