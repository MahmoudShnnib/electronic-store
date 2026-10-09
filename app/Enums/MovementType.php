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
    /** Stolen or missing: gone for good, unlike damaged units that are still on premises. */
    case Lost = 'lost';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Correction = 'correction';
    case PurchaseIncoming = 'purchase_incoming';
    case PurchaseCancelled = 'purchase_cancelled';

    /**
     * Whether an admin-entered quantity for this type must be applied as a
     * decrease. Prevents "decrease by 5" typed as a positive number from
     * silently adding stock.
     */
    public function isDecrease(): bool
    {
        return in_array($this, [self::ManualDecrease, self::Damaged, self::Lost, self::SupplierReturn], true);
    }

    public function label(): string
    {
        return __('enums.movement_type.'.$this->value);
    }
}
