<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\PurchaseOrders;

use App\Actions\Purchasing\SendPurchaseOrder;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use InvalidArgumentException;

/**
 * Marks the purchase order as placed with its supplier and books the ordered
 * quantities as incoming stock. Sends no mail — see SendPurchaseOrder.
 */
final class SendPurchaseOrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:purchase_orders.manage'];
    }

    public function __invoke(Request $request, PurchaseOrder $purchaseOrder, SendPurchaseOrder $sendPurchaseOrder): RedirectResponse
    {
        try {
            $sendPurchaseOrder->handle($purchaseOrder, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Purchase order marked as sent.'));
    }
}
