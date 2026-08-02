<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Products;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Per-product authorization still runs inside the controller loop — a bulk
 * action must not become a way to skip ProductPolicy.
 */
final class BulkProductActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:activate,deactivate,feature,unfeature,delete'],
        ];
    }
}
