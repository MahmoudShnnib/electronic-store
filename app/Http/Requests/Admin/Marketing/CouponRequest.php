<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Marketing;

use App\Enums\CouponType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Decimal money fields are converted to integer cents by the controller.
 */
final class CouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:64', 'alpha_dash',
                // Deleted coupons don't reserve their code — matching the
                // partial unique index, which only covers live rows.
                Rule::unique('coupons', 'code')
                    ->ignore($this->route('coupon')?->id)
                    ->whereNull('deleted_at'),
            ],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['nullable', 'required_if:type,fixed', 'numeric', 'min:0.01'],
            'percent' => ['nullable', 'required_if:type,percentage', 'numeric', 'min:0.01', 'max:100'],
            'min_order' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'applies_to' => ['required', Rule::in(['all', 'products', 'categories', 'brands'])],
            'applies_to_ids' => ['nullable', 'array', 'required_unless:applies_to,all'],
            'applies_to_ids.*' => ['integer'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
        ];
    }
}
