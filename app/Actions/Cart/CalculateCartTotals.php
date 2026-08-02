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
        $cart->loadMissing(['items.product.images', 'items.variant', 'coupon']);

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

        [$discount, $coupon, $couponError, $freeShipping] = $this->resolveCoupon($cart->coupon, $subtotal, $lines, $userId);

        $shipping = $this->shippingFee($subtotal, $freeShipping, $lines !== []);

        // Distribute the order-level discount across lines proportionally so
        // tax is charged on the discounted amount.
        $builtLines = [];
        $tax = 0;
        $remainingDiscount = $discount;

        foreach ($lines as $index => $line) {
            $isLast = $index === array_key_last($lines);

            $lineDiscount = $subtotal > 0
                ? ($isLast ? $remainingDiscount : intdiv($line['subtotal'] * $discount, max(1, $subtotal)))
                : 0;
            $remainingDiscount -= $lineDiscount;

            $taxable = max(0, $line['subtotal'] - $lineDiscount);
            $lineTax = (int) round($taxable * ((float) $line['item']->product->tax_rate) / 100);
            $tax += $lineTax;

            $builtLines[] = new CartLine(
                product: $line['item']->product,
                variant: $line['item']->variant,
                quantity: $line['item']->quantity,
                unitPriceCents: $line['unit'],
                lineSubtotalCents: $line['subtotal'],
                lineDiscountCents: $lineDiscount,
                lineTaxCents: $lineTax,
                lineTotalCents: $taxable + $lineTax,
                cartItemId: $line['item']->id,
            );
        }

        return new CartTotals(
            lines: $builtLines,
            subtotalCents: $subtotal,
            discountCents: $discount,
            taxCents: $tax,
            shippingCents: $shipping,
            totalCents: max(0, $subtotal - $discount) + $tax + $shipping,
            coupon: $coupon,
            couponError: $couponError,
        );
    }

    /**
     * @return array{0: int, 1: ?Coupon, 2: ?string, 3: bool}
     */
    private function resolveCoupon(?Coupon $coupon, int $subtotal, array $lines, ?int $userId): array
    {
        if ($coupon === null) {
            return [0, null, null, false];
        }

        if (! $coupon->isCurrentlyValid()) {
            return [0, $coupon, __('This coupon is no longer valid.'), false];
        }

        if ($userId !== null
            && $coupon->per_customer_limit !== null
            && $coupon->usesByUser($userId) >= $coupon->per_customer_limit) {
            return [0, $coupon, __('You have already used this coupon the maximum number of times.'), false];
        }

        if ($coupon->min_order_cents !== null && $subtotal < $coupon->min_order_cents) {
            return [0, $coupon, __("The order subtotal does not meet this coupon's minimum."), false];
        }

        $eligible = $this->eligibleSubtotal($coupon, $lines);

        if ($eligible === 0 && $coupon->type !== CouponType::FreeShipping) {
            return [0, $coupon, __('This coupon does not apply to any item in your cart.'), false];
        }

        $discount = match ($coupon->type) {
            CouponType::Fixed => min((int) $coupon->value_cents, $eligible),
            CouponType::Percentage => (int) round($eligible * ((float) $coupon->percent) / 100),
            CouponType::FreeShipping => 0,
        };

        if ($coupon->max_discount_cents !== null) {
            $discount = min($discount, $coupon->max_discount_cents);
        }

        return [$discount, $coupon, null, $coupon->type === CouponType::FreeShipping];
    }

    /** Subtotal of the lines the coupon's product/category/brand scope covers. */
    private function eligibleSubtotal(Coupon $coupon, array $lines): int
    {
        if ($coupon->applies_to === 'all') {
            return array_sum(array_column($lines, 'subtotal'));
        }

        $ids = array_map('intval', $coupon->applies_to_ids ?? []);

        return array_sum(array_map(function (array $line) use ($coupon, $ids): int {
            $product = $line['item']->product;

            $matches = match ($coupon->applies_to) {
                'products' => in_array($product->id, $ids, true),
                'categories' => in_array($product->category_id, $ids, true),
                'brands' => $product->brand_id !== null && in_array($product->brand_id, $ids, true),
                default => false,
            };

            return $matches ? $line['subtotal'] : 0;
        }, $lines));
    }

    private function shippingFee(int $subtotal, bool $freeShipping, bool $hasItems): int
    {
        if (! $hasItems || $freeShipping) {
            return 0;
        }

        $threshold = (int) config('store.free_shipping_threshold_cents');

        if ($threshold > 0 && $subtotal >= $threshold) {
            return 0;
        }

        return (int) config('store.shipping_fee_cents');
    }
}
