<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Keys are purchase order item ids; values are the quantity received in this
 * batch. Over-receipt is rejected by ReceivePurchaseOrder, which knows what is
 * still outstanding.
 */
final class ReceivePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantities' => ['required', 'array', 'min:1'],
            'quantities.*' => ['integer', 'min:0', 'max:1000000'],
        ];
    }
}
