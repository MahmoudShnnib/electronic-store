<?php

declare(strict_types=1);

namespace App\Enums;

enum ReturnReason: string
{
    case Defective = 'defective';
    case WrongItem = 'wrong_item';
    case NotAsDescribed = 'not_as_described';
    case NoLongerNeeded = 'no_longer_needed';
    case DamagedInShipping = 'damaged_in_shipping';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.return_reason.'.$this->value);
    }
}
