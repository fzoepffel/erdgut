<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductUnit;
use App\Enums\ShopOrderStatus;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Shop\ShopOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Admin order editing: everything on an order can change, but what was
 * collected stays collected — the difference surfaces as an open remainder
 * (or refund due) and can be settled in person, in either direction.
 */
class AdminShopOrderEditTest extends TestCase
{
    use RefreshDatabase;

    private const DELIVERY_DATE = '2026-08-05';

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-01 10:00:00'));

        $this->admin = User::factory()->admin()->create();

        $producer = Producer::factory()->create();
        $this->product = Product::factory()->create([
            'producer_id' => $producer->id,
            'category' => 'produce',
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 4.00,
            'vat_rate_percent' => 7,
            'is_available' => true,
            'is_published' => true,
        ]);
        ProductStockInflow::create([
            'product_id' => $this->product->id,
            'received_on' => self::DELIVERY_DATE,
            'quantity' => 10,
            'freshness_window_days' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    /**
     * A pickup order with one catalog line (2 x 4.00 = 8.00 EUR).
     */
    private function makeOrder(array $overrides = []): ShopOrder
    {
        $order = ShopOrder::factory()->create(array_merge([
            'status' => ShopOrderStatus::PendingPayment,
            'payment_method' => 'in_person',
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'delivery_street' => null,
            'delivery_postal_code' => null,
            'delivery_city' => null,
            'expected_delivery_date' => self::DELIVERY_DATE,
            'items_total_eur' => 8.00,
            'delivery_fee_eur' => 0,
            'discount_eur' => 0,
            'total_amount_eur' => 8.00,
            'paid_at' => null,
            'sumup_checkout_id' => null,
            'sumup_payment_id' => null,
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

    private function paidOrder(array $overrides = []): ShopOrder
    {
        return $this->makeOrder(array_merge([
            'status' => ShopOrderStatus::Paid,
            'payment_method' => 'online',
            'paid_at' => now(),
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'expected_delivery_date' => self::DELIVERY_DATE,
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2, 'line_total_eur' => '8.00', 'vat_rate_percent' => 7],
            ],
        ], $overrides);
    }

    public function test_non_admin_cannot_edit(): void
    {
        $order = $this->makeOrder();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('app.admin.shop-orders.update', $order), $this->payload())
            ->assertForbidden();
    }

    public function test_awaiting_online_payment_is_not_editable(): void
    {
        $order = $this->makeOrder(['payment_method' => 'online']);

        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.edit', $order))
            ->assertRedirect(route('app.admin.shop-orders.show', $order));

        $this->actingAs($this->admin)
            ->put(route('app.admin.shop-orders.update', $order), $this->payload())
            ->assertForbidden();
    }

    public function test_terminal_statuses_are_not_editable(): void
    {
        foreach ([ShopOrderStatus::Cancelled, ShopOrderStatus::Failed, ShopOrderStatus::Refunded] as $status) {
            $order = $this->makeOrder(['status' => $status]);

            $this->actingAs($this->admin)
                ->put(route('app.admin.shop-orders.update', $order), $this->payload())
                ->assertForbidden();
        }
    }

    public function test_edit_recomputes_lines_totals_and_snapshots_paid_amount(): void
    {
        $order = $this->paidOrder();
        $this->assertNull($order->paid_amount_eur);

        $response = $this->actingAs($this->admin)->put(
            route('app.admin.shop-orders.update', $order),
            $this->payload([
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 3, 'line_total_eur' => '12.00', 'vat_rate_percent' => 7],
                    ['name' => 'Honig vom Nachbarhof', 'unit' => 'piece', 'quantity' => 1, 'line_total_eur' => '5.00', 'vat_rate_percent' => 19],
                ],
            ]),
        );

        $response->assertRedirect(route('app.admin.shop-orders.show', $order));
        $order->refresh()->load('items');

        $this->assertSame(17.00, (float) $order->items_total_eur);
        $this->assertSame(17.00, (float) $order->total_amount_eur);
        $this->assertCount(2, $order->items);

        $catalogLine = $order->items->firstWhere('product_id', $this->product->id);
        $this->assertSame(3.0, (float) $catalogLine->quantity);
        $this->assertSame(12.00, (float) $catalogLine->line_total_eur);
        $this->assertSame(4.0, (float) $catalogLine->unit_price_snapshot_eur);
        $this->assertSame(0.79, (float) $catalogLine->vat_amount_eur);

        $customLine = $order->items->firstWhere('product_id', null);
        $this->assertSame('Honig vom Nachbarhof', $customLine->name_snapshot);
        $this->assertSame(0.80, (float) $customLine->vat_amount_eur);

        // Payment history intact: 8.00 collected, 9.00 open.
        $this->assertSame(ShopOrderStatus::Paid, $order->status);
        $this->assertSame(8.00, (float) $order->paid_amount_eur);
        $this->assertSame(9.00, $order->outstandingAmountEur());
        $this->assertTrue($order->hasOpenAmount());

        $this->assertDatabaseHas('audit_events', [
            'entity_type' => 'shop_order',
            'entity_id' => $order->id,
            'action' => 'update',
        ]);
    }

    public function test_order_consuming_all_stock_can_be_resaved_unchanged(): void
    {
        $order = $this->makeOrder();
        $order->items()->first()->update(['quantity' => 10, 'line_total_eur' => 40.00]);

        $this->actingAs($this->admin)
            ->put(route('app.admin.shop-orders.update', $order), $this->payload([
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 10, 'line_total_eur' => '40.00', 'vat_rate_percent' => 7],
                ],
            ]))
            ->assertRedirect(route('app.admin.shop-orders.show', $order))
            ->assertSessionHasNoErrors();
    }

    public function test_quantity_beyond_remaining_plus_own_fails(): void
    {
        $order = $this->makeOrder(); // 2 committed, 8 remaining, 10 total

        $this->actingAs($this->admin)
            ->put(route('app.admin.shop-orders.update', $order), $this->payload([
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 11, 'line_total_eur' => '44.00', 'vat_rate_percent' => 7],
                ],
            ]))
            ->assertSessionHasErrors('items.0.quantity');
    }

    public function test_date_change_validates_against_new_date_raw(): void
    {
        $order = $this->makeOrder();

        // No stock at all on the new date.
        $this->actingAs($this->admin)
            ->put(route('app.admin.shop-orders.update', $order), $this->payload([
                'expected_delivery_date' => '2026-08-12',
            ]))
            ->assertSessionHasErrors('items.0.quantity');
    }

    public function test_packing_state_survives_quantity_edit_and_removed_lines_disappear(): void
    {
        $order = $this->makeOrder();
        $packer = User::factory()->admin()->create();
        $order->items()->first()->update(['packed_at' => now(), 'packed_by_user_id' => $packer->id]);
        $removed = $order->items()->create([
            'product_id' => null,
            'name_snapshot' => 'Wird entfernt',
            'quantity' => 1,
            'unit' => 'piece',
            'unit_price_snapshot_eur' => 2.00,
            'line_total_eur' => 2.00,
            'vat_rate_snapshot_percent' => 7,
            'vat_amount_eur' => 0.13,
        ]);

        $this->actingAs($this->admin)
            ->put(route('app.admin.shop-orders.update', $order), $this->payload([
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 4, 'line_total_eur' => '16.00', 'vat_rate_percent' => 7],
                ],
            ]))
            ->assertRedirect();

        $order->refresh()->load('items');
        $this->assertCount(1, $order->items);
        $line = $order->items->first();
        $this->assertSame(4.0, (float) $line->quantity);
        $this->assertNotNull($line->packed_at);
        $this->assertSame($packer->id, $line->packed_by_user_id);
        $this->assertDatabaseMissing('shop_order_items', ['id' => $removed->id]);
    }

    public function test_switch_to_delivery_requires_and_stores_address_and_fee(): void
    {
        $order = $this->makeOrder();

        // Missing address + fee → validation errors.
        $this->actingAs($this->admin)
            ->put(route('app.admin.shop-orders.update', $order), $this->payload([
                'fulfillment_mode' => 'delivery',
                'pickup_location' => null,
            ]))
            ->assertSessionHasErrors(['delivery_street', 'delivery_fee_eur']);

        $this->actingAs($this->admin)
            ->put(route('app.admin.shop-orders.update', $order), $this->payload([
                'fulfillment_mode' => 'delivery',
                'pickup_location' => null,
                'delivery_street' => 'Gotenstraße 1',
                'delivery_postal_code' => '10829',
                'delivery_city' => 'Berlin',
                'delivery_fee_eur' => '2.90',
                'delivery_fee_vat_rate_percent' => 7,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('delivery', $order->fulfillment_mode);
        $this->assertNull($order->pickup_location);
        $this->assertSame('Gotenstraße 1', $order->delivery_street);
        $this->assertSame(2.90, (float) $order->delivery_fee_eur);
        $this->assertSame(0.19, (float) $order->delivery_fee_vat_amount_eur);
        $this->assertSame(10.90, (float) $order->total_amount_eur);
    }

    public function test_coupon_discount_stays_frozen(): void
    {
        $order = $this->paidOrder([
            'coupon_code' => 'SOMMER5',
            'discount_eur' => 2.00,
            'items_total_eur' => 8.00,
            'total_amount_eur' => 6.00,
        ]);

        $this->actingAs($this->admin)
            ->put(route('app.admin.shop-orders.update', $order), $this->payload([
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 3, 'line_total_eur' => '12.00', 'vat_rate_percent' => 7],
                ],
            ]))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(2.00, (float) $order->discount_eur);
        $this->assertSame('SOMMER5', $order->coupon_code);
        $this->assertSame(10.00, (float) $order->total_amount_eur);
        // Collected 6.00 before the edit, so 4.00 remain open.
        $this->assertSame(6.00, (float) $order->paid_amount_eur);
        $this->assertSame(4.00, $order->outstandingAmountEur());
    }

    public function test_unpaid_in_person_order_never_has_open_amount(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->admin)
            ->put(route('app.admin.shop-orders.update', $order), $this->payload([
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 5, 'line_total_eur' => '20.00', 'vat_rate_percent' => 7],
                ],
            ]))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(20.00, (float) $order->total_amount_eur);
        $this->assertNull($order->paid_amount_eur);
        $this->assertFalse($order->hasOpenAmount());
    }

    public function test_settle_collected_remainder(): void
    {
        $order = $this->paidOrder(['paid_amount_eur' => 8.00, 'total_amount_eur' => 17.00]);

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.settle-outstanding', $order))
            ->assertRedirect(route('app.admin.shop-orders.show', $order));

        $order->refresh();
        $this->assertSame(17.00, (float) $order->paid_amount_eur);
        $this->assertSame(9.00, (float) $order->outstanding_settled_amount_eur);
        $this->assertNotNull($order->outstanding_settled_at);
        $this->assertSame($this->admin->id, $order->outstanding_settled_by_user_id);
        $this->assertFalse($order->hasOpenAmount());

        $this->assertDatabaseHas('audit_events', [
            'entity_type' => 'shop_order',
            'entity_id' => $order->id,
            'action' => 'settle_outstanding',
        ]);
    }

    public function test_settle_refund_direction_is_marked_as_refunded(): void
    {
        $order = $this->paidOrder(['paid_amount_eur' => 8.00, 'total_amount_eur' => 5.00]);

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.settle-outstanding', $order))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(-3.00, (float) $order->outstanding_settled_amount_eur);
        $this->assertFalse($order->hasOpenAmount());

        // The refund stays visible on the detail page after settling (the
        // test env renders EN; assert the locale-agnostic parts).
        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.show', $order))
            ->assertSee('data-testid="shop-admin-settled-note"', false)
            ->assertSee(__('shop.admin.settled_refunded_note', [
                'amount' => '3,00 €',
                'date' => $order->outstanding_settled_at->locale(app()->getLocale())->isoFormat('D. MMM YYYY, HH:mm'),
            ]), false);
    }

    public function test_settle_without_open_amount_is_rejected(): void
    {
        $order = $this->paidOrder(['paid_amount_eur' => 8.00, 'total_amount_eur' => 8.00]);

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.settle-outstanding', $order))
            ->assertRedirect(route('app.admin.shop-orders.show', $order))
            ->assertSessionHas('error');

        $this->assertNull($order->refresh()->outstanding_settled_at);
    }

    public function test_mark_paid_snapshots_paid_amount(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.mark-paid', $order))
            ->assertRedirect();

        $this->assertSame(8.00, (float) $order->refresh()->paid_amount_eur);
    }

    public function test_show_page_surfaces_open_amount_and_edit_button(): void
    {
        $order = $this->paidOrder(['paid_amount_eur' => 8.00, 'total_amount_eur' => 17.00]);

        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.show', $order))
            ->assertSee('data-testid="shop-admin-edit"', false)
            ->assertSee('data-testid="shop-admin-open-amount"', false)
            ->assertSee('data-testid="shop-admin-settle-outstanding"', false)
            ->assertSee('data-testid="shop-order-open-amount"', false);
    }

    public function test_edit_form_prefills_stored_lines(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.edit', $order))
            ->assertOk()
            ->assertSee('data-testid="shop-order-edit-paid-warning"', false)
            ->assertSee('exclude_order_id', false)
            ->assertSee((string) $this->product->id, false);
    }

    public function test_receipt_pdf_view_shows_open_amount_block(): void
    {
        $order = $this->paidOrder(['paid_amount_eur' => 8.00, 'total_amount_eur' => 17.00]);

        $url = ShopOrderService::receiptUrl($order);
        // The signed receipt endpoint streams a rendered PDF; assert on the
        // underlying blade instead so we can inspect the markup.
        $html = view('shop.pdf.receipt', [
            'order' => $order->load('items.product.translations'),
            'vatByRate' => ShopOrderService::vatByRate($order),
            'producerNames' => collect(),
            'company' => [
                'name' => 'Erdgut', 'street' => '', 'postal_city' => '',
                'phone' => '', 'email' => '', 'vat_id' => '',
            ],
        ])->render();

        $this->assertStringContainsString('Bereits bezahlt (Online-Zahlung)', $html);
        $this->assertStringContainsString('Offener Betrag (Zahlung vor Ort)', $html);
        $this->assertStringContainsString('9,00 €', $html);
        $this->assertStringContainsString('Restbetrag vor Ort', $html);
        $this->assertNotSame('', $url);
    }

    public function test_track_page_shows_open_amount_block(): void
    {
        $order = $this->paidOrder(['paid_amount_eur' => 8.00, 'total_amount_eur' => 17.00]);

        $this->get(ShopOrderService::trackUrl($order))
            ->assertOk()
            ->assertSee('data-testid="shop-track-open-amount"', false);
    }

    public function test_track_page_hides_block_for_settled_orders(): void
    {
        $order = $this->paidOrder(['paid_amount_eur' => 17.00, 'total_amount_eur' => 17.00]);

        $this->get(ShopOrderService::trackUrl($order))
            ->assertOk()
            ->assertDontSee('data-testid="shop-track-open-amount"', false);
    }
}
