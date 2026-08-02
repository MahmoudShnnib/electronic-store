<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Inventory\RecordMovement;
use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Notifications\OrderStatusChangedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancels an order that has not shipped: releases the reservation, restores
 * sellable stock, and writes ledger + history entries.
 */
final class CancelOrder
{
    public function __construct(private readonly RecordMovement $recordMovement)
    {
    }

    public function handle(Order $order, ?string $reason = null, ?int $userId = null): Order
    {
        $order = DB::transaction(function () use ($order, $reason, $userId): Order {
            /** @var Order $order */
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $order->status->isCancellable()) {
                throw ValidationException::withMessages([
                    'order' => __('Order :number can no longer be cancelled (status: :status).', ['number' => $order->number, 'status' => $order->status->label()]),
                ]);
            }

            $fromStatus = $order->status;

            foreach ($order->items()->with(['product', 'variant', 'warehouse'])->get() as $item) {
                if ($item->product === null || $item->warehouse === null) {
                    continue;
                }

                $this->recordMovement->handle(
                    type: MovementType::OrderCancellation,
                    warehouse: $item->warehouse,
                    product: $item->product,
                    variant: $item->variant,
                    quantityChange: $item->quantity,
                    reservedChange: -$item->quantity,
                    reference: $order,
                    reason: $reason,
                    userId: $userId,
                );
            }

            $order->update([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            $order->statusHistories()->create([
                'from_status' => $fromStatus,
                'to_status' => OrderStatus::Cancelled,
                'comment' => $reason ?? 'Order cancelled.',
                'user_id' => $userId,
            ]);

            activity()
                ->performedOn($order)
                ->withProperties(['reason' => $reason, 'from' => $fromStatus->value])
                ->event('order_cancelled')
                ->log("Order {$order->number} cancelled");

            return $order;
        });

        $order->loadMissing('user')->user?->notify(new OrderStatusChangedNotification($order));

        return $order;
    }
}
