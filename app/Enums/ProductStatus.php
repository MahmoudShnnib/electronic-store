<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';
    case OutOfStock = 'out_of_stock';
    case Discontinued = 'discontinued';

    public function isVisibleInStore(): bool
    {
        return in_array($this, [self::Active, self::OutOfStock], true);
    }

    public function isPurchasable(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return __('enums.product_status.'.$this->value);
    }
}
