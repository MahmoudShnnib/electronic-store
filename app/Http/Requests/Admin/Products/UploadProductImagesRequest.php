<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Products;

use Illuminate\Foundation\Http\FormRequest;

final class UploadProductImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }
}
