<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Inventory;

use App\Enums\MovementType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
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
            // The shop never sells from an inactive warehouse, so stock added there
            // would vanish from sale. Draining one is still allowed.
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($this->adds() && ! Warehouse::whereKey($value)->where('is_active', true)->exists()) {
                    $fail(__('This warehouse is inactive. Activate it before adding stock to it.'));
                }
            }],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            // A product that already has variants can't gain new product-level stock
            // (B23 residual: nothing reads it back once variant rows exist) — draining
            // an existing orphan row is still allowed, so only additions require it.
            // A removed variant's leftover can be drained too, but never topped up.
            'product_variant_id' => [
                Rule::requiredIf(fn () => $this->adds()
                    && Product::whereKey($this->input('product_id'))->value('has_variants') === true),
                'nullable', 'integer',
                Rule::exists('product_variants', 'id')->where('product_id', $this->input('product_id')),
                Rule::prohibitedIf(fn () => $this->adds()
                    && ProductVariant::onlyTrashed()->whereKey($this->input('product_variant_id'))->exists()),
            ],
            'quantity_change' => ['required', 'integer', 'not_in:0', 'min:-100000', 'max:100000'],
            'type' => ['required', Rule::in([
                'initial', 'manual_increase', 'manual_decrease', 'damaged', 'lost', 'correction', 'supplier_return',
            ])],
            'reason' => ['required', 'string', 'max:255'],
            'from_damaged' => ['boolean'],
            // What one added unit cost this time (FIFO batch). The stock dialog always asks;
            // left out, the variant's (else the product's) cost price is used.
            'unit_cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999'],
        ];
    }

    /** Whether this adjustment adds stock, mirroring how AdjustStock applies the sign. */
    private function adds(): bool
    {
        $type = MovementType::tryFrom((string) $this->input('type'));

        return (int) $this->input('quantity_change') > 0 && ! ($type?->isDecrease() ?? false);
    }
}
