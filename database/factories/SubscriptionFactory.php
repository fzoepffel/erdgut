<?php

namespace Database\Factories;

use App\Enums\Allergen;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
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
            'plan' => fake()->randomElement([SubscriptionPlan::Weekly, SubscriptionPlan::Biweekly]),
            'box_size' => fake()->randomElement(['small', 'medium', 'large']),
            'preferences' => [
                'veg_only' => fake()->boolean(),
                'meat_add_on' => fake()->boolean(),
                'eggs_dairy_add_on' => false,
            ],
            'allergies' => fake()->randomElements(Allergen::values(), fake()->numberBetween(0, 2)),
            'status' => SubscriptionStatus::Active,
            'sumup_token' => fake()->optional()->sha1(),
            'sumup_customer_id' => fake()->optional()->uuid(),
            'is_test' => false,
            'trial_completed_at' => now(),
        ];
    }

    /**
     * First recurring charge should use trial_price_ttc until a payment is marked paid.
     */
    public function withPendingTrialBilling(): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_completed_at' => null,
        ]);
    }

    public function withPaymentToken(): static
    {
        return $this->state(fn (array $attributes) => [
            'sumup_token' => 'tok_test_'.fake()->unique()->numerify('######'),
            'sumup_customer_id' => 'cust_test',
        ]);
    }
}
