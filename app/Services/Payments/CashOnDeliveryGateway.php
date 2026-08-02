<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;

final class CashOnDeliveryGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'cod';
    }

    public function isManual(): bool
    {
        return true;
    }

    public function initiate(Order $order): array
    {
        return [
            'status' => 'pending',
            'instructions' => 'Pay the courier in cash when your order is delivered.',
        ];
    }
}
