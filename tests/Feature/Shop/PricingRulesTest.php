<?php

namespace Tests\Feature\Shop;

use App\Enums\CustomerType;
use App\Enums\ProductUnit;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Models\ProductStockInflow;
use App\Models\ProductVariant;
use App\Services\Shop\PriceResolver;
use App\Services\Shop\ShopOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PricingRulesTest extends TestCase
{
    use RefreshDatabase;

    private function product(float $price = 10.0): Product
    {
        return Product::factory()->create([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => $price,
            'buying_price_cents' => null,
            'is_available' => true,
            'is_published' => true,
        ]);
    }

    private function resolver(): PriceResolver
    {
        return app(PriceResolver::class);
    }

    public function test_falls_back_to_base_price_without_rules(): void
    {
        $p = $this->product(10.0);
        $r = $this->resolver()->resolve($p, Carbon::parse('2026-06-10'), CustomerType::Private);

        $this->assertSame(10.0, $r->unitPriceEur);
        $this->assertSame('base', $r->reason);
        $this->assertNull($r->ruleId);
    }

    public function test_active_period_override_applies(): void
    {
        $p = $this->product(10.0);
        $rule = ProductPriceRule::factory()->create([
            'product_id' => $p->id,
            'starts_on' => '2026-06-08',
            'ends_on' => '2026-06-12',
            'unit_price' => 7.5,
        ]);

        $r = $this->resolver()->resolve($p->fresh(), Carbon::parse('2026-06-10'), CustomerType::Private);
        $this->assertSame(7.5, $r->unitPriceEur);
        $this->assertSame('period_override', $r->reason);
        $this->assertSame($rule->id, $r->ruleId);
    }

    public function test_override_outside_window_is_ignored(): void
    {
        $p = $this->product(10.0);
        ProductPriceRule::factory()->create([
            'product_id' => $p->id,
            'starts_on' => '2026-06-08',
            'ends_on' => '2026-06-12',
            'unit_price' => 7.5,
        ]);

        $r = $this->resolver()->resolve($p->fresh(), Carbon::parse('2026-07-01'), CustomerType::Private);
        $this->assertSame(10.0, $r->unitPriceEur);
    }

    public function test_inactive_rule_is_ignored(): void
    {
        $p = $this->product(10.0);
        ProductPriceRule::factory()->create([
            'product_id' => $p->id,
            'unit_price' => 5.0,
            'is_active' => false,
        ]);

        $this->assertSame(10.0, $this->resolver()->resolve($p->fresh(), Carbon::today())->unitPriceEur);
    }

    public function test_business_price_applies_only_to_business(): void
    {
        $p = $this->product(10.0);
        ProductPriceRule::factory()->create([
            'product_id' => $p->id,
            'customer_type' => CustomerType::Business->value,
            'unit_price' => 8.0,
        ]);

        $business = $this->resolver()->resolve($p->fresh(), Carbon::today(), CustomerType::Business);
        $private = $this->resolver()->resolve($p->fresh(), Carbon::today(), CustomerType::Private);

        $this->assertSame(8.0, $business->unitPriceEur);
        $this->assertSame('customer_group', $business->reason);
        $this->assertSame(10.0, $private->unitPriceEur);
    }

    public function test_customer_specific_rule_beats_generic_by_specificity(): void
    {
        $p = $this->product(10.0);
        ProductPriceRule::factory()->create(['product_id' => $p->id, 'unit_price' => 9.0]); // generic
        ProductPriceRule::factory()->create([
            'product_id' => $p->id,
            'customer_type' => CustomerType::Business->value,
            'unit_price' => 6.0,
        ]);

        $this->assertSame(6.0, $this->resolver()->resolve($p->fresh(), Carbon::today(), CustomerType::Business)->unitPriceEur);
    }

    public function test_margin_floor_clamps_below_cost(): void
    {
        $p = Product::factory()->create([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 10.0,
            'buying_price_cents' => 800, // 8.00 per piece
        ]);
        ProductPriceRule::factory()->create([
            'product_id' => $p->id,
            'unit_price' => 5.0, // below cost
        ]);

        $r = $this->resolver()->resolve($p->fresh(), Carbon::today(), CustomerType::Private);
        $this->assertSame(8.0, $r->unitPriceEur);
        $this->assertSame('margin_floor', $r->reason);
    }

    public function test_order_snapshots_resolved_price_and_rule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-30 10:00:00'));
        $p = $this->product(10.0);
        ProductStockInflow::create([
            'product_id' => $p->id,
            'received_on' => '2026-06-05',
            'quantity' => 100,
            'freshness_window_days' => 1,
        ]);
        $rule = ProductPriceRule::factory()->create([
            'product_id' => $p->id,
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-30',
            'unit_price' => 6.0,
        ]);

        $order = app(ShopOrderService::class)->createOrder([
            'name' => 'Anna', 'email' => 'a@example.com',
            'fulfillment_mode' => 'pickup', 'pickup_location' => 'karl_kunger',
            'payment_method' => 'online', 'expected_delivery_date' => '2026-06-05',
            'items' => [['product_id' => $p->id, 'quantity' => 2]],
        ]);

        $item = $order->items()->first();
        $this->assertEquals(6.0, (float) $item->unit_price_snapshot_eur);
        $this->assertEquals(12.0, (float) $item->line_total_eur);
        $this->assertSame($rule->id, $item->price_rule_id);

        Carbon::setTestNow(null);
    }

    public function test_variant_base_price_overrides_product_base(): void
    {
        $p = $this->product(10.0);
        $variant = ProductVariant::factory()->create(['product_id' => $p->id, 'unit_price' => 6.0]);

        $r = $this->resolver()->resolve($p->fresh(), Carbon::today(), CustomerType::Private, null, $variant->id, $variant);

        $this->assertSame(6.0, $r->unitPriceEur);
        $this->assertSame('base', $r->reason);
        $this->assertNull($r->ruleId);
    }

    public function test_variant_inherits_product_price_when_null(): void
    {
        $p = $this->product(10.0);
        $variant = ProductVariant::factory()->create(['product_id' => $p->id, 'unit_price' => null]);

        $r = $this->resolver()->resolve($p->fresh(), Carbon::today(), CustomerType::Private, null, $variant->id, $variant);

        $this->assertSame(10.0, $r->unitPriceEur);
    }

    public function test_variant_scoped_rule_beats_general_rule(): void
    {
        $p = $this->product(10.0);
        $variant = ProductVariant::factory()->create(['product_id' => $p->id, 'unit_price' => 8.0]);
        ProductPriceRule::factory()->create(['product_id' => $p->id, 'unit_price' => 9.0]); // general
        ProductPriceRule::factory()->create([
            'product_id' => $p->id,
            'variant_id' => $variant->id,
            'unit_price' => 5.0,
        ]);

        $r = $this->resolver()->resolve($p->fresh(), Carbon::today(), CustomerType::Private, null, $variant->id, $variant);

        $this->assertSame(5.0, $r->unitPriceEur);
    }
}
