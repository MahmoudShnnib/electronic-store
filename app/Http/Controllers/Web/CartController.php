<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Cart\AddToCart;
use App\Actions\Cart\ApplyCoupon;
use App\Actions\Cart\CalculateCartTotals;
use App\Actions\Cart\ResolveCart;
use App\Actions\Cart\UpdateCartItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\ApplyCouponRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartPayload;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

final class CartController extends Controller
{
    public function __construct(
        private readonly ResolveCart $resolveCart,
        private readonly CalculateCartTotals $calculateTotals,
    ) {}

    public function show(Request $request): Response
    {
        $cart = $this->resolveCart->handle($request);

        return Inertia::render('storefront/Cart', [
            'cart' => CartPayload::from($this->calculateTotals->handle($cart, $request->user()?->id)),
        ])->toResponse($request)->withCookie($this->cookie($cart));
    }

    public function add(AddToCartRequest $request, AddToCart $addToCart): RedirectResponse
    {
        $validated = $request->validated();

        $cart = $this->resolveCart->handle($request);

        $addToCart->handle(
            $cart,
            Product::query()->findOrFail($validated['product_id']),
            isset($validated['variant_id']) ? ProductVariant::query()->find($validated['variant_id']) : null,
            (int) $validated['quantity'],
        );

        return back()->with('success', __('Added to cart.'))->withCookie($this->cookie($cart));
    }

    public function update(UpdateCartItemRequest $request, CartItem $item, UpdateCartItem $updateCartItem): RedirectResponse
    {
        $this->authorizeItem($request, $item);

        $updateCartItem->handle($item, (int) $request->validated('quantity'));

        return back();
    }

    public function remove(Request $request, CartItem $item): RedirectResponse
    {
        $this->authorizeItem($request, $item);

        $item->delete();

        return back()->with('success', __('Removed from cart.'));
    }

    public function applyCoupon(ApplyCouponRequest $request, ApplyCoupon $applyCoupon): RedirectResponse
    {
        $cart = $this->resolveCart->handle($request);

        $applyCoupon->handle($cart, $request->validated('code'), $request->user()?->id);

        return back()->with('success', __('Coupon applied.'));
    }

    public function removeCoupon(Request $request, ApplyCoupon $applyCoupon): RedirectResponse
    {
        $applyCoupon->remove($this->resolveCart->handle($request));

        return back();
    }

    /** IDOR guard: the item must belong to the requester's own cart. */
    private function authorizeItem(Request $request, CartItem $item): void
    {
        $cart = $this->resolveCart->handle($request, create: false);

        abort_if($cart === null || $item->cart_id !== $cart->id, 403);
    }

    private function cookie(Cart $cart): Cookie
    {
        return cookie(
            ResolveCart::GUEST_COOKIE,
            $cart->session_token ?? '',
            minutes: 60 * 24 * 30,
            httpOnly: true,
        );
    }
}
