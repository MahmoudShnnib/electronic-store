<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;

/**
 * Contract every payment method implements. Online gateways (Stripe, Tap,
 * HyperPay, …) plug in by implementing this and registering in
 * PaymentGatewayManager — checkout code never changes.
 */
interface PaymentGateway
{
    public function name(): string;

    /** Whether the money is collected outside the app (COD, bank transfer). */
    public function isManual(): bool;

    /**
     * Begin collecting payment for the order. Manual gateways return
     * instructions; online gateways would return a redirect/client secret.
     *
     * @return array{status: string, instructions?: string, redirect_url?: string}
     */
    public function initiate(Order $order): array;
}
