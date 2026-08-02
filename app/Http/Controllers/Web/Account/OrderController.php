<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Account;

use App\Actions\Orders\CancelOrder;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = $request->user()->orders()
            ->latest()
            ->paginate(10)
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'payment_status' => $order->payment_status->value,
                'total_formatted' => Money::format($order->total_cents, $order->currency),
                'items_count' => $order->items()->count(),
                'created_at' => $order->created_at->toDayDateTimeString(),
                'cancellable' => $order->status->isCancellable(),
            ]);

        return Inertia::render('storefront/account/Orders', ['orders' => $orders]);
    }

    public function show(Request $request, Order $order): Response
    {
        Gate::authorize('view', $order);

        $order->load(['items', 'statusHistories', 'refunds']);

        return Inertia::render('storefront/account/OrderDetail', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'payment_status' => $order->payment_status->value,
                'payment_method' => $order->payment_method->label(),
                'customer' => $order->customer,
                'shipping_address' => $order->shipping_address,
                'billing_address' => $order->billing_address,
                'subtotal_formatted' => Money::format($order->subtotal_cents, $order->currency),
                'discount_formatted' => Money::format($order->discount_cents, $order->currency),
                'tax_formatted' => Money::format($order->tax_cents, $order->currency),
                'shipping_formatted' => Money::format($order->shipping_cents, $order->currency),
                'total_formatted' => Money::format($order->total_cents, $order->currency),
                'coupon_code' => $order->coupon_code,
                'customer_note' => $order->customer_note,
                'created_at' => $order->created_at->toDayDateTimeString(),
                'cancellable' => $order->status->isCancellable(),
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->variant_name !== null
                        ? "{$item->product_name} — {$item->variant_name}"
                        : $item->product_name,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unit_price_formatted' => Money::format($item->unit_price_cents, $order->currency),
                    'line_total_formatted' => Money::format($item->line_total_cents, $order->currency),
                ]),
                'timeline' => $order->statusHistories->map(fn ($h) => [
                    'status' => $h->to_status->label(),
                    'comment' => $h->comment,
                    'at' => $h->created_at->toDayDateTimeString(),
                ]),
                'refunds' => $order->refunds->map(fn ($r) => [
                    'amount_formatted' => Money::format($r->amount_cents, $order->currency),
                    'method' => $r->method->label(),
                    'at' => $r->created_at->toDateString(),
                ]),
            ],
        ]);
    }

    public function cancel(Request $request, Order $order, CancelOrder $cancelOrder): RedirectResponse
    {
        Gate::authorize('cancel', $order);

        $cancelOrder->handle($order, 'Cancelled by customer.', $request->user()->id);

        return back()->with('success', __('Your order has been cancelled.'));
    }
}
