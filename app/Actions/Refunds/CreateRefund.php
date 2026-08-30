<?php

declare(strict_types=1);

namespace App\Actions\Refunds;

use App\Actions\Treasury\RecordTreasuryMovement;
use App\Enums\PaymentStatus;
use App\Enums\RefundMethod;
use App\Enums\TreasuryAccount;
use App\Enums\TreasuryDirection;
use App\Models\Order;
use App\Models\Refund;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issues a full or partial refund against an order. Order payment_status rolls
 * up from the refunded total, and the money is taken back out of the treasury
 * unless it was settled as store credit.
 */
final class CreateRefund
{
    public function __construct(private readonly RecordTreasuryMovement $recordTreasuryMovement) {}

    public function handle(
        Order $order,
        int $amountCents,
        RefundMethod $method,
        ?string $reference = null,
        ?string $reason = null,
        ?int $createdBy = null,
    ): Refund {
        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount' => __('Refund amount must be positive.')]);
        }

        return DB::transaction(function () use ($order, $amountCents, $method, $reference, $reason, $createdBy): Refund {
            /** @var Order $order */
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            $alreadyRefunded = (int) $order->refunds()->where('status', 'completed')->sum('amount_cents');

            // A refund can only give back money that was actually collected —
            // never the order total. An unpaid COD order has nothing to refund.
            $paid = (int) $order->payments()->where('status', 'confirmed')->sum('amount_cents');

            if ($paid === 0) {
                throw ValidationException::withMessages([
                    'amount' => __('No payment has been collected for this order yet.'),
                ]);
            }

            if ($alreadyRefunded + $amountCents > $paid) {
                throw ValidationException::withMessages([
                    'amount' => __('Refund would exceed the amount actually paid (:paid).', [
                        'paid' => Money::format($paid, $order->currency),
                    ]),
                ]);
            }

            $refund = Refund::create([
                'order_id' => $order->id,
                'amount_cents' => $amountCents,
                'method' => $method,
                'reference' => $reference,
                'reason' => $reason,
                'status' => 'completed',
                'created_by' => $createdBy,
            ]);

            // Store credit stays inside the shop, so only real cash/bank
            // refunds move money out of the treasury.
            $account = TreasuryAccount::fromRefundMethod($method);

            if ($account !== null) {
                $this->recordTreasuryMovement->handle(
                    direction: TreasuryDirection::Out,
                    account: $account,
                    amountCents: $amountCents,
                    source: $refund,
                    order: $order,
                    reference: $reference,
                    note: $reason ?? __('Refund for order :number', ['number' => $order->number]),
                    userId: $createdBy,
                );
            }

            $totalRefunded = $alreadyRefunded + $amountCents;

            $order->update([
                'payment_status' => $totalRefunded >= $paid
                    ? PaymentStatus::Refunded
                    : PaymentStatus::PartiallyRefunded,
            ]);

            activity()
                ->performedOn($order)
                ->withProperties([
                    'amount_cents' => $amountCents,
                    'method' => $method->value,
                ])
                ->event('refund_created')
                ->log("Refund issued for order {$order->number}");

            return $refund;
        });
    }
}
