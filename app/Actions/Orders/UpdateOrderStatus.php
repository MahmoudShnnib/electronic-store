<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Inventory\RecordMovement;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Notifications\OrderStatusChangedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves an order through its state machine. Cancellation goes through
 * CancelOrder (stock release); shipping clears the physical reservation.
 */
final class UpdateOrderStatus
{
    public function __construct(
        private readonly RecordMovement $recordMovement,
        private readonly CancelOrder $cancelOrder,
    ) {
    }

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

            if ($to === OrderStatus::Shipped) {
                // Goods leave the building: clear the reservation. Sellable
                // quantity was already deducted at checkout, so the ledger is
                // untouched — only the on-premises `reserved` counter drops.
                foreach ($order->items()->get() as $item) {
                    if ($item->warehouse_id === null || $item->product_id === null) {
                        continue;
                    }

                    $stock = \App\Models\StockLevel::query()
                        ->where('warehouse_id', $item->warehouse_id)
                        ->where('product_id', $item->product_id)
                        ->where('product_variant_id', $item->product_variant_id)
                        ->lockForUpdate()
                        ->first();

                    $stock?->update(['reserved' => max(0, $stock->reserved - $item->quantity)]);
                }
            }

            $order->update([
                'status' => $to,
                ...match ($to) {
                    OrderStatus::Confirmed => ['confirmed_at' => now()],
                    OrderStatus::Shipped => ['shipped_at' => now()],
                    OrderStatus::Delivered => ['delivered_at' => now()],
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
}
