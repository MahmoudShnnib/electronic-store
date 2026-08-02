<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
final class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        $storage = $this->faker->randomElement(['128GB', '256GB', '512GB', '1TB']);
        $color = $this->faker->randomElement(['Black', 'Silver', 'Blue', 'Gold']);
        $cost = $this->faker->numberBetween(5_000, 150_000);

        return [
            'product_id' => Product::factory()->withVariants(),
            'name' => "{$storage} / {$color}",
            'sku' => strtoupper($this->faker->unique()->bothify('VAR-#####??')),
            'attributes' => ['storage' => $storage, 'color' => $color],
            'cost_price_cents' => $cost,
            'price_cents' => (int) round($cost * 1.3),
            'low_stock_threshold' => 3,
            'is_active' => true,
        ];
    }
}
