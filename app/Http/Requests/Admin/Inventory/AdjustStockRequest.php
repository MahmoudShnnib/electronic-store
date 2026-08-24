<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Inventory;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Only ledger types that represent a manual admin correction are accepted here.
 * Order-driven types (customer_order, purchase_receipt, …) are written by their
 * own actions and must never be settable from this form.
 */
final class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            // A product that already has variants can't gain new product-level stock
            // (B23 residual: nothing reads it back once variant rows exist) — draining
            // an existing orphan row is still allowed, so only positive changes require it.
            'product_variant_id' => [
                Rule::requiredIf(fn () => (int) $this->input('quantity_change') > 0
                    && Product::whereKey($this->input('product_id'))->value('has_variants') === true),
                'nullable', 'integer', 'exists:product_variants,id',
            ],
            'quantity_change' => ['required', 'integer', 'not_in:0', 'min:-100000', 'max:100000'],
            'type' => ['required', Rule::in([
                'initial', 'manual_increase', 'manual_decrease', 'damaged', 'correction', 'supplier_return',
            ])],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
