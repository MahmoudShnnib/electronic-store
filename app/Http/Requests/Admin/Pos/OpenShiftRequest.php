<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Pos;

use Illuminate\Foundation\Http\FormRequest;

final class OpenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            // Sent as a decimal amount; the controller converts it to cents.
            'opening_float' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
