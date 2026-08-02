<?php

declare(strict_types=1);

namespace App\Enums;

enum CouponType: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';
    case FreeShipping = 'free_shipping';

    public function label(): string
    {
        return __('enums.coupon_type.'.$this->value);
    }
}
