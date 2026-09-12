<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How the money reaches the store. `cod` and `bank_transfer` are the two the
 * storefront offers (gated by config('store.payment_methods')); `cash` is the
 * counter sale — money handed over at the register, never a checkout option.
 */
enum PaymentMethod: string
{
    case CashOnDelivery = 'cod';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';

    /** Whether the money lands in the cashier's drawer rather than in transit. */
    public function isOverTheCounter(): bool
    {
        return $this === self::Cash;
    }

    public function label(): string
    {
        return __('enums.payment_method.'.$this->value);
    }
}
