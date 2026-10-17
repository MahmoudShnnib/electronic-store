<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Suppliers;

use App\Enums\TreasuryAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:100000000'],
            'account' => ['required', Rule::enum(TreasuryAccount::class)],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
