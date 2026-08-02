<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
final class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('SAVE##??')),
            'type' => CouponType::Fixed,
            'value_cents' => $this->faker->numberBetween(500, 5_000),
            'applies_to' => 'all',
            'is_active' => true,
        ];
    }

    public function percentage(float $percent = 10): self
    {
        return $this->state([
            'type' => CouponType::Percentage,
            'value_cents' => null,
            'percent' => $percent,
        ]);
    }

    public function expired(): self
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
