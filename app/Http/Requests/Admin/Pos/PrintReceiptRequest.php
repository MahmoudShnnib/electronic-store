<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Pos;

use Illuminate\Foundation\Http\FormRequest;

/** The rasterised slip the register wants burned. */
final class PrintReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pos.use') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // A mono 80mm slip lands around 30 KB; the ceiling is only here so a
            // broken client cannot post a photograph at the print head.
            'image' => ['required', 'file', 'mimes:png', 'max:4096'],
        ];
    }
}
