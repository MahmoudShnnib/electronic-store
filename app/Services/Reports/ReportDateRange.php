<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Resolves the from/to date range shared by every report screen and export.
 * Defaults to the last 30 days when the request carries no explicit range.
 */
final class ReportDateRange
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function fromRequest(Request $request): array
    {
        self::validate($request);

        $from = $request->filled('from') ? $request->date('from')->toImmutable()->startOfDay() : now()->toImmutable()->subDays(29)->startOfDay();
        $to = $request->filled('to') ? $request->date('to')->toImmutable()->endOfDay() : now()->toImmutable()->endOfDay();

        return [$from, $to];
    }

    /**
     * Guards every screen that takes ?from/?to straight from the URL: a
     * malformed date becomes a validation error instead of a 500, and a
     * backwards range is rejected instead of rendering as "zero results".
     */
    public static function validate(Request $request): void
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
    }
}
