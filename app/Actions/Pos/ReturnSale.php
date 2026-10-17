<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Actions\Orders\ResolveOrderIssue;
use App\Enums\OrderStatus;
use App\Enums\RefundMethod;
use App\Models\CashierShift;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Takes back a counter sale at the till (owner's call, 2026-10-12: counter
 * returns happen at the register, not in the back office).
 *
 * The return itself is ResolveOrderIssue's — whole returns only, every unit
 * back on the shelf at its old cost, every dinar refunded, the treasury checked
 * — unchanged. What this adds is the drawer: a cash refund is paid out of the
 * cashier's open shift, so it comes off that drawer's expected cash and no other.
 */
final class ReturnSale
{
    public function __construct(private readonly ResolveOrderIssue $resolveOrderIssue) {}

    public function handle(CashierShift $shift, Order $order, string $reason, ?RefundMethod $method = null, ?string $reference = null, ?int $userId = null): Order
    {
        if (! $shift->isOpen()) {
            throw ValidationException::withMessages(['shift' => __('Your shift is closed. Open a new one before taking a return.')]);
        }

        if ($order->placed_via !== 'pos') {
            throw ValidationException::withMessages(['number' => __('This is not a counter sale. Return it from its order page.')]);
        }

        if ($order->status !== OrderStatus::Completed) {
            throw ValidationException::withMessages(['number' => __('This sale has already been returned or cannot be returned.')]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => __('Give a reason for the return.')]);
        }

        return DB::transaction(function () use ($shift, $order, $reason, $method, $reference, $userId): Order {
            $before = Refund::query()->where('order_id', $order->id)->max('id') ?? 0;

            $order = $this->resolveOrderIssue->handle(
                order: $order,
                reason: trim($reason),
                refundCents: $order->netPaidCents(),
                method: $method,
                restock: true,
                reference: $reference,
                userId: $userId,
            );

            // The refund this return just wrote is paid out of this till.
            Refund::query()->where('order_id', $order->id)->where('id', '>', $before)->update(['cashier_shift_id' => $shift->id]);

            return $order;
        });
    }
}
