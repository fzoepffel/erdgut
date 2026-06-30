<?php

namespace Tests\Unit;

use App\Enums\CustomerType;
use App\Enums\ProductUnit;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Models\ProductStockInflow;
use App\Models\ShopClosedDay;
use App\Services\Restaurant\RestaurantCatalogService;
use App\Services\Shop\ShopOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RestaurantCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): RestaurantCatalogService
    {
        return app(RestaurantCatalogService::class);
    }

    private function product(array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 10.00,
            'vat_rate_percent' => 7,
            'is_seasonal' => false,
            'is_available' => true,
            'is_published' => true,
        ], $attrs));
    }

    private function stock(Product $product, Carbon $date, float $qty = 50): void
    {
        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => $date->toDateString(),
            'quantity' => $qty,
            'freshness_window_days' => 3,
        ]);
    }

    public function test_lists_in_stock_or_in_season_and_excludes_neither(): void
    {
        $date = Carbon::parse('2026-09-15');
        Producer::factory()->create();

        $inStock = $this->product();
        $this->stock($inStock, $date);

        $inSeasonOnly = $this->product([
            'is_seasonal' => true,
            'season_start_month' => 8, 'season_start_day' => 1,
            'season_end_month' => 10, 'season_end_day' => 31,
        ]);

        $offSeasonNoStock = $this->product([
            'is_seasonal' => true,
            'season_start_month' => 1, 'season_start_day' => 1,
            'season_end_month' => 2, 'season_end_day' => 28,
        ]);

        $rows = $this->service()->productsForDate($date);
        $ids = $rows->pluck('product.id');

        $this->assertTrue($ids->contains($inStock->id));
        $this->assertTrue($ids->contains($inSeasonOnly->id));
        $this->assertFalse($ids->contains($offSeasonNoStock->id));

        $inStockRow = $rows->firstWhere('product.id', $inStock->id);
        $this->assertTrue($inStockRow['inStock']);

        $onRequestRow = $rows->firstWhere('product.id', $inSeasonOnly->id);
        $this->assertFalse($onRequestRow['inStock']);
        $this->assertTrue($onRequestRow['inSeason']);
    }

    public function test_net_price_is_gross_minus_vat(): void
    {
        $date = Carbon::parse('2026-09-15');
        Producer::factory()->create();
        $product = $this->product(['unit_price' => 10.00, 'vat_rate_percent' => 7]);
        $this->stock($product, $date);

        $row = $this->service()->productsForDate($date)->firstWhere('product.id', $product->id);

        $expectedNet = round(10.00 - ShopOrderService::vatAmountFromGross(10.00, 7), 2);
        $this->assertSame(10.00, $row['grossPerDisplay']);
        $this->assertSame($expectedNet, $row['netPerDisplay']); // 9.35
        $this->assertSame(7, $row['vatRate']);
    }

    public function test_business_price_rule_overrides_base(): void
    {
        $date = Carbon::parse('2026-09-15');
        Producer::factory()->create();
        $product = $this->product(['unit_price' => 10.00, 'vat_rate_percent' => 7]);
        $this->stock($product, $date);

        ProductPriceRule::create([
            'product_id' => $product->id,
            'customer_type' => CustomerType::Business->value,
            'unit_price' => 8.00,
            'priority' => 0,
            'is_active' => true,
        ]);

        $row = $this->service()->productsForDate($date)->firstWhere('product.id', $product->id);

        $this->assertSame(8.00, $row['grossPerDisplay']);
        $this->assertSame(round(8.00 - ShopOrderService::vatAmountFromGross(8.00, 7), 2), $row['netPerDisplay']);
    }

    public function test_selectable_dates_exclude_closed_days(): void
    {
        $closed = Carbon::tomorrow()->addDays(2);
        ShopClosedDay::create(['closed_on' => $closed->toDateString(), 'closes_at' => null]);

        $dates = $this->service()->selectableDates()->map(fn (array $r) => $r['date']->toDateString());

        $this->assertNotContains($closed->toDateString(), $dates->all());
        $this->assertContains(Carbon::tomorrow()->toDateString(), $dates->all());
    }
}
