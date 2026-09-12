<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\PurchaseOrders;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\Admin\PurchaseOrderQueryService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/** Renders the printable copy of a purchase order. */
final class PrintPurchaseOrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:purchase_orders.view'];
    }

    public function __construct(private readonly PurchaseOrderQueryService $query) {}

    public function __invoke(PurchaseOrder $purchaseOrder): Response
    {
        return Inertia::render('admin/purchase-orders/Print', $this->query->printProps($purchaseOrder));
    }
}
