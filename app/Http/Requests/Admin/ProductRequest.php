<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'category_id' => ['required', 'integer', 'exists:categories,id'],
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
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:240'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'length_mm' => ['nullable', 'integer', 'min:0'],
            'width_mm' => ['nullable', 'integer', 'min:0'],
            'height_mm' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'is_featured' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],

            'variants' => ['nullable', 'array', 'max:50'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:255'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:64', 'distinct'],
            'variants.*.barcode' => ['nullable', 'string', 'max:64'],
            'variants.*.attributes' => ['nullable', 'array'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.price' => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_active' => ['boolean'],

            'specifications' => ['nullable', 'array'],
            'specifications.*.value' => ['nullable', 'string', 'max:500'],
            'specifications.*.value_ar' => ['nullable', 'string', 'max:500'],
        ];
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
            'tax_rate' => $v['tax_rate'] ?? 0,
            'low_stock_threshold' => $v['low_stock_threshold'] ?? 5,
            'warranty_months' => $v['warranty_months'] ?? null,
            'weight_grams' => $v['weight_grams'] ?? null,
            'length_mm' => $v['length_mm'] ?? null,
            'width_mm' => $v['width_mm'] ?? null,
            'height_mm' => $v['height_mm'] ?? null,
            'status' => $v['status'],
            'is_featured' => (bool) ($v['is_featured'] ?? false),
            'meta_title' => $v['meta_title'] ?? null,
            'meta_description' => $v['meta_description'] ?? null,
            'published_at' => $v['status'] === ProductStatus::Active->value ? now() : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function variantPayload(): ?array
    {
        $variants = $this->validated()['variants'] ?? null;

        if ($variants === null) {
            return null;
        }

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
