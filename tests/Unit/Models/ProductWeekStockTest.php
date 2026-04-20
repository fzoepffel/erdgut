<?php

namespace Tests\Unit\Models;

use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Product;
use App\Models\ProductWeekStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductWeekStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_remaining_for_week_returns_planned_minus_distributed(): void
    {
        $week = Carbon::parse('2026-04-06');
        $product = Product::factory()->create();

        ProductWeekStock::create([
            'product_id' => $product->id,
            'week_start_date' => $week->toDateString(),
            'planned_quantity' => 20,
        ]);

        $user = User::factory()->create();
        $box = Box::factory()->for($user)->create([
            'week_start_date' => $week->toDateString(),
        ]);
        BoxItem::factory()->for($box)->create([
            'product_id' => $product->id,
            'quantity' => 7.5,
            'is_extra' => false,
        ]);

        $this->assertEqualsWithDelta(20.0, $product->plannedQuantityForWeek($week), 0.001);
        $this->assertEqualsWithDelta(7.5, $product->distributedQuantityForWeek($week), 0.001);
        $this->assertEqualsWithDelta(12.5, $product->remainingForWeek($week), 0.001);
    }

    public function test_remaining_can_be_negative_when_over_allocated(): void
    {
        $week = Carbon::parse('2026-04-06');
        $product = Product::factory()->create();

        ProductWeekStock::create([
            'product_id' => $product->id,
            'week_start_date' => $week->toDateString(),
            'planned_quantity' => 3,
        ]);

        $user = User::factory()->create();
        $box = Box::factory()->for($user)->create([
            'week_start_date' => $week->toDateString(),
        ]);
        BoxItem::factory()->for($box)->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'is_extra' => false,
        ]);

        $this->assertLessThan(0, $product->remainingForWeek($week));
    }

    public function test_planned_quantity_is_zero_when_no_stock_row(): void
    {
        $week = Carbon::parse('2026-04-06');
        $product = Product::factory()->create();

        $this->assertSame(0.0, $product->plannedQuantityForWeek($week));
    }

    public function test_available_for_week_scope_includes_only_products_with_positive_stock(): void
    {
        $week = Carbon::parse('2026-04-06');
        $inStock = Product::factory()->create(['is_available' => true, 'is_published' => true]);
        $outOfStock = Product::factory()->create(['is_available' => true, 'is_published' => true]);
        $noRow = Product::factory()->create(['is_available' => true, 'is_published' => true]);

        ProductWeekStock::create([
            'product_id' => $inStock->id,
            'week_start_date' => $week->toDateString(),
            'planned_quantity' => 5,
        ]);
        ProductWeekStock::create([
            'product_id' => $outOfStock->id,
            'week_start_date' => $week->toDateString(),
            'planned_quantity' => 0,
        ]);

        $ids = Product::query()->availableForWeek($week)->pluck('id')->all();

        $this->assertContains($inStock->id, $ids);
        $this->assertNotContains($outOfStock->id, $ids);
        $this->assertNotContains($noRow->id, $ids);
    }
}
