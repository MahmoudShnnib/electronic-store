<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How the money physically goes back to the customer. Every case is a concrete
 * channel — there is deliberately no "same as the original payment" case, so a
 * stored refund always says what was actually handed over. Where the original
 * method is the sensible default, resolve it up front with fromPaymentMethod().
 */
enum RefundMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case StoreCredit = 'store_credit';

    /** The channel money would go back through for a given order payment. */
    public static function fromPaymentMethod(PaymentMethod $method): self
    {
        return match ($method) {
            PaymentMethod::CashOnDelivery, PaymentMethod::Cash => self::Cash,
            PaymentMethod::BankTransfer => self::BankTransfer,
        };
    }

    public function label(): string
    {
        return __('enums.refund_method.'.$this->value);
    }
}
