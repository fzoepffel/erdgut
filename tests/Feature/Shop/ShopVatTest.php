<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductUnit;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ShopOrder;
use App\Services\Shop\ShopOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Per-product VAT (7% reduced / 19% standard).
 *
 * VAT is the tax *contained* in the gross prices (prices are consumer
 * gross): vat = gross * rate / (100 + rate). The service computes it per
 * line in quote() and snapshots it on each ShopOrderItem in createOrder().
 */
class ShopVatTest extends TestCase
{
    use RefreshDatabase;

    private const DELIVERY_DATE = '2026-06-05';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-30 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function makeProduct(float $unitPrice, int $vatRate): Product
    {
        $product = Product::factory()->create([
            'producer_id' => Producer::factory()->create()->id,
            'category' => 'produce',
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => $unitPrice,
            'vat_rate_percent' => $vatRate,
            'is_available' => true,
            'is_published' => true,
        ]);

        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => self::DELIVERY_DATE,
            'quantity' => 50,
            'freshness_window_days' => 1,
        ]);

        return $product;
    }

    public function test_quote_computes_contained_vat_at_reduced_rate(): void
    {
        $product = $this->makeProduct(unitPrice: 4.00, vatRate: 7);

        $quote = app(ShopOrderService::class)->quote(
            [['product_id' => $product->id, 'quantity' => 3]],
            'pickup',
        );

        // 3 × 4.00 = 12.00 gross. VAT contained = 12.00 × 7 / 107 = 0.7850… → 0.79.
        $line = $quote['lines'][0];
        $this->assertSame(7, $line['vat_rate_percent']);
        $this->assertSame(0.79, $line['vat_amount_eur']);
    }

    public function test_quote_computes_contained_vat_at_standard_rate(): void
    {
        $product = $this->makeProduct(unitPrice: 5.00, vatRate: 19);

        $quote = app(ShopOrderService::class)->quote(
            [['product_id' => $product->id, 'quantity' => 2]],
            'pickup',
        );

        // 2 × 5.00 = 10.00 gross. VAT contained = 10.00 × 19 / 119 = 1.5966… → 1.60.
        $line = $quote['lines'][0];
        $this->assertSame(19, $line['vat_rate_percent']);
        $this->assertSame(1.60, $line['vat_amount_eur']);
    }

    public function test_create_order_snapshots_vat_per_line(): void
    {
        $reduced = $this->makeProduct(unitPrice: 4.00, vatRate: 7);
        $standard = $this->makeProduct(unitPrice: 5.00, vatRate: 19);

        $service = app(ShopOrderService::class);
        $service->createOrder([
            'name' => 'Mixed Cart',
            'email' => 'mixed@example.com',
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'payment_method' => 'online',
            'expected_delivery_date' => self::DELIVERY_DATE,
            'items' => [
                ['product_id' => $reduced->id, 'quantity' => 3],  // 12.00 @ 7%
                ['product_id' => $standard->id, 'quantity' => 2], // 10.00 @ 19%
            ],
        ]);

        $order = ShopOrder::query()->latest('id')->firstOrFail();
        $this->assertSame(2, $order->items()->count());

        $reducedItem = $order->items()->where('product_id', $reduced->id)->firstOrFail();
        $this->assertSame(7, (int) $reducedItem->vat_rate_snapshot_percent);
        $this->assertSame('0.79', (string) $reducedItem->vat_amount_eur);

        $standardItem = $order->items()->where('product_id', $standard->id)->firstOrFail();
        $this->assertSame(19, (int) $standardItem->vat_rate_snapshot_percent);
        $this->assertSame('1.60', (string) $standardItem->vat_amount_eur);
    }

    public function test_delivery_fee_vat_follows_dominant_basket_rate(): void
    {
        // Two distinct 19% lines vs one 7% line → the dominant rate (by line
        // count) is 19%, so the delivery fee VAT should use 19%.
        $standardA = $this->makeProduct(unitPrice: 5.00, vatRate: 19);
        $standardB = $this->makeProduct(unitPrice: 6.00, vatRate: 19);
        $reduced = $this->makeProduct(unitPrice: 4.00, vatRate: 7);

        $service = app(ShopOrderService::class);
        $service->createOrder([
            'name' => 'Delivery Cart',
            'email' => 'delivery@example.com',
            'fulfillment_mode' => 'delivery',
            'phone' => '+49 30 12345678',
            'delivery_street' => 'Musterstraße 12',
            'delivery_postal_code' => '10115',
            'delivery_city' => 'Berlin',
            'payment_method' => 'online',
            'expected_delivery_date' => self::DELIVERY_DATE,
            'items' => [
                ['product_id' => $standardA->id, 'quantity' => 2],
                ['product_id' => $standardB->id, 'quantity' => 1],
                ['product_id' => $reduced->id, 'quantity' => 1],
            ],
        ]);

        $order = ShopOrder::query()->latest('id')->firstOrFail();
        $this->assertSame(19, (int) $order->delivery_fee_vat_rate_percent);
        // 2.90 delivery fee × 19 / 119 = 0.4630… → 0.46.
        $this->assertSame('0.46', (string) $order->delivery_fee_vat_amount_eur);
    }
}
