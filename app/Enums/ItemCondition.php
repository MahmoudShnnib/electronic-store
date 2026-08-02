<?php

declare(strict_types=1);

namespace App\Enums;

enum ItemCondition: string
{
    case Resellable = 'resellable';
    case Damaged = 'damaged';

    public function label(): string
    {
        return __('enums.item_condition.'.$this->value);
    }
}
