<?php

declare(strict_types=1);

/*
 * BD5 (owner decision 2026-10-10): revenue is money received. It lands on the day the
 * money arrives — delivery day for cash on delivery, the day a transfer is confirmed —
 * and a refund takes it out on the day it is given back. Past days never change later.
 */

use App\Services\Reports\ReportService;
use Carbon\CarbonImmutable;

require_once __DIR__.'/../FinanceAudit/FinanceAuditSetup.php';

beforeEach(fn () => finSetUp($this));

/** Sales-report revenue for one shop day (Africa/Tripoli). */
function finDayRevenue(string $day): string
{
    $tz = (string) config('store.timezone');
    $from = CarbonImmutable::parse($day, $tz)->startOfDay()->utc();

    return app(ReportService::class)->salesSummary($from, $from->addDay()->subSecond())['revenue_formatted'];
}

it('books cash on delivery on the delivery day, and a refund on the day it is given back', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00:00')); // Monday: ordered
    $order = finBuy($this, $this->buyer, [[$this->phone, 1]]);
    expect(finDayRevenue('2026-10-05'))->toBe(finFmt(0));

    $this->travelTo(CarbonImmutable::parse('2026-10-08 09:00:00')); // Thursday: delivered, cash collected
    finComplete($this, $order);
    $dash = $this->actingAs($this->admin)->get('/admin')->viewData('page')['props'];
    expect(finDayRevenue('2026-10-05'))->toBe(finFmt(0))
        ->and(finDayRevenue('2026-10-08'))->toBe(finFmt(100_000))
        ->and($dash['kpis']['revenue_today'])->toBe(finFmt(100_000))
        ->and(collect($dash['revenueSeries'])->pluck('revenue', 'day')->all())->toBe(['2026-10-08' => 1000.0]);

    $this->travelTo(CarbonImmutable::parse('2026-10-09 09:00:00')); // Friday: returned
    finReturn($this, $order, 100_000, ['PHONE' => 1]);
    expect(finDayRevenue('2026-10-08'))->toBe(finFmt(100_000)) // Thursday stays as it was
        ->and(finDayRevenue('2026-10-09'))->toBe(finFmt(-100_000))
        ->and(auditFailures())->toBe([]);
});

it('books a bank transfer on the day it is confirmed, not the delivery day', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00:00'));
    $order = finBuy($this, $this->buyer, [[$this->cable, 2]]);

    $this->travelTo(CarbonImmutable::parse('2026-10-06 09:00:00')); // transfer seen in the bank
    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/payments", ['amount' => 100])->assertSessionHasNoErrors();

    $this->travelTo(CarbonImmutable::parse('2026-10-07 09:00:00')); // delivered
    finComplete($this, $order);

    expect(finDayRevenue('2026-10-05'))->toBe(finFmt(0))
        ->and(finDayRevenue('2026-10-06'))->toBe(finFmt(10_000))
        ->and(finDayRevenue('2026-10-07'))->toBe(finFmt(0))
        ->and(auditFailures())->toBe([]);
});
