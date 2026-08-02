<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Purchasing\ReceivePurchaseOrder;
use App\Actions\Purchasing\SendPurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Purchasing\PurchaseOrderRequest;
use App\Http\Requests\Admin\Purchasing\ReceivePurchaseOrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Support\Money;
use App\Support\ReferenceNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class PurchaseOrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:purchase_orders.view', only: ['index', 'show']),
            new Middleware('can:purchase_orders.manage', only: ['create', 'store', 'update', 'destroy', 'send', 'cancel']),
            new Middleware('can:purchase_orders.receive', only: ['receive']),
        ];
    }

    public function index(Request $request): Response
    {
        $orders = PurchaseOrder::query()
            ->with(['supplier:id,company_name', 'warehouse:id,name'])
            ->withCount('items')
            ->when($request->filled('q'), fn ($q) => $q->whereRaw('po_number ILIKE ?', ['%'.$request->query('q').'%']))
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->query('status')))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', (int) $request->query('supplier_id')))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PurchaseOrder $po) => [
                'id' => $po->id,
                'po_number' => $po->po_number,
                'supplier' => $po->supplier->company_name,
                'warehouse' => $po->warehouse->name,
                'status' => $po->status->value,
                'status_label' => $po->status->label(),
                'total_formatted' => Money::format($po->total_cents),
                'items_count' => $po->items_count,
                'expected_at' => $po->expected_at?->toDateString(),
                'created_at' => $po->created_at->toDateString(),
            ]);

        return Inertia::render('admin/purchase-orders/Index', [
            'orders' => $orders,
            'suppliers' => Supplier::query()->active()->orderBy('company_name')->get(['id', 'company_name']),
            'statuses' => array_map(fn (PurchaseOrderStatus $s) => ['value' => $s->value, 'label' => $s->label()], PurchaseOrderStatus::cases()),
            'filters' => (object) $request->only(['q', 'status', 'supplier_id']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/purchase-orders/Form', [
            'purchaseOrder' => null,
            'suppliers' => Supplier::query()->active()->orderBy('company_name')->get(['id', 'company_name']),
            'warehouses' => Warehouse::query()->active()->get(['id', 'name']),
            'products' => $this->productOptions(),
        ]);
    }

    public function store(PurchaseOrderRequest $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $po = DB::transaction(function () use ($validated, $request): PurchaseOrder {
            $po = PurchaseOrder::create([
                'po_number' => ReferenceNumber::generate('PO'),
                'supplier_id' => $validated['supplier_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'status' => PurchaseOrderStatus::Draft,
                'discount_cents' => Money::fromDecimal($validated['discount'] ?? 0),
                'tax_cents' => Money::fromDecimal($validated['tax'] ?? 0),
                'shipping_cents' => Money::fromDecimal($validated['shipping'] ?? 0),
                'expected_at' => $validated['expected_at'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $this->syncItems($po, $validated['items']);

            return $po;
        });

        return redirect()
            ->route('admin.purchase-orders.show', $po)
            ->with('success', "Purchase order {$po->po_number} created.");
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load(['supplier', 'warehouse', 'items.product:id,name,sku', 'items.variant:id,name,sku', 'creator:id,name']);

        return Inertia::render('admin/purchase-orders/Show', [
            'purchaseOrder' => [
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'supplier' => $purchaseOrder->supplier->only(['id', 'company_name', 'contact_person', 'email', 'phone']),
                'warehouse' => $purchaseOrder->warehouse->only(['id', 'name']),
                'status' => $purchaseOrder->status->value,
                'status_label' => $purchaseOrder->status->label(),
                'is_editable' => $purchaseOrder->status->isEditable(),
                'is_receivable' => $purchaseOrder->status->isReceivable(),
                'subtotal_formatted' => Money::format($purchaseOrder->subtotal_cents),
                'discount_formatted' => Money::format($purchaseOrder->discount_cents),
                'tax_formatted' => Money::format($purchaseOrder->tax_cents),
                'shipping_formatted' => Money::format($purchaseOrder->shipping_cents),
                'total_formatted' => Money::format($purchaseOrder->total_cents),
                'expected_at' => $purchaseOrder->expected_at?->toDateString(),
                'received_at' => $purchaseOrder->received_at?->toDateTimeString(),
                'notes' => $purchaseOrder->notes,
                'created_by' => $purchaseOrder->creator?->name,
                'created_at' => $purchaseOrder->created_at->toDateTimeString(),
                'items' => $purchaseOrder->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->variant !== null
                        ? "{$item->product->name} — {$item->variant->name}"
                        : $item->product->name,
                    'sku' => $item->variant?->sku ?? $item->product->sku,
                    'quantity_ordered' => $item->quantity_ordered,
                    'quantity_received' => $item->quantity_received,
                    'outstanding' => $item->outstandingQuantity(),
                    'unit_cost_formatted' => Money::format($item->unit_cost_cents),
                    'line_total_formatted' => Money::format($item->line_total_cents),
                ]),
            ],
        ]);
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! $purchaseOrder->status->isEditable()) {
            return back()->with('error', 'Only draft purchase orders can be edited.');
        }

        $validated = $this->validated($request);

        DB::transaction(function () use ($purchaseOrder, $validated): void {
            $purchaseOrder->update([
                'supplier_id' => $validated['supplier_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'discount_cents' => Money::fromDecimal($validated['discount'] ?? 0),
                'tax_cents' => Money::fromDecimal($validated['tax'] ?? 0),
                'shipping_cents' => Money::fromDecimal($validated['shipping'] ?? 0),
                'expected_at' => $validated['expected_at'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $purchaseOrder->items()->delete();
            $this->syncItems($purchaseOrder, $validated['items']);
        });

        return back()->with('success', 'Purchase order updated.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! $purchaseOrder->status->isEditable()) {
            return back()->with('error', 'Only draft purchase orders can be deleted.');
        }

        $purchaseOrder->delete();

        return redirect()->route('admin.purchase-orders.index')->with('success', 'Purchase order deleted.');
    }

    public function send(PurchaseOrder $purchaseOrder, SendPurchaseOrder $sendPurchaseOrder): RedirectResponse
    {
        try {
            $sendPurchaseOrder->handle($purchaseOrder);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Purchase order sent to supplier.');
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, ReceivePurchaseOrder $receivePurchaseOrder): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $receivePurchaseOrder->handle(
                $purchaseOrder,
                array_map('intval', $validated['quantities']),
                $request->user()->id,
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['quantities' => $e->getMessage()]);
        }

        return back()->with('success', 'Stock received.');
    }

    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (in_array($purchaseOrder->status, [PurchaseOrderStatus::Received, PurchaseOrderStatus::Cancelled], true)) {
            return back()->with('error', 'This purchase order cannot be cancelled.');
        }

        $purchaseOrder->update(['status' => PurchaseOrderStatus::Cancelled]);

        return back()->with('success', 'Purchase order cancelled.');
    }

    /**
     * @param  list<array{product_id: int, product_variant_id: ?int, quantity: int, unit_cost: float|string}>  $items
     */
    private function syncItems(PurchaseOrder $po, array $items): void
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $unitCost = Money::fromDecimal($item['unit_cost']);
            $lineTotal = $unitCost * (int) $item['quantity'];
            $subtotal += $lineTotal;

            $po->items()->create([
                'product_id' => $item['product_id'],
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'quantity_ordered' => $item['quantity'],
                'unit_cost_cents' => $unitCost,
                'line_total_cents' => $lineTotal,
            ]);
        }

        $po->update([
            'subtotal_cents' => $subtotal,
            'total_cents' => max(0, $subtotal - $po->discount_cents) + $po->tax_cents + $po->shipping_cents,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(PurchaseOrderRequest $request): array
    {
        return $request->validated();
    }

    private function productOptions(): \Illuminate\Support\Collection
    {
        return Product::query()
            ->with('variants:id,product_id,name,sku,cost_price_cents')
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'cost_price_cents', 'has_variants'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'cost_price' => Money::toDecimal($p->cost_price_cents),
                'variants' => $p->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'sku' => $v->sku,
                    'cost_price' => Money::toDecimal($v->cost_price_cents),
                ]),
            ]);
    }
}
