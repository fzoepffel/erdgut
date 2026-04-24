<?php

namespace Database\Factories;

use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_type' => OrderProductType::TrialBox,
            'status' => OrderStatus::Paid,
            'box_size' => 'medium',
            'amount' => fake()->randomFloat(2, 20, 60),
            'currency' => 'EUR',
            'is_test' => false,
        ];
    }
}
