<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Orders;

use Illuminate\Foundation\Http\FormRequest;

/** Orders picked on the list for one action (complete, print slips). */
final class BulkOrdersRequest extends FormRequest
{
    public const MAX = 100;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX],
            'ids.*' => ['integer', 'distinct'],
        ];
    }

    /** @return list<int> */
    public function ids(): array
    {
        return array_values(array_map('intval', $this->validated('ids')));
    }
}
