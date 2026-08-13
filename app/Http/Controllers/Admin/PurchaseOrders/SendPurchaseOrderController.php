<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\PurchaseOrders;

use App\Actions\Purchasing\SendPurchaseOrder;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use InvalidArgumentException;

/** Marks a draft PO as sent to the supplier and books the incoming stock. */
final class SendPurchaseOrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:purchase_orders.manage'];
    }

    public function __invoke(PurchaseOrder $purchaseOrder, SendPurchaseOrder $sendPurchaseOrder): RedirectResponse
    {
        try {
            $sendPurchaseOrder->handle($purchaseOrder);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Purchase order sent to supplier.');
    }
}
