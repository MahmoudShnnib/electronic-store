<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Refunds;

use App\Actions\Returns\CreateRefund;
use App\Enums\RefundMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Refunds\StoreRefundRequest;
use App\Models\Order;
use App\Models\ProductReturn;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/** Issues a refund against an order, optionally linked to a return (RMA). */
final class StoreRefundController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:refunds.manage'];
    }

    public function __invoke(StoreRefundRequest $request, Order $order, CreateRefund $createRefund): RedirectResponse
    {
        $validated = $request->validated();

        $createRefund->handle(
            $order,
            Money::fromDecimal($validated['amount']),
            RefundMethod::from($validated['method']),
            isset($validated['return_id']) ? ProductReturn::query()->find($validated['return_id']) : null,
            $validated['reference'] ?? null,
            $validated['reason'] ?? null,
            $request->user()->id,
        );

        return back()->with('success', 'Refund issued.');
    }
}
