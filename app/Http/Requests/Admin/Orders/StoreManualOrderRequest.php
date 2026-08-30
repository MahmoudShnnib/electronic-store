<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Orders;

use App\DTOs\CheckoutData;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Deliberately the same shape as the storefront's CheckoutRequest, plus the
 * lines the admin picked — the shop takes the same details over WhatsApp that
 * the checkout form asks for, no more.
 */
final class StoreManualOrderRequest extends FormRequest
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
            'shipping_address.city' => [
                'required', 'string', 'max:120',
                Rule::in(array_column(config('store.cities', []), 'en')),
            ],
            'shipping_address.area' => ['required', 'string', 'max:120'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'source' => ['nullable', 'string', 'max:32'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toCheckoutData(): CheckoutData
    {
        $validated = $this->validated();

        $shipping = [
            'name' => $validated['customer']['name'],
            'phone' => $validated['customer']['phone'],
            'country' => 'LY',
            ...$validated['shipping_address'],
        ];

        return new CheckoutData(
            customer: $validated['customer'],
            shippingAddress: $shipping,
            billingAddress: $shipping,
            paymentMethod: PaymentMethod::from($validated['payment_method']),
            customerNote: $validated['customer_note'] ?? null,
        );
    }
}
