<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A cashier shift is open while the drawer is in use and closed once it has
 * been counted. There is no third state: an uncounted shift stays open.
 */
enum CashierShiftStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return __('enums.cashier_shift_status.'.$this->value);
    }
}
