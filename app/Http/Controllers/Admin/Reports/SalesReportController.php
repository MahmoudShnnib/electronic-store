<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportDateRange;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/** Renders the sales report — also the landing page of the reports section. */
final class SalesReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:reports.view'];
    }

    public function __construct(private readonly ReportService $reports) {}

    public function __invoke(Request $request): Response
    {
        [$from, $to] = ReportDateRange::fromRequest($request);

        return Inertia::render('admin/reports/Sales', [
            'summary' => $this->reports->salesSummary($from, $to),
            'daily' => $this->reports->salesByDay($from, $to),
            'byProduct' => $this->reports->salesByProduct($from, $to),
            'byCategory' => $this->reports->salesByCategory($from, $to),
            'byBrand' => $this->reports->salesByBrand($from, $to),
            'byPaymentMethod' => $this->reports->salesByPaymentMethod($from, $to),
            'byStatus' => $this->reports->salesByStatus($from, $to),
            'byCustomer' => $this->reports->salesByCustomer($from, $to),
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }
}
