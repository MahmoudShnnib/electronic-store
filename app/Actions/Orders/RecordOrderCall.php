<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderCallAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Logs a call made to confirm a cash-on-delivery order. "Confirmed" stamps
 * the order (confirmed_at), which also exempts it from the automatic
 * cancellation of unconfirmed orders; "no answer" is kept so the next person
 * calling knows how many tries there have been.
 */
final class RecordOrderCall
{
    public function handle(Order $order, string $outcome, ?string $note, ?int $userId): OrderCallAttempt
    {
        if ($order->status !== OrderStatus::InProgress) {
            throw ValidationException::withMessages(['outcome' => __('Only orders in progress can be confirmed.')]);
        }

        return DB::transaction(function () use ($order, $outcome, $note, $userId): OrderCallAttempt {
            $attempt = $order->callAttempts()->create(['outcome' => $outcome, 'note' => $note, 'user_id' => $userId]);

            if ($outcome === OrderCallAttempt::CONFIRMED && $order->confirmed_at === null) {
                $order->update(['confirmed_at' => now()]);
                $order->statusHistories()->create([
                    'from_status' => null,
                    'to_status' => $order->status->value,
                    'comment' => __('Confirmed with the customer by phone.'),
                    'user_id' => $userId,
                ]);
            }

            return $attempt;
        });
    }
}
