<?php

namespace Database\Factories;

use App\Enums\RestaurantVerificationStatus;
use App\Models\RestaurantProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantProfile>
 */
class RestaurantProfileFactory extends Factory
{
    protected $model = RestaurantProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->restaurant(),
            'business_name' => fake()->company(),
            'contact_name' => fake()->name(),
            'handelsregisternummer' => null,
            'ust_id_nr' => null,
            'billing_street' => fake()->streetAddress(),
            'billing_postal_code' => fake()->postcode(),
            'billing_city' => fake()->city(),
            'verification_status' => RestaurantVerificationStatus::Pending,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => RestaurantVerificationStatus::Verified,
            'verified_at' => now(),
        ]);
    }
}
