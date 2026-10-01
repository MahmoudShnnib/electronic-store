<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Treasury;

use App\Actions\Treasury\RecordTreasuryMovement;
use App\Enums\TreasuryAccount;
use App\Enums\TreasuryDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Treasury\StoreTreasuryTransactionRequest;
use App\Services\Admin\TreasuryQueryService;
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
        $direction = TreasuryDirection::from($validated['direction']);
        $account = TreasuryAccount::from($validated['account']);
        $amountCents = Money::fromDecimal($validated['amount']);

        // You can't take out money the account doesn't hold.
        // ponytail: read-then-write, not locked; fine for occasional manual entries.
        if ($direction === TreasuryDirection::Out && $amountCents > app(TreasuryQueryService::class)->balances()[$account->value]) {
            return back()->withErrors(['amount' => __('That is more than the account holds.')]);
        }

        $recordMovement->handle(
            direction: $direction,
            account: $account,
            amountCents: $amountCents,
            reference: $validated['reference'] ?? null,
            note: $validated['note'] ?? null,
            userId: $request->user()->id,
        );

        return back()->with('success', __('Treasury movement recorded.'));
    }
}
