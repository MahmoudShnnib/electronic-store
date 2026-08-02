<?php

declare(strict_types=1);

namespace App\Enums;

enum CouponType: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';

    public function label(): string
    {
        return __('enums.coupon_type.'.$this->value);
    }
}
