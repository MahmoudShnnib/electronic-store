<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\ExportReportRequest;
use App\Services\Reports\ReportDateRange;
use App\Services\Reports\ReportService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams any of the known reports as a CSV download. */
final class ExportReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:reports.view'];
    }

    public function __construct(private readonly ReportService $reports) {}

    public function __invoke(ExportReportRequest $request, string $report): StreamedResponse
    {
        abort_unless(in_array($report, [
            'sales_by_day', 'sales_by_product', 'sales_by_category', 'sales_by_brand',
            'sales_by_customer', 'invoices', 'inventory_valuation', 'low_stock', 'movements', 'returns', 'refunds',
        ], true), 404);

        [$from, $to] = ReportDateRange::fromRequest($request);

        return $this->reports->exportCsv($report, $from, $to);
    }
}
