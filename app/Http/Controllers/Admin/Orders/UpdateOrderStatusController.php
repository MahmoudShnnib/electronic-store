<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Orders;

use App\Actions\Orders\UpdateOrderStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Orders\UpdateOrderStatusRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/** Moves an order to its next status, recording the transition in the timeline. */
final class UpdateOrderStatusController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:orders.manage'];
    }

    public function __invoke(UpdateOrderStatusRequest $request, Order $order, UpdateOrderStatus $updateOrderStatus): RedirectResponse
    {
        $validated = $request->validated();

        $updateOrderStatus->handle(
            $order,
            OrderStatus::from($validated['status']),
            $validated['comment'] ?? null,
            $request->user()->id,
        );

        return back()->with('success', 'Order status updated.');
    }
}
