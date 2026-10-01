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

    /** Libyan mobile: 09XXXXXXXX or +2189XXXXXXXX, separators ignored. */
    private const PHONE_PATTERN = '/^(?:\+?218|0)9\d{8}$/';

    /**
     * Strip the separators people type and turn Arabic-Indic (٠-٩) and Persian
     * (۰-۹) digits from an Arabic keyboard into 0-9, so the pattern only sees ASCII digits.
     */
    protected function prepareForValidation(): void
    {
        $phone = $this->input('customer.phone');

        if (is_string($phone)) {
            $digits = array_combine(
                [...mb_str_split('٠١٢٣٤٥٦٧٨٩'), ...mb_str_split('۰۱۲۳۴۵۶۷۸۹')],
                [...range('0', '9'), ...range('0', '9')],
            );
            $phone = strtr(preg_replace('/[\s\-().]/u', '', $phone), array_map('strval', $digits));

            $this->merge([
                'customer' => [...(array) $this->input('customer'), 'phone' => $phone],
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'customer.name' => ['required', 'string', 'max:120'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.phone' => ['required', 'string', 'max:32', 'regex:'.self::PHONE_PATTERN],
            'shipping_address.city' => [
                'required', 'string', 'max:120',
                Rule::in(array_column(config('store.cities', []), 'en')),
            ],
            'shipping_address.area' => ['required', 'string', 'max:120'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class), Rule::in(config('store.payment_methods', ['cod']))],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return ['customer.phone.regex' => __('validation.phone_ly')];
    }

    public function toCheckoutData(): CheckoutData
    {
        $validated = $this->validated();

        // The store ships to one country, so it is configuration, not input.
        $validated['shipping_address']['country'] = config('store.country', 'LY');

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
