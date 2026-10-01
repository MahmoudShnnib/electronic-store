<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\Validator;

/**
 * `after()` hook for forms with `items.*.product_id` / `items.*.product_variant_id`:
 * a variant product's line must name one of ITS variants, and a plain product's
 * line must not name any — otherwise stock lands on a row nothing can sell.
 */
final class ItemVariantsMatchProducts
{
    /** @param  bool  $allowRemoved  let a line move stock still sitting on a removed variant */
    public function __construct(private readonly bool $allowRemoved = false) {}

    public function __invoke(Validator $validator): void
    {
        foreach ((array) ($validator->getData()['items'] ?? []) as $i => $item) {
            $product = Product::query()->find($item['product_id'] ?? null);

            if ($product === null) {
                continue;
            }

            $variantId = $item['product_variant_id'] ?? null;

            if ($variantId === null) {
                if ($product->has_variants) {
                    $validator->errors()->add("items.{$i}.product_variant_id", __('Choose a variant of :product.', ['product' => $product->name]));
                }

                continue;
            }

            $belongs = ProductVariant::query()
                ->when($this->allowRemoved, fn ($q) => $q->withTrashed())
                ->where('product_id', $product->id)
                ->whereKey($variantId)
                ->exists();

            if (! $belongs) {
                $validator->errors()->add("items.{$i}.product_variant_id", __('That variant does not belong to :product.', ['product' => $product->name]));
            }
        }
    }
}
