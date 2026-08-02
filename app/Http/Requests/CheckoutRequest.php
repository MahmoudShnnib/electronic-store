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
        $address = fn (string $prefix) => [
            "{$prefix}.name" => ['required', 'string', 'max:120'],
            "{$prefix}.phone" => ['required', 'string', 'max:32'],
            "{$prefix}.country" => ['required', 'string', 'size:2'],
            "{$prefix}.city" => ['required', 'string', 'max:120'],
            "{$prefix}.area" => ['nullable', 'string', 'max:120'],
            "{$prefix}.street" => ['required', 'string', 'max:255'],
            "{$prefix}.building" => ['nullable', 'string', 'max:120'],
            "{$prefix}.notes" => ['nullable', 'string', 'max:500'],
        ];

        return [
            'customer.name' => ['required', 'string', 'max:120'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.phone' => ['required', 'string', 'max:32'],
            ...$address('shipping_address'),
            'billing_same_as_shipping' => ['boolean'],
            ...collect($address('billing_address'))
                ->map(fn (array $rules) => array_map(
                    fn ($rule) => $rule === 'required' ? 'required_if:billing_same_as_shipping,false' : $rule,
                    $rules,
                ))
                ->all(),
            'shipping_method' => ['nullable', 'string', 'max:64'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class), Rule::in(config('store.payment_methods', ['cod']))],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function toCheckoutData(): CheckoutData
    {
        $validated = $this->validated();

        $shipping = $validated['shipping_address'];
        $billing = ($validated['billing_same_as_shipping'] ?? true)
            ? $shipping
            : $validated['billing_address'];

        return new CheckoutData(
            customer: $validated['customer'],
            shippingAddress: $shipping,
            billingAddress: $billing,
            paymentMethod: PaymentMethod::from($validated['payment_method']),
            shippingMethod: $validated['shipping_method'] ?? 'standard',
            customerNote: $validated['customer_note'] ?? null,
            idempotencyKey: $validated['idempotency_key'] ?? $this->header('Idempotency-Key'),
        );
    }
}
