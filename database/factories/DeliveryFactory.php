<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Models\Address;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'address_id' => Address::factory(),
            'scheduled_for_date' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'timeslot' => fake()->randomElement(['08:00-12:00', '12:00-16:00', '16:00-20:00']),
            'status' => DeliveryStatus::Scheduled,
            'special_instructions' => fake()->optional()->sentence(),
            'is_weekly_override' => false,
            'overrides_delivery_id' => null,
        ];
    }
}
