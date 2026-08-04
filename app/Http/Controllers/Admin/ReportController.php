<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\ExportReportRequest;
use App\Services\Reports\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:reports.view'];
    }

    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): Response
    {
        return $this->sales($request);
    }

    public function sales(Request $request): Response
    {
        [$from, $to] = $this->dateRange($request);

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

    public function invoices(Request $request): Response
    {
        [$from, $to] = $this->dateRange($request);

        return Inertia::render('admin/reports/Invoices', [
            'summary' => $this->reports->invoiceSummary($from, $to),
            'invoices' => $this->reports->invoices($from, $to),
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    public function inventory(Request $request): Response
    {
        return Inertia::render('admin/reports/Inventory', [
            'valuation' => $this->reports->inventoryValuation(),
            'lowStock' => $this->reports->lowStockProducts(),
            'outOfStock' => $this->reports->outOfStockProducts(),
        ]);
    }

    public function export(ExportReportRequest $request, string $report): StreamedResponse
    {
        abort_unless(in_array($report, [
            'sales_by_day', 'sales_by_product', 'sales_by_category', 'sales_by_brand',
            'sales_by_customer', 'invoices', 'inventory_valuation', 'low_stock', 'movements', 'returns', 'refunds',
        ], true), 404);

        [$from, $to] = $this->dateRange($request);

        return $this->reports->exportCsv($report, $from, $to);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function dateRange(Request $request): array
    {
        $from = $request->filled('from') ? $request->date('from')->toImmutable()->startOfDay() : now()->toImmutable()->subDays(29)->startOfDay();
        $to = $request->filled('to') ? $request->date('to')->toImmutable()->endOfDay() : now()->toImmutable()->endOfDay();

        return [$from, $to];
    }
}
