<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The single place a date becomes a display string with day/month names in
 * it — mirrors Money. Numeric-only formats (`toDateString()`,
 * `toDateTimeString()`) need no translation and are left alone.
 */
final class Dates
{
    /** "Sun, Aug 23, 2026 2:58 PM" in English; day/month names translate under any other locale. */
    public static function dayDateTime(CarbonInterface $date): string
    {
        return $date->locale(app()->getLocale())->translatedFormat('D, M j, Y g:i A');
    }

    /** "2 hours ago" in English; the whole phrase translates under any other locale. Nullable in, nullable out — last_login_at etc. are optional. */
    public static function relative(?CarbonInterface $date): ?string
    {
        return $date?->locale(app()->getLocale())->diffForHumans();
    }
}
