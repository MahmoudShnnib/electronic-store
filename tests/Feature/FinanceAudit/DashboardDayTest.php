<?php

declare(strict_types=1);

/*
 * Finance audit X2: the dashboard and the sales report must put an order on the same
 * day. Timestamps are stored in UTC; the shop's day is config('store.timezone').
 */

use App\Services\Reports\ReportService;
use Carbon\CarbonImmutable;

require_once __DIR__.'/FinanceAuditSetup.php';

beforeEach(fn () => finSetUp($this));

it('X2: the dashboard "today" and the sales report agree on which day an order belongs to', function (): void {
    // 21:30 UTC on 5 Oct is 23:30 in Tripoli: still the 5th for the shop. Delivered and
    // paid then (revenue is money received).
    $this->travelTo(CarbonImmutable::parse('2026-10-05 21:30:00', 'UTC'));
    finComplete($this, finBuy($this, $this->buyer, [[$this->phone, 1]]));

    // 22:30 UTC on 5 Oct = 00:30 on 6 Oct in Tripoli: the shop's day has turned.
    $this->travelTo(CarbonImmutable::parse('2026-10-05 22:30:00', 'UTC'));
    $dash = $this->actingAs($this->admin)->get('/admin')->viewData('page')['props'];
    $tz = (string) config('store.timezone');
    $shopToday = app(ReportService::class)->salesSummary(now($tz)->startOfDay()->utc()->toImmutable(), now($tz)->endOfDay()->utc()->toImmutable());

    expect($shopToday['revenue_formatted'])->toBe(finFmt(0))
        ->and($dash['kpis']['revenue_today'])->toBe($shopToday['revenue_formatted']);
});

it('X2: the dashboard chart files a sale under the same date as "sales by day"', function (): void {
    // 22:30 UTC on 5 Oct = 00:30 on 6 Oct in Tripoli: delivered and paid then.
    $this->travelTo(CarbonImmutable::parse('2026-10-05 22:30:00', 'UTC'));
    finComplete($this, finBuy($this, $this->buyer, [[$this->phone, 1]]));

    $dash = $this->actingAs($this->admin)->get('/admin')->viewData('page')['props'];
    $byDay = app(ReportService::class)->salesByDay(now()->subDays(2)->toImmutable(), now()->addDays(2)->toImmutable());

    expect($byDay->pluck('day')->all())->toBe(['2026-10-06'])
        ->and(collect($dash['revenueSeries'])->pluck('day')->all())->toBe($byDay->pluck('day')->all());
});
