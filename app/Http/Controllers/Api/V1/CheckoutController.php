<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Cart\ResolveCart;
use App\Actions\Checkout\PlaceOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\V1\OrderResource;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;

final class CheckoutController extends Controller
{
    /**
     * Place an order from the current cart.
     *
     * Send an Idempotency-Key header (or idempotency_key field) — replaying
     * the same key returns the original order instead of double-charging.
     */
    public function store(
        CheckoutRequest $request,
        ResolveCart $resolveCart,
        PlaceOrder $placeOrder,
        PaymentGatewayManager $gateways,
    ): JsonResponse {
        $cart = $resolveCart->handle($request);

        $order = $placeOrder->handle($cart, $request->toCheckoutData(), $request->user(), 'api');

        $instructions = $gateways->driverFor($order->payment_method)->initiate($order);

        return response()->json([
            'data' => new OrderResource($order->load('items')),
            'payment' => $instructions,
        ], 201);
    }
}
