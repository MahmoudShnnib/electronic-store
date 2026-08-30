<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Treasury;

use App\Actions\Treasury\RecordTreasuryMovement;
use App\Enums\TreasuryAccount;
use App\Enums\TreasuryDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Treasury\StoreTreasuryTransactionRequest;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/** A manual deposit or withdrawal that is not tied to an order. */
final class StoreTreasuryTransactionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:treasury.manage'];
    }

    public function __invoke(StoreTreasuryTransactionRequest $request, RecordTreasuryMovement $recordMovement): RedirectResponse
    {
        $validated = $request->validated();

        $recordMovement->handle(
            direction: TreasuryDirection::from($validated['direction']),
            account: TreasuryAccount::from($validated['account']),
            amountCents: Money::fromDecimal($validated['amount']),
            reference: $validated['reference'] ?? null,
            note: $validated['note'] ?? null,
            userId: $request->user()->id,
        );

        return back()->with('success', __('Treasury movement recorded.'));
    }
}
