<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Orders\UpdateOrderStatus;
use App\Actions\Payments\ConfirmPayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Orders\ConfirmPaymentRequest;
use App\Http\Requests\Admin\Orders\UpdateOrderNoteRequest;
use App\Http\Requests\Admin\Orders\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Models\Setting;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

final class OrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:orders.view', only: ['index', 'show', 'invoice']),
            new Middleware('can:orders.manage', only: ['updateStatus', 'updateNote', 'confirmPayment']),
        ];
    }

    public function index(Request $request): Response
    {
        $orders = Order::query()
            ->withCount('items')
            ->when($request->filled('q'), fn ($q) => $q->search((string) $request->query('q')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->query('status')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', (string) $request->query('payment_status')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'customer_name' => $o->customer['name'] ?? '—',
                'customer_email' => $o->customer['email'] ?? '—',
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'payment_status' => $o->payment_status->value,
                'payment_status_label' => $o->payment_status->label(),
                'payment_method' => $o->payment_method->label(),
                'items_count' => $o->items_count,
                'total_formatted' => Money::format($o->total_cents, $o->currency),
                'created_at' => $o->created_at->toDateTimeString(),
            ]);

        return Inertia::render('admin/orders/Index', [
            'orders' => $orders,
            'statuses' => array_map(fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()], OrderStatus::cases()),
            'paymentStatuses' => array_map(fn (PaymentStatus $s) => ['value' => $s->value, 'label' => $s->label()], PaymentStatus::cases()),
            'filters' => (object) $request->only(['q', 'status', 'payment_status', 'from', 'to']),
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load([
            'items', 'statusHistories.user:id,name', 'payments.confirmer:id,name',
            'refunds', 'returns', 'user:id,name,email,phone',
        ]);

        $paidCents = (int) $order->payments->where('status', 'confirmed')->sum('amount_cents');
        $refundedCents = (int) $order->refunds->where('status', 'completed')->sum('amount_cents');

        return Inertia::render('admin/orders/Show', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'allowed_transitions' => array_map(
                    fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                    $order->status->allowedTransitions(),
                ),
                'payment_status' => $order->payment_status->value,
                'payment_status_label' => $order->payment_status->label(),
                'payment_method' => $order->payment_method->label(),
                'placed_via' => $order->placed_via,
                'customer' => $order->customer,
                'account' => $order->user?->only(['id', 'name', 'email', 'phone']),
                'shipping_address' => $order->shipping_address,
                'billing_address' => $order->billing_address,
                'subtotal_formatted' => Money::format($order->subtotal_cents, $order->currency),
                'discount_formatted' => Money::format($order->discount_cents, $order->currency),
                'coupon_code' => $order->coupon_code,
                'tax_formatted' => Money::format($order->tax_cents, $order->currency),
                'total_formatted' => Money::format($order->total_cents, $order->currency),
                'total_cents' => $order->total_cents,
                'paid_cents' => $paidCents,
                'paid_formatted' => Money::format($paidCents, $order->currency),
                'outstanding_formatted' => Money::format(max(0, $order->total_cents - $paidCents), $order->currency),
                'refunded_formatted' => Money::format($refundedCents, $order->currency),
                'customer_note' => $order->customer_note,
                'internal_note' => $order->internal_note,
                'created_at' => $order->created_at->toDateTimeString(),
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->variant_name !== null
                        ? "{$item->product_name} — {$item->variant_name}"
                        : $item->product_name,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unit_price_formatted' => Money::format($item->unit_price_cents, $order->currency),
                    'discount_formatted' => Money::format($item->discount_cents, $order->currency),
                    'tax_formatted' => Money::format($item->tax_cents, $order->currency),
                    'line_total_formatted' => Money::format($item->line_total_cents, $order->currency),
                ]),
                'timeline' => $order->statusHistories->map(fn ($h) => [
                    'from' => $h->from_status?->label(),
                    'to' => $h->to_status->label(),
                    'comment' => $h->comment,
                    'user' => $h->user?->name ?? __('admin.system'),
                    'at' => $h->created_at->toDateTimeString(),
                ]),
                'payments' => $order->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'amount_formatted' => Money::format($p->amount_cents, $order->currency),
                    'method' => $p->method,
                    'status' => $p->status,
                    'reference' => $p->reference,
                    'confirmed_by' => $p->confirmer?->name,
                    'at' => $p->created_at->toDateTimeString(),
                ]),
                'refunds' => $order->refunds->map(fn ($r) => [
                    'id' => $r->id,
                    'amount_formatted' => Money::format($r->amount_cents, $order->currency),
                    'method' => $r->method->label(),
                    'reference' => $r->reference,
                    'reason' => $r->reason,
                    'at' => $r->created_at->toDateTimeString(),
                ]),
                'returns' => $order->returns->map(fn ($r) => [
                    'id' => $r->id,
                    'rma_number' => $r->rma_number,
                    'status_label' => $r->status->label(),
                ]),
            ],
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order, UpdateOrderStatus $updateOrderStatus): RedirectResponse
    {
        $validated = $request->validated();

        $updateOrderStatus->handle(
            $order,
            OrderStatus::from($validated['status']),
            $validated['comment'] ?? null,
            $request->user()->id,
        );

        return back()->with('success', 'Order status updated.');
    }

    public function updateNote(UpdateOrderNoteRequest $request, Order $order): RedirectResponse
    {
        $validated = $request->validated();

        $order->update(['internal_note' => $validated['internal_note'] ?? null]);

        return back()->with('success', 'Note saved.');
    }

    public function confirmPayment(ConfirmPaymentRequest $request, Order $order, ConfirmPayment $confirmPayment): RedirectResponse
    {
        $validated = $request->validated();

        $confirmPayment->handle(
            $order,
            Money::fromDecimal($validated['amount']),
            $validated['reference'] ?? null,
            $request->user()->id,
        );

        return back()->with('success', 'Payment recorded.');
    }

    public function invoice(Order $order): Response
    {
        $order->load('items');

        return Inertia::render('admin/orders/Invoice', [
            'order' => [
                'number' => $order->number,
                'created_at' => $order->created_at->toDateString(),
                'customer' => $order->customer,
                'billing_address' => $order->billing_address,
                'shipping_address' => $order->shipping_address,
                'payment_method' => $order->payment_method->label(),
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->variant_name !== null
                        ? "{$item->product_name} — {$item->variant_name}"
                        : $item->product_name,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unit_price_formatted' => Money::format($item->unit_price_cents, $order->currency),
                    'line_total_formatted' => Money::format($item->line_total_cents, $order->currency),
                ]),
                'subtotal_formatted' => Money::format($order->subtotal_cents, $order->currency),
                'discount_formatted' => Money::format($order->discount_cents, $order->currency),
                'tax_formatted' => Money::format($order->tax_cents, $order->currency),
                'total_formatted' => Money::format($order->total_cents, $order->currency),
            ],
            'store' => [
                'name' => config('app.name'),
                'details' => Setting::get('invoice_details'),
            ],
        ]);
    }
}
