<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan' => fake()->randomElement(['weekly', 'biweekly']),
            'box_size' => fake()->randomElement(['small', 'medium', 'large']),
            'preferences' => [
                'veg_only' => fake()->boolean(),
                'meat_add_on' => fake()->boolean(),
            ],
            'allergies' => fake()->randomElements(['nuts', 'gluten', 'lactose'], fake()->numberBetween(0, 2)),
            'status' => Subscription::STATUS_ACTIVE,
            'sumup_token' => fake()->optional()->sha1(),
            'sumup_customer_id' => fake()->optional()->uuid(),
        ];
    }
}
