<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Builds the Inertia props for the admin purchase-order screens.
 * Controllers stay thin — authorize, delegate here, render.
 */
final class PurchaseOrderQueryService
{
    /**
     * Props for admin/purchase-orders/Index: a filtered PO list.
     *
     * @return array<string, mixed>
     */
    public function indexProps(Request $request): array
    {
        $orders = PurchaseOrder::query()
            ->with(['supplier:id,company_name', 'warehouse:id,name'])
            // Units ordered, not line count.
            ->withSum('items as items_count', 'quantity_ordered')
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
                'items_count' => (int) $po->items_count,
                'expected_at' => $po->expected_at?->toDateString(),
                'created_at' => $po->created_at->toDateString(),
            ]);

        return [
            'orders' => $orders,
            'suppliers' => Supplier::query()->active()->orderBy('company_name')->get(['id', 'company_name']),
            'statuses' => array_map(fn (PurchaseOrderStatus $s) => ['value' => $s->value, 'label' => $s->label()], PurchaseOrderStatus::cases()),
            'filters' => (object) $request->only(['q', 'status', 'supplier_id']),
        ];
    }

    /**
     * Props for admin/purchase-orders/Form when drafting a new PO.
     *
     * @return array<string, mixed>
     */
    public function createProps(): array
    {
        return [
            'purchaseOrder' => null,
            'suppliers' => Supplier::query()->active()->orderBy('company_name')->get(['id', 'company_name']),
            'warehouses' => Warehouse::query()->active()->get(['id', 'name']),
            'products' => $this->productOptions(),
        ];
    }

    /**
     * Props for admin/purchase-orders/Form when editing a draft PO.
     *
     * @return array<string, mixed>
     */
    public function editProps(PurchaseOrder $purchaseOrder): array
    {
        $purchaseOrder->load('items');

        return [
            'purchaseOrder' => [
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'supplier_id' => $purchaseOrder->supplier_id,
                'warehouse_id' => $purchaseOrder->warehouse_id,
                'discount' => Money::toDecimal($purchaseOrder->discount_cents),
                'shipping' => Money::toDecimal($purchaseOrder->shipping_cents),
                'expected_at' => $purchaseOrder->expected_at?->toDateString(),
                'notes' => $purchaseOrder->notes,
                'items' => $purchaseOrder->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => $item->quantity_ordered,
                    'unit_cost' => Money::toDecimal($item->unit_cost_cents),
                ]),
            ],
            'suppliers' => Supplier::query()->active()->orderBy('company_name')->get(['id', 'company_name']),
            'warehouses' => Warehouse::query()->active()->get(['id', 'name']),
            'products' => $this->productOptions(),
        ];
    }

    /**
     * Props for admin/purchase-orders/Show: the PO detail with its lines.
     *
     * @return array<string, mixed>
     */
    public function showProps(PurchaseOrder $purchaseOrder): array
    {
        $purchaseOrder->load(['supplier', 'warehouse', 'items.product:id,name,sku', 'items.variant:id,name,sku', 'creator:id,name']);

        return [
            'purchaseOrder' => [
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'supplier' => $purchaseOrder->supplier->only(['id', 'company_name', 'contact_person', 'email', 'phone']),
                'warehouse' => $purchaseOrder->warehouse->only(['id', 'name']),
                'status' => $purchaseOrder->status->value,
                'status_label' => $purchaseOrder->status->label(),
                'is_editable' => $purchaseOrder->status->isEditable(),
                'is_receivable' => $purchaseOrder->status->isReceivable(),
                'discount_formatted' => Money::format($purchaseOrder->discount_cents),
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
        ];
    }

    /**
     * Every product (with variants) as options for the PO line-item picker.
     */
    private function productOptions(): Collection
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
