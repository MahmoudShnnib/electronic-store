<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Orders;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Amount arrives as a decimal from the form and is converted to integer cents
 * by Money::fromDecimal in the controller.
 */
final class ConfirmPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
