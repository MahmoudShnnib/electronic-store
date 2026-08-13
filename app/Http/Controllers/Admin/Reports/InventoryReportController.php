<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/** Renders the inventory report: valuation plus low/out-of-stock lists. */
final class InventoryReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:reports.view'];
    }

    public function __construct(private readonly ReportService $reports) {}

    public function __invoke(): Response
    {
        return Inertia::render('admin/reports/Inventory', [
            'valuation' => $this->reports->inventoryValuation(),
            'lowStock' => $this->reports->lowStockProducts(),
            'outOfStock' => $this->reports->outOfStockProducts(),
        ]);
    }
}
