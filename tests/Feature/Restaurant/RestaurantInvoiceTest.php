<?php

namespace Tests\Feature\Restaurant;

use App\Enums\ProductUnit;
use App\Enums\RestaurantOrderStatus;
use App\Enums\ShopStockAudience;
use App\Mail\RestaurantOrderInvoicedMail;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\RestaurantOrder;
use App\Models\RestaurantProfile;
use App\Models\User;
use App\Services\Admin\InventoryService;
use App\Services\Restaurant\RestaurantInvoiceService;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RestaurantInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $date;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->date = Carbon::tomorrow();
        Producer::factory()->create();
        $this->product = Product::factory()->create([
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece',
            'unit_price' => 10.00, 'vat_rate_percent' => 7,
            'is_available' => true, 'is_published' => true,
        ]);
        ProductStockInflow::create([
            'product_id' => $this->product->id, 'received_on' => $this->date->toDateString(),
            'quantity' => 50, 'freshness_window_days' => 3,
        ]);
    }

    private function confirmedOrder(?array $items = null): RestaurantOrder
    {
        $profile = RestaurantProfile::factory()->verified()->create();
        $order = app(RestaurantOrderService::class)->submit($profile->user, [
            'items' => $items ?? [['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 10]],
            'expected_delivery_date' => $this->date->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);
        app(RestaurantOrderService::class)->confirm($order);

        return $order->refresh();
    }

    private function remaining(): float
    {
        return app(InventoryService::class)->availableOnDate($this->product, $this->date, ShopStockAudience::BusinessTrade);
    }

    public function test_pricing_recompute_net_to_gross_vat_totals(): void
    {
        $order = $this->confirmedOrder();
        $item = $order->items()->firstOrFail();

        app(RestaurantInvoiceService::class)->updatePricing($order, [
            ['id' => $item->id, 'net_total_eur' => 100.00, 'vat_rate_percent' => 7, 'quantity' => 10],
        ]);

        $item->refresh();
        $this->assertEqualsWithDelta(107.00, (float) $item->line_total_eur, 0.001);
        $this->assertEqualsWithDelta(7.00, (float) $item->vat_amount_eur, 0.001);

        $order->refresh();
        $this->assertEqualsWithDelta(100.00, (float) $order->items_total_net_eur, 0.01);
        $this->assertEqualsWithDelta(7.00, (float) $order->vat_total_eur, 0.01);
        $this->assertEqualsWithDelta(107.00, (float) $order->items_total_gross_eur, 0.01);
    }

    public function test_editing_reserved_quantity_changes_availability(): void
    {
        $order = $this->confirmedOrder(); // qty 10 reserved → 40 remain
        $this->assertEqualsWithDelta(40.0, $this->remaining(), 0.01);

        $item = $order->items()->firstOrFail();
        app(RestaurantInvoiceService::class)->updatePricing($order, [
            ['id' => $item->id, 'quantity' => 4, 'net_total_eur' => $item->lineNetEur()],
        ]);

        $this->assertEqualsWithDelta(46.0, $this->remaining(), 0.01);
    }

    public function test_dropping_a_reserved_line_releases_the_hold(): void
    {
        $order = $this->confirmedOrder();
        $this->assertEqualsWithDelta(40.0, $this->remaining(), 0.01);

        $item = $order->items()->firstOrFail();
        app(RestaurantInvoiceService::class)->updatePricing($order, [['id' => $item->id, 'remove' => true]]);

        $this->assertSame(0, $order->items()->count());
        $this->assertEqualsWithDelta(50.0, $this->remaining(), 0.01);
    }

    public function test_issue_allocates_sequential_gap_free_numbers(): void
    {
        $svc = app(RestaurantInvoiceService::class);
        Carbon::setTestNow(Carbon::create(2026, 6, 30, 12));

        $a = $this->confirmedOrder();
        $svc->updatePricing($a, [['id' => $a->items()->firstOrFail()->id, 'net_total_eur' => 50, 'vat_rate_percent' => 7]]);
        $svc->issueInvoice($a);

        $b = $this->confirmedOrder();
        $svc->updatePricing($b, [['id' => $b->items()->firstOrFail()->id, 'net_total_eur' => 60, 'vat_rate_percent' => 7]]);
        $svc->issueInvoice($b);

        $this->assertSame('RG-2026-0001', $a->refresh()->invoice_number);
        $this->assertSame('RG-2026-0002', $b->refresh()->invoice_number);
        $this->assertSame($a->invoiced_at->copy()->addDays(14)->toDateString(), $a->invoice_due_date->toDateString());

        Carbon::setTestNow(null);
    }

    public function test_issue_blocks_re_edit_and_re_issue(): void
    {
        $svc = app(RestaurantInvoiceService::class);
        $order = $this->confirmedOrder();
        $svc->updatePricing($order, [['id' => $order->items()->firstOrFail()->id, 'net_total_eur' => 50, 'vat_rate_percent' => 7]]);
        $svc->issueInvoice($order);
        $number = $order->refresh()->invoice_number;

        $this->expectException(\RuntimeException::class);
        $svc->updatePricing($order, [['id' => $order->items()->firstOrFail()->id, 'net_total_eur' => 999]]);

        $this->assertSame($number, $order->refresh()->invoice_number);
    }

    public function test_cannot_invoice_submitted_or_zero_total(): void
    {
        // Submitted (not confirmed) → guard. A seasonal out-of-stock line keeps
        // the order pending (an all-in-stock order would auto-confirm).
        $seasonal = Product::factory()->create([
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece',
            'unit_price' => 5.00, 'vat_rate_percent' => 7,
            'is_seasonal' => true, 'is_available' => true, 'is_published' => true,
        ]);
        $profile = RestaurantProfile::factory()->verified()->create();
        $submitted = app(RestaurantOrderService::class)->submit($profile->user, [
            'items' => [['product_id' => $seasonal->id, 'variant_id' => null, 'quantity' => 1]],
            'expected_delivery_date' => $this->date->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);
        $this->assertSame(RestaurantOrderStatus::Submitted, $submitted->status);
        try {
            app(RestaurantInvoiceService::class)->issueInvoice($submitted);
            $this->fail('Expected RuntimeException for a submitted order');
        } catch (\RuntimeException) {
        }
        $this->assertNull($submitted->refresh()->invoice_number);
    }

    public function test_admin_issue_route_mails_invoice_and_blocks_non_admin(): void
    {
        Mail::fake();
        $order = $this->confirmedOrder();
        app(RestaurantInvoiceService::class)->updatePricing($order, [
            ['id' => $order->items()->firstOrFail()->id, 'net_total_eur' => 80, 'vat_rate_percent' => 7],
        ]);

        // Non-admin blocked.
        $this->actingAs(User::factory()->create())
            ->post(route('app.admin.restaurant-orders.invoice', $order))->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('app.admin.restaurant-orders.invoice', $order))->assertRedirect();

        $order->refresh();
        $this->assertNotNull($order->invoice_number);
        Mail::assertSent(RestaurantOrderInvoicedMail::class, fn ($m) => $m->hasTo($order->user->email));

        // Re-issue blocked (already invoiced).
        $this->actingAs($admin)->post(route('app.admin.restaurant-orders.invoice', $order))->assertForbidden();
    }

    public function test_invoice_pdf_routes_are_gated(): void
    {
        $order = $this->confirmedOrder();
        app(RestaurantInvoiceService::class)->updatePricing($order, [
            ['id' => $order->items()->firstOrFail()->id, 'net_total_eur' => 80, 'vat_rate_percent' => 7],
        ]);

        // Pre-invoice → 404 for the owner route.
        $this->actingAs($order->user)
            ->get(route('app.restaurant.orders.invoice-pdf', $order))->assertNotFound();

        app(RestaurantInvoiceService::class)->issueInvoice($order);

        // Owner downloads.
        $this->actingAs($order->user)
            ->get(route('app.restaurant.orders.invoice-pdf', $order))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        // A different restaurant cannot.
        $other = RestaurantProfile::factory()->verified()->create()->user;
        $this->actingAs($other)
            ->get(route('app.restaurant.orders.invoice-pdf', $order))->assertForbidden();

        // Admin downloads.
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('app.admin.restaurant-orders.invoice-pdf', $order))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_invoice_pdf_contains_section_14_fields(): void
    {
        $profile = RestaurantProfile::factory()->verified()->create([
            'business_name' => 'Bistro Beet', 'ust_id_nr' => 'DE123456789',
            'billing_street' => 'Marktstr. 1', 'billing_postal_code' => '10115', 'billing_city' => 'Berlin',
        ]);
        config()->set('shop.company_iban', 'DE00 1234 5678 9012 3456 00');

        $order = app(RestaurantOrderService::class)->submit($profile->user, [
            'items' => [['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 10]],
            'expected_delivery_date' => $this->date->toDateString(),
            'delivery_street' => 'Lieferstr. 2', 'delivery_postal_code' => '10117', 'delivery_city' => 'Berlin',
        ]);
        app(RestaurantOrderService::class)->confirm($order);
        $svc = app(RestaurantInvoiceService::class);
        $svc->updatePricing($order, [['id' => $order->items()->firstOrFail()->id, 'net_total_eur' => 100, 'vat_rate_percent' => 7]]);
        $svc->issueInvoice($order);

        $html = $svc->renderPdf($order->refresh())->getDomPDF()->outputHtml();

        $this->assertStringContainsString($order->invoice_number, $html);
        $this->assertStringContainsString('Bistro Beet', $html);
        $this->assertStringContainsString('DE123456789', $html);
        $this->assertStringContainsString('Marktstr. 1', $html);
        $this->assertStringContainsString('Nettosumme', $html);
        $this->assertStringContainsString('7 % MwSt', $html);
        $this->assertStringContainsString('Gesamtbetrag', $html);
        $this->assertStringContainsString('DE00 1234 5678 9012 3456 00', $html);
        $this->assertStringContainsString('Lieferdatum', $html);
    }
}
