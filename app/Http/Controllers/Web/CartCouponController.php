<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Cart\ApplyCoupon;
use App\Actions\Cart\ResolveCart;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\ApplyCouponRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Applies a coupon code to the cart, or takes it off again. */
final class CartCouponController extends Controller
{
    public function __construct(private readonly ResolveCart $resolveCart) {}

    public function store(ApplyCouponRequest $request, ApplyCoupon $applyCoupon): RedirectResponse
    {
        $cart = $this->resolveCart->handle($request);

        $applyCoupon->handle($cart, $request->validated('code'), $request->user()?->id);

        return back()->with('success', __('Coupon applied.'));
    }

    public function destroy(Request $request, ApplyCoupon $applyCoupon): RedirectResponse
    {
        $applyCoupon->remove($this->resolveCart->handle($request));

        return back();
    }
}
