<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Specifications;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by storeGroup and updateGroup — the rules were identical.
 */
final class SpecificationGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'name_ar' => ['nullable', 'string', 'max:120'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
