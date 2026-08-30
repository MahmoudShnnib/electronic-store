<?php

declare(strict_types=1);

namespace App\Enums;

enum TreasuryDirection: string
{
    case In = 'in';
    case Out = 'out';

    /** +1 for money coming in, -1 for money going out — used to sum balances. */
    public function sign(): int
    {
        return $this === self::In ? 1 : -1;
    }

    public function label(): string
    {
        return __('enums.treasury_direction.'.$this->value);
    }
}
