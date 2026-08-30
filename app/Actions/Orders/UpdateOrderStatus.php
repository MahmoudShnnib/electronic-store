<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Payments\ConfirmPayment;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\StockLevel;
use App\Notifications\OrderStatusChangedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves an order through its state machine. Cancellation goes through
 * CancelOrder (stock release); completing clears the physical reservation and
 * collects whatever is still owed, banking it in the treasury.
 */
final class UpdateOrderStatus
{
    public function __construct(
        private readonly CancelOrder $cancelOrder,
        private readonly ConfirmPayment $confirmPayment,
    ) {}

    public function handle(Order $order, OrderStatus $to, ?string $comment = null, ?int $userId = null): Order
    {
        if ($to === OrderStatus::Cancelled) {
            return $this->cancelOrder->handle($order, $comment, $userId);
        }

        $order = DB::transaction(function () use ($order, $to, $comment, $userId): Order {
            /** @var Order $order */
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            $from = $order->status;

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => __('Cannot move order :number from :from to :to.', ['number' => $order->number, 'from' => $from->label(), 'to' => $to->label()]),
                ]);
            }

            if ($to === OrderStatus::Completed) {
                // Goods are with the customer: clear the reservation. Sellable
                // quantity was already deducted at checkout, so the ledger is
                // untouched — only the on-premises `reserved` counter drops.
                $this->releaseReservation($order);

                $this->settleOutstanding($order, $userId);
            }

            $order->update([
                'status' => $to,
                ...match ($to) {
                    OrderStatus::Completed => ['completed_at' => now()],
                    default => [],
                },
            ]);

            $order->statusHistories()->create([
                'from_status' => $from,
                'to_status' => $to,
                'comment' => $comment,
                'user_id' => $userId,
            ]);

            activity()
                ->performedOn($order)
                ->withProperties(['from' => $from->value, 'to' => $to->value])
                ->event('order_status_changed')
                ->log("Order {$order->number}: {$from->label()} → {$to->label()}");

            return $order;
        });

        $order->loadMissing('user')->user?->notify(new OrderStatusChangedNotification($order));

        return $order;
    }

    /** Drops the on-premises `reserved` counter for every line of the order. */
    private function releaseReservation(Order $order): void
    {
        foreach ($order->items()->get() as $item) {
            if ($item->warehouse_id === null || $item->product_id === null) {
                continue;
            }

            $stock = StockLevel::query()
                ->where('warehouse_id', $item->warehouse_id)
                ->where('product_id', $item->product_id)
                ->where('product_variant_id', $item->product_variant_id)
                ->lockForUpdate()
                ->first();

            if ($stock === null) {
                continue;
            }

            // Clamping a short reservation used to be invisible, and the
            // missing units later made cancelling the order blow up. Log
            // the shortfall so the drift is traceable.
            $release = min($item->quantity, $stock->reserved);
            $stock->update(['reserved' => $stock->reserved - $release]);

            if ($release < $item->quantity) {
                activity()
                    ->performedOn($order)
                    ->withProperties([
                        'product_id' => $item->product_id,
                        'warehouse_id' => $item->warehouse_id,
                        'expected' => $item->quantity,
                        'released' => $release,
                    ])
                    ->event('reservation_shortfall')
                    ->log("Order {$order->number}: reserved stock was short on completion");
            }
        }
    }

    /**
     * Completing an order means the goods are with the customer and the money
     * is with us: collect whatever is still owed as a payment, which in turn
     * banks it in the treasury (cash for COD, bank for transfers).
     */
    private function settleOutstanding(Order $order, ?int $userId): void
    {
        $paid = (int) $order->payments()->where('status', 'confirmed')->sum('amount_cents');
        $outstanding = $order->total_cents - $paid;

        if ($outstanding <= 0) {
            return;
        }

        $this->confirmPayment->handle($order, $outstanding, null, $userId);
    }
}
