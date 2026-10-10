<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The storefront homepage's curated blocks: hero slides and the two promo
 * tiles beside the hero. An empty list is valid and means "automatic" — the
 * storefront falls back to featured products and its own tile picks.
 *
 * `image_path` is the image a row already had. It is only kept if it is one
 * the server stored itself (checked in HomepageController), so a crafted
 * request cannot point a slide at an arbitrary file.
 */
final class UpdateHomepageRequest extends FormRequest
{
    public const MAX_SLIDES = 8;

    public const MAX_TILES = 2;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $row = [
            'title' => ['nullable', 'string', 'max:120'],
            'title_ar' => ['nullable', 'string', 'max:120'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'image_path' => ['nullable', 'string', 'max:255'],
        ];

        return [
            'hero' => ['nullable', 'array', 'max:'.self::MAX_SLIDES],
            'hero.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            ...collect($row)->mapWithKeys(fn (array $rules, string $field) => ["hero.*.{$field}" => $rules])->all(),

            'tiles' => ['nullable', 'array', 'max:'.self::MAX_TILES],
            'tiles.*.category_id' => ['required', 'integer', 'distinct', 'exists:categories,id'],
            ...collect($row)->mapWithKeys(fn (array $rules, string $field) => ["tiles.*.{$field}" => $rules])->all(),
        ];
    }
}
