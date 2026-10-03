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
        ?array $restockQuantities = null,
    ): Order {
        // No method given means "give it back the way it came in" — resolved to
        // a concrete channel here so the refund record never stores a
        // placeholder the reader has to look up the order to understand.
        $method ??= RefundMethod::fromPaymentMethod($order->payment_method);

        // One transaction around everything: a refund that fails validation must
        // not leave the goods restocked, and the order lock makes a double-click
        // wait for the first submit instead of running alongside it.
        return DB::transaction(function () use ($order, $reason, $refundCents, $method, $restock, $reference, $userId, $restockQuantities): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

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
                $this->restock($order, $reason, $userId, $restockQuantities);
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
            }

            // CreateRefund moves the order to refunded itself.
            return $order->refresh();
        });
    }

    /**
     * Puts the returned goods back on the shelf they shipped from — only the
     * units staff say came back (all by default), and never more than are still
     * out, so a partial return or a second report can't create phantom stock.
     *
     * @param  array<int, int>|null  $quantities  order_item_id => units back; null = everything still out
     */
    private function restock(Order $order, string $reason, ?int $userId, ?array $quantities): void
    {
        $left = $order->returnableQuantities();

        foreach ($order->items()->with(['product', 'variant', 'warehouse'])->get() as $item) {
            if ($item->product === null || $item->warehouse === null) {
                continue;
            }

            $quantity = min($left[$item->id] ?? 0, $quantities === null ? PHP_INT_MAX : max(0, (int) ($quantities[$item->id] ?? 0)));

            if ($quantity <= 0) {
                continue;
            }

            $this->recordMovement->handle(
                type: MovementType::CustomerReturn,
                warehouse: $item->warehouse,
                product: $item->product,
                variant: $item->variant,
                quantityChange: $quantity,
                reservedChange: 0,
                reference: $order,
                reason: $reason,
                userId: $userId,
            );
        }
    }
}
