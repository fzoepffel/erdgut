<?php

namespace Database\Factories;

use App\Enums\ProductUnit;
use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopOrderItem>
 */
class ShopOrderItemFactory extends Factory
{
    protected $model = ShopOrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomFloat(2, 1, 12);

        return [
            'shop_order_id' => ShopOrder::factory(),
            'product_id' => Product::factory()->create([
                'unit' => ProductUnit::Piece->value,
                'price_display_unit' => 'piece',
            ])->id,
            'quantity' => $quantity,
            'unit' => 'piece',
            'unit_price_snapshot_eur' => $unitPrice,
            'line_total_eur' => round($quantity * $unitPrice, 2),
        ];
    }
}
