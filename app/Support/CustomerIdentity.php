<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalised keys for recognising the same customer across orders when there
 * is no account — a guest who orders twice is the same phone (or email),
 * however they typed it.
 */
final class CustomerIdentity
{
    /**
     * The last nine digits: `+218 91-234 5678`, `00218912345678`,
     * `0912345678` and `٠٩١٢٣٤٥٦٧٨` all become `912345678`. Null when there
     * are too few digits to identify anyone.
     */
    public static function phoneKey(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', strtr($phone, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ])) ?? '';

        return strlen($digits) >= 9 ? substr($digits, -9) : null;
    }

    public static function emailKey(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }
}
