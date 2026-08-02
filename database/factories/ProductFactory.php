<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->words(3, true));
        $cost = $this->faker->numberBetween(5_000, 150_000);

        return [
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 999999),
            'sku' => strtoupper($this->faker->unique()->bothify('SKU-#####??')),
            'barcode' => $this->faker->ean13(),
            'short_description' => $this->faker->sentence(10),
            'description' => $this->faker->paragraphs(3, true),
            'cost_price_cents' => $cost,
            'price_cents' => (int) round($cost * $this->faker->randomFloat(2, 1.15, 1.8)),
            'tax_rate' => 0,
            'low_stock_threshold' => 5,
            'warranty_months' => $this->faker->randomElement([null, 12, 24]),
            'weight_grams' => $this->faker->numberBetween(100, 5000),
            'status' => ProductStatus::Active,
            'is_featured' => false,
            'published_at' => now(),
        ];
    }

    public function draft(): self
    {
        return $this->state(['status' => ProductStatus::Draft, 'published_at' => null]);
    }

    public function featured(): self
    {
        return $this->state(['is_featured' => true]);
    }

    public function onSale(): self
    {
        return $this->state(fn (array $attributes) => [
            'sale_price_cents' => (int) round($attributes['price_cents'] * 0.85),
        ]);
    }

    public function withVariants(): self
    {
        return $this->state(['has_variants' => true]);
    }
}
