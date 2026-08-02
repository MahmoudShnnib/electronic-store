<?php

declare(strict_types=1);

namespace App\Enums;

enum PriceType: string
{
    case Regular = 'regular';
    case Sale = 'sale';
    case Cost = 'cost';

    public function label(): string
    {
        return __('enums.price_type.'.$this->value);
    }
}
