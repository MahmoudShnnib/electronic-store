<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\RefundMethod;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\Pos\PrinterConfig;
use App\Services\Pos\ReceiptPrinter;
use App\Services\Reports\ReportDateRange;
use App\Support\Dates;
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
        [$from, $to] = ReportDateRange::localBounds($request);

        $orders = Order::query()
            // Counter sales have their own page (admin/pos/sales); this list is
            // the orders that need fulfilling — website, WhatsApp, phone.
            ->where('placed_via', '<>', 'pos')
            // Units, not line count: 3 × one product should read as 3.
            ->withSum('items as items_count', 'quantity')
            ->when($request->filled('q'), fn ($q) => $q->search((string) $request->query('q')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->query('status')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', (string) $request->query('payment_status')))
            // Bank transfers waiting for a confirmed payment are their own queue;
            // unpaid cash-on-delivery orders are normal until the driver returns.
            ->when($request->filled('payment_method'), fn ($q) => $q->where('payment_method', (string) $request->query('payment_method')))
            // Cities are stored by their English name (the checkout's canonical value).
            ->when($request->filled('city'), fn ($q) => $q->where('shipping_address->city', (string) $request->query('city')))
            ->when($request->filled('driver_id'), fn ($q) => $q->where('driver_id', (int) $request->query('driver_id'))->whereNotNull('shipped_at'))
            // The calling queue: in-progress orders nobody has confirmed yet.
            ->when($request->query('confirmation') === 'unconfirmed', fn ($q) => $q->where('status', OrderStatus::InProgress)->whereNull('confirmed_at'))
            ->when($request->query('confirmation') === 'confirmed', fn ($q) => $q->whereNotNull('confirmed_at'))
            ->with('driver:id,name')
            ->withCount(['callAttempts as no_answer_count' => fn ($q) => $q->where('outcome', 'no_answer')])
            // The shop's local days, like the reports — not UTC days.
            ->when($from !== null, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('created_at', '<=', $to))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'customer_name' => $o->customer['name'] ?? '—',
                'customer_email' => $o->customer['email'] ?? '—',
                // What dispatch works from: who to call and where it goes.
                'phone' => $o->shipping_address['phone'] ?? $o->customer['phone'] ?? null,
                'city' => self::cityLabel($o->shipping_address['city'] ?? null),
                'can_complete' => $o->status === OrderStatus::InProgress,
                'driver' => $o->shipped_at !== null ? $o->driver?->name : null,
                'confirmed' => $o->confirmed_at !== null,
                'no_answer_count' => (int) $o->no_answer_count,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'payment_status' => $o->payment_status->value,
                'payment_status_label' => $o->payment_status->label(),
                'payment_method' => $o->payment_method->label(),
                'payment_method_value' => $o->payment_method->value,
                'items_count' => (int) $o->items_count,
                'total_formatted' => Money::format($o->total_cents, $o->currency),
                'created_at' => Dates::dateTime($o->created_at),
            ]);

        return [
            'orders' => $orders,
            'statuses' => array_map(fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()], OrderStatus::cases()),
            'paymentStatuses' => array_map(fn (PaymentStatus $s) => ['value' => $s->value, 'label' => $s->label()], PaymentStatus::cases()),
            'paymentMethods' => array_map(
                fn (PaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()],
                array_filter(PaymentMethod::cases(), fn (PaymentMethod $m) => ! $m->isOverTheCounter()),
            ),
            'cities' => array_map(fn (array $city) => ['value' => $city['en'], 'label' => self::cityLabel($city['en'])], config('store.cities', [])),
            'drivers' => Driver::query()->active()->orderBy('name')->get(['id', 'name'])->map(fn (Driver $d) => ['value' => $d->id, 'label' => $d->name]),
            'filters' => (object) $request->only(['q', 'status', 'payment_status', 'payment_method', 'city', 'driver_id', 'confirmation', 'from', 'to']),
            'can' => ['manage' => $request->user()->can('orders.manage')],
        ];
    }

    /** A delivery city in the admin's language (orders store the English name). */
    public static function cityLabel(?string $city): ?string
    {
        if ($city === null || app()->getLocale() !== 'ar') {
            return $city;
        }

        foreach (config('store.cities', []) as $known) {
            if ($known['en'] === $city) {
                return $known['ar'];
            }
        }

        return $city;
    }

    /**
     * Props for admin/orders/DeliverySlips: several slips on one sheet, in the
     * order they were picked, for printing a driver's run in one go.
     *
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    public function deliverySlipsProps(array $ids): array
    {
        $orders = Order::query()->whereIn('id', $ids)->with('items')->get()->keyBy('id');

        return [
            'orders' => collect($ids)
                ->map(fn (int $id) => $orders->get($id))
                ->filter()
                ->map(fn (Order $order) => $this->deliverySlipProps($order)['order'])
                ->values(),
            'store' => $this->slipStore(),
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
            'refunds', 'user:id,name,email,phone', 'driver:id,name,phone', 'callAttempts.user:id,name',
        ]);

        $paidCents = (int) $order->payments->where('status', 'confirmed')->sum('amount_cents');
        $refundedCents = (int) $order->refunds->where('status', 'completed')->sum('amount_cents');
        $returnable = $order->returnableQuantities();
        $outstandingCents = in_array($order->status, [OrderStatus::InProgress, OrderStatus::Completed], true)
            ? max(0, $order->total_cents - $paidCents + $refundedCents)
            : 0;

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
                'manual_discount_formatted' => $order->manual_discount_cents > 0 ? Money::format($order->manual_discount_cents, $order->currency) : null,
                'manual_discount_reason' => $order->manual_discount_reason,
                'coupon_code' => $order->coupon_code,
                'total_formatted' => Money::format($order->total_cents, $order->currency),
                'total_cents' => $order->total_cents,
                'paid_cents' => $paidCents,
                'refunded_cents' => $refundedCents,
                'paid_formatted' => Money::format($paidCents, $order->currency),
                // What completing the order would still collect (net of refunds); nothing once it's cancelled or refunded.
                'outstanding_formatted' => Money::format($outstandingCents, $order->currency),
                // The one payment that can be recorded: always the whole amount owed.
                'outstanding_cents' => $outstandingCents,
                'refunded_formatted' => Money::format($refundedCents, $order->currency),
                'customer_note' => $order->customer_note,
                // Drives the "return / product problem" button: only a
                // finished order can be sent back.
                // A counter sale is taken back at the till, so the cash leaves a drawer.
                'can_resolve_issue' => $order->status === OrderStatus::Completed && $order->placed_via !== 'pos',
                'is_counter_sale' => $order->placed_via === 'pos',
                // Hide what this staff member can't do, instead of showing buttons that answer "forbidden".
                'can' => [
                    'manage' => (bool) request()->user()?->can('orders.manage'),
                    'refund' => (bool) request()->user()?->can('refunds.manage'),
                ],
                'created_at' => Dates::dateTime($order->created_at),
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
                    'at' => Dates::dateTime($h->created_at),
                ]),
                'payments' => $order->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'amount_formatted' => Money::format($p->amount_cents, $order->currency),
                    'method' => $p->method,
                    'status' => $p->status,
                    'reference' => $p->reference,
                    'confirmed_by' => $p->confirmer?->name,
                    'at' => Dates::dateTime($p->created_at),
                ]),
                'refunds' => $order->refunds->map(fn ($r) => [
                    'id' => $r->id,
                    'amount_formatted' => Money::format($r->amount_cents, $order->currency),
                    'method' => $r->method->label(),
                    'reference' => $r->reference,
                    'reason' => $r->reason,
                    'at' => Dates::dateTime($r->created_at),
                ]),
                // Dispatch: which of our drivers has it, since when.
                'driver' => $order->driver === null ? null : [
                    'id' => $order->driver->id,
                    'name' => $order->driver->name,
                    'phone' => $order->driver->phone,
                ],
                'shipped_at' => $order->shipped_at !== null ? Dates::dateTime($order->shipped_at) : null,
                // Confirmation of a cash-on-delivery order by phone, and every try so far.
                'confirmed_at' => $order->confirmed_at !== null ? Dates::dateTime($order->confirmed_at) : null,
                'needs_confirmation' => $order->status === OrderStatus::InProgress && $order->confirmed_at === null && $order->placed_via !== 'pos',
                'call_attempts' => $order->callAttempts->map(fn ($c) => [
                    'id' => $c->id,
                    'outcome' => $c->outcome,
                    'note' => $c->note,
                    'user' => $c->user?->name,
                    'at' => Dates::dateTime($c->created_at),
                ]),
            ],
            'drivers' => Driver::query()->active()->orderBy('name')->get(['id', 'name']),
            'unconfirmedCancelHours' => (int) config('store.unconfirmed_cancel_hours'),
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
                'created_at' => Dates::date($order->created_at),
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
                'created_at' => Dates::date($order->created_at),
                'recipient' => [
                    'name' => $shipping['name'] ?? $customer['name'] ?? null,
                    'phone' => $shipping['phone'] ?? $customer['phone'] ?? null,
                    'address' => array_values(array_filter([
                        $shipping['area'] ?? null,
                        self::cityLabel($shipping['city'] ?? null),
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
            'store' => $this->slipStore(),
            // Same contract as the receipt page: with no printer wired up the
            // slip still has to reach the driver, so the page falls back to the
            // browser dialog rather than silently doing nothing.
            'printer' => [
                'direct' => ReceiptPrinter::isConfigured(),
                'width' => (int) PrinterConfig::get('width'),
            ],
        ];
    }

    /**
     * @return array{name: string, details: mixed, logo: mixed}
     */
    private function slipStore(): array
    {
        return [
            'name' => Setting::get('store_name') ?: config('app.name'),
            'details' => Setting::get('invoice_details'),
            'logo' => Setting::get('receipt_logo'),
        ];
    }
}
