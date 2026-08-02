<?php

declare(strict_types=1);

namespace App\Enums;

enum TransferStatus: string
{
    case Pending = 'pending';
    case InTransit = 'in_transit';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('enums.transfer_status.'.$this->value);
    }
}
