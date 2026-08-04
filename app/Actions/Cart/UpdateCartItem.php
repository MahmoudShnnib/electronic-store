<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\CartItem;

final class UpdateCartItem
{
    public function __construct(private readonly AddToCart $addToCart) {}

    public function handle(CartItem $item, int $quantity): ?CartItem
    {
        if ($quantity <= 0) {
            $item->delete();

            return null;
        }

        $item->loadMissing(['product', 'variant']);

        $this->addToCart->assertQuantityAvailable($item->product, $item->variant, $quantity);

        $item->update(['quantity' => $quantity]);

        return $item;
    }
}
