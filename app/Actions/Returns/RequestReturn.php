<?php

declare(strict_types=1);

namespace App\Actions\Returns;

use App\Enums\ReturnStatus;
use App\Models\Order;
use App\Models\ProductReturn;
use App\Models\Setting;
use App\Models\User;
use App\Support\ReferenceNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RequestReturn
{
    /**
     * @param  list<array{order_item_id: int, quantity: int}>  $items
     * @param  list<string>  $images  stored file paths
     */
    public function handle(
        Order $order,
        User $user,
        array $items,
        string $reason,
        ?string $customerNote = null,
        array $images = [],
    ): ProductReturn {
        if ($order->user_id !== $user->id) {
            throw ValidationException::withMessages(['order' => __('This order does not belong to you.')]);
        }

        if (! $order->status->allowsReturn()) {
            throw ValidationException::withMessages(['order' => __('Only delivered orders can be returned.')]);
        }

        if (! $order->returnWindowIsOpen()) {
            throw ValidationException::withMessages([
                'order' => __('The :days-day return window for this order has closed.', [
                    'days' => (int) (Setting::get('return_window_days') ?? config('store.return_window_days')),
                ]),
            ]);
        }

        if ($items === []) {
            throw ValidationException::withMessages(['items' => __('Select at least one item to return.')]);
        }

        return DB::transaction(function () use ($order, $user, $items, $reason, $customerNote, $images): ProductReturn {
            $orderItems = $order->items()->with('returnItems.productReturn')->get();

            $return = ProductReturn::create([
                'rma_number' => ReferenceNumber::generate('RMA'),
                'order_id' => $order->id,
                'user_id' => $user->id,
                'status' => ReturnStatus::Requested,
                'reason' => $reason,
                'customer_note' => $customerNote,
                'images' => $images === [] ? null : $images,
            ]);

            foreach ($items as $item) {
                $orderItem = $orderItems->firstWhere('id', $item['order_item_id']);

                if ($orderItem === null) {
                    throw ValidationException::withMessages(['items' => __('One of the items does not belong to this order.')]);
                }

                $alreadyReturned = $orderItem->returnedQuantity();
                $returnable = $orderItem->quantity - $alreadyReturned;

                if ($item['quantity'] < 1 || $item['quantity'] > $returnable) {
                    throw ValidationException::withMessages([
                        'items' => __('You can return at most :max of ":name".', ['max' => $returnable, 'name' => $orderItem->product_name]),
                    ]);
                }

                $return->items()->create([
                    'order_item_id' => $orderItem->id,
                    'quantity' => $item['quantity'],
                ]);
            }

            return $return;
        });
    }
}
