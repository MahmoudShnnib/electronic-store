<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Human-friendly, collision-safe reference numbers for orders, POs, RMAs, etc.
 * Format: PREFIX-YYMMDD-XXXXXX (uppercase base36 random suffix, uniqueness
 * enforced by the DB unique index; callers retry on the rare collision).
 */
final class ReferenceNumber
{
    public static function generate(string $prefix): string
    {
        return sprintf(
            '%s-%s-%s',
            strtoupper($prefix),
            now()->format('ymd'),
            strtoupper(Str::random(6)),
        );
    }
}
