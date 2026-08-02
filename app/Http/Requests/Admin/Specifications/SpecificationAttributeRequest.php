<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Specifications;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An attribute's owning group is set at creation and cannot be moved afterwards,
 * so specification_group_id is only part of the payload on store. Omitting the
 * key on update keeps it out of validated() entirely.
 */
final class SpecificationAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'name_ar' => ['nullable', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:32'],
            'type' => ['required', 'in:text,number,boolean'],
            'is_filterable' => ['boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];

        if ($this->route('attribute') === null) {
            $rules['specification_group_id'] = ['required', 'integer', 'exists:specification_groups,id'];
        }

        return $rules;
    }
}
