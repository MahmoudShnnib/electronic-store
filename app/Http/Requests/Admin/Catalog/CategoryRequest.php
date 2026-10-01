<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Catalog;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * notIn on parent_id stops a category being reparented onto itself. Deeper
 * cycles (A → B → A) are not caught here.
 */
final class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** A blank slug is derived from the name here, so a clash is a validation error, not a 500. */
    protected function prepareForValidation(): void
    {
        if (blank($this->input('slug')) && is_string($this->input('name'))) {
            $this->merge(['slug' => Str::slug($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:120'],
            'name_ar' => ['nullable', 'string', 'max:120'],
            'slug' => [
                'nullable', 'string', 'max:140', 'alpha_dash',
                Rule::unique('categories', 'slug')->ignore($category?->id),
            ],
            // Never under itself or anything below it — that would make a loop.
            'parent_id' => [
                'nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at'),
                Rule::notIn($category !== null ? Category::subtreeIds($category->id) : []),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            // Ticked by the admin form to clear an existing image without
            // uploading a replacement.
            'remove_image' => ['boolean'],
        ];
    }
}
