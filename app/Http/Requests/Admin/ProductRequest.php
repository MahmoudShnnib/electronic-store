<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SpecificationAttribute;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route-level policy check; see controller.
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('products', 'slug')->ignore($productId)],
            'sku' => ['required', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($productId)],
            'barcode' => ['nullable', 'string', 'max:64'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'short_description_ar' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:65000'],
            'description_ar' => ['nullable', 'string', 'max:65000'],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at' => ['nullable', 'date', 'after:sale_starts_at'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'is_featured' => ['boolean'],

            'variants' => ['nullable', 'array', 'max:50'],
            'variants.*.id' => ['nullable', 'integer'],
            'stock_split' => ['nullable', 'array', 'max:500'],
            'stock_split.*.warehouse_id' => ['required', 'integer'],
            'stock_split.*.sku' => ['required', 'string'],
            'stock_split.*.quantity' => ['required', 'integer', 'min:0'],
            'known_variant_ids' => ['nullable', 'array'],
            'known_variant_ids.*' => ['integer'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:255'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:64', 'distinct'],
            'variants.*.barcode' => ['nullable', 'string', 'max:64'],
            'variants.*.attributes' => ['nullable', 'array'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.price' => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.sale_price' => ['nullable', 'numeric', 'min:0', 'lt:variants.*.price'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_active' => ['boolean'],

            'specifications' => ['nullable', 'array'],
            'specifications.*.value' => ['nullable', 'string', 'max:500'],
            'specifications.*.value_ar' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validateVariantSkus($v);
            $this->validateSpecificationTypes($v);
        });
    }

    /**
     * A friendly "already taken" message instead of a database crash: the
     * variants.sku column carries a hard unique index (soft-deleted rows
     * included), so anything that would violate it must fail validation.
     * A trashed variant of THIS product is fine — SaveProduct restores it.
     */
    private function validateVariantSkus(Validator $v): void
    {
        $productId = $this->route('product')?->id;

        foreach ($this->input('variants', []) ?? [] as $index => $variant) {
            $sku = $variant['sku'] ?? null;

            if (! is_string($sku) || $sku === '') {
                continue;
            }

            // Conflicts: any row holding the sku (trashed rows still occupy the
            // unique index) except the variant itself and trashed variants of
            // this same product — those SaveProduct restores instead.
            $taken = ProductVariant::withTrashed()
                ->where('sku', $sku)
                ->when(isset($variant['id']), fn ($q) => $q->whereKeyNot((int) $variant['id']))
                ->when($productId !== null, fn ($q) => $q->where(fn ($qq) => $qq
                    ->where('product_id', '!=', $productId)
                    ->orWhereNull('deleted_at')))
                ->exists();

            if ($taken) {
                $v->errors()->add(
                    "variants.{$index}.sku",
                    __('The SKU ":sku" is already used by another variant.', ['sku' => $sku]),
                );
            } elseif (Product::withTrashed()->where('sku', $sku)->when($productId !== null, fn ($q) => $q->whereKeyNot($productId))->exists()) {
                // Barcode scanners and search look SKUs up across products AND variants.
                $v->errors()->add(
                    "variants.{$index}.sku",
                    __('The SKU ":sku" is already used by another product.', ['sku' => $sku]),
                );
            }
        }

        // Two variants with the same options (e.g. both "Red") can't be told apart by a customer.
        $seen = [];

        foreach ($this->input('variants', []) ?? [] as $index => $variant) {
            $attributes = array_filter((array) ($variant['attributes'] ?? []), fn ($value) => filled($value));

            if ($attributes === []) {
                continue;
            }

            ksort($attributes);
            $key = json_encode($attributes);

            if (isset($seen[$key])) {
                $v->errors()->add("variants.{$index}.attributes", __('Another variant already has these options.'));
            }

            $seen[$key] = true;
        }
    }

    /**
     * Number-type specification attributes must actually receive numbers, and an
     * attribute must be scoped to the submitted category (or have no scope at all —
     * universal) — the client already hides out-of-scope attributes, this is the
     * server-side half of that same rule (B28 gap).
     */
    private function validateSpecificationTypes(Validator $v): void
    {
        $specs = $this->input('specifications', []) ?? [];

        if ($specs === []) {
            return;
        }

        $categoryId = (int) $this->input('category_id');
        $parentId = Category::whereKey($categoryId)->value('parent_id');

        $attributes = SpecificationAttribute::query()
            ->whereIn('id', array_keys($specs))
            ->with('categories:id')
            ->get()
            ->keyBy('id');

        foreach ($specs as $attributeId => $spec) {
            $value = $spec['value'] ?? null;

            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            $attribute = $attributes->get((int) $attributeId);

            if ($attribute === null) {
                continue;
            }

            $scopedIds = $attribute->categories->pluck('id');

            if ($scopedIds->isNotEmpty() && ! $scopedIds->contains($categoryId) && ! $scopedIds->contains($parentId)) {
                $v->errors()->add(
                    "specifications.{$attributeId}.value",
                    __('This specification does not apply to the selected category.'),
                );

                continue;
            }

            if ($attribute->type === 'number' && ! is_numeric($value)) {
                $v->errors()->add(
                    "specifications.{$attributeId}.value",
                    __('This specification only accepts a number.'),
                );
            }
        }
    }

    /**
     * Product attributes with money converted to integer cents.
     *
     * @return array<string, mixed>
     */
    public function productAttributes(): array
    {
        $v = $this->validated();

        return [
            'name' => $v['name'],
            'name_ar' => $v['name_ar'] ?? null,
            'slug' => $v['slug'] ?? null,
            'sku' => $v['sku'],
            'barcode' => $v['barcode'] ?? null,
            'category_id' => $v['category_id'],
            'brand_id' => $v['brand_id'] ?? null,
            'supplier_id' => $v['supplier_id'] ?? null,
            'short_description' => $v['short_description'] ?? null,
            'short_description_ar' => $v['short_description_ar'] ?? null,
            'description' => $v['description'] ?? null,
            'description_ar' => $v['description_ar'] ?? null,
            'cost_price_cents' => Money::fromDecimal($v['cost_price']),
            'price_cents' => Money::fromDecimal($v['price']),
            'sale_price_cents' => isset($v['sale_price']) ? Money::fromDecimal($v['sale_price']) : null,
            'sale_starts_at' => $v['sale_starts_at'] ?? null,
            'sale_ends_at' => $v['sale_ends_at'] ?? null,
            'low_stock_threshold' => $v['low_stock_threshold'] ?? 5,
            'status' => $v['status'],
            'is_featured' => (bool) ($v['is_featured'] ?? false),
            // First activation stamps the publish date; after that it never
            // moves — editing a live product must not push it back to the top
            // of "Newest", and deactivating must not erase its history.
            'published_at' => $this->route('product')?->published_at
                ?? ($v['status'] === ProductStatus::Active->value ? now() : null),
        ];
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function variantPayload(): ?array
    {
        // Only an absent key means "leave variants untouched" — a submitted
        // empty list is an instruction to delete every variant.
        if (! $this->has('variants')) {
            return null;
        }

        $variants = $this->validated()['variants'] ?? [];

        return array_map(fn (array $v) => [
            'id' => $v['id'] ?? null,
            'name' => $v['name'],
            'sku' => $v['sku'],
            'barcode' => $v['barcode'] ?? null,
            'attributes' => $v['attributes'] ?? null,
            'cost_price_cents' => isset($v['cost_price']) ? Money::fromDecimal($v['cost_price']) : 0,
            'price_cents' => Money::fromDecimal($v['price']),
            'sale_price_cents' => isset($v['sale_price']) ? Money::fromDecimal($v['sale_price']) : null,
            'low_stock_threshold' => $v['low_stock_threshold'] ?? 5,
            'is_active' => (bool) ($v['is_active'] ?? true),
        ], $variants);
    }

    /**
     * How a plain product's shelf stock is split between its new variants.
     *
     * @return list<array{warehouse_id: int, sku: string, quantity: int}>|null
     */
    public function stockSplit(): ?array
    {
        $split = $this->validated()['stock_split'] ?? null;

        return $split === null ? null : array_map(fn (array $row) => [
            'warehouse_id' => (int) $row['warehouse_id'],
            'sku' => (string) $row['sku'],
            'quantity' => (int) $row['quantity'],
        ], array_values($split));
    }

    /**
     * Variant ids the form was opened with; null when the client didn't say
     * (API, older pages), which keeps "omitted = removed" for every variant.
     *
     * @return list<int>|null
     */
    public function knownVariantIds(): ?array
    {
        return $this->has('known_variant_ids')
            ? array_map('intval', $this->validated()['known_variant_ids'] ?? [])
            : null;
    }

    /**
     * @return array<int, array{value: string, value_ar: ?string}>|null
     */
    public function specificationPayload(): ?array
    {
        $specs = $this->validated()['specifications'] ?? null;

        if ($specs === null) {
            return null;
        }

        return array_map(fn (array $s) => [
            'value' => (string) ($s['value'] ?? ''),
            'value_ar' => $s['value_ar'] ?? null,
        ], $specs);
    }
}
