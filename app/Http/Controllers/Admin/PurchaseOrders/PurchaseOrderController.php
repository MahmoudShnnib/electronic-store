<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\PurchaseOrders;

use App\Actions\Purchasing\SavePurchaseOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Purchasing\PurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Services\Admin\PurchaseOrderQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Purchase-order CRUD only. The send / receive / cancel workflow steps each
 * have their own single-action controller in this namespace.
 */
final class PurchaseOrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:purchase_orders.view', only: ['index', 'show']),
            new Middleware('can:purchase_orders.manage', only: ['create', 'store', 'edit', 'update', 'destroy']),
        ];
    }

    public function __construct(private readonly PurchaseOrderQueryService $query) {}

    public function index(Request $request): Response
    {
        return Inertia::render('admin/purchase-orders/Index', $this->query->indexProps($request));
    }

    /**
     * `?reorder=1&supplier=<id|none>` opens the form already filled with that
     * supplier's low-stock lines (see ReorderSuggestions).
     */
    public function create(Request $request): Response
    {
        $reorderFor = null;

        if ($request->boolean('reorder')) {
            $supplier = (string) $request->query('supplier', 'none');
            $reorderFor = ['supplier_id' => ctype_digit($supplier) ? (int) $supplier : null];
        }

        return Inertia::render('admin/purchase-orders/Form', $this->query->createProps($reorderFor));
    }

    public function store(PurchaseOrderRequest $request, SavePurchaseOrder $savePurchaseOrder): RedirectResponse
    {
        $po = $savePurchaseOrder->handle(null, $request->validated(), $request->user()->id);

        return redirect()
            ->route('admin.purchase-orders.show', $po)
            ->with('success', __('Purchase order :number created.', ['number' => $po->po_number]));
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        return Inertia::render('admin/purchase-orders/Show', $this->query->showProps($purchaseOrder));
    }

    public function edit(PurchaseOrder $purchaseOrder): Response|RedirectResponse
    {
        if (! $purchaseOrder->status->isEditable()) {
            return redirect()
                ->route('admin.purchase-orders.show', $purchaseOrder)
                ->with('error', __('Only draft purchase orders can be edited.'));
        }

        return Inertia::render('admin/purchase-orders/Form', $this->query->editProps($purchaseOrder));
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, SavePurchaseOrder $savePurchaseOrder): RedirectResponse
    {
        if (! $purchaseOrder->status->isEditable()) {
            return back()->with('error', __('Only draft purchase orders can be edited.'));
        }

        $savePurchaseOrder->handle($purchaseOrder, $request->validated(), $request->user()->id);

        return back()->with('success', __('Purchase order updated.'));
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! $purchaseOrder->status->isEditable()) {
            return back()->with('error', __('Only draft purchase orders can be deleted.'));
        }

        $purchaseOrder->delete();

        return redirect()->route('admin.purchase-orders.index')->with('success', __('Purchase order deleted.'));
    }
}
