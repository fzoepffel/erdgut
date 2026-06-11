<?php

namespace Tests\Feature\Shop;

use App\Models\Product;
use App\Models\ProductEngagementDaily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShopEngagementBeaconTest extends TestCase
{
    use RefreshDatabase;

    public function test_beacon_increments_per_product_daily_counters(): void
    {
        config(['shop.ranking.enabled' => true]);
        $a = Product::factory()->create();
        $b = Product::factory()->create();

        $response = $this->postJson(route('public.shop.engagement'), [
            'events' => [
                ['product_id' => $a->id, 'type' => 'impression'],
                ['product_id' => $a->id, 'type' => 'impression'],
                ['product_id' => $a->id, 'type' => 'add_to_cart'],
                ['product_id' => $b->id, 'type' => 'impression'],
            ],
        ]);

        $response->assertNoContent();

        $today = Carbon::today()->toDateString();
        $rowA = ProductEngagementDaily::where('product_id', $a->id)->whereDate('day', $today)->first();
        $rowB = ProductEngagementDaily::where('product_id', $b->id)->whereDate('day', $today)->first();

        $this->assertSame(2, $rowA->impressions);
        $this->assertSame(1, $rowA->add_to_carts);
        $this->assertSame(1, $rowB->impressions);
        $this->assertSame(0, $rowB->add_to_carts);
    }

    public function test_repeated_beacons_accumulate_into_the_same_day_row(): void
    {
        config(['shop.ranking.enabled' => true]);
        $p = Product::factory()->create();

        $this->postJson(route('public.shop.engagement'), ['events' => [['product_id' => $p->id, 'type' => 'impression']]])->assertNoContent();
        $this->postJson(route('public.shop.engagement'), ['events' => [['product_id' => $p->id, 'type' => 'impression']]])->assertNoContent();

        $this->assertSame(1, ProductEngagementDaily::where('product_id', $p->id)->count());
        $this->assertSame(2, ProductEngagementDaily::where('product_id', $p->id)->first()->impressions);
    }

    public function test_unknown_or_unpublished_product_ids_are_dropped(): void
    {
        config(['shop.ranking.enabled' => true]);
        $hidden = Product::factory()->create(['is_published' => false]);

        $this->postJson(route('public.shop.engagement'), [
            'events' => [
                ['product_id' => 999999, 'type' => 'impression'],
                ['product_id' => $hidden->id, 'type' => 'impression'],
            ],
        ])->assertNoContent();

        $this->assertSame(0, ProductEngagementDaily::count());
    }

    public function test_validation_rejects_bad_payloads(): void
    {
        config(['shop.ranking.enabled' => true]);

        $this->postJson(route('public.shop.engagement'), ['events' => [['product_id' => 1, 'type' => 'bogus']]])
            ->assertStatus(422);
        $this->postJson(route('public.shop.engagement'), ['events' => 'nope'])
            ->assertStatus(422);
    }

    public function test_disabled_flag_is_a_noop(): void
    {
        config(['shop.ranking.enabled' => false]);
        $p = Product::factory()->create();

        $this->postJson(route('public.shop.engagement'), ['events' => [['product_id' => $p->id, 'type' => 'impression']]])
            ->assertNoContent();

        $this->assertSame(0, ProductEngagementDaily::count());
    }
}
