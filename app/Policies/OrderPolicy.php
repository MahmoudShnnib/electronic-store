<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

final class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.view');
    }

    /** Staff with orders.view, or the customer who owns the order. */
    public function view(User $user, Order $order): bool
    {
        return $user->can('orders.view') || $order->user_id === $user->id;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->can('orders.manage');
    }

    /** Customers may cancel their own order while it is still cancellable. */
    public function cancel(User $user, Order $order): bool
    {
        if ($user->can('orders.manage')) {
            return true;
        }

        return $order->user_id === $user->id && $order->status->isCancellable();
    }
}
