<?php

namespace Database\Factories;

use App\Models\Producer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producer>
 */
class ProducerFactory extends Factory
{
    protected $model = Producer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'region' => fake()->city(),
            'description' => fake()->sentence(),
            'image_url' => fake()->imageUrl(),
            'image_path' => null,
            'is_test' => false,
        ];
    }
}
