<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Suppliers;

use App\Enums\PurchaseOrderStatus;
use App\Enums\TreasuryAccount;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Services\Admin\TreasuryQueryService;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A supplier's account: what the shop owes them and every line that made it so,
 * newest first, each with the balance it left behind.
 */
final class SupplierAccountController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:suppliers.manage'];
    }

    public function __invoke(Request $request, Supplier $supplier, TreasuryQueryService $treasury): Response
    {
        $totals = SupplierTransaction::query()
            ->where('supplier_id', $supplier->id)
            ->selectRaw("COALESCE(SUM(amount_cents),0) AS balance, COALESCE(SUM(amount_cents) FILTER (WHERE type = 'purchase'),0) AS received, COALESCE(-SUM(amount_cents) FILTER (WHERE type = 'payment'),0) AS paid, COALESCE(SUM(amount_cents) FILTER (WHERE type = 'adjustment'),0) AS adjusted")
            ->first();

        $transactions = SupplierTransaction::query()
            ->where('supplier_id', $supplier->id)
            ->with(['purchaseOrder:id,po_number', 'creator:id,name'])
            // The balance after each line, over the whole account — computed before the page is cut.
            ->select('supplier_transactions.*')
            ->selectRaw('SUM(amount_cents) OVER (ORDER BY created_at, id) AS balance_after')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (SupplierTransaction $t) => [
                'id' => $t->id,
                'type' => $t->type->value,
                'type_label' => $t->type->label(),
                'amount_cents' => $t->amount_cents,
                'amount_formatted' => Money::format(abs($t->amount_cents)),
                'balance_after_formatted' => Money::format((int) $t->balance_after),
                'purchase_order' => $t->purchaseOrder?->only(['id', 'po_number']),
                'account_label' => $t->account?->label(),
                'reference' => $t->reference,
                'note' => $t->note,
                'by' => $t->creator?->name,
                'at' => Dates::dateTime($t->created_at),
            ]);

        $balances = $treasury->balances();

        return Inertia::render('admin/suppliers/Show', [
            'supplier' => $supplier->only(['id', 'company_name', 'contact_person', 'phone', 'email', 'status']),
            'totals' => [
                'balance_cents' => (int) $totals->balance,
                'balance_formatted' => Money::format(abs((int) $totals->balance)),
                'received_formatted' => Money::format((int) $totals->received),
                'paid_formatted' => Money::format((int) $totals->paid),
                'adjusted_cents' => (int) $totals->adjusted,
                'adjusted_formatted' => Money::format(abs((int) $totals->adjusted)),
            ],
            'transactions' => $transactions,
            // A payment can be tied to one of this supplier's orders that has goods in.
            'purchaseOrders' => $supplier->purchaseOrders()
                ->whereIn('status', [PurchaseOrderStatus::PartiallyReceived, PurchaseOrderStatus::Received])
                ->latest('id')
                ->limit(50)
                ->get(['id', 'po_number', 'total_cents'])
                ->map(fn ($po) => ['id' => $po->id, 'label' => $po->po_number.' — '.Money::format($po->total_cents)]),
            'accounts' => array_map(fn (TreasuryAccount $a) => [
                'value' => $a->value,
                'label' => $a->label(),
                'balance_formatted' => Money::format($balances[$a->value]),
            ], TreasuryAccount::cases()),
            // Payments and hand-written debts move money or what is owed: treasury managers only.
            'canRecord' => $request->user()->can('treasury.manage'),
        ]);
    }
}
