<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\CancelOrder;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Cancels an order that has not shipped yet. */
final class CancelOrderController extends Controller
{
    public function __invoke(Request $request, Order $order, CancelOrder $cancelOrder): JsonResponse
    {
        Gate::authorize('cancel', $order);

        $order = $cancelOrder->handle($order, 'Cancelled by customer.', $request->user()->id);

        return response()->json(['data' => new OrderResource($order)]);
    }
}
