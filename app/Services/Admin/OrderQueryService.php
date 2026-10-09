<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\RefundMethod;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\Pos\PrinterConfig;
use App\Services\Pos\ReceiptPrinter;
use App\Services\Reports\ReportDateRange;
use App\Support\Money;
use Illuminate\Http\Request;

/**
 * Builds the Inertia props for the admin order screens (list, detail, invoice).
 * Controllers stay thin — authorize, delegate here, render.
 */
final class OrderQueryService
{
    /**
     * Props for admin/orders/Index: a filtered order list.
     *
     * @return array<string, mixed>
     */
    public function indexProps(Request $request): array
    {
        ReportDateRange::validate($request);

        $orders = Order::query()
            // Units, not line count: 3 × one product should read as 3.
            ->withSum('items as items_count', 'quantity')
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
                'items_count' => (int) $o->items_count,
                'total_formatted' => Money::format($o->total_cents, $o->currency),
                'created_at' => $o->created_at->toDateTimeString(),
            ]);

        return [
            'orders' => $orders,
            'statuses' => array_map(fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()], OrderStatus::cases()),
            'paymentStatuses' => array_map(fn (PaymentStatus $s) => ['value' => $s->value, 'label' => $s->label()], PaymentStatus::cases()),
            'filters' => (object) $request->only(['q', 'status', 'payment_status', 'from', 'to']),
        ];
    }

    /**
     * Props for admin/orders/Create: everything the manual-order form needs to
     * pick lines and a delivery city.
     *
     * @return array<string, mixed>
     */
    public function createProps(): array
    {
        return [
            'products' => Product::query()
                ->where('status', ProductStatus::Active)
                ->with('variants:id,product_id,name,sku,price_cents,sale_price_cents,is_active')
                ->orderBy('name')
                ->get(['id', 'name', 'name_ar', 'sku', 'price_cents', 'sale_price_cents', 'sale_starts_at', 'sale_ends_at', 'has_variants'])
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'has_variants' => (bool) $p->has_variants,
                    'price' => Money::toDecimal($p->currentPriceCents()),
                    'price_formatted' => Money::format($p->currentPriceCents()),
                    'variants' => $p->variants
                        ->where('is_active', true)
                        ->map(fn (ProductVariant $v) => [
                            'id' => $v->id,
                            'name' => $v->name,
                            'sku' => $v->sku,
                            'price' => Money::toDecimal($v->setRelation('product', $p)->currentPriceCents()),
                            'price_formatted' => Money::format($v->currentPriceCents()),
                        ])
                        ->values(),
                ]),
            'cities' => config('store.cities', []),
            'paymentMethods' => array_map(
                fn (PaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()],
                PaymentMethod::cases(),
            ),
        ];
    }

    /**
     * Props for admin/orders/Show: the full order detail with items, timeline,
     * payments and refunds.
     *
     * @return array<string, mixed>
     */
    public function showProps(Order $order): array
    {
        $order->load([
            'items', 'statusHistories.user:id,name', 'payments.confirmer:id,name',
            'refunds', 'user:id,name,email,phone',
        ]);

        $paidCents = (int) $order->payments->where('status', 'confirmed')->sum('amount_cents');
        $refundedCents = (int) $order->refunds->where('status', 'completed')->sum('amount_cents');
        $returnable = $order->returnableQuantities();

        return [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'allowed_transitions' => array_map(
                    fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                    // Refunded has its own form (refund + restock), never a bare button.
                    array_values(array_filter($order->status->allowedTransitions(), fn (OrderStatus $s) => $s !== OrderStatus::Refunded)),
                ),
                'payment_status' => $order->payment_status->value,
                'payment_status_label' => $order->payment_status->label(),
                'payment_method' => $order->payment_method->label(),
                // Preselects the refund form on the channel the money came in
                // through, so the common case needs no thought and still lands
                // in the database as a concrete method.
                'default_refund_method' => RefundMethod::fromPaymentMethod($order->payment_method)->value,
                'placed_via' => $order->placed_via,
                'customer' => $order->customer,
                'account' => $order->user?->only(['id', 'name', 'email', 'phone']),
                'shipping_address' => $order->shipping_address,
                'billing_address' => $order->billing_address,
                'discount_formatted' => Money::format($order->discount_cents, $order->currency),
                'coupon_code' => $order->coupon_code,
                'total_formatted' => Money::format($order->total_cents, $order->currency),
                'total_cents' => $order->total_cents,
                'paid_cents' => $paidCents,
                'refunded_cents' => $refundedCents,
                'paid_formatted' => Money::format($paidCents, $order->currency),
                // What completing the order would still collect (net of refunds); nothing once it's cancelled or refunded.
                'outstanding_formatted' => Money::format(
                    in_array($order->status, [OrderStatus::InProgress, OrderStatus::Completed], true) ? max(0, $order->total_cents - $paidCents + $refundedCents) : 0,
                    $order->currency,
                ),
                'refunded_formatted' => Money::format($refundedCents, $order->currency),
                'customer_note' => $order->customer_note,
                // Drives the "return / product problem" button: only a
                // finished order can be sent back.
                'can_resolve_issue' => $order->status === OrderStatus::Completed,
                // Hide what this staff member can't do, instead of showing buttons that answer "forbidden".
                'can' => [
                    'manage' => (bool) request()->user()?->can('orders.manage'),
                    'refund' => (bool) request()->user()?->can('refunds.manage'),
                ],
                'created_at' => $order->created_at->toDateTimeString(),
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'returnable' => $returnable[$item->id] ?? 0,
                    'name' => $item->variant_name !== null
                        ? "{$item->product_name} — {$item->variant_name}"
                        : $item->product_name,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unit_price_formatted' => Money::format($item->unit_price_cents, $order->currency),
                    'discount_formatted' => Money::format($item->discount_cents, $order->currency),
                    'line_total_formatted' => Money::format($item->line_total_cents, $order->currency),
                ]),
                'timeline' => $order->statusHistories->map(fn ($h) => [
                    'from' => OrderStatusHistory::labelFor($h->from_status),
                    'to' => OrderStatusHistory::labelFor($h->to_status),
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
            ],
        ];
    }

    /**
     * Props for admin/orders/Invoice: the printable invoice.
     *
     * @return array<string, mixed>
     */
    public function invoiceProps(Order $order): array
    {
        $order->load('items');

        return [
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
                'discount_formatted' => Money::format($order->discount_cents, $order->currency),
                'total_formatted' => Money::format($order->total_cents, $order->currency),
            ],
            'store' => [
                // The dashboard's store name; app.name is only the fallback.
                'name' => Setting::get('store_name') ?: config('app.name'),
                'details' => Setting::get('invoice_details'),
            ],
        ];
    }

    /**
     * Props for admin/orders/DeliverySlip: who the parcel goes to, what is in
     * it, and what the driver has to collect. No unit prices — the slip is
     * handled by people who have no business reading the margin.
     *
     * Sized and priced like a counter receipt because it prints on the same
     * 80mm head: amounts drop the LTR isolate so each one follows its own line
     * in Arabic, exactly as they do on a POS slip.
     *
     * @return array<string, mixed>
     */
    public function deliverySlipProps(Order $order): array
    {
        $order->load('items');

        $shipping = $order->shipping_address ?? [];
        $customer = $order->customer ?? [];

        return [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'created_at' => $order->created_at->toDateString(),
                'recipient' => [
                    'name' => $shipping['name'] ?? $customer['name'] ?? null,
                    'phone' => $shipping['phone'] ?? $customer['phone'] ?? null,
                    'address' => array_values(array_filter([
                        $shipping['area'] ?? null,
                        $shipping['city'] ?? null,
                    ])),
                    'notes' => $shipping['notes'] ?? null,
                ],
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->variant_name !== null
                        ? "{$item->product_name} — {$item->variant_name}"
                        : $item->product_name,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                ]),
                'payment_method' => $order->payment_method->label(),
                'total_formatted' => Money::format($order->total_cents, $order->currency, isolate: false),
                // Cash the driver has to come back with. Anything already settled
                // must not be collected twice, so only unpaid orders show it.
                'collect_formatted' => $order->payment_status === PaymentStatus::Unpaid
                    ? Money::format($order->total_cents, $order->currency, isolate: false)
                    : null,
                'customer_note' => $order->customer_note,
            ],
            'store' => [
                'name' => Setting::get('store_name') ?: config('app.name'),
                'details' => Setting::get('invoice_details'),
                'logo' => Setting::get('receipt_logo'),
            ],
            // Same contract as the receipt page: with no printer wired up the
            // slip still has to reach the driver, so the page falls back to the
            // browser dialog rather than silently doing nothing.
            'printer' => [
                'direct' => ReceiptPrinter::isConfigured(),
                'width' => (int) PrinterConfig::get('width'),
            ],
        ];
    }
}
