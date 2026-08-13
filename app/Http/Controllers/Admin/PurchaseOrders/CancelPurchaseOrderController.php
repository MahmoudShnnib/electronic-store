<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\PurchaseOrders;

use App\Actions\Purchasing\CancelPurchaseOrder;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use InvalidArgumentException;

/** Cancels a purchase order, releasing any incoming stock it had booked. */
final class CancelPurchaseOrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:purchase_orders.manage'];
    }

    public function __invoke(Request $request, PurchaseOrder $purchaseOrder, CancelPurchaseOrder $cancelPurchaseOrder): RedirectResponse
    {
        try {
            $cancelPurchaseOrder->handle($purchaseOrder, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Purchase order cancelled.'));
    }
}
