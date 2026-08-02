<?php

declare(strict_types=1);

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isReceivable(): bool
    {
        return in_array($this, [self::Sent, self::PartiallyReceived], true);
    }

    public function label(): string
    {
        return __('enums.purchase_order_status.'.$this->value);
    }
}
