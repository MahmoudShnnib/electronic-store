<?php

declare(strict_types=1);

namespace App\Enums;

enum StockCountStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('enums.stock_count_status.'.$this->value);
    }
}
