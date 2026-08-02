<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\CartLine;
use App\DTOs\CartTotals;
use App\Support\Money;
use Illuminate\Support\Facades\Storage;

/**
 * Serializes CartTotals for both Inertia props and the JSON API so the web
 * and mobile cart payloads never drift apart.
 */
final class CartPayload
{
    public static function from(CartTotals $totals): array
    {
        return [
            'items' => array_map(fn (CartLine $line) => [
                'id' => $line->cartItemId,
                'product_id' => $line->product->id,
                'variant_id' => $line->variant?->id,
                'name' => app()->getLocale() === 'ar' && $line->product->name_ar !== null
                    ? ($line->variant !== null ? $line->product->name_ar.' — '.$line->variant->name : $line->product->name_ar)
                    : $line->displayName(),
                'slug' => $line->product->slug,
                'sku' => $line->sku(),
                'image' => self::imageUrl($line),
                'quantity' => $line->quantity,
                'unit_price_cents' => $line->unitPriceCents,
                'unit_price_formatted' => Money::format($line->unitPriceCents),
                'line_subtotal_cents' => $line->lineSubtotalCents,
                'line_subtotal_formatted' => Money::format($line->lineSubtotalCents),
                'line_discount_cents' => $line->lineDiscountCents,
                'line_total_cents' => $line->lineTotalCents,
                'line_total_formatted' => Money::format($line->lineTotalCents),
                'purchasable' => $line->product->status->isPurchasable(),
            ], $totals->lines),
            'item_count' => $totals->itemCount(),
            'subtotal_cents' => $totals->subtotalCents,
            'subtotal_formatted' => Money::format($totals->subtotalCents),
            'discount_cents' => $totals->discountCents,
            'discount_formatted' => Money::format($totals->discountCents),
            'tax_cents' => $totals->taxCents,
            'tax_formatted' => Money::format($totals->taxCents),
            'total_cents' => $totals->totalCents,
            'total_formatted' => Money::format($totals->totalCents),
            'coupon' => $totals->coupon?->only(['code', 'type']),
            'coupon_error' => $totals->couponError,
        ];
    }

    private static function imageUrl(CartLine $line): ?string
    {
        if ($line->variant?->image_path !== null) {
            return Storage::disk('public')->url($line->variant->image_path);
        }

        $image = $line->product->relationLoaded('images')
            ? ($line->product->images->firstWhere('is_primary', true) ?? $line->product->images->first())
            : null;

        return $image !== null ? Storage::disk('public')->url($image->path) : null;
    }
}
