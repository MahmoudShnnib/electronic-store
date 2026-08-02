<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\CancelOrder;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class OrderController extends Controller
{
    /**
     * The authenticated user's orders.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return OrderResource::collection(
            $request->user()->orders()
                ->withCount('items')
                ->latest()
                ->paginate(min(50, max(1, (int) $request->query('per_page', '15')))),
        );
    }

    /**
     * One order, with items and status timeline.
     */
    public function show(Request $request, Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource($order->load(['items', 'statusHistories']));
    }

    /**
     * Cancel an order that has not shipped yet.
     */
    public function cancel(Request $request, Order $order, CancelOrder $cancelOrder): JsonResponse
    {
        Gate::authorize('cancel', $order);

        $order = $cancelOrder->handle($order, 'Cancelled by customer.', $request->user()->id);

        return response()->json(['data' => new OrderResource($order)]);
    }
}
