<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Inventory;

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
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'quantity_change' => ['required', 'integer', 'not_in:0', 'min:-100000', 'max:100000'],
            'type' => ['required', Rule::in([
                'initial', 'manual_increase', 'manual_decrease', 'damaged', 'correction', 'supplier_return',
            ])],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
