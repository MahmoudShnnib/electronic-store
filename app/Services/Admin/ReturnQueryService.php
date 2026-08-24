<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\ReturnStatus;
use App\Models\ProductReturn;
use App\Models\Warehouse;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the Inertia props for the admin return (RMA) screens.
 * Controllers stay thin — authorize, delegate here, render.
 */
final class ReturnQueryService
{
    /**
     * Props for admin/returns/Index: a filtered RMA list.
     *
     * @return array<string, mixed>
     */
    public function indexProps(Request $request): array
    {
        $returns = ProductReturn::query()
            ->with(['order:id,number', 'user:id,name'])
            // Units being returned, not line count.
            ->withSum('items as items_count', 'quantity')
            ->when($request->filled('q'), fn ($q) => $q->whereRaw('rma_number ILIKE ?', ['%'.$request->query('q').'%']))
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->query('status')))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ProductReturn $r) => [
                'id' => $r->id,
                'rma_number' => $r->rma_number,
                'order_number' => $r->order->number,
                'customer' => $r->user?->name ?? '—',
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'reason_label' => $r->reason->label(),
                'items_count' => (int) $r->items_count,
                'created_at' => $r->created_at->toDateTimeString(),
            ]);

        return [
            'returns' => $returns,
            'statuses' => array_map(fn (ReturnStatus $s) => ['value' => $s->value, 'label' => $s->label()], ReturnStatus::cases()),
            'filters' => (object) $request->only(['q', 'status']),
        ];
    }

    /**
     * Props for admin/returns/Show: the RMA detail with items, evidence photos
     * and the refund summary.
     *
     * @return array<string, mixed>
     */
    public function showProps(ProductReturn $productReturn): array
    {
        $productReturn->load([
            'order:id,number,total_cents,currency,payment_status',
            'user:id,name,email',
            'items.orderItem',
            'reviewer:id,name',
            'refunds',
        ]);

        return [
            'productReturn' => [
                'id' => $productReturn->id,
                'rma_number' => $productReturn->rma_number,
                'order' => [
                    'id' => $productReturn->order->id,
                    'number' => $productReturn->order->number,
                    'payment_status' => $productReturn->order->payment_status->label(),
                ],
                'customer' => $productReturn->user?->only(['id', 'name', 'email']),
                'status' => $productReturn->status->value,
                'status_label' => $productReturn->status->label(),
                'allowed_transitions' => array_map(
                    fn (ReturnStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                    $productReturn->status->allowedTransitions(),
                ),
                'reason_label' => $productReturn->reason->label(),
                'customer_note' => $productReturn->customer_note,
                'admin_note' => $productReturn->admin_note,
                'images' => array_map(
                    fn (string $path) => Storage::disk('public')->url($path),
                    $productReturn->images ?? [],
                ),
                'reviewed_by' => $productReturn->reviewer?->name,
                'reviewed_at' => $productReturn->reviewed_at?->toDateTimeString(),
                'received_at' => $productReturn->received_at?->toDateTimeString(),
                'created_at' => $productReturn->created_at->toDateTimeString(),
                'items' => $productReturn->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->orderItem->variant_name !== null
                        ? "{$item->orderItem->product_name} — {$item->orderItem->variant_name}"
                        : $item->orderItem->product_name,
                    'sku' => $item->orderItem->sku,
                    'quantity' => $item->quantity,
                    'unit_price_formatted' => Money::format($item->orderItem->unit_price_cents),
                    'condition' => $item->condition?->value,
                    'restock' => $item->restock,
                ]),
                'refund_total_formatted' => Money::format(
                    (int) $productReturn->refunds->sum('amount_cents'),
                ),
                'suggested_refund_formatted' => Money::format((int) $productReturn->items->sum(
                    fn ($item) => $item->orderItem->unit_price_cents * $item->quantity
                        - intdiv($item->orderItem->discount_cents * $item->quantity, max(1, $item->orderItem->quantity)),
                )),
            ],
            'warehouses' => Warehouse::query()->active()->get(['id', 'name']),
        ];
    }
}
