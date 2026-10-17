<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Pos;

use App\Actions\Pos\OpenShift;
use App\Actions\Pos\ReturnSale;
use App\Enums\OrderStatus;
use App\Enums\RefundMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Admin\PosQueryService;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\Rule;

/**
 * Returns at the till: find the sale by its receipt number, then take it back
 * with the cash paid out of the cashier's own drawer.
 */
final class PosReturnController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:pos.use', 'can:refunds.manage'];
    }

    /** The sale behind a receipt number, as the return dialog shows it. */
    public function lookup(Request $request, PosQueryService $query): JsonResponse
    {
        $number = strtoupper(trim((string) $request->query('number')));

        $order = $number === '' ? null : Order::query()
            ->where('placed_via', 'pos')
            ->where('number', $number)
            ->with(['items:id,order_id,product_name,variant_name,quantity,line_total_cents', 'user:id,name'])
            ->first();

        if ($order === null) {
            return response()->json(['found' => false, 'message' => __('No counter sale with that receipt number.')]);
        }

        $returnable = $order->status === OrderStatus::Completed && $order->netPaidCents() > 0;

        return response()->json([
            'found' => true,
            'sale' => [
                'id' => $order->id,
                'number' => $order->number,
                'at' => Dates::dateTime($order->created_at),
                // A linked account, a typed name, or null for a cash customer.
                'customer' => $order->user?->name ?? $query->namedCustomer($order),
                'payment_method' => $order->payment_method->value,
                'payment_label' => $order->payment_method->label(),
                'total_formatted' => Money::format($order->total_cents, $order->currency),
                'refund_formatted' => Money::format(max(0, $order->netPaidCents()), $order->currency),
                'status_label' => $order->status->label(),
                'returnable' => $returnable,
                'items' => $order->items->map(fn ($item) => [
                    'name' => trim($item->product_name.' '.($item->variant_name ?? '')),
                    'quantity' => $item->quantity,
                    'total_formatted' => Money::format($item->line_total_cents, $order->currency),
                ])->all(),
            ],
        ]);
    }

    public function store(Request $request, OpenShift $openShift, ReturnSale $returnSale): RedirectResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'reason' => ['required', 'string', 'max:500'],
            'method' => ['nullable', Rule::in([RefundMethod::Cash->value, RefundMethod::BankTransfer->value])],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $shift = $openShift->current($request->user());

        if ($shift === null) {
            return back()->with('error', __('Open a shift before taking a return.'));
        }

        $order = $returnSale->handle(
            shift: $shift,
            order: Order::query()->findOrFail($validated['order_id']),
            reason: $validated['reason'],
            method: isset($validated['method']) ? RefundMethod::from($validated['method']) : null,
            reference: $validated['reference'] ?? null,
            userId: $request->user()->id,
        );

        return back()->with('success', __('Sale :number returned. Everything is back on the shelf and the money was paid out.', ['number' => $order->number]));
    }
}
