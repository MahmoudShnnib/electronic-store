<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Product;
use App\Models\ProductVariant;
use RuntimeException;

final class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly Product $product,
        public readonly ?ProductVariant $variant,
        public readonly int $requested,
        public readonly int $available,
    ) {
        $name = $variant !== null ? $product->name.' — '.$variant->name : $product->name;

        parent::__construct(__('Insufficient stock for ":name": requested :requested, available :available.', [
            'name' => $name,
            'requested' => $requested,
            'available' => $available,
        ]));
    }
}
