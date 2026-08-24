<?php

declare(strict_types=1);

namespace App\Http\Requests\Returns;

use App\Enums\ReturnReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The web route binds the order ({order} in the URL); the mobile API sends it
 * in the body. Ownership of the order, membership of each line, the returnable
 * quantity and the return window are all enforced by Actions\Returns\RequestReturn
 * — this class only shapes the payload.
 */
final class RequestReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => [
                Rule::requiredIf(fn (): bool => $this->route('order') === null),
                'integer',
                'exists:orders,id',
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::enum(ReturnReason::class)],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }
}
