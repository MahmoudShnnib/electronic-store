<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Orders;

use App\Enums\RefundMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The unified "return / product problem" form. The reason is always required —
 * it is what the timeline and the refund record are annotated with. An amount
 * of zero logs the problem without moving any money.
 */
final class ResolveOrderIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'method' => ['required', Rule::enum(RefundMethod::class)->except([RefundMethod::StoreCredit])],
            'restock' => ['boolean'],
            // order_item_id => how many of that line came back
            'items' => ['nullable', 'array'],
            'items.*' => ['integer', 'min:0'],
            'reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
