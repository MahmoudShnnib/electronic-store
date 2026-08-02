<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Returns;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Keys are return item ids; each value decides whether that unit goes back into
 * sellable stock or is written off as damaged.
 */
final class ReceiveReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'conditions' => ['required', 'array', 'min:1'],
            'conditions.*' => ['required', 'in:resellable,damaged'],
        ];
    }
}
