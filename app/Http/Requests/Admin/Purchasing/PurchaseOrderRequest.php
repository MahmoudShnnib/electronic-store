<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Purchasing;

use App\Rules\ItemVariantsMatchProducts;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Decimal money fields (discount, tax, shipping, unit_cost) are converted to
 * integer cents by the controller before the purchase order is written.
 */
final class PurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'discount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'shipping' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'expected_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_cost' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            new ItemVariantsMatchProducts,
            // A discount bigger than the goods would be dropped silently by the
            // total (it floors at 0), costing the stock at next to nothing — a
            // typo (150 for 15) must be refused, not absorbed.
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $subtotal = 0;

                foreach ((array) $this->input('items', []) as $item) {
                    $subtotal += Money::fromDecimal($item['unit_cost'] ?? 0) * (int) ($item['quantity'] ?? 0);
                }

                if (Money::fromDecimal($this->input('discount') ?? 0) > $subtotal) {
                    $validator->errors()->add('discount', __('The discount cannot be more than the items cost (:amount).', ['amount' => Money::format($subtotal)]));
                }
            },
        ];
    }
}
