<?php

namespace Tests\Feature\Shop;

use App\Models\Producer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
