<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The single place a timestamp becomes a display string — mirrors Money.
 *
 * Timestamps are stored in UTC; everything a person reads is in the shop's
 * time (config('store.timezone'), Libya, UTC+2). Formatting a raw timestamp
 * directly shows the clock two hours behind, so screens go through here.
 */
final class Dates
{
    /** "Sun, Aug 23, 2026 2:58 PM" in English, shop time; day/month names translate under any other locale. */
    public static function dayDateTime(CarbonInterface $date): string
    {
        return self::local($date)->locale(app()->getLocale())->translatedFormat('D, M j, Y g:i A');
    }

    /** "2026-10-12 14:17:41" in shop time. Nullable in, nullable out. */
    public static function dateTime(?CarbonInterface $date): ?string
    {
        return $date === null ? null : self::local($date)->toDateTimeString();
    }

    /** "2026-10-12" — the shop's day, not the UTC one. Nullable in, nullable out. */
    public static function date(?CarbonInterface $date): ?string
    {
        return $date === null ? null : self::local($date)->toDateString();
    }

    /** "14:17" in shop time. */
    public static function time(CarbonInterface $date): string
    {
        return self::local($date)->format('H:i');
    }

    private static function local(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->setTimezone((string) config('store.timezone'));
    }

    /** "2 hours ago" in English; the whole phrase translates under any other locale. Nullable in, nullable out — last_login_at etc. are optional. */
    public static function relative(?CarbonInterface $date): ?string
    {
        return $date?->locale(app()->getLocale())->diffForHumans();
    }
}
