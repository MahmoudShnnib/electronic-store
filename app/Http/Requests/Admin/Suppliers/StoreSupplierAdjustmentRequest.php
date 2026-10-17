<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Suppliers;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSupplierAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // debt: we owe the supplier this much more; credit: they owe us / we owe less.
            'direction' => ['required', 'in:debt,credit'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:100000000'],
            'note' => ['required', 'string', 'max:1000'],
        ];
    }
}
