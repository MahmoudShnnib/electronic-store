<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Orders;

use App\Actions\Payments\ConfirmPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Orders\ConfirmPaymentRequest;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/** Records a confirmed (bank-transfer or cash) payment against an order. */
final class ConfirmOrderPaymentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:orders.manage'];
    }

    public function __invoke(ConfirmPaymentRequest $request, Order $order, ConfirmPayment $confirmPayment): RedirectResponse
    {
        $validated = $request->validated();

        $confirmPayment->handle(
            $order,
            Money::fromDecimal($validated['amount']),
            $validated['reference'] ?? null,
            $request->user()->id,
        );

        return back()->with('success', __('Payment recorded.'));
    }
}
