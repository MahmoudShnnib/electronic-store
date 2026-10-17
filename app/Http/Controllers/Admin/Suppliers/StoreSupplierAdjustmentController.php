<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Suppliers;

use App\Actions\Suppliers\RecordSupplierAdjustment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Suppliers\StoreSupplierAdjustmentRequest;
use App\Models\Supplier;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

final class StoreSupplierAdjustmentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:suppliers.manage', 'can:treasury.manage'];
    }

    public function __invoke(StoreSupplierAdjustmentRequest $request, Supplier $supplier, RecordSupplierAdjustment $adjust): RedirectResponse
    {
        $validated = $request->validated();
        $amount = Money::fromDecimal($validated['amount']);

        $adjust->handle(
            supplier: $supplier,
            // "We owe" raises the account; "they owe us" (a credit) lowers it.
            amountCents: $validated['direction'] === 'debt' ? $amount : -$amount,
            note: $validated['note'],
            userId: $request->user()->id,
        );

        return back()->with('success', __('Supplier account updated.'));
    }
}
