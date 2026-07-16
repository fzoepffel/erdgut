<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductUnit;
use App\Enums\ShopOrderStatus;
use App\Models\Producer;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Shop\ShopOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WYSIWYG receipt editor for B2C orders: every text on the Kaufbeleg is
 * editable per receipt (display-only; money edits go through the order
 * editor). Available for any order, matching the always-available download.
 */
class AdminReceiptEditorTest extends TestCase
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
            'is_available' => true,
            'is_published' => true,
        ]);
    }

    private function makeOrder(array $overrides = []): ShopOrder
    {
        $order = ShopOrder::factory()->create(array_merge([
            'name' => 'Erika Beispiel',
            'status' => ShopOrderStatus::Paid,
            'payment_method' => 'online',
            'paid_at' => now(),
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'delivery_street' => null,
            'delivery_postal_code' => null,
            'delivery_city' => null,
            'items_total_eur' => 8.00,
            'delivery_fee_eur' => 0,
            'discount_eur' => 0,
            'total_amount_eur' => 8.00,
        ], $overrides));

        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit' => 'piece',
            'unit_price_snapshot_eur' => 4.00,
            'line_total_eur' => 8.00,
            'vat_rate_snapshot_percent' => 7,
            'vat_amount_eur' => 0.52,
        ]);

        return $order->refresh();
    }

    private function renderReceipt(ShopOrder $order): string
    {
        return view('shop.pdf.receipt', [
            'order' => $order->load('items.product.translations'),
            'vatByRate' => ShopOrderService::vatByRate($order),
            'producerNames' => collect(),
            'company' => [
                'name' => 'Erdgut', 'street' => '', 'postal_city' => '',
                'phone' => '', 'email' => '', 'vat_id' => '', 'register' => '',
            ],
        ])->render();
    }

    public function test_non_admin_is_blocked(): void
    {
        $order = $this->makeOrder();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('app.admin.shop-orders.receipt-editor', $order))->assertForbidden();
        $this->actingAs($user)->post(route('app.admin.shop-orders.receipt-editor.save', $order))->assertForbidden();
    }

    public function test_editor_is_available_for_any_status_and_prefills_defaults(): void
    {
        foreach ([ShopOrderStatus::PendingPayment, ShopOrderStatus::Paid, ShopOrderStatus::Fulfilled, ShopOrderStatus::Cancelled] as $status) {
            $order = $this->makeOrder(['status' => $status, 'payment_method' => 'in_person', 'paid_at' => null]);

            $this->actingAs($this->admin)
                ->get(route('app.admin.shop-orders.receipt-editor', $order))
                ->assertOk()
                ->assertSee('Erika Beispiel', false)
                ->assertSee('data-testid="receipt-editor-save"', false);
        }
    }

    public function test_saving_overrides_and_line_name_changes_the_receipt(): void
    {
        $order = $this->makeOrder();
        $item = $order->items()->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.receipt-editor.save', $order), [
                'items' => [
                    ['id' => $item->id, 'display_name' => 'Karotten wie besprochen'],
                ],
                'overrides' => [
                    'recipient_name' => 'Erika Beispiel-Muster',
                    'payment_text' => 'Bereits per Überweisung beglichen',
                    'notes_text' => 'Individueller Hinweis für diesen Beleg.',
                ],
            ])
            ->assertRedirect(route('app.admin.shop-orders.receipt-editor', $order));

        $html = $this->renderReceipt($order->refresh());
        $this->assertStringContainsString('Karotten wie besprochen', $html);
        $this->assertStringContainsString('Erika Beispiel-Muster', $html);
        $this->assertStringContainsString('Bereits per Überweisung beglichen', $html);
        $this->assertStringContainsString('Individueller Hinweis für diesen Beleg.', $html);

        $this->assertDatabaseHas('audit_events', [
            'entity_type' => 'shop_order',
            'entity_id' => $order->id,
            'action' => 'receipt_edit',
        ]);

        // Clearing falls back to the real values.
        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.receipt-editor.save', $order), [
                'items' => [
                    ['id' => $item->id, 'display_name' => ''],
                ],
                'overrides' => ['recipient_name' => ''],
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertNull($order->items()->first()->display_name_override);
        $html = $this->renderReceipt($order);
        $this->assertStringContainsString('Erika Beispiel', $html);
        $this->assertStringNotContainsString('Karotten wie besprochen', $html);
    }

    public function test_open_amount_block_survives_receipt_overrides(): void
    {
        $order = $this->makeOrder(['paid_amount_eur' => 8.00, 'total_amount_eur' => 17.00]);

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.receipt-editor.save', $order), [
                'overrides' => ['recipient_name' => 'Jemand Anderes'],
            ])
            ->assertRedirect();

        $html = $this->renderReceipt($order->refresh());
        $this->assertStringContainsString('Bereits bezahlt (Online-Zahlung)', $html);
        $this->assertStringContainsString('Offener Betrag (Zahlung vor Ort)', $html);
    }

    public function test_mirror_show_page_offers_invoice_download_and_mail_instead_of_receipt(): void
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
            'invoice_number' => 'EB260042',
            'invoiced_at' => now(),
            'invoice_due_date' => now()->addDays(7)->toDateString(),
        ]);
        $mirror = $this->makeOrder(['restaurant_order_id' => $restaurantOrder->id]);

        // The B2B document actions live on the order level: Rechnung
        // download + mail, and no (wrong) Kaufbeleg download.
        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.show', $mirror))
            ->assertOk()
            ->assertSee('data-testid="shop-admin-invoice-download"', false)
            ->assertSee('data-testid="shop-admin-invoice-mail"', false)
            ->assertDontSee('data-testid="shop-admin-receipt"', false);
    }

    public function test_restaurant_mirror_routes_to_the_invoice_editor(): void
    {
        $producer = Producer::query()->first();
        $restaurantOrder = RestaurantOrder::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'confirmed',
            'kind' => 'order',
            'expected_delivery_date' => now()->addDay()->toDateString(),
            'delivery_street' => 'Teststr. 1',
            'delivery_postal_code' => '10115',
            'delivery_city' => 'Berlin',
            'items_total_net_eur' => 0,
            'items_total_gross_eur' => 0,
            'vat_total_eur' => 0,
        ]);
        $mirror = $this->makeOrder(['restaurant_order_id' => $restaurantOrder->id]);

        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.receipt-editor', $mirror))
            ->assertRedirect(route('app.admin.shop-orders.invoice-editor', $restaurantOrder->id));
    }
}
