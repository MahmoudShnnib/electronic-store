<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Orders;

use App\Actions\Orders\UpdateOrderStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Orders\BulkOrdersRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Completes the orders a driver came back with, in one go. Each order goes
 * through the same action as its own "Completed" button — the outstanding cash
 * is booked to the treasury — and one that can't be completed (already done,
 * cancelled, refused by a rule) is skipped and counted, not left half-done.
 */
final class BulkCompleteOrdersController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:orders.manage'];
    }

    public function __invoke(BulkOrdersRequest $request, UpdateOrderStatus $updateOrderStatus): RedirectResponse
    {
        $completed = 0;
        $skipped = 0;

        $orders = Order::query()->whereIn('id', $request->ids())->where('placed_via', '<>', 'pos')->get();

        foreach ($orders as $order) {
            if ($order->status !== OrderStatus::InProgress) {
                $skipped++;

                continue;
            }

            try {
                $updateOrderStatus->handle($order, OrderStatus::Completed, null, $request->user()->id);
                $completed++;
            } catch (ValidationException) {
                $skipped++;
            } catch (Throwable $e) {
                report($e);
                $skipped++;
            }
        }

        $skipped += count($request->ids()) - $orders->count();

        return back()->with(
            $skipped > 0 ? 'error' : 'success',
            $skipped > 0
                ? __('Completed :done orders. :skipped could not be completed.', ['done' => $completed, 'skipped' => $skipped])
                : __('Completed :done orders.', ['done' => $completed]),
        );
    }
}
