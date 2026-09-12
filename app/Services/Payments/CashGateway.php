<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;

/**
 * Cash handed over at the register. Unlike COD there is no courier and no
 * waiting — the sale is settled the moment it is rung up.
 */
final class CashGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'cash';
    }

    public function isManual(): bool
    {
        return true;
    }

    public function initiate(Order $order): array
    {
        return [
            'status' => 'paid',
            'instructions' => 'Paid in cash at the register.',
        ];
    }
}
