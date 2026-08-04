<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a (manual) payment against an order and rolls the order's
 * payment_status up from the sum of confirmed payments.
 */
final class ConfirmPayment
{
    public function __construct(private readonly PaymentGatewayManager $gateways) {}

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
