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

    /**
     * Rounds half-up on the digits as typed: as a float, 1.005 is 1.00499…
     * and would round down, so the decimal is read as text instead.
     */
    public static function fromDecimal(float|string $amount): int
    {
        $text = is_string($amount) && preg_match('/^\s*-?\d+(\.\d+)?\s*$/', $amount)
            ? trim($amount)
            : sprintf('%.6F', (float) $amount);

        $negative = str_starts_with($text, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($text, '-'), 2), 2, '');
        $fraction = str_pad($fraction, 3, '0');

        $cents = (int) $whole * 100 + (int) substr($fraction, 0, 2) + ((int) $fraction[2] >= 5 ? 1 : 0);

        return $negative ? -$cents : $cents;
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
     *
     * @param  bool  $isolate  Pass false to drop the LTR isolate and let the
     *                         amount follow its container's direction instead.
     *                         Only for surfaces where the amount stands alone in
     *                         its own element — a table cell, a total line — and
     *                         has no neighbouring text to be mangled by. In an
     *                         RTL layout that puts the number first (rightmost)
     *                         and the symbol after it, which is how a price is
     *                         read on an Arabic receipt. Anywhere an amount sits
     *                         inside a sentence, keep the isolate.
     */
    public static function format(int $cents, ?string $currency = null, bool $isolate = true): string
    {
        $currency ??= config('store.currency', 'USD');
        $symbol = config('store.currency_symbols.'.app()->getLocale(), $currency);
        $amount = number_format(self::toDecimal($cents), 3, '.', ',');

        return $isolate ? "\u{2066}{$amount} {$symbol}\u{2069}" : "{$amount} {$symbol}";
    }
}
