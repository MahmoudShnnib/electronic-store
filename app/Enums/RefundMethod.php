<?php

declare(strict_types=1);

namespace App\Enums;

enum RefundMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case StoreCredit = 'store_credit';
    case OriginalMethod = 'original';

    public function label(): string
    {
        return __('enums.refund_method.'.$this->value);
    }
}
