<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPriceRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPriceRule>
 */
class ProductPriceRuleFactory extends Factory
{
    protected $model = ProductPriceRule::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'variant_id' => null,
            'customer_type' => null,
            'starts_on' => null,
            'ends_on' => null,
            'min_quantity' => null,
            'unit_price' => fake()->randomFloat(2, 1, 20),
            'priority' => 0,
            'label' => null,
            'is_active' => true,
        ];
    }
}
