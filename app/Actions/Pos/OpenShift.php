<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Enums\CashierShiftStatus;
use App\Models\CashierShift;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Starts a cashier's session at a register. The opening float is whatever cash
 * is already in the drawer — it is not treasury income, it is money that was
 * already counted, so nothing is posted to the ledger here.
 */
final class OpenShift
{
    public function handle(User $cashier, Warehouse $warehouse, int $openingFloatCents = 0, ?string $note = null): CashierShift
    {
        if ($openingFloatCents < 0) {
            throw ValidationException::withMessages([
                'opening_float' => __('The opening float cannot be negative.'),
            ]);
        }

        if (! $warehouse->is_active) {
            throw ValidationException::withMessages([
                'warehouse_id' => __('Warehouse :name is not active.', ['name' => $warehouse->name]),
            ]);
        }

        return DB::transaction(function () use ($cashier, $warehouse, $openingFloatCents, $note): CashierShift {
            // Locked, not just checked: two tabs hitting "open" together would
            // otherwise both pass the check and leave the cashier with two
            // drawers, and every later sale would land in an arbitrary one.
            User::query()->whereKey($cashier->id)->lockForUpdate()->first();

            $existing = CashierShift::query()
                ->where('user_id', $cashier->id)
                ->where('status', CashierShiftStatus::Open)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'shift' => __('You already have an open shift. Close it before opening another.'),
                ]);
            }

            $shift = CashierShift::create([
                'user_id' => $cashier->id,
                'warehouse_id' => $warehouse->id,
                'status' => CashierShiftStatus::Open,
                'opening_float_cents' => $openingFloatCents,
                'opened_at' => now(),
                'note' => $note,
            ]);

            activity()
                ->performedOn($shift)
                ->withProperties([
                    'warehouse_id' => $warehouse->id,
                    'opening_float_cents' => $openingFloatCents,
                ])
                ->event('shift_opened')
                ->log("Cashier shift #{$shift->id} opened by {$cashier->name}");

            return $shift;
        });
    }

    /** The cashier's drawer, or null when they have not opened one. */
    public function current(User $cashier): ?CashierShift
    {
        return CashierShift::query()
            ->with('warehouse')
            ->where('user_id', $cashier->id)
            ->where('status', CashierShiftStatus::Open)
            ->latest('opened_at')
            ->first();
    }
}
