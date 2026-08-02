<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Coupon;

final readonly class CartTotals
{
    /**
     * @param  list<CartLine>  $lines
     */
    public function __construct(
        public array $lines,
        public int $subtotalCents,
        public int $discountCents,
        public int $taxCents,
        public int $shippingCents,
        public int $totalCents,
        public ?Coupon $coupon = null,
        public ?string $couponError = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function itemCount(): int
    {
        return array_sum(array_map(fn (CartLine $line) => $line->quantity, $this->lines));
    }
}
