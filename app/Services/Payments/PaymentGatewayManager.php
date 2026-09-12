<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use InvalidArgumentException;

final class PaymentGatewayManager
{
    /** @var array<string, PaymentGateway> */
    private array $gateways = [];

    public function __construct()
    {
        $this->register(new CashOnDeliveryGateway);
        $this->register(new BankTransferGateway);
        $this->register(new CashGateway);
    }

    public function register(PaymentGateway $gateway): void
    {
        $this->gateways[$gateway->name()] = $gateway;
    }

    public function driver(string $name): PaymentGateway
    {
        return $this->gateways[$name]
            ?? throw new InvalidArgumentException("Unknown payment gateway [{$name}].");
    }

    public function driverFor(PaymentMethod $method): PaymentGateway
    {
        return $this->driver($method->value);
    }
}
