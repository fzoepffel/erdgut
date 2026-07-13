<?php

namespace Tests\Feature\Restaurant;

use App\Enums\ProductUnit;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\RestaurantProfile;
use App\Services\Restaurant\RestaurantInvoiceService;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * B2B multi-producer display (P4): catalog cards name the farms with B2B
 * stock on the date, the requests page lists all registered suppliers, and
 * the invoice names the farms the allocation says actually deliver.
 */
class RestaurantMultiProducerDisplayTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $date;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-30 10:00:00'));
        $this->date = Carbon::parse('2026-06-05');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function product(Producer $primary, array $farmIds, array $attrs = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'producer_id' => $primary->id,
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 10.00,
            'vat_rate_percent' => 7,
            'is_seasonal' => false,
            'is_available' => true,
            'is_published' => true,
        ], $attrs));
        $product->syncProducers($farmIds);

        return $product;
    }

    private function inflow(Product $product, Producer $farm, float $qty, bool $b2bOnly = false): void
    {
        ProductStockInflow::create([
            'product_id' => $product->id,
            'producer_id' => $farm->id,
            'received_on' => $this->date->toDateString(),
            'quantity' => $qty,
            'freshness_window_days' => 1,
            'b2b_only' => $b2bOnly,
        ]);
    }

    public function test_catalog_card_names_both_farms_including_b2b_only_stock(): void
    {
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = $this->product($farmA, [$farmA->id, $farmB->id]);
        $this->inflow($product, $farmA, 4);
        $this->inflow($product, $farmB, 50, b2bOnly: true);

        $user = RestaurantProfile::factory()->verified()->create()->user;

        $this->actingAs($user)
            ->get(route('app.restaurant.catalog', ['date' => $this->date->toDateString()]))
            ->assertOk()
            ->assertSee($farmB->name.' · '.$farmA->name);
    }

    public function test_requests_page_lists_all_registered_farms_without_stock(): void
    {
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        // Seasonal, zero inflows → surfaces on the requests season list.
        $this->product($farmA, [$farmA->id, $farmB->id], ['is_seasonal' => true]);
        // One stocked product so the date is selectable at all.
        $other = $this->product($farmA, [$farmA->id]);
        $this->inflow($other, $farmA, 10);

        $user = RestaurantProfile::factory()->verified()->create()->user;

        $this->actingAs($user)
            ->get(route('app.restaurant.requests.index', ['date' => $this->date->toDateString()]))
            ->assertOk()
            ->assertSee($farmA->name.' · '.$farmB->name);
    }

    public function test_invoice_pdf_names_the_farm_that_actually_delivers(): void
    {
        // Primary is farm A, but the stock covering the date comes from
        // farm B — the invoice's producer column must say farm B.
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = $this->product($farmA, [$farmA->id, $farmB->id]);
        $this->inflow($product, $farmB, 50);

        $profile = RestaurantProfile::factory()->verified()->create();
        $order = app(RestaurantOrderService::class)->submitOrder($profile->user, [
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 10]],
            'expected_delivery_date' => $this->date->toDateString(),
            'delivery_street' => 'Lieferstr. 2', 'delivery_postal_code' => '10117', 'delivery_city' => 'Berlin',
        ]);
        app(RestaurantOrderService::class)->confirm($order);
        $svc = app(RestaurantInvoiceService::class);
        $svc->issueInvoice($order);

        $html = $svc->renderPdf($order->refresh())->getDomPDF()->outputHtml();

        $this->assertStringContainsString($farmB->name, $html);
        $this->assertStringNotContainsString($farmA->name, $html);
    }
}
