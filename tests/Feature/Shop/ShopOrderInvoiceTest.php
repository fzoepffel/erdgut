<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductUnit;
use App\Enums\ShopOrderStatus;
use App\Mail\ShopInvoiceCustomMail;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\RestaurantOrder;
use App\Models\RestaurantProfile;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Restaurant\RestaurantInvoiceService;
use App\Services\Restaurant\RestaurantOrderService;
use App\Services\Shop\ShopOrderInvoiceService;
use App\Services\Shop\ShopOrderService;
use App\Support\InvoiceSequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Proper Rechnungen for shop orders: any non-mirror order gets an invoice
 * number from the SAME yearly ledger as restaurant invoices, a net-forward
 * PDF that reuses the receipt overrides, and the shared mail editor.
 * Coupon-discounted orders are blocked (no per-line net/VAT attribution).
 */
class ShopOrderInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        Producer::factory()->create();
        $this->product = Product::factory()->create([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 4.00,
            'vat_rate_percent' => 7,
            'is_available' => true,
            'is_published' => true,
        ]);
    }

    /** A paid pickup order: one line, gross 107.00 at 7 % VAT (net 100.00). */
    private function makeOrder(array $overrides = [], array $itemOverrides = []): ShopOrder
    {
        $order = ShopOrder::factory()->create(array_merge([
            'name' => 'Erika Beispiel',
            'email' => 'erika@example.net',
            'status' => ShopOrderStatus::Paid,
            'payment_method' => 'online',
            'paid_at' => now(),
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'delivery_street' => null,
            'delivery_postal_code' => null,
            'delivery_city' => null,
            'expected_delivery_date' => now()->addDays(3)->toDateString(),
            'items_total_eur' => 107.00,
            'delivery_fee_eur' => 0,
            'delivery_fee_vat_amount_eur' => 0,
            'discount_eur' => 0,
            'total_amount_eur' => 107.00,
        ], $overrides));

        $order->items()->create(array_merge([
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit' => 'piece',
            'unit_price_snapshot_eur' => 53.50,
            'line_total_eur' => 107.00,
            'vat_rate_snapshot_percent' => 7,
            'vat_amount_eur' => 7.00,
        ], $itemOverrides));

        return $order->refresh();
    }

    private function mirrorOrder(): ShopOrder
    {
        $restaurantOrder = RestaurantOrder::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'confirmed',
            'kind' => 'order',
            'expected_delivery_date' => now()->addDay()->toDateString(),
            'delivery_street' => 'Teststr. 1',
            'delivery_postal_code' => '10115',
            'delivery_city' => 'Berlin',
            'items_total_net_eur' => 100,
            'items_total_gross_eur' => 107,
            'vat_total_eur' => 7,
        ]);

        return $this->makeOrder(['restaurant_order_id' => $restaurantOrder->id]);
    }

    private function renderInvoice(ShopOrder $order, ?string $previewNumber = null): string
    {
        return view('shop.pdf.invoice', [
            'order' => $order->load('items.product.translations'),
            'previewNumber' => $previewNumber,
            'vatByRate' => ShopOrderService::vatByRate($order),
            'seller' => [
                'name' => 'Erdgut UG (haftungsbeschränkt)', 'street' => 'Gotenstraße 19',
                'postal_city' => '10829 Berlin', 'phone' => '+33 7 83 38 85 93',
                'email' => 'hallo@erdgut.example', 'vat_id' => 'DE462049505',
                'tax_number' => '', 'iban' => 'DE32 2022 0800 0040 2029 90',
                'bank' => 'Vivid', 'register' => 'HRB 286220 B',
            ],
        ])->render();
    }

    // ── Issuing ─────────────────────────────────────────────────────────

    public function test_shop_and_restaurant_invoices_share_one_number_ledger(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 30, 12));

        // Restaurant invoice first: consumes the first number of the year.
        Producer::factory()->create();
        ProductStockInflow::create([
            'product_id' => $this->product->id, 'received_on' => now()->addDay()->toDateString(),
            'quantity' => 50, 'freshness_window_days' => 3,
        ]);
        $profile = RestaurantProfile::factory()->verified()->create();
        $restaurantOrder = app(RestaurantOrderService::class)->submitOrder($profile->user, [
            'items' => [['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 10]],
            'expected_delivery_date' => now()->addDay()->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);
        app(RestaurantOrderService::class)->confirm($restaurantOrder);
        app(RestaurantInvoiceService::class)->issueInvoice($restaurantOrder);

        $shopOrder = app(ShopOrderInvoiceService::class)->issueInvoice($this->makeOrder());

        $this->assertSame('EB260001', $restaurantOrder->refresh()->invoice_number);
        $this->assertSame('EB260002', $shopOrder->invoice_number);
        $this->assertNotNull($shopOrder->invoiced_at);

        Carbon::setTestNow();
    }

    public function test_due_date_is_delivery_plus_configured_days(): void
    {
        $order = $this->makeOrder(['expected_delivery_date' => now()->addDays(3)->toDateString()]);

        $order = app(ShopOrderInvoiceService::class)->issueInvoice($order);

        $this->assertSame(
            now()->addDays(3)->addDays(7)->toDateString(),
            $order->invoice_due_date->toDateString(),
        );
    }

    public function test_due_date_never_falls_before_the_invoice_date(): void
    {
        $order = $this->makeOrder(['expected_delivery_date' => now()->subDays(30)->toDateString()]);

        $order = app(ShopOrderInvoiceService::class)->issueInvoice($order);

        $this->assertSame(now()->addDays(7)->toDateString(), $order->invoice_due_date->toDateString());
    }

    public function test_a_preset_due_date_wins(): void
    {
        $order = $this->makeOrder(['invoice_due_date' => now()->addDays(30)->toDateString()]);

        $order = app(ShopOrderInvoiceService::class)->issueInvoice($order);

        $this->assertSame(now()->addDays(30)->toDateString(), $order->invoice_due_date->toDateString());
    }

    public function test_reissue_mirror_discount_status_and_zero_total_are_blocked(): void
    {
        $svc = app(ShopOrderInvoiceService::class);

        $issued = $svc->issueInvoice($this->makeOrder());
        foreach ([
            $issued,
            $this->mirrorOrder(),
            $this->makeOrder(['discount_eur' => 5.00]),
            $this->makeOrder(['status' => ShopOrderStatus::Cancelled]),
            $this->makeOrder(['total_amount_eur' => 0]),
        ] as $order) {
            try {
                $svc->issueInvoice($order);
                $this->fail('Expected RuntimeException for order '.$order->id);
            } catch (\RuntimeException) {
                // expected
            }
        }

        // Only the initially issued order carries a number.
        $this->assertSame(1, ShopOrder::query()->whereNotNull('invoice_number')->count());
    }

    // ── PDF ─────────────────────────────────────────────────────────────

    public function test_pdf_shows_net_prices_derived_from_gross_and_vat(): void
    {
        $order = app(ShopOrderInvoiceService::class)->issueInvoice($this->makeOrder());

        $html = $this->renderInvoice($order);

        // Gross 107.00 at 7 % → line net 100.00; 2 Stück → 50.00 net each.
        $this->assertStringContainsString('100,00 €', $html);
        $this->assertStringContainsString('50,00 €', $html);
        $this->assertStringContainsString('2 Stück', $html);
        $this->assertStringContainsString('Umsatzsteuer (7%)', $html);
        $this->assertStringContainsString('7,00 €', $html);
        // Rechnungsbetrag = gross total.
        $this->assertStringContainsString('107,00 €', $html);
        $this->assertStringContainsString('RECHNUNG: '.$order->invoice_number, $html);
        // Payment status lives on the Beleg, never on the Rechnung.
        $this->assertStringNotContainsString('Bereits bezahlt', $html);
        $this->assertStringNotContainsString('Offener Betrag', $html);
    }

    public function test_pdf_delivery_fee_appears_as_net_line(): void
    {
        $order = $this->makeOrder([
            'fulfillment_mode' => 'delivery',
            'pickup_location' => null,
            'delivery_street' => 'Lieferstr. 2',
            'delivery_postal_code' => '10117',
            'delivery_city' => 'Berlin',
            'delivery_fee_eur' => 5.00,
            'delivery_fee_vat_amount_eur' => 0.80,
            'total_amount_eur' => 112.00,
        ]);
        $order = app(ShopOrderInvoiceService::class)->issueInvoice($order);

        $html = $this->renderInvoice($order);

        // Fee net 5.00 − 0.80 = 4.20; total net 104.20.
        $this->assertStringContainsString('4,20 €', $html);
        $this->assertStringContainsString('104,20 €', $html);
        $this->assertStringContainsString('Lieferung Lieferstr. 2, 10117 Berlin', $html);
    }

    public function test_pdf_renders_receipt_overrides_and_line_renames(): void
    {
        $order = app(ShopOrderInvoiceService::class)->issueInvoice($this->makeOrder());
        app(ShopOrderService::class)->saveReceiptOverrides($order, [
            'recipient_name' => 'Maxi Muster GmbH',
            'bank_name' => 'Testbank',
            'bank_iban' => 'DE00 1234',
            'terms_text' => 'Zahlbar sofort ohne Abzug.',
        ], [
            ['id' => $order->items()->firstOrFail()->id, 'display_name' => 'Bergkäse vom Fass'],
        ]);

        $html = $this->renderInvoice($order->refresh());

        $this->assertStringContainsString('Maxi Muster GmbH', $html);
        $this->assertStringContainsString('Testbank', $html);
        $this->assertStringContainsString('DE00 1234', $html);
        $this->assertStringContainsString('Zahlbar sofort ohne Abzug.', $html);
        $this->assertStringContainsString('Bergkäse vom Fass', $html);
    }

    public function test_pdf_draft_mode_shows_preview_number_and_entwurf(): void
    {
        $order = $this->makeOrder();

        $html = $this->renderInvoice($order, InvoiceSequence::preview());

        $this->assertStringContainsString(InvoiceSequence::preview(), $html);
        $this->assertStringContainsString('Entwurf', $html);
    }

    // ── Routes + show page ──────────────────────────────────────────────

    public function test_issue_route_redirects_to_the_mail_editor(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.issue-invoice', $order))
            ->assertRedirect(route('app.admin.shop-orders.shop-invoice-mail', $order));

        $this->assertNotNull($order->refresh()->invoice_number);
        $this->assertDatabaseHas('audit_events', [
            'entity_type' => 'shop_order',
            'entity_id' => $order->id,
            'action' => 'invoice',
        ]);
    }

    public function test_issue_route_flashes_the_discount_block_instead_of_issuing(): void
    {
        $order = $this->makeOrder(['discount_eur' => 5.00]);

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.issue-invoice', $order))
            ->assertRedirect(route('app.admin.shop-orders.show', $order))
            ->assertSessionHas('status', __('shop.admin.invoice_blocked_discount'));

        $this->assertNull($order->refresh()->invoice_number);
    }

    public function test_invoice_pdf_requires_an_issued_invoice_and_admin(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.invoice-pdf', $order))
            ->assertNotFound();

        $order = app(ShopOrderInvoiceService::class)->issueInvoice($order);

        $this->actingAs(User::factory()->create())
            ->get(route('app.admin.shop-orders.invoice-pdf', $order))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.invoice-pdf', $order))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_preview_pdf_works_before_issuing_but_not_for_mirrors(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.shop-invoice-preview', $this->makeOrder()))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.shop-invoice-preview', $this->mirrorOrder()))
            ->assertNotFound();
    }

    public function test_show_page_offers_the_right_invoice_actions_per_state(): void
    {
        // Not invoiced, no discount: issue button + preview link.
        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.show', $this->makeOrder()))
            ->assertOk()
            ->assertSee('data-testid="shop-admin-issue-invoice"', false)
            ->assertSee('data-testid="shop-admin-invoice-preview"', false);

        // Coupon discount: blocked hint instead of the issue button.
        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.show', $this->makeOrder(['discount_eur' => 5.00])))
            ->assertOk()
            ->assertSee('data-testid="shop-admin-invoice-blocked"', false)
            ->assertDontSee('data-testid="shop-admin-issue-invoice"', false);

        // Issued: download + mail + meta line.
        $issued = app(ShopOrderInvoiceService::class)->issueInvoice($this->makeOrder());
        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.show', $issued))
            ->assertOk()
            ->assertSee('data-testid="shop-admin-invoice-download"', false)
            ->assertSee('data-testid="shop-admin-invoice-mail"', false)
            ->assertSee($issued->invoice_number)
            ->assertDontSee('data-testid="shop-admin-issue-invoice"', false);
    }

    // ── Mail ────────────────────────────────────────────────────────────

    public function test_mail_editor_opens_prefilled_and_send_queues_per_recipient(): void
    {
        Mail::fake();
        $order = app(ShopOrderInvoiceService::class)->issueInvoice($this->makeOrder());

        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.shop-invoice-mail', $order))
            ->assertOk()
            ->assertSee('erika@example.net')
            ->assertSee('erdgut-rechnung-'.$order->invoice_number.'.pdf');

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.shop-invoice-mail.send', $order), [
                'recipients' => 'erika@example.net, buchhaltung@example.net',
                'subject' => 'Deine Rechnung',
                'body' => 'Hallo, anbei die Rechnung.',
            ])
            ->assertRedirect(route('app.admin.shop-orders.show', $order));

        Mail::assertQueued(ShopInvoiceCustomMail::class, 2);
        Mail::assertQueued(ShopInvoiceCustomMail::class, fn ($mail) => $mail->hasTo('buchhaltung@example.net')
            && $mail->mailSubject === 'Deine Rechnung');
        $this->assertDatabaseHas('audit_events', [
            'entity_type' => 'shop_order',
            'entity_id' => $order->id,
            'action' => 'invoice_mail_sent',
        ]);
    }

    public function test_mail_send_rejects_invalid_recipients(): void
    {
        Mail::fake();
        $order = app(ShopOrderInvoiceService::class)->issueInvoice($this->makeOrder());

        $this->actingAs($this->admin)
            ->from(route('app.admin.shop-orders.shop-invoice-mail', $order))
            ->post(route('app.admin.shop-orders.shop-invoice-mail.send', $order), [
                'recipients' => 'kein-email',
                'subject' => 'Betreff',
                'body' => 'Text',
            ])
            ->assertSessionHasErrors('recipients');

        Mail::assertNothingQueued();
    }

    public function test_mail_editor_requires_an_issued_invoice(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.shop-invoice-mail', $this->makeOrder()))
            ->assertForbidden();
    }
}
