<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\PurchaseOrders;

use App\Actions\Purchasing\ReceivePurchaseOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Purchasing\ReceivePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Books received quantities into stock for a sent purchase order. */
final class ReceivePurchaseOrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:purchase_orders.receive'];
    }

    public function __invoke(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, ReceivePurchaseOrder $receivePurchaseOrder): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $receivePurchaseOrder->handle(
                $purchaseOrder,
                array_map('intval', $validated['quantities']),
                $request->user()->id,
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['quantities' => $e->getMessage()]);
        }

        return back()->with('success', __('Stock received.'));
    }
}
