<?php

declare(strict_types=1);

namespace App\Actions\Treasury;

use App\Enums\TreasuryAccount;
use App\Enums\TreasuryDirection;
use App\Models\Order;
use App\Models\TreasuryTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * The single entry point for money moving in or out of the store treasury.
 * Confirmed payments post here automatically, refunds post the reverse, and the
 * admin can add manual deposits/withdrawals from the treasury page.
 */
final class RecordTreasuryMovement
{
    public function handle(
        TreasuryDirection $direction,
        TreasuryAccount $account,
        int $amountCents,
        ?Model $source = null,
        ?Order $order = null,
        ?string $reference = null,
        ?string $note = null,
        ?int $userId = null,
    ): ?TreasuryTransaction {
        if ($amountCents <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Treasury amount must be positive.'),
            ]);
        }

        $attributes = [
            'direction' => $direction,
            'account' => $account,
            'amount_cents' => $amountCents,
            'currency' => $order?->currency ?? (string) config('store.currency', 'LYD'),
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'order_id' => $order?->id,
            'reference' => $reference,
            'note' => $note,
            'created_by' => $userId,
        ];

        try {
            $transaction = TreasuryTransaction::create($attributes);
        } catch (UniqueConstraintViolationException) {
            // This source document was already posted — keep the ledger idempotent.
            return null;
        }

        activity()
            ->performedOn($transaction)
            ->withProperties([
                'direction' => $direction->value,
                'account' => $account->value,
                'amount_cents' => $amountCents,
            ])
            ->event('treasury_movement')
            ->log("Treasury {$direction->value}: {$amountCents} cents ({$account->value})");

        return $transaction;
    }
}
