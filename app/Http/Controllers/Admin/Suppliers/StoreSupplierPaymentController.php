<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Suppliers;

use App\Actions\Suppliers\RecordSupplierPayment;
use App\Enums\TreasuryAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Suppliers\StoreSupplierPaymentRequest;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

final class StoreSupplierPaymentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:suppliers.manage', 'can:treasury.manage'];
    }

    public function __invoke(StoreSupplierPaymentRequest $request, Supplier $supplier, RecordSupplierPayment $pay): RedirectResponse
    {
        $validated = $request->validated();

        $pay->handle(
            supplier: $supplier,
            amountCents: Money::fromDecimal($validated['amount']),
            account: TreasuryAccount::from($validated['account']),
            purchaseOrder: isset($validated['purchase_order_id']) ? PurchaseOrder::query()->find($validated['purchase_order_id']) : null,
            reference: $validated['reference'] ?? null,
            note: $validated['note'] ?? null,
            userId: $request->user()->id,
        );

        return back()->with('success', __('Payment recorded.'));
    }
}
