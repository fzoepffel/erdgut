<?php

namespace Tests\Feature\Admin;

use App\Enums\CustomerType;
use App\Enums\ProductUnit;
use App\Enums\ShopOrderStatus;
use App\Enums\ShopStockAudience;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ShopOrder;
use App\Services\Admin\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Which orders hold stock: the abandoned-checkout grace window (30 min)
 * applies ONLY to online pending payments. In-person orders (admin manual
 * orders, storefront pay-at-pickup) stay pending until handover and must
 * hold their stock the whole time — the regression that let an admin
 * B2B order silently stop counting after 30 minutes ("5 Bund Zitronen-
 * verbene still shown after ordering 8").
 */
class InventoryCommitmentTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Carbon $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->date = Carbon::tomorrow();
        Producer::factory()->create();
        $this->product = Product::factory()->create([
            'unit' => ProductUnit::Bunch->value,
            'price_display_unit' => 'bunch',
            'unit_price' => 1.90,
            'vat_rate_percent' => 7,
            'is_available' => true,
            'is_published' => true,
        ]);
        ProductStockInflow::create([
            'product_id' => $this->product->id,
            'received_on' => $this->date->toDateString(),
            'quantity' => 5,
            'freshness_window_days' => 3,
        ]);
    }

    private function order(array $overrides = [], float $qty = 8): ShopOrder
    {
        $order = ShopOrder::factory()->create(array_merge([
            'status' => ShopOrderStatus::PendingPayment,
            'payment_method' => 'in_person',
            'customer_type' => CustomerType::Business,
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'expected_delivery_date' => $this->date->toDateString(),
            'items_total_eur' => 16.26,
            'delivery_fee_eur' => 0,
            'discount_eur' => 0,
            'total_amount_eur' => 16.26,
        ], $overrides));

        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => $qty,
            'unit' => 'bunch',
            'unit_price_snapshot_eur' => 2.03,
            'line_total_eur' => round(2.03 * $qty, 2),
            'vat_rate_snapshot_percent' => 7,
            'vat_amount_eur' => round(2.03 * $qty * 7 / 107, 2),
        ]);

        return $order->refresh();
    }

    private function remaining(ShopStockAudience $audience = ShopStockAudience::Consumer): float
    {
        return app(InventoryService::class)->availableOnDate($this->product, $this->date, $audience);
    }

    public function test_old_in_person_admin_b2b_order_still_holds_stock(): void
    {
        // Fred's exact case: 5 Bund in stock, admin B2B order for 8 Bund,
        // created hours ago — must clamp availability to 0, not bounce
        // back to 5 after the 30-minute grace window.
        $order = $this->order();
        $order->forceFill(['created_at' => now()->subHours(2)])->save();

        $this->assertTrue($order->refresh()->commitsStockNow());
        $this->assertEqualsWithDelta(0.0, $this->remaining(), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->remaining(ShopStockAudience::BusinessTrade), 0.001);
    }

    public function test_old_in_person_storefront_pickup_order_holds_stock(): void
    {
        $order = $this->order(['customer_type' => CustomerType::Private], 3);
        $order->forceFill(['created_at' => now()->subDay()])->save();

        $this->assertTrue($order->refresh()->commitsStockNow());
        $this->assertEqualsWithDelta(2.0, $this->remaining(), 0.001);
    }

    public function test_old_online_pending_order_is_still_released_as_abandoned(): void
    {
        $order = $this->order(['payment_method' => 'online', 'customer_type' => CustomerType::Private], 3);
        $order->forceFill(['created_at' => now()->subMinutes(45)])->save();

        $this->assertFalse($order->refresh()->commitsStockNow());
        $this->assertEqualsWithDelta(5.0, $this->remaining(), 0.001);
    }

    public function test_fresh_online_pending_order_holds_stock(): void
    {
        $order = $this->order(['payment_method' => 'online', 'customer_type' => CustomerType::Private], 3);
        $order->forceFill(['created_at' => now()->subMinutes(5)])->save();

        $this->assertTrue($order->refresh()->commitsStockNow());
        $this->assertEqualsWithDelta(2.0, $this->remaining(), 0.001);
    }

    public function test_cancelled_in_person_order_releases_stock(): void
    {
        $order = $this->order();
        $order->forceFill(['created_at' => now()->subHours(2), 'status' => ShopOrderStatus::Cancelled])->save();

        $this->assertFalse($order->refresh()->commitsStockNow());
        $this->assertEqualsWithDelta(5.0, $this->remaining(), 0.001);
    }
}
