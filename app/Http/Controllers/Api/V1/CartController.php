<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile cart endpoints. Guests identify their cart with the X-Cart-Token
 * header returned on first use; authenticated requests use the user's cart.
 */
final class CartController extends Controller
{
    public function __construct(
        private readonly ResolveCart $resolveCart,
        private readonly CalculateCartTotals $calculateTotals,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->resolveCart->handle($request);

        return $this->payload($request, $cart);
    }

    public function addItem(AddToCartRequest $request, AddToCart $addToCart): JsonResponse
    {
        $validated = $request->validated();

        $cart = $this->resolveCart->handle($request);

        $addToCart->handle(
            $cart,
            Product::query()->findOrFail($validated['product_id']),
            isset($validated['variant_id']) ? ProductVariant::query()->find($validated['variant_id']) : null,
            (int) $validated['quantity'],
        );

        return $this->payload($request, $cart, 201);
    }

    public function updateItem(UpdateCartItemRequest $request, CartItem $item, UpdateCartItem $updateCartItem): JsonResponse
    {
        $cart = $this->authorizedCart($request, $item);

        $updateCartItem->handle($item, (int) $request->validated('quantity'));

        return $this->payload($request, $cart);
    }

    public function removeItem(Request $request, CartItem $item): JsonResponse
    {
        $cart = $this->authorizedCart($request, $item);

        $item->delete();

        return $this->payload($request, $cart);
    }

    public function applyCoupon(ApplyCouponRequest $request, ApplyCoupon $applyCoupon): JsonResponse
    {
        $cart = $this->resolveCart->handle($request);

        $applyCoupon->handle($cart, $request->validated('code'), $request->user()?->id);

        return $this->payload($request, $cart);
    }

    public function removeCoupon(Request $request, ApplyCoupon $applyCoupon): JsonResponse
    {
        $cart = $this->resolveCart->handle($request);

        $applyCoupon->remove($cart);

        return $this->payload($request, $cart);
    }

    private function authorizedCart(Request $request, CartItem $item): Cart
    {
        $cart = $this->resolveCart->handle($request, create: false);

        abort_if($cart === null || $item->cart_id !== $cart->id, 403, 'This item does not belong to your cart.');

        return $cart;
    }

    private function payload(Request $request, Cart $cart, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => CartPayload::from($this->calculateTotals->handle($cart->refresh(), $request->user()?->id)),
            'cart_token' => $cart->session_token,
        ], $status);
    }
}
