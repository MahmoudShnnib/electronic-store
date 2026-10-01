<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Inventory;

use App\Rules\ItemVariantsMatchProducts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransferStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Only active warehouses move stock; a turned-off one is closed for business.
            'from_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)->whereNull('deleted_at'), 'different:to_warehouse_id'],
            'to_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    public function after(): array
    {
        return [new ItemVariantsMatchProducts];
    }
}
