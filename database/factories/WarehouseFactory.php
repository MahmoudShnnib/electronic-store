<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
final class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->city().' Warehouse',
            'code' => strtoupper($this->faker->unique()->bothify('WH-###')),
            'address' => $this->faker->address(),
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function default(): self
    {
        return $this->state(['is_default' => true]);
    }
}
