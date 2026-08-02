<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\CheckoutData;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer.name' => ['required', 'string', 'max:120'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.phone' => ['required', 'string', 'max:32'],
            'shipping_address.country' => ['required', 'string', 'size:2'],
            'shipping_address.city' => [
                'required', 'string', 'max:120',
                Rule::in(array_column(config('store.cities', []), 'en')),
            ],
            'shipping_address.area' => ['nullable', 'string', 'max:120'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class), Rule::in(config('store.payment_methods', ['cod']))],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function toCheckoutData(): CheckoutData
    {
        $validated = $this->validated();

        // Contact and delivery details are one section now — the address
        // snapshot reuses the customer's name and phone.
        $shipping = [
            'name' => $validated['customer']['name'],
            'phone' => $validated['customer']['phone'],
            ...$validated['shipping_address'],
        ];

        return new CheckoutData(
            customer: $validated['customer'],
            shippingAddress: $shipping,
            billingAddress: $shipping,
            paymentMethod: PaymentMethod::from($validated['payment_method']),
            customerNote: $validated['customer_note'] ?? null,
            idempotencyKey: $validated['idempotency_key'] ?? $this->header('Idempotency-Key'),
        );
    }
}
