<?php

namespace Tests\Feature\Restaurant;

use App\Enums\ProductUnit;
use App\Enums\RestaurantOrderStatus;
use App\Enums\ShopStockAudience;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\RestaurantOrder;
use App\Models\RestaurantProfile;
use App\Models\ShopOrder;
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
        $order = app(RestaurantOrderService::class)->submitOrder($profile->user, [
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

        $this->assertSame('EB260001', $a->refresh()->invoice_number);
        $this->assertSame('EB260002', $b->refresh()->invoice_number);
        // Due within 7 days of DELIVERY, never before the invoice date.
        $expectedDue = Carbon::parse($a->expected_delivery_date)->addDays(7);
        if ($expectedDue->lt($a->invoiced_at)) {
            $expectedDue = $a->invoiced_at->copy()->addDays(7);
        }
        $this->assertSame($expectedDue->toDateString(), $a->invoice_due_date->toDateString());

        Carbon::setTestNow(null);
    }

    public function test_delivery_fee_flows_into_totals_vat_and_pdf(): void
    {
        $svc = app(RestaurantInvoiceService::class);
        $order = $this->confirmedOrder();
        $item = $order->items()->firstOrFail();

        $svc->updatePricing(
            $order,
            [['id' => $item->id, 'net_total_eur' => 100.00, 'vat_rate_percent' => 7]],
            deliveryFeeNetEur: 4.10,
            deliveryFeeVatRatePercent: 7,
        );

        $order->refresh();
        $this->assertEqualsWithDelta(104.10, (float) $order->items_total_net_eur, 0.01);
        $this->assertEqualsWithDelta(7.29, (float) $order->vat_total_eur, 0.01);
        $this->assertEqualsWithDelta(111.39, (float) $order->items_total_gross_eur, 0.01);
        $this->assertEqualsWithDelta(7.29, (float) $svc->vatByRate($order)[7], 0.01);

        $svc->issueInvoice($order);
        $html = $svc->renderPdf($order->refresh())->getDomPDF()->outputHtml();

        $this->assertStringContainsString('Lieferung S, 1 B', $html);
        $this->assertStringContainsString('4,10', $html);
        $this->assertStringContainsString('111,39', $html);

        // Clearing the fee rolls it back out of the totals.
        $fresh = $this->confirmedOrder();
        $freshItem = $fresh->items()->firstOrFail();
        $svc->updatePricing($fresh, [['id' => $freshItem->id, 'net_total_eur' => 50, 'vat_rate_percent' => 7]], 4.10, 7);
        $svc->updatePricing($fresh, [['id' => $freshItem->id, 'net_total_eur' => 50, 'vat_rate_percent' => 7]], null, null);
        $this->assertEqualsWithDelta(53.50, (float) $fresh->refresh()->items_total_gross_eur, 0.01);
        $this->assertNull($fresh->delivery_fee_net_eur);
    }

    public function test_pricing_and_fee_sync_the_mirror_shop_order(): void
    {
        $svc = app(RestaurantInvoiceService::class);
        $order = $this->confirmedOrder();
        $mirror = ShopOrder::query()->where('restaurant_order_id', $order->id)->firstOrFail();

        $svc->updatePricing(
            $order,
            [['id' => $order->items()->firstOrFail()->id, 'net_total_eur' => 100.00, 'vat_rate_percent' => 7]],
            deliveryFeeNetEur: 4.10,
            deliveryFeeVatRatePercent: 7,
        );

        $mirror->refresh()->load('items');
        $this->assertEqualsWithDelta(107.00, (float) $mirror->items_total_eur, 0.01);
        $this->assertEqualsWithDelta(4.39, (float) $mirror->delivery_fee_eur, 0.01);
        $this->assertEqualsWithDelta(0.29, (float) $mirror->delivery_fee_vat_amount_eur, 0.01);
        $this->assertEqualsWithDelta(111.39, (float) $mirror->total_amount_eur, 0.01);
        $this->assertEqualsWithDelta(107.00, (float) $mirror->items->first()->line_total_eur, 0.01);
    }

    public function test_admin_can_align_sequence_forward_only(): void
    {
        $svc = app(RestaurantInvoiceService::class);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.restaurant-orders.invoice-sequence'), ['next_invoice_number' => 46])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertStringEndsWith('0046', $svc->nextInvoiceNumberPreview());

        $order = $this->confirmedOrder();
        $svc->updatePricing($order, [['id' => $order->items()->firstOrFail()->id, 'net_total_eur' => 50, 'vat_rate_percent' => 7]]);
        $svc->issueInvoice($order);
        $this->assertStringEndsWith('0046', $order->refresh()->invoice_number);

        // Backwards is blocked: number 46 is already used.
        $this->actingAs($admin)
            ->post(route('app.admin.restaurant-orders.invoice-sequence'), ['next_invoice_number' => 10])
            ->assertSessionHasErrors('next_invoice_number');
        $this->assertStringEndsWith('0047', $svc->nextInvoiceNumberPreview());

        // Non-admin blocked.
        $this->actingAs(User::factory()->create())
            ->post(route('app.admin.restaurant-orders.invoice-sequence'), ['next_invoice_number' => 99])
            ->assertForbidden();
    }

    public function test_issued_invoice_stays_editable_but_cannot_be_reissued(): void
    {
        $svc = app(RestaurantInvoiceService::class);
        $order = $this->confirmedOrder();
        $svc->updatePricing($order, [['id' => $order->items()->firstOrFail()->id, 'net_total_eur' => 50, 'vat_rate_percent' => 7]]);
        $svc->issueInvoice($order);
        $order->refresh();
        $number = $order->invoice_number;
        $invoicedAt = $order->invoiced_at;

        // Editing after issue is an explicit product decision: totals move,
        // number and invoice date never do.
        $svc->updatePricing($order, [['id' => $order->items()->firstOrFail()->id, 'net_total_eur' => 999, 'vat_rate_percent' => 7]]);
        $order->refresh();
        $this->assertEqualsWithDelta(999.00, (float) $order->items_total_net_eur, 0.01);
        $this->assertSame($number, $order->invoice_number);
        $this->assertSame($invoicedAt->toIso8601String(), $order->invoiced_at->toIso8601String());

        // Re-issuing (a second number) stays blocked.
        $this->expectException(\RuntimeException::class);
        $svc->issueInvoice($order);
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
        $submitted = app(RestaurantOrderService::class)->submitRequest($profile->user, [
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

    public function test_admin_can_mark_invoice_paid_once(): void
    {
        $svc = app(RestaurantInvoiceService::class);
        $order = $this->confirmedOrder();
        $svc->updatePricing($order, [['id' => $order->items()->firstOrFail()->id, 'net_total_eur' => 50, 'vat_rate_percent' => 7]]);
        $svc->issueInvoice($order);
        $admin = User::factory()->admin()->create();

        // Not paid yet; unpaid state renders on the admin card.
        $this->assertFalse($order->refresh()->isInvoicePaid());

        $this->actingAs($admin)
            ->post(route('app.admin.restaurant-orders.invoice-paid', $order))
            ->assertRedirect(route('app.admin.restaurant-orders.show', $order));
        $this->assertTrue($order->refresh()->isInvoicePaid());
        $paidAt = $order->invoice_paid_at;

        // Idempotent: a second click keeps the original timestamp.
        $this->actingAs($admin)->post(route('app.admin.restaurant-orders.invoice-paid', $order))->assertRedirect();
        $this->assertSame($paidAt->toIso8601String(), $order->refresh()->invoice_paid_at->toIso8601String());

        // Non-admin blocked; uninvoiced order blocked.
        $this->actingAs(User::factory()->create())
            ->post(route('app.admin.restaurant-orders.invoice-paid', $order))->assertForbidden();
        $fresh = $this->confirmedOrder();
        $this->actingAs($admin)->post(route('app.admin.restaurant-orders.invoice-paid', $fresh))->assertForbidden();
    }

    public function test_admin_issue_route_opens_mail_editor_and_blocks_non_admin(): void
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
        // No auto-mail: issuing routes into the mail editor for review.
        $this->actingAs($admin)
            ->post(route('app.admin.restaurant-orders.invoice', $order))
            ->assertRedirect(route('app.admin.restaurant-orders.invoice-mail', $order));

        $order->refresh();
        $this->assertNotNull($order->invoice_number);
        Mail::assertNothingQueued();

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

        $order = app(RestaurantOrderService::class)->submitOrder($profile->user, [
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
        $this->assertStringContainsString('Gesamtbetrag Netto', $html);
        $this->assertStringContainsString('Umsatzsteuer (7%)', $html);
        $this->assertStringContainsString('Rechnungsbetrag', $html);
        $this->assertStringContainsString('DE00 1234 5678 9012 3456 00', $html);
        $this->assertStringContainsString('Verwendungszweck: '.$order->invoice_number, $html);
        $this->assertStringContainsString('Lieferdatum', $html);
        $this->assertStringContainsString('Tagen nach Lieferung zahlbar', $html);
    }
}
