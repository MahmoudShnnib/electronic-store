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

/** Renders the invoice report: paid, outstanding and per-invoice breakdown. */
final class InvoiceReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:reports.view'];
    }

    public function __construct(private readonly ReportService $reports) {}

    public function __invoke(Request $request): Response
    {
        [$from, $to] = ReportDateRange::fromRequest($request);

        return Inertia::render('admin/reports/Invoices', [
            'summary' => $this->reports->invoiceSummary($from, $to),
            'invoices' => $this->reports->invoices($from, $to),
            'filters' => ReportDateRange::localDates($from, $to),
        ]);
    }
}
