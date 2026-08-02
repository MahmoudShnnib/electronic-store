<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\AdjustStock;
use App\Actions\Inventory\TransferStock;
use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\AdjustStockRequest;
use App\Http\Requests\Admin\Inventory\TransferStockRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:inventory.view', only: ['index', 'movements']),
            new Middleware('can:inventory.adjust', only: ['adjust']),
            new Middleware('can:inventory.transfer', only: ['transfer']),
        ];
    }

    public function index(Request $request): Response
    {
        $levels = StockLevel::query()
            ->with(['product:id,name,sku,low_stock_threshold', 'variant:id,name,sku,low_stock_threshold', 'warehouse:id,name,code'])
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->select('stock_levels.*')
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', (int) $request->query('warehouse_id')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($qq) => $qq
                ->whereRaw('products.name ILIKE ?', ['%'.$request->query('q').'%'])
                ->orWhereRaw('products.sku ILIKE ?', ['%'.$request->query('q').'%'])))
            ->when($request->query('filter') === 'low', fn ($q) => $q
                ->leftJoin('product_variants', 'product_variants.id', '=', 'stock_levels.product_variant_id')
                ->whereRaw('stock_levels.quantity <= COALESCE(product_variants.low_stock_threshold, products.low_stock_threshold)')
                ->where('stock_levels.quantity', '>', 0))
            ->when($request->query('filter') === 'out', fn ($q) => $q->where('stock_levels.quantity', '<=', 0))
            ->orderBy('products.name')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (StockLevel $s) => [
                'id' => $s->id,
                'product_id' => $s->product_id,
                'variant_id' => $s->product_variant_id,
                'name' => $s->variant !== null ? "{$s->product->name} — {$s->variant->name}" : $s->product->name,
                'sku' => $s->variant?->sku ?? $s->product->sku,
                'warehouse' => $s->warehouse->name,
                'warehouse_id' => $s->warehouse_id,
                'quantity' => $s->quantity,
                'reserved' => $s->reserved,
                'damaged' => $s->damaged,
                'incoming' => $s->incoming,
                'low_stock' => $s->quantity <= ($s->variant?->low_stock_threshold ?? $s->product->low_stock_threshold),
            ]);

        return Inertia::render('admin/inventory/Index', [
            'levels' => $levels,
            'warehouses' => Warehouse::query()->active()->get(['id', 'name', 'code']),
            'filters' => (object) $request->only(['q', 'warehouse_id', 'filter']),
            'movementTypes' => array_map(
                fn (MovementType $t) => ['value' => $t->value, 'label' => $t->label()],
                [MovementType::ManualIncrease, MovementType::ManualDecrease, MovementType::Damaged, MovementType::Correction, MovementType::Initial],
            ),
        ]);
    }

    public function movements(Request $request): Response
    {
        $movements = InventoryMovement::query()
            ->with(['product:id,name,sku', 'variant:id,name', 'warehouse:id,name', 'user:id,name'])
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', (int) $request->query('product_id')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', (int) $request->query('warehouse_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', (string) $request->query('type')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->latest('created_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (InventoryMovement $m) => [
                'id' => $m->id,
                'name' => $m->variant !== null ? "{$m->product->name} — {$m->variant->name}" : $m->product->name,
                'sku' => $m->product->sku,
                'warehouse' => $m->warehouse->name,
                'type' => $m->type->value,
                'type_label' => $m->type->label(),
                'before' => $m->quantity_before,
                'change' => $m->quantity_change,
                'after' => $m->quantity_after,
                'reason' => $m->reason,
                'user' => $m->user?->name ?? __('admin.system'),
                'created_at' => $m->created_at->toDateTimeString(),
            ]);

        return Inertia::render('admin/inventory/Movements', [
            'movements' => $movements,
            'warehouses' => Warehouse::query()->get(['id', 'name']),
            'types' => array_map(fn (MovementType $t) => ['value' => $t->value, 'label' => $t->label()], MovementType::cases()),
            'filters' => (object) $request->only(['product_id', 'warehouse_id', 'type', 'from', 'to']),
        ]);
    }

    public function adjust(AdjustStockRequest $request, AdjustStock $adjustStock): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $adjustStock->handle(
                Warehouse::query()->findOrFail($validated['warehouse_id']),
                Product::query()->findOrFail($validated['product_id']),
                isset($validated['product_variant_id']) ? ProductVariant::query()->find($validated['product_variant_id']) : null,
                (int) $validated['quantity_change'],
                MovementType::from($validated['type']),
                $validated['reason'],
                $request->user()->id,
            );
        } catch (\App\Actions\Inventory\InsufficientStockException $e) {
            return back()->withErrors(['quantity_change' => $e->getMessage()]);
        }

        return back()->with('success', 'Stock adjusted.');
    }

    public function transfer(TransferStockRequest $request, TransferStock $transferStock): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $transferStock->handle(
                Warehouse::query()->findOrFail($validated['from_warehouse_id']),
                Warehouse::query()->findOrFail($validated['to_warehouse_id']),
                $validated['items'],
                $validated['notes'] ?? null,
                $request->user()->id,
            );
        } catch (\App\Actions\Inventory\InsufficientStockException $e) {
            return back()->withErrors(['items' => $e->getMessage()]);
        }

        return back()->with('success', 'Stock transferred.');
    }
}
