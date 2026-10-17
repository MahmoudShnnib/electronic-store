<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Pos;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A counter sale asks for far less than checkout does: there is no address to
 * collect and no account to match. Customer details are optional throughout —
 * most walk-ins give none.
 */
final class CompleteSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => [
                'required',
                Rule::enum(PaymentMethod::class),
                // COD has no meaning at a register — there is no delivery.
                Rule::in([PaymentMethod::Cash->value, PaymentMethod::BankTransfer->value]),
            ],
            'customer.name' => ['nullable', 'string', 'max:120'],
            'customer.phone' => ['nullable', 'string', 'max:32'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'discount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:1000000'],
            // Optional (owner's call, 2026-10-12): the cap is the control, the reason a note.
            'discount_reason' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
