<?php

declare(strict_types=1);

namespace App\Support;

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

    /**
     * Always "1,234.000 LYD": amount first, three fraction digits (the dinar's
     * minor unit — values are stored in hundredths, so the third digit is
     * padding, not precision), symbol last.
     *
     * ICU is deliberately not used for placement: for locale `en` CLDR emits
     * "LYD 1,234.00" with the code in front, which is not how prices are read
     * here. The whole string is wrapped in an LTR isolate so it keeps that
     * order inside Arabic (RTL) text as well.
     */
    public static function format(int $cents, ?string $currency = null): string
    {
        $currency ??= config('store.currency', 'USD');
        $symbol = config('store.currency_symbols.'.app()->getLocale(), $currency);
        $amount = number_format(self::toDecimal($cents), 3, '.', ',');

        return "\u{2066}{$amount} {$symbol}\u{2069}";
    }
}
