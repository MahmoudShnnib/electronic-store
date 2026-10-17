<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A line in a supplier's account. Purchases are written when goods are received
 * against a purchase order; payments when the shop pays from cash or bank;
 * adjustments are debts or credits written in by hand (an opening balance, a
 * credit note for goods sent back).
 */
enum SupplierTransactionType: string
{
    case Purchase = 'purchase';
    case Payment = 'payment';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return __('enums.supplier_transaction_type.'.$this->value);
    }
}
