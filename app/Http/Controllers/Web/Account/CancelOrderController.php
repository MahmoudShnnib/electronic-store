<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Account;

use App\Actions\Orders\CancelOrder;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Lets a customer cancel their own order while it is still cancellable. */
final class CancelOrderController extends Controller
{
    public function __invoke(Request $request, Order $order, CancelOrder $cancelOrder): RedirectResponse
    {
        Gate::authorize('cancel', $order);

        $cancelOrder->handle($order, 'Cancelled by customer.', $request->user()->id);

        return back()->with('success', __('Your order has been cancelled.'));
    }
}
