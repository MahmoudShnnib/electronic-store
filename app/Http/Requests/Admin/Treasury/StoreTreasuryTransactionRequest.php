<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Treasury;

use App\Enums\TreasuryAccount;
use App\Enums\TreasuryDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTreasuryTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::enum(TreasuryDirection::class)],
            'account' => ['required', Rule::enum(TreasuryAccount::class)],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
