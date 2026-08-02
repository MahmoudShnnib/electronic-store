<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by the web and API address controllers. Ownership on update/delete is
 * enforced by AddressPolicy in the controllers.
 */
final class AddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'country' => ['required', 'string', 'size:2'],
            'city' => [
                'required', 'string', 'max:120',
                Rule::in(array_column(config('store.cities', []), 'en')),
            ],
            'area' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_default_shipping' => ['boolean'],
            'is_default_billing' => ['boolean'],
        ];
    }
}
