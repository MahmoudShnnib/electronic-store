<?php

declare(strict_types=1);

namespace App\Support;

use NumberFormatter;

/**
 * All monetary values are stored as integer cents. This helper is the single
 * place where cents become display strings or decimal API values.
 */
final class Money
{
    public static function toDecimal(int $cents): float
    {
        return round($cents / 100, 2);
    }

    public static function fromDecimal(float|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function format(int $cents, ?string $currency = null): string
    {
        $currency ??= config('store.currency', 'USD');

        $formatter = new NumberFormatter(app()->getLocale(), NumberFormatter::CURRENCY);

        // Values are stored as integer cents (2 decimals), so display 2 even
        // for currencies whose official minor unit is 3 digits (LYD).
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);

        // Amount first, currency after — English would otherwise render
        // "LYD 119.00" while Arabic renders "119.00 د.ل.", so the two locales
        // disagreed on which side the currency sits.
        $formatter->setPattern('#,##0.00 ¤');

        return $formatter->formatCurrency(self::toDecimal($cents), $currency);
    }
}
