<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Actions\Cart\CalculateCartTotals;
use App\Http\Resources\CartPayload;
use App\Models\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared response shape for every cart endpoint: the priced cart plus the
 * cart_token a guest needs to send back as X-Cart-Token.
 *
 * Consumers must inject CalculateCartTotals as $this->calculateTotals.
 */
trait RespondsWithCart
{
    private function payload(Request $request, Cart $cart, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => CartPayload::from($this->calculateTotals->handle($cart->refresh(), $request->user()?->id)),
            'cart_token' => $cart->session_token,
        ], $status);
    }
}
