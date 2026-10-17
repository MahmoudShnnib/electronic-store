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

        // A day means the shop's local day (Libya is UTC+2), converted back to
        // UTC because that is how timestamps are stored.
        $tz = (string) config('store.timezone');
        $today = now($tz)->toImmutable();

        $from = $request->filled('from') ? CarbonImmutable::parse((string) $request->query('from'), $tz)->startOfDay() : $today->subDays(29)->startOfDay();
        $to = $request->filled('to') ? CarbonImmutable::parse((string) $request->query('to'), $tz)->endOfDay() : $today->endOfDay();

        return [$from->utc(), $to->utc()];
    }

    /**
     * The range back as the shop's local dates, for the filter boxes and file
     * names. fromRequest() hands out UTC instants, and local midnight is 22:00
     * the day before in UTC — echoing those dates showed the 31st for a range
     * starting on the 1st, and every "Apply" moved the start another day back.
     *
     * @return array{from: string, to: string}
     */
    public static function localDates(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $tz = (string) config('store.timezone');

        return ['from' => $from->setTimezone($tz)->toDateString(), 'to' => $to->setTimezone($tz)->toDateString()];
    }

    /**
     * A list filter's ?from / ?to as UTC instants bounding the shop's local days,
     * so "orders on the 12th" means the same day the reports mean. Either side may
     * be null (not filtered).
     *
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    public static function localBounds(Request $request): array
    {
        $tz = (string) config('store.timezone');

        return [
            $request->filled('from') ? CarbonImmutable::parse((string) $request->query('from'), $tz)->startOfDay()->utc() : null,
            $request->filled('to') ? CarbonImmutable::parse((string) $request->query('to'), $tz)->endOfDay()->utc() : null,
        ];
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
