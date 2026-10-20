<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Orders;

use App\Actions\Orders\DispatchOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Orders\BulkOrdersRequest;
use App\Models\Driver;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\ValidationException;

/** Loads a driver's run: every picked order that can go out is handed to them; the rest are counted, not half-done. */
final class BulkDispatchOrdersController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:orders.manage'];
    }

    public function __invoke(BulkOrdersRequest $request, DispatchOrder $dispatch): RedirectResponse
    {
        $driver = Driver::query()->findOrFail((int) $request->validate(['driver_id' => ['required', 'integer']])['driver_id']);
        $handed = 0;

        foreach (Order::query()->whereIn('id', $request->ids())->get() as $order) {
            try {
                $dispatch->handle($order, $driver, $request->user()->id);
                $handed++;
            } catch (ValidationException) {
                // Not in progress, a counter sale, or an inactive driver: skipped.
            }
        }

        $skipped = count($request->ids()) - $handed;

        return back()->with(
            $skipped > 0 ? 'error' : 'success',
            __('Handed :count orders to :name.', ['count' => $handed, 'name' => $driver->name])
                .($skipped > 0 ? ' '.__(':skipped could not go out (not in progress).', ['skipped' => $skipped]) : ''),
        );
    }
}
