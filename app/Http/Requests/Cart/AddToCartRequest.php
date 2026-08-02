<?php

declare(strict_types=1);

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by the web and API cart controllers so the two surfaces can never
 * drift apart on what a valid "add to cart" payload looks like.
 *
 * Carts are guest-accessible; ownership of an existing item is enforced by the
 * controllers' IDOR guard, not here.
 */
final class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
