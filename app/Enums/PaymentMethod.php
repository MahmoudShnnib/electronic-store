<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case CashOnDelivery = 'cod';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return __('enums.payment_method.'.$this->value);
    }
}
