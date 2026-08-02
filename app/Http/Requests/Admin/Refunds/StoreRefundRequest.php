<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Refunds;

use App\Enums\RefundMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Whether the amount exceeds what is actually refundable on the order is
 * decided by CreateRefund, not here.
 */
final class StoreRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::enum(RefundMethod::class)],
            'return_id' => ['nullable', 'integer', 'exists:returns,id'],
            'reference' => ['nullable', 'string', 'max:120'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
