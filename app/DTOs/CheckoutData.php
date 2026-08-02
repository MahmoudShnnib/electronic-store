<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\PaymentMethod;

final readonly class CheckoutData
{
    /**
     * @param  array{name: string, email: string, phone: string}  $customer
     * @param  array<string, mixed>  $shippingAddress
     * @param  array<string, mixed>  $billingAddress
     */
    public function __construct(
        public array $customer,
        public array $shippingAddress,
        public array $billingAddress,
        public PaymentMethod $paymentMethod,
        public ?string $customerNote = null,
        public ?string $idempotencyKey = null,
    ) {
    }
}
