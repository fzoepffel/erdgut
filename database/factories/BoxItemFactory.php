<?php

namespace Database\Factories;

use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoxItem>
 */
class BoxItemFactory extends Factory
{
    protected $model = BoxItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'box_id' => Box::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->randomFloat(2, 0.5, 5),
            'unit' => fake()->randomElement(['kg', 'piece', 'bundle']),
            'is_extra' => false,
        ];
    }
}
