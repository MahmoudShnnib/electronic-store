<?php

declare(strict_types=1);

namespace App\Actions\Returns;

use App\Enums\PaymentStatus;
use App\Enums\RefundMethod;
use App\Enums\ReturnStatus;
use App\Models\Order;
use App\Models\ProductReturn;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issues a full or partial refund against an order (optionally tied to a
 * return). Order payment_status rolls up from the refunded total.
 */
final class CreateRefund
{
    public function handle(
        Order $order,
        int $amountCents,
        RefundMethod $method,
        ?ProductReturn $return = null,
        ?string $reference = null,
        ?string $reason = null,
        ?int $createdBy = null,
    ): Refund {
        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount' => __('Refund amount must be positive.')]);
        }

        return DB::transaction(function () use ($order, $amountCents, $method, $return, $reference, $reason, $createdBy): Refund {
            /** @var Order $order */
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($return !== null && $return->order_id !== $order->id) {
                throw ValidationException::withMessages(['return' => __('Return does not belong to this order.')]);
            }

            $alreadyRefunded = (int) $order->refunds()->where('status', 'completed')->sum('amount_cents');

            if ($alreadyRefunded + $amountCents > $order->total_cents) {
                throw ValidationException::withMessages([
                    'amount' => __('Refund would exceed the order total.'),
                ]);
            }

            $refund = Refund::create([
                'order_id' => $order->id,
                'return_id' => $return?->id,
                'amount_cents' => $amountCents,
                'method' => $method,
                'reference' => $reference,
                'reason' => $reason,
                'status' => 'completed',
                'created_by' => $createdBy,
            ]);

            $totalRefunded = $alreadyRefunded + $amountCents;

            $order->update([
                'payment_status' => $totalRefunded >= $order->total_cents
                    ? PaymentStatus::Refunded
                    : PaymentStatus::PartiallyRefunded,
            ]);

            if ($return !== null && $return->status === ReturnStatus::ItemReceived) {
                $return->update(['status' => ReturnStatus::Refunded]);
            }

            activity()
                ->performedOn($order)
                ->withProperties([
                    'amount_cents' => $amountCents,
                    'method' => $method->value,
                    'return' => $return?->rma_number,
                ])
                ->event('refund_created')
                ->log("Refund issued for order {$order->number}");

            return $refund;
        });
    }
}
