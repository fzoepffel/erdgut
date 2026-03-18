<?php

namespace Database\Factories;

use App\Enums\BoxStatus;
use App\Models\Box;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Box>
 */
class BoxFactory extends Factory
{
    protected $model = Box::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $weekStart = fake()->dateTimeBetween('+1 day', '+30 days');

        return [
            'user_id' => User::factory(),
            'week_start_date' => $weekStart->format('Y-m-d'),
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => (clone $weekStart)->modify('-2 days')->format('Y-m-d H:i:s'),
            'estimated_value' => fake()->randomFloat(2, 15, 60),
        ];
    }
}
