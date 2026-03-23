<?php

namespace Database\Factories;

use App\Models\Producer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'producer_id' => Producer::factory(),
            'name' => fake()->words(2, true),
            'category' => fake()->randomElement(['vegetables', 'fruit', 'dairy', 'bakery']),
            'unit_price' => fake()->randomFloat(2, 1, 30),
            'is_seasonal' => fake()->boolean(),
            'is_available' => true,
            'is_published' => true,
            'metadata' => [
                'unit' => fake()->randomElement(['kg', 'bundle', 'piece']),
                'origin' => fake()->city(),
            ],
            'image_path' => null,
            'is_test' => false,
        ];
    }
}
