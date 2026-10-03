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
                // A finished coupon does not reserve its code: deleted ones are
                // excluded by the partial unique index, and expired ones are
                // soft-deleted by the controller before the new row is saved.
                Rule::unique('coupons', 'code')
                    ->ignore($this->route('coupon')?->id)
                    ->whereNull('deleted_at')
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())),
            ],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['nullable', 'required_if:type,fixed', 'numeric', 'decimal:0,2', 'min:0.01'],
            'percent' => ['nullable', 'required_if:type,percentage', 'numeric', 'min:0.01', 'max:100'],
            // Whole units only — a 0.03 minimum is a rule that can never bind.
            'min_order' => ['nullable', 'integer', 'min:1'],
            'max_discount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
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
