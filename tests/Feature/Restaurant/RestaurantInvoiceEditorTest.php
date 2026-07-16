<?php

namespace Tests\Feature\Restaurant;

use App\Enums\ProductUnit;
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
use Tests\TestCase;

/**
 * The WYSIWYG invoice editor: every variable part of the bill is editable,
 * overrides are display-only with fallback to the real defaults, and issued
 * invoices stay editable (number frozen) by explicit product decision.
 */
class RestaurantInvoiceEditorTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $date;

    private Product $product;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->date = Carbon::tomorrow();
        $this->admin = User::factory()->admin()->create();
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

    private function confirmedOrder(): RestaurantOrder
    {
        $profile = RestaurantProfile::factory()->verified()->create([
            'business_name' => 'Bistro Beet',
            'billing_street' => 'Marktstr. 1',
            'billing_postal_code' => '10115',
            'billing_city' => 'Berlin',
        ]);
        $order = app(RestaurantOrderService::class)->submitOrder($profile->user, [
            'items' => [['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 10]],
            'expected_delivery_date' => $this->date->toDateString(),
            'delivery_street' => 'Lieferstr. 2', 'delivery_postal_code' => '10117', 'delivery_city' => 'Berlin',
        ]);
        app(RestaurantOrderService::class)->confirm($order);

        return $order->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(RestaurantOrder $order, array $overrides = []): array
    {
        $item = $order->items()->firstOrFail();

        return array_merge([
            'items' => [
                ['id' => $item->id, 'display_name' => $item->invoiceDisplayName(), 'quantity' => 10, 'net_total_eur' => '100.00', 'vat_rate_percent' => 7],
            ],
        ], $overrides);
    }

    public function test_non_admin_cannot_open_or_save_the_editor(): void
    {
        $order = $this->confirmedOrder();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('app.admin.restaurant-orders.invoice-editor', $order))->assertForbidden();
        $this->actingAs($user)->post(route('app.admin.restaurant-orders.invoice-editor.save', $order), $this->payload($order))->assertForbidden();
        $this->actingAs($user)->get(route('app.admin.restaurant-orders.invoice-preview', $order))->assertForbidden();
    }

    public function test_editor_prefills_defaults_from_config_and_profile(): void
    {
        config()->set('shop.company_bank', 'Vivid');
        config()->set('shop.company_iban', 'DE00 1111 2222 3333 4444 55');
        $order = $this->confirmedOrder();

        $this->actingAs($this->admin)
            ->get(route('app.admin.restaurant-orders.invoice-editor', $order))
            ->assertOk()
            ->assertSee('Bistro Beet', false)
            ->assertSee('Marktstr. 1', false)
            ->assertSee('Vivid', false)
            ->assertSee('DE00 1111 2222 3333 4444 55', false)
            ->assertSee('data-testid="invoice-editor-save"', false)
            ->assertSee('data-testid="invoice-editor-add-line"', false);
    }

    public function test_saving_overrides_changes_the_pdf_and_clearing_falls_back(): void
    {
        $order = $this->confirmedOrder();
        $svc = app(RestaurantInvoiceService::class);

        $this->actingAs($this->admin)
            ->post(route('app.admin.restaurant-orders.invoice-editor.save', $order), $this->payload($order, [
                'overrides' => [
                    'recipient_name' => 'Technotofu UG',
                    'bank_name' => 'Monobank',
                    'terms_text' => 'Zahlbar sofort ohne Abzug.',
                ],
            ]))
            ->assertRedirect(route('app.admin.restaurant-orders.invoice-editor', $order));

        $html = $svc->renderPdf($order->refresh(), 'EB260099')->getDomPDF()->outputHtml();
        $this->assertStringContainsString('Technotofu UG', $html);
        $this->assertStringNotContainsString('Bistro Beet', $html);
        $this->assertStringContainsString('Bankname: Monobank', $html);
        $this->assertStringContainsString('Zahlbar sofort ohne Abzug.', $html);

        // Clearing the recipient override restores the profile default.
        $this->actingAs($this->admin)
            ->post(route('app.admin.restaurant-orders.invoice-editor.save', $order), $this->payload($order, [
                'overrides' => ['recipient_name' => ''],
            ]))
            ->assertRedirect();

        $html = $svc->renderPdf($order->refresh(), 'EB260099')->getDomPDF()->outputHtml();
        $this->assertStringContainsString('Bistro Beet', $html);
        $this->assertStringNotContainsString('Monobank', $html);
    }

    public function test_line_rename_prints_on_pdf_but_keeps_real_name_in_mirror(): void
    {
        $order = $this->confirmedOrder();
        $item = $order->items()->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('app.admin.restaurant-orders.invoice-editor.save', $order), [
                'items' => [
                    ['id' => $item->id, 'display_name' => 'Feiner Bergkäse (Rechnungstext)', 'quantity' => 10, 'net_total_eur' => '100.00', 'vat_rate_percent' => 7],
                ],
            ])
            ->assertRedirect();

        // dompdf entity-encodes umlauts in outputHtml; decode before asserting.
        $html = html_entity_decode(app(RestaurantInvoiceService::class)->renderPdf($order->refresh(), 'EB260099')->getDomPDF()->outputHtml());
        $this->assertStringContainsString('Feiner Bergkäse (Rechnungstext)', $html);

        // The mirror ShopOrder keeps the real product reference untouched.
        $mirror = ShopOrder::query()->where('restaurant_order_id', $order->id)->firstOrFail();
        $this->assertSame($this->product->id, $mirror->items()->first()->product_id);
        $this->assertNull($mirror->items()->first()->name_snapshot);
    }

    public function test_new_custom_line_bills_without_touching_stock(): void
    {
        $order = $this->confirmedOrder();
        $item = $order->items()->firstOrFail();
        $inventory = app(InventoryService::class);
        $remainingBefore = $inventory->availableOnDate($this->product, $this->date, ShopStockAudience::BusinessTrade);

        $this->actingAs($this->admin)
            ->post(route('app.admin.restaurant-orders.invoice-editor.save', $order), [
                'items' => [
                    ['id' => $item->id, 'quantity' => 10, 'net_total_eur' => '100.00', 'vat_rate_percent' => 7],
                    ['name' => 'Anfahrt Sondertour', 'unit' => 'piece', 'quantity' => 1, 'net_total_eur' => '12.00', 'vat_rate_percent' => 19],
                ],
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(2, $order->items()->count());
        $this->assertEqualsWithDelta(112.00, (float) $order->items_total_net_eur, 0.01);
        $this->assertEqualsWithDelta(121.28, (float) $order->items_total_gross_eur, 0.01);

        $custom = $order->items()->whereNull('product_id')->firstOrFail();
        $this->assertSame('Anfahrt Sondertour', $custom->name_snapshot);

        // Stock unchanged: custom lines never hold inventory.
        $this->assertEqualsWithDelta(
            $remainingBefore,
            $inventory->availableOnDate($this->product, $this->date, ShopStockAudience::BusinessTrade),
            0.01,
        );

        $this->assertDatabaseHas('audit_events', [
            'entity_type' => 'restaurant_order',
            'entity_id' => $order->id,
            'action' => 'invoice_edit',
        ]);
    }

    public function test_issued_invoice_remains_editable_and_due_date_survives_issue(): void
    {
        $order = $this->confirmedOrder();
        $svc = app(RestaurantInvoiceService::class);

        // Editor-set due date survives issuing.
        $this->actingAs($this->admin)
            ->post(route('app.admin.restaurant-orders.invoice-editor.save', $order), $this->payload($order, [
                'invoice_due_date' => '2027-01-15',
            ]))
            ->assertRedirect();

        $svc->issueInvoice($order->refresh());
        $order->refresh();
        $number = $order->invoice_number;
        $this->assertSame('2027-01-15', $order->invoice_due_date->toDateString());

        // Still editable after issue; number stays.
        $this->actingAs($this->admin)
            ->post(route('app.admin.restaurant-orders.invoice-editor.save', $order), $this->payload($order, [
                'overrides' => ['recipient_name' => 'Nachtrag GmbH'],
            ]))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame($number, $order->invoice_number);
        $this->assertSame('Nachtrag GmbH', $order->invoiceOverride('recipient_name'));

        $this->actingAs($this->admin)
            ->get(route('app.admin.restaurant-orders.invoice-editor', $order))
            ->assertOk()
            ->assertSee('Nachtrag GmbH', false);
    }

    public function test_preview_pdf_streams_with_upcoming_number_before_issue(): void
    {
        $order = $this->confirmedOrder();
        app(RestaurantInvoiceService::class)->updatePricing($order, [
            ['id' => $order->items()->firstOrFail()->id, 'net_total_eur' => 100, 'vat_rate_percent' => 7],
        ]);

        $this->actingAs($this->admin)
            ->get(route('app.admin.restaurant-orders.invoice-preview', $order))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_editor_opens_for_a_submitted_request_too(): void
    {
        // Wherever a document can be downloaded it can be edited: the editor
        // is not gated on confirmed/invoiceable status.
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

        $this->actingAs($this->admin)
            ->get(route('app.admin.restaurant-orders.invoice-editor', $submitted))
            ->assertOk()
            ->assertSee('data-testid="invoice-editor-save"', false);

        $this->actingAs($this->admin)
            ->get(route('app.admin.restaurant-orders.invoice-preview', $submitted))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_new_line_without_name_is_rejected(): void
    {
        $order = $this->confirmedOrder();
        $item = $order->items()->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('app.admin.restaurant-orders.invoice-editor.save', $order), [
                'items' => [
                    ['id' => $item->id, 'quantity' => 10, 'net_total_eur' => '100.00', 'vat_rate_percent' => 7],
                    ['name' => '', 'quantity' => 1, 'net_total_eur' => '5.00', 'vat_rate_percent' => 7],
                ],
            ])
            ->assertSessionHasErrors('items.1.name');
    }
}
