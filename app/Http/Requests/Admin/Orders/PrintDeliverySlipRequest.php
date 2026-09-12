<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Orders;

use Illuminate\Foundation\Http\FormRequest;

/** The rasterised delivery slip the order page wants burned. */
final class PrintDeliverySlipRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Whoever may read the order may put it on paper. Printing a slip is not
        // a till action, so this deliberately does not ask for `pos.use`.
        return $this->user()?->can('orders.view') ?? false;
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
