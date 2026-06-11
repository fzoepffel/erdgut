<?php

namespace Tests\Feature\Shop;

use App\Enums\ShopOrderStatus;
use App\Models\Product;
use App\Models\ProductEngagementDaily;
use App\Models\ShopOrder;
use App\Services\Shop\ProductRankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShopRankingTest extends TestCase
{
    use RefreshDatabase;

    private function row(Product $product, bool $orderable = true): array
    {
        return [
            'product' => $product,
            'remaining' => 100.0,
            'available_on_selected' => $orderable,
            'available_dates' => [],
        ];
    }

    /** Seed engagement: impressions/add-to-carts today, and a purchase. */
    private function seedEngagement(Product $product, int $impressions, int $carts): void
    {
        ProductEngagementDaily::create([
            'product_id' => $product->id,
            'day' => Carbon::today()->toDateString(),
            'impressions' => $impressions,
            'add_to_carts' => $carts,
        ]);
    }

    public function test_proven_high_value_product_beats_proven_low_value_product(): void
    {
        config(['shop.ranking.enabled' => true, 'shop.ranking.objective' => 'revenue']);

        $winner = Product::factory()->create(['unit_price' => 20]);
        $loser = Product::factory()->create(['unit_price' => 20]);

        // Winner: strong cart rate; loser: many impressions, few carts.
        $this->seedEngagement($winner, impressions: 100, carts: 40);
        $this->seedEngagement($loser, impressions: 100, carts: 2);

        // Give both a realized value per cart via an order each.
        $this->createPaidOrderLine($winner, 40, 800.0); // ~20/cart
        $this->createPaidOrderLine($loser, 2, 40.0);

        $service = app(ProductRankingService::class);
        $rows = collect([$this->row($loser), $this->row($winner)]);

        // Across many seeds the winner should top the large majority of the time.
        $winnerFirst = 0;
        for ($seed = 1; $seed <= 60; $seed++) {
            $ordered = $service->orderForStorefront($rows, $seed);
            if ($ordered->first()['product']->id === $winner->id) {
                $winnerFirst++;
            }
        }

        $this->assertGreaterThan(45, $winnerFirst, "Winner topped only {$winnerFirst}/60 — exploitation too weak.");
    }

    public function test_brand_new_product_still_gets_explored_to_the_top_sometimes(): void
    {
        config(['shop.ranking.enabled' => true, 'shop.ranking.objective' => 'revenue']);

        $established = Product::factory()->create(['unit_price' => 20]);
        $fresh = Product::factory()->create(['unit_price' => 20]);

        // Established has a solid record; fresh has zero engagement data.
        $this->seedEngagement($established, impressions: 200, carts: 30);
        $this->createPaidOrderLine($established, 30, 600.0);

        $service = app(ProductRankingService::class);
        $rows = collect([$this->row($established), $this->row($fresh)]);

        $freshFirst = 0;
        for ($seed = 1; $seed <= 80; $seed++) {
            $ordered = $service->orderForStorefront($rows, $seed);
            if ($ordered->first()['product']->id === $fresh->id) {
                $freshFirst++;
            }
        }

        // Exploration: the new product should reach the top sometimes, but not always.
        $this->assertGreaterThan(0, $freshFirst, 'New product was never explored to the top.');
        $this->assertLessThan(80, $freshFirst, 'New product always topped — no exploitation.');
    }

    public function test_same_seed_is_reproducible(): void
    {
        config(['shop.ranking.enabled' => true]);

        $a = Product::factory()->create();
        $b = Product::factory()->create();
        $c = Product::factory()->create();
        $this->seedEngagement($a, 50, 5);
        $this->seedEngagement($b, 50, 10);

        $service = app(ProductRankingService::class);
        $rows = collect([$this->row($a), $this->row($b), $this->row($c)]);

        $first = $service->orderForStorefront($rows, 4242)->pluck('product.id')->all();
        $second = $service->orderForStorefront($rows, 4242)->pluck('product.id')->all();

        $this->assertSame($first, $second);
    }

    public function test_feature_flag_off_keeps_orderable_first_legacy_order(): void
    {
        config(['shop.ranking.enabled' => false]);

        $orderable = Product::factory()->create();
        $greyed = Product::factory()->create();

        $service = app(ProductRankingService::class);
        $rows = collect([$this->row($greyed, orderable: false), $this->row($orderable, orderable: true)]);

        $ordered = $service->orderForStorefront($rows, 1);

        $this->assertTrue($ordered->first()['available_on_selected']);
        $this->assertFalse($ordered->last()['available_on_selected']);
    }

    public function test_greyed_products_always_sort_behind_orderable(): void
    {
        config(['shop.ranking.enabled' => true]);

        $orderable = Product::factory()->create();
        $greyed = Product::factory()->create();
        $this->seedEngagement($greyed, 500, 200); // high engagement, but not orderable today

        $service = app(ProductRankingService::class);
        $rows = collect([$this->row($greyed, orderable: false), $this->row($orderable, orderable: true)]);

        for ($seed = 1; $seed <= 20; $seed++) {
            $ordered = $service->orderForStorefront($rows, $seed);
            $this->assertSame($orderable->id, $ordered->first()['product']->id);
        }
    }

    private function createPaidOrderLine(Product $product, float $quantity, float $lineTotal): void
    {
        $order = ShopOrder::create([
            'name' => 'Test', 'email' => 't@example.com', 'phone' => null,
            'fulfillment_mode' => 'pickup', 'expected_delivery_date' => Carbon::tomorrow()->toDateString(),
            'pickup_location' => 'karl_kunger',
            'items_total_eur' => $lineTotal, 'delivery_fee_eur' => 0, 'total_amount_eur' => $lineTotal,
            'payment_method' => 'online', 'status' => ShopOrderStatus::Paid,
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'quantity' => $quantity, 'unit' => 'piece',
            'unit_price_snapshot_eur' => $lineTotal / max(1, $quantity), 'line_total_eur' => $lineTotal,
            'vat_rate_snapshot_percent' => 7, 'vat_amount_eur' => round($lineTotal * 7 / 107, 2),
        ]);
    }
}
