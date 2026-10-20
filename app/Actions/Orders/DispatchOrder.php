<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Driver;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hands an order to one of the shop's drivers, or takes it back.
 *
 * Not a status of its own (the pipeline was collapsed to four on purpose):
 * the order stays in progress, `driver_id` + `shipped_at` record who has it
 * since when. From then on the customer can no longer cancel it, and it shows
 * on that driver's manifest until it is completed or comes back.
 */
final class DispatchOrder
{
    public function handle(Order $order, Driver $driver, ?int $userId = null): Order
    {
        return DB::transaction(function () use ($order, $driver, $userId): Order {
            /** @var Order $order */
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== OrderStatus::InProgress || $order->placed_via === 'pos') {
                throw ValidationException::withMessages(['driver_id' => __('Only orders in progress can go out with a driver.')]);
            }

            if (! $driver->is_active) {
                throw ValidationException::withMessages(['driver_id' => __('This driver is no longer active.')]);
            }

            $order->update(['driver_id' => $driver->id, 'shipped_at' => $order->shipped_at ?? now()]);
            $this->log($order, __('Handed to driver :name.', ['name' => $driver->name]), $userId);

            return $order;
        });
    }

    /** The parcel came back undelivered (or went to the wrong driver): it is in the shop again. */
    public function recall(Order $order, ?int $userId = null): Order
    {
        return DB::transaction(function () use ($order, $userId): Order {
            /** @var Order $order */
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $order->isWithDriver()) {
                return $order;
            }

            $name = $order->driver?->name ?? '—';
            $order->update(['driver_id' => null, 'shipped_at' => null]);
            $this->log($order, __('Back from driver :name.', ['name' => $name]), $userId);

            return $order;
        });
    }

    private function log(Order $order, string $comment, ?int $userId): void
    {
        $order->statusHistories()->create([
            'from_status' => null,
            'to_status' => $order->status->value,
            'comment' => $comment,
            'user_id' => $userId,
        ]);
    }
}
