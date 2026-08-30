<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Cart\AddToCart;
use App\Actions\Checkout\PlaceOrder;
use App\DTOs\CheckoutData;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
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
        private readonly PlaceOrder $placeOrder,
    ) {}

    /**
     * @param  list<array{product_id: int, product_variant_id?: ?int, quantity: int}>  $items
     */
    public function handle(array $items, CheckoutData $data, ?int $userId = null, string $placedVia = 'manual'): Order
    {
        $cart = Cart::create([]);

        try {
            foreach ($items as $item) {
                $this->addToCart->handle(
                    $cart,
                    Product::query()->findOrFail($item['product_id']),
                    isset($item['product_variant_id']) ? ProductVariant::query()->find($item['product_variant_id']) : null,
                    (int) $item['quantity'],
                );
            }

            return $this->placeOrder->handle($cart->refresh(), $data, null, $placedVia);
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
