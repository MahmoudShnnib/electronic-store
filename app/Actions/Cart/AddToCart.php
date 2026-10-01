<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

final class AddToCart
{
    /** @param  bool  $customerLimit  the per-item cap guards the shop; staff ringing up a sale skip it */
    public function handle(Cart $cart, Product $product, ?ProductVariant $variant, int $quantity, bool $customerLimit = true): CartItem
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => __('Quantity must be at least 1.')]);
        }

        if (! $product->status->isPurchasable()) {
            throw ValidationException::withMessages(['product' => __('This product is not available for purchase.')]);
        }

        if ($product->has_variants && $variant === null) {
            throw ValidationException::withMessages(['variant' => __('Please choose a variant.')]);
        }

        if ($variant !== null && ($variant->product_id !== $product->id || ! $variant->is_active)) {
            throw ValidationException::withMessages(['variant' => __('This variant is not available.')]);
        }

        $existing = $cart->items()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->first();

        $newQuantity = ($existing?->quantity ?? 0) + $quantity;

        $this->assertQuantityAvailable($product, $variant, $newQuantity, $customerLimit);

        if ($existing !== null) {
            $existing->update(['quantity' => $newQuantity]);

            return $existing;
        }

        return $cart->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'quantity' => $quantity,
        ]);
    }

    /**
     * Soft availability check for cart UX. The authoritative check happens
     * under row locks inside PlaceOrder.
     */
    public function assertQuantityAvailable(Product $product, ?ProductVariant $variant, int $quantity, bool $customerLimit = true): void
    {
        $max = (int) config('store.max_quantity_per_item');

        if ($customerLimit && $quantity > $max) {
            throw ValidationException::withMessages(['quantity' => __('You can order at most :max of one item.', ['max' => $max])]);
        }

        $available = (int) ($variant?->stockLevels() ?? $product->stockLevels()->whereNull('product_variant_id'))
            ->sellable()
            ->max('quantity');

        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'quantity' => $available > 0
                    ? __('Only :available left in stock.', ['available' => $available])
                    : __('This item is out of stock.'),
            ]);
        }
    }
}
