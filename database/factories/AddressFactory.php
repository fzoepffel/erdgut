<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Home', 'Work', 'Parents']),
            'recipient_name' => fake()->name(),
            'street_line_1' => fake()->streetAddress(),
            'street_line_2' => fake()->optional()->secondaryAddress(),
            'postal_code' => fake()->postcode(),
            'city' => fake()->city(),
            'country_code' => 'DE',
            'phone' => fake()->optional()->phoneNumber(),
            'building_code' => fake()->optional()->bothify('##?'),
            'floor' => fake()->optional()->numberBetween(0, 8),
            'delivery_instructions' => fake()->optional()->sentence(),
            'is_primary' => false,
        ];
    }
}
