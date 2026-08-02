<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Setting;

final class BankTransferGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'bank_transfer';
    }

    public function isManual(): bool
    {
        return true;
    }

    public function initiate(Order $order): array
    {
        $details = Setting::get('bank_transfer_details',
            'Transfer the order total to our bank account and include your order number in the reference.');

        return [
            'status' => 'pending',
            'instructions' => is_string($details) ? $details : json_encode($details),
        ];
    }
}
