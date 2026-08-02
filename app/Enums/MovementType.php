<?php

declare(strict_types=1);

namespace App\Enums;

enum MovementType: string
{
    case Initial = 'initial';
    case PurchaseReceipt = 'purchase_receipt';
    case CustomerOrder = 'customer_order';
    case OrderCancellation = 'order_cancellation';
    case CustomerReturn = 'customer_return';
    case SupplierReturn = 'supplier_return';
    case ManualIncrease = 'manual_increase';
    case ManualDecrease = 'manual_decrease';
    case Damaged = 'damaged';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Correction = 'correction';

    public function label(): string
    {
        return __('enums.movement_type.'.$this->value);
    }
}
