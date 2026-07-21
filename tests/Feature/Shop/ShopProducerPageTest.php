<?php

namespace Tests\Feature\Shop;

use App\Models\Producer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Shop-shell producer detail page (/shop/producers/{slug}): since the
 * multi-producer epic it lists ALL supplied products, including ones
 * where the farm is only a secondary pivot supplier.
 */
class ShopProducerPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_producer_page_lists_secondary_supplied_products(): void
    {
        $secondary = Producer::factory()->create();
        $primary = Producer::factory()->create();

        $product = Product::factory()->for($primary)->create(['is_available' => true, 'is_published' => true]);
        $product->translations()->where('locale', 'en')->update(['name' => 'Geteilte Pastinake']);
        $product->syncProducers([$primary->id, $secondary->id]);

        $this->get(route('public.shop.producers.show', $secondary))
            ->assertOk()
            ->assertSee('Geteilte Pastinake', false)
            ->assertSee('data-testid="producer-product-card"', false);
    }

    public function test_shop_producer_page_still_lists_primary_products(): void
    {
        $producer = Producer::factory()->create();
        $product = Product::factory()->for($producer)->create(['is_available' => true, 'is_published' => true]);
        $product->translations()->where('locale', 'en')->update(['name' => 'Eigene Karotte']);

        $this->get(route('public.shop.producers.show', $producer))
            ->assertOk()
            ->assertSee('Eigene Karotte', false);
    }

    /**
     * The availability CTAs must be resolved in one batched pass, not per
     * product day-by-day. Before batching, each product walked the whole
     * horizon calling availableOnDate() (~4 queries/day → hundreds of queries
     * for a handful of products). Pin a low ceiling that stays flat as the
     * product count grows so the N×days regression can't creep back.
     */
    public function test_shop_producer_page_resolves_availability_in_few_queries(): void
    {
        $producer = Producer::factory()->create();
        Product::factory()->count(6)->for($producer)->create(['is_available' => true, 'is_published' => true]);

        DB::enableQueryLog();
        $this->get(route('public.shop.producers.show', $producer))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(
            60,
            $count,
            "Producer page ran {$count} queries; availability CTAs should be batched, not per-product-per-day.",
        );
    }
}
