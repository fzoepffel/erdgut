<?php

namespace Tests\Feature\Shop;

use App\Enums\CustomerType;
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
use App\Support\Money;
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
            ->assertSee('data-testid="shop-admin-invoice-preview"', false)
            ->assertSee('data-testid="shop-admin-invoice-sequence"', false);

        // Coupon discount: blocked hint instead of the issue button.
        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.show', $this->makeOrder(['discount_eur' => 5.00])))
            ->assertOk()
            ->assertSee('data-testid="shop-admin-invoice-blocked"', false)
            ->assertDontSee('data-testid="shop-admin-issue-invoice"', false);

        // Issued: download + mail + meta line + revert button.
        $issued = app(ShopOrderInvoiceService::class)->issueInvoice($this->makeOrder());
        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.show', $issued))
            ->assertOk()
            ->assertSee('data-testid="shop-admin-invoice-download"', false)
            ->assertSee('data-testid="shop-admin-invoice-mail"', false)
            ->assertSee('data-testid="shop-admin-revert-invoice"', false)
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

    // ── WYSIWYG invoice editor ──────────────────────────────────────────

    public function test_invoice_editor_renders_and_mirrors_redirect_to_the_b2b_editor(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.shop-invoice-editor', $this->makeOrder()))
            ->assertOk()
            ->assertSee('data-testid="invoice-editor-save"', false)
            ->assertSee('data-testid="invoice-editor-preview"', false)
            ->assertSee('data-testid="invoice-editor-issue"', false);

        $mirror = $this->mirrorOrder();
        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.shop-invoice-editor', $mirror))
            ->assertRedirect(route('app.admin.shop-orders.invoice-editor', $mirror->restaurant_order_id));
    }

    public function test_editor_save_converts_net_entry_to_gross_snapshots(): void
    {
        $order = $this->makeOrder();
        $item = $order->items()->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.shop-invoice-editor.save', $order), [
                'items' => [[
                    'id' => $item->id,
                    'display_name' => 'Marktgemüse gemischt',
                    'net_total_eur' => '50.00',
                    'vat_rate_percent' => 7,
                    'quantity' => 2,
                ]],
            ])
            ->assertRedirect(route('app.admin.shop-orders.shop-invoice-editor', $order));

        $item->refresh();
        $this->assertEqualsWithDelta(53.50, (float) $item->line_total_eur, 0.001);
        $this->assertEqualsWithDelta(3.50, (float) $item->vat_amount_eur, 0.001);
        $this->assertSame('Marktgemüse gemischt', $item->display_name_override);

        $order->refresh();
        $this->assertEqualsWithDelta(53.50, (float) $order->total_amount_eur, 0.01);
        $this->assertDatabaseHas('audit_events', [
            'entity_type' => 'shop_order',
            'entity_id' => $order->id,
            'action' => 'invoice_edit',
        ]);
    }

    public function test_editor_save_adds_custom_lines_removes_lines_and_reopens_settlement(): void
    {
        // Paid 107; editing to 53.50 + 10.70 = 64.20 owed reopens the
        // settlement (to-refund 42.80 shows on the order again).
        $order = $this->makeOrder([
            'paid_amount_eur' => 107.00,
            'outstanding_settled_amount_eur' => 0,
            'outstanding_settled_at' => now(),
        ]);
        $item = $order->items()->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.shop-invoice-editor.save', $order), [
                'items' => [
                    ['id' => $item->id, 'net_total_eur' => '50.00', 'vat_rate_percent' => 7, 'quantity' => 2],
                    ['name' => 'Marktstand-Aufbau', 'unit' => 'piece', 'quantity' => 1, 'net_total_eur' => '10.00', 'vat_rate_percent' => 7],
                ],
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(2, $order->items()->count());
        $custom = $order->items()->whereNull('product_id')->firstOrFail();
        $this->assertEqualsWithDelta(10.70, (float) $custom->line_total_eur, 0.001);
        $this->assertEqualsWithDelta(64.20, (float) $order->total_amount_eur, 0.01);
        $this->assertNull($order->outstanding_settled_at);
        $this->assertEqualsWithDelta(-42.80, $order->outstandingAmountEur(), 0.01);

        // Removing the custom line brings the total back down.
        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.shop-invoice-editor.save', $order), [
                'items' => [
                    ['id' => $item->id, 'net_total_eur' => '50.00', 'vat_rate_percent' => 7, 'quantity' => 2],
                    ['id' => $custom->id, 'remove' => 1],
                ],
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(1, $order->items()->count());
        $this->assertEqualsWithDelta(53.50, (float) $order->total_amount_eur, 0.01);
    }

    public function test_editor_save_sets_delivery_fee_net_and_dates(): void
    {
        $order = $this->makeOrder([
            'fulfillment_mode' => 'delivery',
            'pickup_location' => null,
            'delivery_street' => 'Lieferstr. 2',
            'delivery_postal_code' => '10117',
            'delivery_city' => 'Berlin',
        ]);
        $item = $order->items()->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.shop-invoice-editor.save', $order), [
                'items' => [['id' => $item->id, 'net_total_eur' => '100.00', 'vat_rate_percent' => 7, 'quantity' => 2]],
                'delivery_fee_net_eur' => '4.20',
                'delivery_fee_vat_rate_percent' => 19,
                'invoice_due_date' => now()->addDays(21)->toDateString(),
                'expected_delivery_date' => now()->addDays(5)->toDateString(),
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertEqualsWithDelta(5.00, (float) $order->delivery_fee_eur, 0.001);
        $this->assertEqualsWithDelta(0.80, (float) $order->delivery_fee_vat_amount_eur, 0.001);
        $this->assertEqualsWithDelta(112.00, (float) $order->total_amount_eur, 0.01);
        $this->assertSame(now()->addDays(21)->toDateString(), $order->invoice_due_date->toDateString());
        $this->assertSame(now()->addDays(5)->toDateString(), $order->expected_delivery_date->toDateString());
    }

    public function test_editor_live_preview_renders_unsaved_state_without_persisting(): void
    {
        $order = $this->makeOrder();
        $item = $order->items()->firstOrFail();

        $response = $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.shop-invoice-editor.preview', $order), [
                'items' => [['id' => $item->id, 'net_total_eur' => '999.00', 'vat_rate_percent' => 7, 'quantity' => 2]],
            ]);

        $response->assertOk()->assertHeader('content-type', 'application/pdf');

        // Rolled back: the stored line is untouched.
        $this->assertEqualsWithDelta(107.00, (float) $item->refresh()->line_total_eur, 0.001);
        $this->assertEqualsWithDelta(107.00, (float) $order->refresh()->total_amount_eur, 0.01);
    }

    public function test_show_page_adds_net_column_and_totals_for_business_orders(): void
    {
        $b2b = $this->makeOrder(['customer_type' => CustomerType::Business]);
        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.show', $b2b))
            ->assertOk()
            ->assertSee('data-testid="shop-admin-line-net"', false)
            ->assertSee('data-testid="shop-admin-net-total"', false)
            // Line gross 107,00 at 7 % → net 100,00 (locale-aware format).
            ->assertSee(e(Money::formatEur(100.00)), false);

        // B2C orders keep the pure gross view.
        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.show', $this->makeOrder(['customer_type' => CustomerType::Private])))
            ->assertOk()
            ->assertDontSee('data-testid="shop-admin-line-net"', false)
            ->assertDontSee('data-testid="shop-admin-net-total"', false);
    }

    public function test_receipt_and_invoice_editor_overrides_merge_instead_of_wiping(): void
    {
        $order = $this->makeOrder();
        $svc = app(ShopOrderService::class);

        // Invoice editor saves its subset ...
        $svc->saveReceiptOverrides($order, ['bank_iban' => 'DE00 1234', 'terms_text' => 'Sofort zahlbar.']);
        // ... then the receipt editor saves ITS subset (no bank keys).
        $svc->saveReceiptOverrides($order->refresh(), ['payment_text' => 'Barzahlung', 'notes_text' => 'Hinweis']);

        $order->refresh();
        $this->assertSame('DE00 1234', $order->receiptOverride('bank_iban'));
        $this->assertSame('Sofort zahlbar.', $order->receiptOverride('terms_text'));
        $this->assertSame('Barzahlung', $order->receiptOverride('payment_text'));

        // A key submitted blank drops its override.
        $svc->saveReceiptOverrides($order, ['bank_iban' => '']);
        $this->assertNull($order->refresh()->receiptOverride('bank_iban'));
    }

    // ── Revert to draft + editable number ───────────────────────────────

    public function test_revert_invoice_returns_to_draft_and_keeps_the_number(): void
    {
        $order = app(ShopOrderInvoiceService::class)->issueInvoice($this->makeOrder());
        $number = $order->invoice_number;
        $this->assertTrue($order->isInvoiced());

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.revert-invoice', $order))
            ->assertRedirect(route('app.admin.shop-orders.show', $order));

        $order->refresh();
        $this->assertFalse($order->isInvoiced());
        $this->assertNull($order->invoiced_at);
        $this->assertNull($order->invoice_due_date);
        $this->assertSame($number, $order->invoice_number);

        // PDF download and mail stay blocked while draft.
        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.invoice-pdf', $order))
            ->assertNotFound();
        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.shop-invoice-mail', $order))
            ->assertForbidden();

        // Show page offers issue again + no download.
        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.show', $order))
            ->assertOk()
            ->assertSee('data-testid="shop-admin-issue-invoice"', false)
            ->assertDontSee('data-testid="shop-admin-invoice-download"', false);

        $this->assertDatabaseHas('audit_events', [
            'entity_type' => 'shop_order',
            'entity_id' => $order->id,
            'action' => 'invoice_revert',
        ]);
    }

    public function test_reissue_after_revert_reuses_the_manual_number_without_consuming_sequence(): void
    {
        $order = app(ShopOrderInvoiceService::class)->issueInvoice($this->makeOrder());
        $number = $order->invoice_number;
        app(ShopOrderInvoiceService::class)->revertInvoice($order);

        // Peek at the next auto-assigned number BEFORE re-issue.
        $nextWouldBe = InvoiceSequence::preview();

        $reissued = app(ShopOrderInvoiceService::class)->issueInvoice($order->refresh());
        $this->assertSame($number, $reissued->invoice_number);
        $this->assertTrue($reissued->isInvoiced());

        // Sequence was not advanced by the re-issue (manual number reused).
        $this->assertSame($nextWouldBe, InvoiceSequence::preview());
    }

    public function test_editor_can_set_a_manual_invoice_number_while_draft(): void
    {
        $order = $this->makeOrder();
        $item = $order->items()->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.shop-invoice-editor.save', $order), [
                'invoice_number' => 'EB269999',
                'items' => [[
                    'id' => $item->id,
                    'net_total_eur' => '100.00',
                    'vat_rate_percent' => 7,
                    'quantity' => 2,
                ]],
            ])
            ->assertRedirect(route('app.admin.shop-orders.shop-invoice-editor', $order));

        $order->refresh();
        $this->assertSame('EB269999', $order->invoice_number);
        $this->assertFalse($order->isInvoiced());

        $issued = app(ShopOrderInvoiceService::class)->issueInvoice($order);
        $this->assertSame('EB269999', $issued->invoice_number);
        $this->assertTrue($issued->isInvoiced());
    }

    public function test_manual_invoice_number_must_be_unique(): void
    {
        $existing = app(ShopOrderInvoiceService::class)->issueInvoice($this->makeOrder());
        $order = $this->makeOrder();
        $item = $order->items()->firstOrFail();

        $this->actingAs($this->admin)
            ->from(route('app.admin.shop-orders.shop-invoice-editor', $order))
            ->post(route('app.admin.shop-orders.shop-invoice-editor.save', $order), [
                'invoice_number' => $existing->invoice_number,
                'items' => [[
                    'id' => $item->id,
                    'net_total_eur' => '100.00',
                    'vat_rate_percent' => 7,
                    'quantity' => 2,
                ]],
            ])
            ->assertSessionHasErrors('invoice_number');
    }

    public function test_revert_is_blocked_for_mirror_orders(): void
    {
        $mirror = $this->mirrorOrder();
        // Force an "issued" look so the service reaches the mirror guard.
        $mirror->forceFill([
            'invoice_number' => 'EB260042',
            'invoiced_at' => now(),
        ])->save();

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.revert-invoice', $mirror))
            ->assertRedirect(route('app.admin.shop-orders.show', $mirror))
            ->assertSessionHas('status', __('shop.admin.invoice_blocked_mirror'));

        $this->assertTrue($mirror->refresh()->isInvoiced());
    }

    // ── Display-only unit/quantity override ─────────────────────────────

    public function test_editor_save_stores_display_overrides_without_changing_canonical_qty(): void
    {
        // Order line is 300 g mass; invoice should show "2 Stück".
        $mass = Product::factory()->create([
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 10.00,
            'vat_rate_percent' => 7,
            'is_available' => true,
            'is_published' => true,
        ]);
        $order = $this->makeOrder([], [
            'product_id' => $mass->id,
            'quantity' => 300,
            'unit' => 'mass',
            'unit_price_snapshot_eur' => 0.01,
            'line_total_eur' => 3.21, // gross, ~3.00 net at 7 %
            'vat_rate_snapshot_percent' => 7,
            'vat_amount_eur' => 0.21,
        ]);
        $order->forceFill([
            'items_total_eur' => 3.21,
            'total_amount_eur' => 3.21,
            'paid_amount_eur' => 3.21,
            'outstanding_settled_amount_eur' => 0,
            'outstanding_settled_at' => now(),
        ])->save();
        $item = $order->items()->firstOrFail();
        $canonicalQty = (float) $item->quantity;
        $canonicalUnit = $item->unit;
        $lineTotal = (float) $item->line_total_eur;

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.shop-invoice-editor.save', $order), [
                'items' => [[
                    'id' => $item->id,
                    'net_total_eur' => '3.00',
                    'vat_rate_percent' => 7,
                    'quantity' => 300,
                    'display_unit' => 'Stück',
                    'display_quantity' => 2,
                ]],
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame($canonicalQty, (float) $item->quantity);
        $this->assertSame($canonicalUnit, $item->unit);
        $this->assertEqualsWithDelta($lineTotal, (float) $item->line_total_eur, 0.001);
        $this->assertSame('Stück', $item->display_unit_override);
        $this->assertEqualsWithDelta(2.0, (float) $item->display_quantity_override, 0.0001);

        // Settlement stays closed — totals did not move.
        $order->refresh();
        $this->assertNotNull($order->outstanding_settled_at);
    }

    public function test_editor_save_clears_a_display_override_when_the_fields_come_back_blank(): void
    {
        // The undo control in the editor empties both display fields; saving
        // that must drop the stored override and return to the natural unit.
        $order = $this->makeOrder([], [
            'display_unit_override' => 'Stück',
            'display_quantity_override' => 2,
        ]);
        $item = $order->items()->firstOrFail();
        $this->assertSame('Stück', $item->display_unit_override);

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.shop-invoice-editor.save', $order), [
                'items' => [[
                    'id' => $item->id,
                    'net_total_eur' => '100.00',
                    'vat_rate_percent' => 7,
                    'quantity' => (float) $item->quantity,
                    'display_unit' => '',
                    'display_quantity' => '',
                ]],
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertNull($item->display_unit_override);
        $this->assertNull($item->display_quantity_override);
    }

    public function test_invoice_pdf_renders_display_unit_override(): void
    {
        $order = $this->makeOrder();
        $item = $order->items()->firstOrFail();
        $item->forceFill([
            'display_unit_override' => 'Stück',
            'display_quantity_override' => 2,
        ])->save();

        $issued = app(ShopOrderInvoiceService::class)->issueInvoice($order);
        $html = $this->renderInvoice($issued);

        $this->assertStringContainsString('2 Stück', $html);
        // Unit price = net 100 / 2 = 50,00 € (German formatting).
        $this->assertStringContainsString('50,00', $html);
    }

    public function test_invoice_pdf_promotes_gram_override_to_a_kilo_price(): void
    {
        // A gram override should read like a shelf price: 100 € net over
        // 500 g is shown as 0,5 kg at 200,00 €/kg, not a per-gram pittance.
        $order = $this->makeOrder();
        $item = $order->items()->firstOrFail();
        $item->forceFill([
            'display_unit_override' => 'g',
            'display_quantity_override' => 500,
        ])->save();

        $issued = app(ShopOrderInvoiceService::class)->issueInvoice($order);
        $html = $this->renderInvoice($issued);

        $this->assertStringContainsString('0,5 kg', $html);
        $this->assertStringContainsString('200,00', $html);
        // The raw per-gram price must not leak through.
        $this->assertStringNotContainsString('500 g', $html);
    }

    public function test_invoice_pdf_renders_100g_products_in_kilograms(): void
    {
        // A product priced per 100 g must not print "100 g" as a unit: it is
        // shown in kg (500 g at 100 € net → 0,5 kg at 200,00 €/kg).
        $per100g = Product::factory()->create([
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => '100g',
            'unit_price' => 21.50,
            'vat_rate_percent' => 7,
            'is_available' => true,
            'is_published' => true,
        ]);
        $order = $this->makeOrder([], [
            'product_id' => $per100g->id,
            'quantity' => 500,
            'unit' => 'mass',
            // 0,214 €/g gross → 214 €/kg gross → 200 €/kg net at 7 % VAT.
            'unit_price_snapshot_eur' => 0.214,
            'line_total_eur' => 107.00,
            'vat_rate_snapshot_percent' => 7,
            'vat_amount_eur' => 7.00,
        ]);

        $issued = app(ShopOrderInvoiceService::class)->issueInvoice($order);
        $html = $this->renderInvoice($issued);

        $this->assertStringContainsString('0,5 kg', $html);
        $this->assertStringContainsString('200,00', $html);
        // No "100 g" unit and no "1 100 g" quantity leak through.
        $this->assertStringNotContainsString('100 g', $html);
    }

    /** A 30 g line of a 2,15 €/100 g product (7 % VAT), snapshot 0,0215 €/g. */
    private function makePer100gOrder(): ShopOrder
    {
        $per100g = Product::factory()->create([
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => '100g',
            'unit_price' => 2.15,
            'vat_rate_percent' => 7,
            'is_available' => true,
            'is_published' => true,
        ]);

        // Gross 0,645 € rounds to the stored 0,64 €; net 0,60 €, VAT 0,04 €.
        return $this->makeOrder([], [
            'product_id' => $per100g->id,
            'quantity' => 30,
            'unit' => 'mass',
            'unit_price_snapshot_eur' => 0.0215,
            'line_total_eur' => 0.64,
            'vat_rate_snapshot_percent' => 7,
            'vat_amount_eur' => 0.04,
        ]);
    }

    public function test_receipt_unit_price_comes_from_the_snapshot_not_the_rounded_total(): void
    {
        // The gross total (0,64 €) divided by 0,03 kg would misprice at
        // 21,33 €/kg. The per-unit price must come from the 4-decimal snapshot
        // instead: 0,0215 €/g → 21,50 €/kg gross.
        $order = $this->makePer100gOrder();

        $html = view('shop.pdf.receipt', [
            'order' => $order->load('items.product.translations'),
            'vatByRate' => ShopOrderService::vatByRate($order),
            'producerNames' => collect(),
            'company' => [
                'name' => 'Erdgut', 'street' => '', 'postal_city' => '',
                'phone' => '', 'email' => '', 'vat_id' => '', 'register' => '',
            ],
        ])->render();

        $this->assertStringContainsString('21,50', $html);
        $this->assertStringNotContainsString('21,33', $html);
    }

    public function test_invoice_unit_price_comes_from_the_snapshot_not_the_rounded_total(): void
    {
        // Same line on the (net) invoice: 0,0215 €/g gross → 20,09 €/kg net at
        // 7 % VAT, not the 20,00 €/kg the rounded total (0,60 € net ÷ 0,03 kg)
        // would give.
        $issued = app(ShopOrderInvoiceService::class)->issueInvoice($this->makePer100gOrder());
        $html = $this->renderInvoice($issued);

        $this->assertStringContainsString('20,09', $html);
        $this->assertStringNotContainsString('20,00', $html);
    }

    public function test_editor_resave_keeps_the_precise_unit_price_snapshot(): void
    {
        // Re-saving an untouched line must not degrade its 4-decimal snapshot
        // by recomputing it from the 2-decimal total (0,0215 → 0,0213).
        $order = $this->makePer100gOrder();
        $item = $order->items()->firstOrFail();

        // Submit the line unchanged (same net total, quantity and rate).
        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.shop-invoice-editor.save', $order), [
                'items' => [[
                    'id' => $item->id,
                    'net_total_eur' => '0.60',
                    'vat_rate_percent' => 7,
                    'quantity' => 30,
                ]],
            ])
            ->assertRedirect(route('app.admin.shop-orders.shop-invoice-editor', $order));

        $item->refresh();
        $this->assertEqualsWithDelta(0.0215, (float) $item->unit_price_snapshot_eur, 0.00001);
    }

    public function test_receipt_pdf_renders_display_unit_override(): void
    {
        $order = $this->makeOrder();
        $item = $order->items()->firstOrFail();
        $item->forceFill([
            'display_unit_override' => 'Schale',
            'display_quantity_override' => 1,
        ])->save();

        $html = view('shop.pdf.receipt', [
            'order' => $order->load('items.product.translations'),
            'vatByRate' => ShopOrderService::vatByRate($order),
            'producerNames' => collect(),
            'company' => [
                'name' => 'Erdgut', 'street' => '', 'postal_city' => '',
                'phone' => '', 'email' => '', 'vat_id' => '', 'register' => '',
            ],
        ])->render();

        $this->assertStringContainsString('1 Schale', $html);
    }
}
