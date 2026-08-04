<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Product;
use App\Models\ProductVariant;

final readonly class CartLine
{
    public function __construct(
        public Product $product,
        public ?ProductVariant $variant,
        public int $quantity,
        public int $unitPriceCents,
        public int $lineSubtotalCents,
        public int $lineDiscountCents,
        public int $lineTaxCents,
        public int $lineTotalCents,
        public ?int $cartItemId = null,
    ) {}

    public function sku(): string
    {
        return $this->variant?->sku ?? $this->product->sku;
    }

    public function displayName(): string
    {
        return $this->variant !== null
            ? $this->product->name.' — '.$this->variant->name
            : $this->product->name;
    }
}
