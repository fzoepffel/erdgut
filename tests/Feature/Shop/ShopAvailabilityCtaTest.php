<?php

namespace Tests\Feature\Shop;

use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Services\Shop\ShopOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The shop's product- and producer-detail pages carry an availability CTA
 * that sends a guest back into the storefront: an order button when the
 * product is in stock this ISO week, an "available again from {date}" link
 * when the next inflow is later, and a muted "unavailable" chip when no
 * future inflow exists at all.
 */
class ShopAvailabilityCtaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Fix "today" to a Wednesday so this-week vs next-week is deterministic:
        // ISO week is Mon 2026-06-01 … Sun 2026-06-07.
        Carbon::setTestNow('2026-06-03 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function publishedProduct(?Producer $producer = null): Product
    {
        $factory = Product::factory();
        if ($producer) {
            $factory = $factory->for($producer);
        }

        return $factory->create([
            'category' => 'produce',
            'is_available' => true,
            'is_published' => true,
        ]);
    }

    private function inflow(Product $product, string $receivedOn, int $freshnessDays = 1): void
    {
        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => $receivedOn,
            'original_received_on' => $receivedOn,
            'quantity' => 1000.0,
            'freshness_window_days' => $freshnessDays,
        ]);
    }

    public function test_service_classifies_this_week_future_and_none(): void
    {
        $service = app(ShopOrderService::class);

        $thisWeek = $this->publishedProduct();
        $this->inflow($thisWeek, '2026-06-05'); // Fri, current week

        $future = $this->publishedProduct();
        $this->inflow($future, '2026-06-10'); // Wed next week

        $none = $this->publishedProduct();

        $this->assertSame('this_week', $service->productAvailabilityCta($thisWeek)['state']);

        $futureCta = $service->productAvailabilityCta($future);
        $this->assertSame('future', $futureCta['state']);
        $this->assertSame('2026-06-10', $futureCta['date']->toDateString());

        $noneCta = $service->productAvailabilityCta($none);
        $this->assertSame('none', $noneCta['state']);
        $this->assertNull($noneCta['date']);
    }

    public function test_product_page_shows_order_button_when_available_this_week(): void
    {
        $product = $this->publishedProduct();
        $this->inflow($product, '2026-06-05');

        $this->get(route('public.shop.products.show', $product->fresh()))
            ->assertOk()
            ->assertSee('data-testid="shop-cta-available"', false)
            ->assertSee(route('public.shop.index', ['date' => '2026-06-05']), false);
    }

    public function test_product_page_shows_available_again_when_future(): void
    {
        $product = $this->publishedProduct();
        $this->inflow($product, '2026-06-10');

        $this->get(route('public.shop.products.show', $product->fresh()))
            ->assertOk()
            ->assertSee('data-testid="shop-cta-future"', false)
            ->assertSee(route('public.shop.index', ['date' => '2026-06-10']), false)
            ->assertDontSee('data-testid="shop-cta-available"', false);
    }

    public function test_product_page_shows_unavailable_when_no_inflow(): void
    {
        $product = $this->publishedProduct();

        $this->get(route('public.shop.products.show', $product->fresh()))
            ->assertOk()
            ->assertSee('data-testid="shop-cta-unavailable"', false)
            ->assertDontSee('data-testid="shop-cta-available"', false);
    }

    public function test_producer_page_shows_cta_per_product(): void
    {
        $producer = Producer::factory()->create();

        $available = $this->publishedProduct($producer->fresh());
        $this->inflow($available, '2026-06-05');

        $this->get(route('public.shop.producers.show', $producer->fresh()))
            ->assertOk()
            ->assertSee('data-testid="shop-cta-available"', false);
    }

    public function test_storefront_product_links_open_in_same_tab(): void
    {
        $product = $this->publishedProduct();
        $this->inflow($product, '2026-06-05');

        $html = $this->get(route('public.shop.index', ['date' => '2026-06-05']))
            ->assertOk()
            ->getContent();

        // The product card link must not force a new tab.
        $needle = 'href="'.route('public.shop.products.show', $product->fresh()).'"';
        $this->assertStringContainsString($needle, $html);
        $pos = strpos($html, $needle);
        $snippet = substr($html, max(0, $pos - 200), 400);
        $this->assertStringNotContainsString('target="_blank"', $snippet);
    }
}
