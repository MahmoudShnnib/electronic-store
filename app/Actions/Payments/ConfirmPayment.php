<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\Treasury\RecordTreasuryMovement;
use App\Enums\PaymentStatus;
use App\Enums\TreasuryAccount;
use App\Enums\TreasuryDirection;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a (manual) payment against an order, rolls the order's
 * payment_status up from the sum of confirmed payments, and posts the money
 * into the store treasury — cash for COD, bank for transfers.
 */
final class ConfirmPayment
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly RecordTreasuryMovement $recordTreasuryMovement,
    ) {}

    public function handle(
        Order $order,
        int $amountCents,
        ?string $reference = null,
        ?int $confirmedBy = null,
    ): Payment {
        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount' => __('Payment amount must be positive.')]);
        }

        return DB::transaction(function () use ($order, $amountCents, $reference, $confirmedBy): Payment {
            /** @var Order $order */
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            $paidSoFar = (int) $order->payments()->where('status', 'confirmed')->sum('amount_cents');

            if ($paidSoFar + $amountCents > $order->total_cents) {
                throw ValidationException::withMessages([
                    'amount' => __('Payment would exceed the order total.'),
                ]);
            }

            $payment = $order->payments()->create([
                'method' => $order->payment_method->value,
                'status' => 'confirmed',
                'amount_cents' => $amountCents,
                'reference' => $reference,
                'gateway' => $this->gateways->driverFor($order->payment_method)->name(),
                'confirmed_by' => $confirmedBy,
                'confirmed_at' => now(),
            ]);

            $this->recordTreasuryMovement->handle(
                direction: TreasuryDirection::In,
                account: TreasuryAccount::fromPaymentMethod($order->payment_method),
                amountCents: $amountCents,
                source: $payment,
                order: $order,
                reference: $reference,
                note: __('Payment for order :number', ['number' => $order->number]),
                userId: $confirmedBy,
            );

            $totalPaid = $paidSoFar + $amountCents;

            $order->update([
                'payment_status' => $totalPaid >= $order->total_cents
                    ? PaymentStatus::Paid
                    : PaymentStatus::PartiallyPaid,
            ]);

            activity()
                ->performedOn($order)
                ->withProperties(['amount_cents' => $amountCents, 'reference' => $reference])
                ->event('payment_confirmed')
                ->log("Payment recorded for order {$order->number}");

            return $payment;
        });
    }
}
