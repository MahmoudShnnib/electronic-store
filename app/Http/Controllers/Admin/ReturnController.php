<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Returns\ReceiveReturnItems;
use App\Actions\Returns\ReviewReturn;
use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Returns\ReceiveReturnRequest;
use App\Http\Requests\Admin\Returns\ReviewReturnRequest;
use App\Models\ProductReturn;
use App\Models\Warehouse;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class ReturnController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:returns.manage'];
    }

    public function index(Request $request): Response
    {
        $returns = ProductReturn::query()
            ->with(['order:id,number', 'user:id,name'])
            ->withCount('items')
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
                'reason' => $r->reason,
                'items_count' => $r->items_count,
                'created_at' => $r->created_at->toDateTimeString(),
            ]);

        return Inertia::render('admin/returns/Index', [
            'returns' => $returns,
            'statuses' => array_map(fn (ReturnStatus $s) => ['value' => $s->value, 'label' => $s->label()], ReturnStatus::cases()),
            'filters' => (object) $request->only(['q', 'status']),
        ]);
    }

    public function show(ProductReturn $productReturn): Response
    {
        $productReturn->load([
            'order:id,number,total_cents,currency,payment_status',
            'user:id,name,email',
            'items.orderItem',
            'reviewer:id,name',
            'refunds',
        ]);

        return Inertia::render('admin/returns/Show', [
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
                'reason' => $productReturn->reason,
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
                'suggested_refund_cents' => $productReturn->items->sum(
                    fn ($item) => $item->orderItem->unit_price_cents * $item->quantity
                        - intdiv($item->orderItem->discount_cents * $item->quantity, max(1, $item->orderItem->quantity)),
                ),
            ],
            'warehouses' => Warehouse::query()->active()->get(['id', 'name']),
        ]);
    }

    public function review(ReviewReturnRequest $request, ProductReturn $productReturn, ReviewReturn $reviewReturn): RedirectResponse
    {
        $validated = $request->validated();

        $reviewReturn->handle(
            $productReturn,
            ReturnStatus::from($validated['status']),
            $validated['admin_note'] ?? null,
            $request->user()->id,
        );

        return back()->with('success', 'Return updated.');
    }

    public function receive(ReceiveReturnRequest $request, ProductReturn $productReturn, ReceiveReturnItems $receiveReturnItems): RedirectResponse
    {
        $validated = $request->validated();

        $receiveReturnItems->handle(
            $productReturn,
            $validated['conditions'],
            Warehouse::query()->findOrFail($validated['warehouse_id']),
            $request->user()->id,
        );

        return back()->with('success', 'Return items received.');
    }
}
