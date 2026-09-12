<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Actions\Treasury\RecordTreasuryMovement;
use App\Enums\CashierShiftStatus;
use App\Enums\TreasuryAccount;
use App\Enums\TreasuryDirection;
use App\Models\CashierShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Counts a drawer down and closes it.
 *
 * The expected figure is computed once, here, and stored: a refund raised
 * tomorrow against a sale from this shift must not retroactively change what
 * the cashier was held to tonight.
 *
 * A gap between the count and the expectation is a real gain or loss of cash,
 * so it is posted to the treasury. Without that, the ledger would keep
 * insisting on money that is demonstrably not in the drawer.
 */
final class CloseShift
{
    public function __construct(private readonly RecordTreasuryMovement $recordTreasuryMovement) {}

    public function handle(CashierShift $shift, int $countedCashCents, ?string $note = null, ?int $userId = null): CashierShift
    {
        if ($countedCashCents < 0) {
            throw ValidationException::withMessages([
                'counted_cash' => __('The counted cash cannot be negative.'),
            ]);
        }

        return DB::transaction(function () use ($shift, $countedCashCents, $note, $userId): CashierShift {
            /** @var CashierShift $shift */
            $shift = CashierShift::query()->lockForUpdate()->findOrFail($shift->id);

            if (! $shift->isOpen()) {
                throw ValidationException::withMessages([
                    'shift' => __('This shift is already closed.'),
                ]);
            }

            $expected = $shift->expectedCashCents();
            $difference = $countedCashCents - $expected;

            $shift->update([
                'status' => CashierShiftStatus::Closed,
                'expected_cash_cents' => $expected,
                'counted_cash_cents' => $countedCashCents,
                'difference_cents' => $difference,
                'closed_at' => now(),
                'note' => $note ?? $shift->note,
            ]);

            if ($difference !== 0) {
                $this->recordTreasuryMovement->handle(
                    direction: $difference > 0 ? TreasuryDirection::In : TreasuryDirection::Out,
                    account: TreasuryAccount::Cash,
                    amountCents: abs($difference),
                    source: $shift,
                    note: __('Cash count variance on shift #:id', ['id' => $shift->id]),
                    userId: $userId,
                );
            }

            activity()
                ->performedOn($shift)
                ->withProperties([
                    'expected_cash_cents' => $expected,
                    'counted_cash_cents' => $countedCashCents,
                    'difference_cents' => $difference,
                ])
                ->event('shift_closed')
                ->log("Cashier shift #{$shift->id} closed");

            return $shift;
        });
    }
}
