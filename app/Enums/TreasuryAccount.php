<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where the money physically sits. Cash is the drawer; bank is the transfer
 * account. Every treasury movement belongs to exactly one of them.
 */
enum TreasuryAccount: string
{
    case Cash = 'cash';
    case Bank = 'bank';

    /** How money arrives for a given order payment method. */
    public static function fromPaymentMethod(PaymentMethod $method): self
    {
        return match ($method) {
            PaymentMethod::CashOnDelivery => self::Cash,
            PaymentMethod::BankTransfer => self::Bank,
        };
    }

    /**
     * How money leaves for a given refund method. Store credit never leaves the
     * treasury, so it has no account.
     */
    public static function fromRefundMethod(RefundMethod $method): ?self
    {
        return match ($method) {
            RefundMethod::Cash => self::Cash,
            RefundMethod::BankTransfer => self::Bank,
            RefundMethod::StoreCredit => null,
        };
    }

    public function label(): string
    {
        return __('enums.treasury_account.'.$this->value);
    }
}
