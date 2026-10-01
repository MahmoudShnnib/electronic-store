<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Cart\AddToCart;
use App\Actions\Cart\ApplyCoupon;
use App\Actions\Checkout\PlaceOrder;
use App\DTOs\CheckoutData;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * An order the shop takes by hand — over WhatsApp, on the phone, at the
 * counter. It runs through a throwaway cart and the normal PlaceOrder path on
 * purpose: stock, pricing, and the treasury must behave exactly as they do for
 * an order the customer placed themselves. Nothing here reimplements checkout.
 */
final class CreateManualOrder
{
    public function __construct(
        private readonly AddToCart $addToCart,
        private readonly ApplyCoupon $applyCoupon,
        private readonly PlaceOrder $placeOrder,
    ) {}

    /**
     * @param  list<array{product_id: int, product_variant_id?: ?int, quantity: int}>  $items
     * @param  ?Warehouse  $warehouse  Forces fulfilment from one warehouse (the register).
     * @param  int  $manualDiscountCents  Order-level discount keyed in by hand.
     */
    public function handle(
        array $items,
        CheckoutData $data,
        ?int $userId = null,
        string $placedVia = 'manual',
        ?string $couponCode = null,
        ?Warehouse $warehouse = null,
        int $manualDiscountCents = 0,
    ): Order {
        $cart = Cart::create([]);

        try {
            foreach ($items as $item) {
                $this->addToCart->handle(
                    $cart,
                    Product::query()->findOrFail($item['product_id']),
                    isset($item['product_variant_id']) ? ProductVariant::query()->find($item['product_variant_id']) : null,
                    (int) $item['quantity'],
                    customerLimit: false,
                );
            }

            // After the lines exist, so the coupon's minimum-order rule is
            // checked against the real subtotal.
            if ($couponCode !== null && trim($couponCode) !== '') {
                $this->applyCoupon->handle($cart->refresh(), $couponCode, $userId);
            }

            $order = $this->placeOrder->handle($cart->refresh(), $data, null, $placedVia, $warehouse, $manualDiscountCents);

            // Keyed in by staff: the order belongs to the customer whose email it
            // carries (so it shows in their account), and the timeline credits
            // the staff member who entered it.
            $email = $data->customer['email'] ?? null;
            $customerId = filled($email) ? User::query()->where('email', $email)->value('id') : null;

            if ($customerId !== null) {
                $order->update(['user_id' => $customerId]);
            }

            $order->statusHistories()->whereNull('user_id')->update(['user_id' => $userId]);

            return $order;
        } finally {
            // The cart is scaffolding, not a record: drop it either way so a
            // failed attempt leaves nothing behind.
            DB::transaction(function () use ($cart): void {
                $cart->items()->delete();
                $cart->delete();
            });
        }
    }
}
