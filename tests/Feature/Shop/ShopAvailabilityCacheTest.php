<?php

namespace Tests\Feature\Shop;

use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Services\Admin\InventoryService;
use App\Support\InventoryCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopAvailabilityCacheTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InventoryService::class);
    }

    public function test_matrix_is_cached_until_version_bumps(): void
    {
        $product = Product::factory()->create();
        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => '2026-05-25',
            'quantity' => 1000,
            'freshness_window_days' => 1,
        ]);

        $from = Carbon::parse('2026-05-25');
        $to = Carbon::parse('2026-05-25');
        $products = Product::query()->whereKey($product->id)->get();

        // Warm the cache.
        $first = $this->service->availabilityMatrix($products, $from, $to);
        $this->assertEqualsWithDelta(1000.0, $first[$product->id]['2026-05-25'], 0.001);

        // Mutate the underlying data WITHOUT bumping the version (raw delete
        // skips Eloquent events). The cached value must persist.
        DB::table('product_stock_inflows')->where('product_id', $product->id)->delete();

        $stillCached = $this->service->availabilityMatrix($products, $from, $to);
        $this->assertEqualsWithDelta(1000.0, $stillCached[$product->id]['2026-05-25'], 0.001, 'Expected the stale cached value before invalidation.');

        // Bump invalidates → recompute reflects the deletion (no stock left).
        InventoryCache::bump();
        $afterBump = $this->service->availabilityMatrix($products, $from, $to);
        $this->assertSame(0.0, (float) ($afterBump[$product->id]['2026-05-25'] ?? 0.0));
    }

    public function test_inflow_write_bumps_cache_version_via_observer(): void
    {
        $before = InventoryCache::version();

        $product = Product::factory()->create();          // Product save → bump
        ProductStockInflow::create([                       // inflow save → bump
            'product_id' => $product->id,
            'received_on' => '2026-05-25',
            'quantity' => 500,
            'freshness_window_days' => 1,
        ]);

        $this->assertGreaterThan($before, InventoryCache::version());
    }

    public function test_warm_cache_serves_matrix_without_further_queries(): void
    {
        $product = Product::factory()->create();
        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => '2026-05-25',
            'quantity' => 1000,
            'freshness_window_days' => 3,
        ]);

        $from = Carbon::parse('2026-05-24');
        $to = Carbon::parse('2026-05-30');
        $products = Product::query()->whereKey($product->id)->get();

        // First call computes (issues queries).
        $this->service->availabilityMatrix($products, $from, $to);

        // Second identical call must be served from cache: zero DB queries.
        DB::enableQueryLog();
        $this->service->availabilityMatrix($products, $from, $to);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queries, 'Warm availability matrix should hit cache, not the database.');
    }
}
