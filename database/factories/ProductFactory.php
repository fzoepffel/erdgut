<?php

namespace Database\Factories;

use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void {
            foreach (['de', 'en', 'fr'] as $locale) {
                ProductTranslation::query()->create([
                    'product_id' => $product->id,
                    'locale' => $locale,
                    'name' => fake()->words(2, true).' ('.$locale.')',
                    'unit' => fake()->randomElement(['kg', 'bundle', 'piece']),
                    'origin' => fake()->city(),
                    'variety' => fake()->word(),
                    'taste_profile' => fake()->sentence(),
                ]);
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'producer_id' => Producer::factory(),
            'category' => fake()->randomElement(['vegetables', 'fruit', 'dairy', 'bakery']),
            'unit_price' => fake()->randomFloat(2, 1, 30),
            'is_seasonal' => fake()->boolean(),
            'is_available' => true,
            'is_published' => true,
            'metadata' => null,
            'image_path' => null,
            'is_test' => false,
        ];
    }
}
