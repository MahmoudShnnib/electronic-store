<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Inventory\RecordMovement;
use App\Actions\Refunds\CreateRefund;
use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Enums\RefundMethod;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one action behind the "return / product problem" button on a completed
 * order. It always records the reason on the timeline; the amount decides how
 * far it goes: zero just logs the problem and leaves the order completed,
 * anything more refunds the customer, takes the money back out of the treasury
 * and moves the order to refunded.
 */
final class ResolveOrderIssue
{
    public function __construct(
        private readonly UpdateOrderStatus $updateOrderStatus,
        private readonly CreateRefund $createRefund,
        private readonly RecordMovement $recordMovement,
    ) {}

    public function handle(
        Order $order,
        string $reason,
        int $refundCents = 0,
        ?RefundMethod $method = null,
        bool $restock = true,
        ?string $reference = null,
        ?int $userId = null,
    ): Order {
        // No method given means "give it back the way it came in" — resolved to
        // a concrete channel here so the refund record never stores a
        // placeholder the reader has to look up the order to understand.
        $method ??= RefundMethod::fromPaymentMethod($order->payment_method);

        if ($order->status !== OrderStatus::Completed) {
            throw ValidationException::withMessages([
                'reason' => __('Only a completed order can be returned (status: :status).', [
                    'status' => $order->status->label(),
                ]),
            ]);
        }

        // The reason is the point of the action, so it is logged even when no
        // money moves — a problem worth recording is not always worth a refund.
        $order->statusHistories()->create([
            'from_status' => OrderStatus::Completed,
            'to_status' => OrderStatus::Completed,
            'comment' => $reason,
            'user_id' => $userId,
        ]);

        if ($restock) {
            $this->restock($order, $reason, $userId);
        }

        if ($refundCents > 0) {
            $this->createRefund->handle(
                order: $order,
                amountCents: $refundCents,
                method: $method,
                reference: $reference,
                reason: $reason,
                createdBy: $userId,
            );

            $order = $this->updateOrderStatus->handle($order->refresh(), OrderStatus::Refunded, $reason, $userId);
        }

        return $order;
    }

    /** Puts the returned goods back on the shelf they shipped from. */
    private function restock(Order $order, string $reason, ?int $userId): void
    {
        DB::transaction(function () use ($order, $reason, $userId): void {
            foreach ($order->items()->with(['product', 'variant', 'warehouse'])->get() as $item) {
                if ($item->product === null || $item->warehouse === null) {
                    continue;
                }

                $this->recordMovement->handle(
                    type: MovementType::CustomerReturn,
                    warehouse: $item->warehouse,
                    product: $item->product,
                    variant: $item->variant,
                    quantityChange: $item->quantity,
                    reservedChange: 0,
                    reference: $order,
                    reason: $reason,
                    userId: $userId,
                );
            }
        });
    }
}
