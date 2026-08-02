<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Orders;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateOrderNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
