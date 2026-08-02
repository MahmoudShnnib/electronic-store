<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class WarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required', 'string', 'max:32', 'alpha_dash',
                Rule::unique('warehouses', 'code')->ignore($this->route('warehouse')?->id),
            ],
            'address' => ['nullable', 'string', 'max:500'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
