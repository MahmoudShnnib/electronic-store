<?php

declare(strict_types=1);

namespace App\Actions\Refunds;

use App\Actions\Orders\UpdateOrderStatus;
use App\Actions\Treasury\RecordTreasuryMovement;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundMethod;
use App\Enums\TreasuryAccount;
use App\Enums\TreasuryDirection;
use App\Models\Order;
use App\Models\Refund;
use App\Services\Admin\TreasuryQueryService;
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
    public function __construct(
        private readonly RecordTreasuryMovement $recordTreasuryMovement,
        private readonly TreasuryQueryService $treasury,
        private readonly UpdateOrderStatus $updateOrderStatus,
    ) {}

    public function handle(
        Order $order,
        int $amountCents,
        RefundMethod $method,
        ?string $reference = null,
        ?string $reason = null,
        ?int $createdBy = null,
        bool $goodsReturned = false,
    ): Refund {
        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount' => __('Refund amount must be positive.')]);
        }

        // There is no customer credit balance to put it in, so the money would vanish.
        if ($method === RefundMethod::StoreCredit) {
            throw ValidationException::withMessages(['method' => __('Store credit is not available. Refund in cash or by bank transfer.')]);
        }

        return DB::transaction(function () use ($order, $amountCents, $method, $reference, $reason, $createdBy, $goodsReturned): Refund {
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
            // No part refunds (owner decision 2026-10-10): whatever is still held goes back at once.
            if ($alreadyRefunded + $amountCents !== $paid) {
                throw ValidationException::withMessages([
                    'amount' => __('Refund the full amount held (:held). Part refunds are not accepted.', [
                        'held' => Money::format($paid - $alreadyRefunded, $order->currency),
                    ]),
                ]);
            }
            // A delivered order is refunded only together with its goods (the return form).
            if ($order->status === OrderStatus::Completed && ! $goodsReturned) {
                throw ValidationException::withMessages([
                    'amount' => __('Order :number was delivered: use the return form, which takes every item back with the refund.', ['number' => $order->number]),
                ]);
            }

            // Same rule as a manual withdrawal: money can't leave an account
            // that doesn't hold it, or the treasury goes negative unnoticed.
            // ponytail: unlocked read, two refunds racing for the last dinar can both pass; add an advisory lock if that ever matters.
            $account = TreasuryAccount::fromRefundMethod($method);
            $balance = $account !== null ? $this->treasury->balances()[$account->value] : null;

            if ($balance !== null && $amountCents > $balance) {
                throw ValidationException::withMessages([
                    'amount' => __('The :account account only holds :balance, not enough for this refund.', [
                        'account' => $account->label(),
                        'balance' => Money::format($balance, $order->currency),
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

            // Money going back on a finished sale makes it a refunded order, whichever
            // screen issued it — otherwise it reads "completed" and reports count it in full.
            if ($order->status === OrderStatus::Completed) {
                $this->updateOrderStatus->handle($order, OrderStatus::Refunded, $reason, $createdBy);
            }

            return $refund;
        });
    }
}
