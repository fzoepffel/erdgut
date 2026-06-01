<?php

namespace Database\Factories;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SAVE####')),
            'label' => fake()->optional()->sentence(3),
            'type' => CouponType::Percent,
            'value' => 10.00,
            'max_discount_eur' => null,
            'min_order_eur' => null,
            'max_redemptions' => null,
            'per_email_limit' => 1,
            'redeemed_count' => 0,
            'starts_at' => null,
            'expires_at' => null,
            'is_active' => true,
            'created_by_user_id' => null,
        ];
    }

    public function percent(float $value, ?float $maxDiscount = null): static
    {
        return $this->state(fn () => [
            'type' => CouponType::Percent,
            'value' => $value,
            'max_discount_eur' => $maxDiscount,
        ]);
    }

    public function fixed(float $value, float $minOrder): static
    {
        return $this->state(fn () => [
            'type' => CouponType::Fixed,
            'value' => $value,
            'min_order_eur' => $minOrder,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
