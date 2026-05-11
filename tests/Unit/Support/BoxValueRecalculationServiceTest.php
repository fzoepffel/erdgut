<?php

namespace Tests\Unit\Support;

use App\Models\Address;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use App\Support\BoxValueRecalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoxValueRecalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recalculate_updates_box_estimated_value_from_items(): void
    {
        $user = User::factory()->create();
        Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);
        $box = Box::factory()->create(['user_id' => $user->id, 'estimated_value' => 0]);
        $producer = Producer::factory()->create();
        $product1 = Product::factory()->create(['producer_id' => $producer->id, 'unit' => 'piece', 'price_display_unit' => 'piece', 'unit_price' => 5.00]);
        $product2 = Product::factory()->create(['producer_id' => $producer->id, 'unit' => 'piece', 'price_display_unit' => 'piece', 'unit_price' => 3.00]);

        BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product1->id,
            'quantity' => 2,
            'unit' => 'kg',
            'is_extra' => false,
        ]);
        BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product2->id,
            'quantity' => 1,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $service = new BoxValueRecalculationService;
        $result = $service->recalculate($box);

        $this->assertSame(13.0, $result);
        $this->assertDatabaseHas('boxes', ['id' => $box->id, 'estimated_value' => 13.00]);
    }

    public function test_recalculate_returns_zero_for_empty_box(): void
    {
        $user = User::factory()->create();
        Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);
        $box = Box::factory()->create(['user_id' => $user->id, 'estimated_value' => 99.99]);

        $service = new BoxValueRecalculationService;
        $result = $service->recalculate($box);

        $this->assertSame(0.0, $result);
        $this->assertDatabaseHas('boxes', ['id' => $box->id, 'estimated_value' => 0]);
    }

    public function test_recalculate_handles_single_item(): void
    {
        $user = User::factory()->create();
        Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);
        $box = Box::factory()->create(['user_id' => $user->id, 'estimated_value' => 0]);
        $producer = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $producer->id, 'unit' => 'piece', 'price_display_unit' => 'piece', 'unit_price' => 7.50]);

        BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $service = new BoxValueRecalculationService;
        $result = $service->recalculate($box);

        $this->assertSame(30.0, $result);
        $this->assertDatabaseHas('boxes', ['id' => $box->id, 'estimated_value' => 30.00]);
    }
}
