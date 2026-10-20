<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Orders;

use App\Actions\Orders\DispatchOrder;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\Rule;

/** Hands one order to a driver, or takes it back into the shop. */
final class OrderDispatchController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:orders.manage'];
    }

    public function store(Request $request, Order $order, DispatchOrder $dispatch): RedirectResponse
    {
        $validated = $request->validate(['driver_id' => ['required', 'integer', Rule::exists('drivers', 'id')]]);

        $dispatch->handle($order, Driver::query()->findOrFail($validated['driver_id']), $request->user()->id);

        return back()->with('success', __('Order handed to the driver.'));
    }

    public function destroy(Request $request, Order $order, DispatchOrder $dispatch): RedirectResponse
    {
        $dispatch->recall($order, $request->user()->id);

        return back()->with('success', __('Order is back in the shop.'));
    }
}
