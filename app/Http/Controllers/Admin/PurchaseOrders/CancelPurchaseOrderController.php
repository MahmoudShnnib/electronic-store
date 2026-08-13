<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\PurchaseOrders;

use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/** Cancels a purchase order that has not been received yet. */
final class CancelPurchaseOrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:purchase_orders.manage'];
    }

    public function __invoke(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (in_array($purchaseOrder->status, [PurchaseOrderStatus::Received, PurchaseOrderStatus::Cancelled], true)) {
            return back()->with('error', 'This purchase order cannot be cancelled.');
        }

        $purchaseOrder->update(['status' => PurchaseOrderStatus::Cancelled]);

        return back()->with('success', 'Purchase order cancelled.');
    }
}
