<?php

declare(strict_types=1);

namespace App\Http\Requests\Reviews;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The web route binds the product ({product} in the URL); the mobile API sends
 * it in the body. product_id is therefore required only when the route did not
 * already supply one.
 */
final class SubmitReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => [
                Rule::requiredIf(fn (): bool => $this->route('product') === null),
                'integer',
                'exists:products,id',
            ],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
