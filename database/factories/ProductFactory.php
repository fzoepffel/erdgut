<?php

namespace Database\Factories;

use App\Enums\ProductUnit;
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
            // Mirror the single producer into the multi-producer pivot so
            // factory-built products satisfy the column<->pivot invariant.
            if ($product->producer_id !== null) {
                $product->producers()->syncWithoutDetaching([
                    $product->producer_id => ['position' => 0],
                ]);
            }
            foreach (['de', 'en', 'fr'] as $locale) {
                ProductTranslation::query()->create([
                    'product_id' => $product->id,
                    'locale' => $locale,
                    'name' => fake()->words(2, true).' ('.$locale.')',
                    'origin' => fake()->city(),
                    'variety' => fake()->word(),
                    'taste_profile' => fake()->sentence(),
                    'short_description' => fake()->sentence(6),
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
        // Default to piece so factory products have factor=1 between
        // canonical storage and display — keeps existing tests that
        // assume `quantity × unit_price = € total` valid without
        // needing per-test setup. Mass/volume/bunch are still
        // available by passing `unit` explicitly.
        return [
            'producer_id' => Producer::factory(),
            // Chapter taxonomy (D6/D7/F8 in UX-review notes).
            'category' => fake()->randomElement(['produce', 'eggs_and_dairy', 'bread']),
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
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
