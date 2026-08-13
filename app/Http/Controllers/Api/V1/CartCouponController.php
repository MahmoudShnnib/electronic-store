<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Cart\ApplyCoupon;
use App\Actions\Cart\CalculateCartTotals;
use App\Actions\Cart\ResolveCart;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithCart;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\ApplyCouponRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Applies a coupon code to the mobile cart, or takes it off again. */
final class CartCouponController extends Controller
{
    use RespondsWithCart;

    public function __construct(
        private readonly ResolveCart $resolveCart,
        private readonly CalculateCartTotals $calculateTotals,
    ) {}

    public function store(ApplyCouponRequest $request, ApplyCoupon $applyCoupon): JsonResponse
    {
        $cart = $this->resolveCart->handle($request);

        $applyCoupon->handle($cart, $request->validated('code'), $request->user()?->id);

        return $this->payload($request, $cart);
    }

    public function destroy(Request $request, ApplyCoupon $applyCoupon): JsonResponse
    {
        $cart = $this->resolveCart->handle($request);

        $applyCoupon->remove($cart);

        return $this->payload($request, $cart);
    }
}
