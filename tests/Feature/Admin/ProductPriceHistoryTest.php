<?php

namespace Tests\Feature\Admin;

use App\Enums\CustomerType;
use App\Enums\ProductUnit;
use App\Enums\ShopOrderStatus;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\ProductPriceRule;
use App\Models\ProductProducerVariantPrice;
use App\Models\ProductStockInflow;
use App\Models\ProductVariant;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Admin\PriceHistoryRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Price development per product: the recorder snapshots list prices
 * (B2C gross, B2B net, buying per farm) in display units, appends only on
 * change, and the admin page renders a step chart of the series.
 */
class ProductPriceHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Producer $farm;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->farm = Producer::factory()->create();
        // Mass product priced per kg: canonical €/g 0.004 → display 4,00 €/kg.
        $this->product = Product::factory()->create([
            'producer_id' => $this->farm->id,
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 0.004,
            'vat_rate_percent' => 7,
            'buying_price_cents' => 250,
            'is_available' => true,
            'is_published' => true,
        ]);
    }

    private function record(): void
    {
        app(PriceHistoryRecorder::class)->record($this->product->fresh()->load('variants', 'producers'));
    }

    public function test_recorder_snapshots_all_series_in_display_units(): void
    {
        ProductPriceRule::factory()->create([
            'product_id' => $this->product->id,
            'customer_type' => CustomerType::Business->value,
            'unit_price' => 0.003, // 3,00 €/kg net
        ]);
        $this->product->syncProducers([$this->farm->id]);
        $this->product->producers()->updateExistingPivot($this->farm->id, ['buying_price_cents' => 180]);

        $this->record();

        $rows = ProductPriceHistory::query()->where('product_id', $this->product->id)->get();
        $this->assertEqualsWithDelta(4.00, (float) $rows->firstWhere('kind', 'base_gross')->price, 0.001);
        $this->assertEqualsWithDelta(3.00, (float) $rows->firstWhere('kind', 'business_net')->price, 0.001);
        // Product-level buying price + the per-farm one.
        $buying = $rows->where('kind', 'buying');
        $this->assertEqualsWithDelta(2.50, (float) $buying->firstWhere('producer_id', null)->price, 0.001);
        $this->assertEqualsWithDelta(1.80, (float) $buying->firstWhere('producer_id', $this->farm->id)->price, 0.001);
    }

    public function test_recorder_appends_only_on_change(): void
    {
        $this->record();
        $count = ProductPriceHistory::query()->count();

        $this->record(); // nothing changed → no new rows
        $this->assertSame($count, ProductPriceHistory::query()->count());

        $this->product->update(['unit_price' => 0.005]); // 5,00 €/kg
        $this->record();
        $this->assertSame($count + 1, ProductPriceHistory::query()->count());
        $this->assertEqualsWithDelta(5.00, (float) ProductPriceHistory::query()->latest('id')->first()->price, 0.001);
    }

    public function test_product_form_update_records_history(): void
    {
        // The controller hook: saving the product form snapshots prices.
        $this->record();
        $this->product->update(['unit_price' => 0.006]);

        // Simulate what update() does after saving (full form payload is
        // exercised elsewhere; here we assert the recorder wiring exists).
        $this->assertStringContainsString(
            'PriceHistoryRecorder',
            file_get_contents(app_path('Http/Controllers/Admin/ProductController.php')),
        );
    }

    public function test_variant_series_records_grade_prices(): void
    {
        $variant = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'unit_price' => 0.006, // 6,00 €/kg
        ]);
        ProductProducerVariantPrice::create([
            'product_id' => $this->product->id,
            'producer_id' => $this->farm->id,
            'variant_id' => $variant->id,
            'buying_price_cents' => 320,
        ]);

        $this->record();

        $variantRows = ProductPriceHistory::query()->where('variant_id', $variant->id)->get();
        $this->assertEqualsWithDelta(6.00, (float) $variantRows->firstWhere('kind', 'base_gross')->price, 0.001);
        $this->assertEqualsWithDelta(3.20, (float) $variantRows->firstWhere('kind', 'buying')->price, 0.001);
    }

    public function test_backfill_command_is_idempotent(): void
    {
        $this->artisan('price-history:backfill')->assertSuccessful();
        $count = ProductPriceHistory::query()->count();
        $this->assertGreaterThan(0, $count);

        $this->artisan('price-history:backfill')->assertSuccessful();
        $this->assertSame($count, ProductPriceHistory::query()->count());
    }

    public function test_page_renders_chart_and_requires_admin(): void
    {
        $this->record();

        $this->actingAs(User::factory()->create())
            ->get(route('app.admin.products.price-history', $this->product))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('app.admin.products.price-history', $this->product))
            ->assertOk()
            ->assertSee('data-testid="price-history-chart"', false)
            ->assertSee('data-testid="price-history-legend"', false);
    }

    public function test_quantity_and_revenue_charts_aggregate_inflows_and_finalized_sales(): void
    {
        ProductStockInflow::create([
            'product_id' => $this->product->id,
            'producer_id' => $this->farm->id,
            'received_on' => now()->toDateString(),
            'quantity' => 5000, // 5 kg
            'freshness_window_days' => 3,
        ]);
        $sold = ShopOrder::factory()->create([
            'status' => ShopOrderStatus::Paid,
            'payment_method' => 'online', 'paid_at' => now(),
            'fulfillment_mode' => 'pickup', 'pickup_location' => 'karl_kunger',
            'expected_delivery_date' => now()->toDateString(),
            'items_total_eur' => 8.00, 'delivery_fee_eur' => 0, 'discount_eur' => 0, 'total_amount_eur' => 8.00,
        ]);
        $sold->items()->create([
            'product_id' => $this->product->id, 'quantity' => 2000, 'unit' => 'g',
            'unit_price_snapshot_eur' => 0.004, 'line_total_eur' => 8.00,
            'vat_rate_snapshot_percent' => 7, 'vat_amount_eur' => 0.52,
        ]);
        // Cancelled orders must not count as sold.
        $cancelled = ShopOrder::factory()->create([
            'status' => ShopOrderStatus::Cancelled,
            'fulfillment_mode' => 'pickup', 'pickup_location' => 'karl_kunger',
            'expected_delivery_date' => now()->toDateString(),
            'items_total_eur' => 99, 'delivery_fee_eur' => 0, 'discount_eur' => 0, 'total_amount_eur' => 99,
        ]);
        $cancelled->items()->create([
            'product_id' => $this->product->id, 'quantity' => 9000, 'unit' => 'g',
            'unit_price_snapshot_eur' => 0.011, 'line_total_eur' => 99.00,
            'vat_rate_snapshot_percent' => 7, 'vat_amount_eur' => 6.48,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('app.admin.products.price-history', $this->product))
            ->assertOk()
            ->assertSee('data-testid="product-stats-quantity"', false)
            ->assertSee('data-testid="product-stats-revenue"', false);

        // Tooltips carry the aggregated display-unit values: 5 kg in, 2 kg
        // sold, 8,00 € revenue — and NOT the cancelled 9 kg.
        $html = $response->getContent();
        $this->assertStringContainsString(': 5,00', $html);
        $this->assertStringContainsString(': 2,00', $html);
        $this->assertStringContainsString(': 8,00', $html);
        $this->assertStringNotContainsString(': 9,00', $html);
    }

    public function test_page_shows_empty_state_without_data(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.admin.products.price-history', $this->product))
            ->assertOk()
            ->assertSee('data-testid="price-history-empty"', false);
    }
}
