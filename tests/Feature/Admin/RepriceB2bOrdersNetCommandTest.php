<?php

namespace Tests\Feature\Admin;

use App\Enums\CustomerType;
use App\Enums\ProductUnit;
use App\Enums\RestaurantOrderLineStatus;
use App\Enums\RestaurantOrderStatus;
use App\Enums\ShopOrderStatus;
use App\Models\Producer;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One-off repair of pre-fix B2B orders: stored gross amounts were the
 * intended net values; the command adds the VAT on top. Dry-run by
 * default, idempotent via audit event, invoiced orders skipped.
 */
class RepriceB2bOrdersNetCommandTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
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

    /** A pre-fix B2B order: gross columns carry the intended NET values. */
    private function preFixOrder(array $overrides = []): ShopOrder
    {
        $order = ShopOrder::factory()->create(array_merge([
            'name' => 'Pars',
            'customer_type' => CustomerType::Business,
            'status' => ShopOrderStatus::Paid,
            'payment_method' => 'in_person',
            'paid_at' => now()->subDays(2),
            'paid_amount_eur' => 78.00,
            'fulfillment_mode' => 'delivery',
            'pickup_location' => null,
            'delivery_street' => 'Pars', 'delivery_postal_code' => '10115', 'delivery_city' => 'Berlin',
            'expected_delivery_date' => now()->subDay()->toDateString(),
            'items_total_eur' => 72.90,
            'delivery_fee_eur' => 5.10,
            'delivery_fee_vat_rate_percent' => 7,
            'delivery_fee_vat_amount_eur' => 0.33,
            'discount_eur' => 0,
            'total_amount_eur' => 78.00,
            'created_at' => '2026-07-10 09:00:00',
        ], $overrides));

        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit' => 'piece',
            'unit_price_snapshot_eur' => 14.58,
            'line_total_eur' => 72.90,
            'vat_rate_snapshot_percent' => 7,
            'vat_amount_eur' => 4.77,
        ]);

        return $order->refresh();
    }

    public function test_dry_run_reports_but_writes_nothing(): void
    {
        $order = $this->preFixOrder();

        $this->artisan('b2b:reprice-net')
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();

        $this->assertEqualsWithDelta(78.00, (float) $order->refresh()->total_amount_eur, 0.001);
    }

    public function test_apply_adds_vat_on_top_and_reopens_settlement(): void
    {
        $order = $this->preFixOrder([
            'outstanding_settled_amount_eur' => 0,
            'outstanding_settled_at' => now(),
        ]);

        $this->artisan('b2b:reprice-net --apply')->assertSuccessful();

        $order->refresh();
        $item = $order->items()->firstOrFail();
        // 72,90 net → 78,00 gross; fee 5,10 net → 5,46 gross; total 83,46.
        $this->assertEqualsWithDelta(78.00, (float) $item->line_total_eur, 0.001);
        $this->assertEqualsWithDelta(5.10, (float) $item->vat_amount_eur, 0.001);
        $this->assertEqualsWithDelta(78.00, (float) $order->items_total_eur, 0.001);
        $this->assertEqualsWithDelta(5.46, (float) $order->delivery_fee_eur, 0.001);
        $this->assertEqualsWithDelta(0.36, (float) $order->delivery_fee_vat_amount_eur, 0.001);
        $this->assertEqualsWithDelta(83.46, (float) $order->total_amount_eur, 0.001);
        // Paid 78,00 → 5,46 open, settlement re-opened.
        $this->assertNull($order->outstanding_settled_at);
        $this->assertEqualsWithDelta(5.46, $order->outstandingAmountEur(), 0.01);
        $this->assertDatabaseHas('audit_events', [
            'entity_type' => 'shop_order',
            'entity_id' => $order->id,
            'action' => 'b2b_net_reprice',
        ]);
    }

    public function test_apply_is_idempotent(): void
    {
        $order = $this->preFixOrder();

        $this->artisan('b2b:reprice-net --apply')->assertSuccessful();
        $this->artisan('b2b:reprice-net --apply')->assertSuccessful();

        // Scaled exactly once.
        $this->assertEqualsWithDelta(83.46, (float) $order->refresh()->total_amount_eur, 0.001);
    }

    public function test_skips_private_invoiced_mirror_and_post_fix_orders(): void
    {
        $private = $this->preFixOrder(['customer_type' => CustomerType::Private]);
        $invoiced = $this->preFixOrder(['invoice_number' => 'EB260099', 'invoiced_at' => now()]);
        $postFix = $this->preFixOrder(['created_at' => '2026-07-17 09:00:00']);

        $this->artisan('b2b:reprice-net --apply')
            ->expectsOutputToContain('EB260099')
            ->assertSuccessful();

        $this->assertEqualsWithDelta(78.00, (float) $private->refresh()->total_amount_eur, 0.001);
        $this->assertEqualsWithDelta(78.00, (float) $invoiced->refresh()->total_amount_eur, 0.001);
        $this->assertEqualsWithDelta(78.00, (float) $postFix->refresh()->total_amount_eur, 0.001);
    }

    public function test_restaurant_orders_get_repriced_and_the_mirror_follows(): void
    {
        // Pre-fix restaurant order: gross 20,00 stored where 20,00 was the
        // intended NET (2 × 10,00 net list price).
        $order = RestaurantOrder::create([
            'user_id' => User::factory()->create()->id,
            'status' => RestaurantOrderStatus::Confirmed->value,
            'kind' => 'order',
            'expected_delivery_date' => now()->addDay()->toDateString(),
            'delivery_street' => 'S 1', 'delivery_postal_code' => '10115', 'delivery_city' => 'Berlin',
            'items_total_net_eur' => 18.69,
            'items_total_gross_eur' => 20.00,
            'vat_total_eur' => 1.31,
            'locale' => 'de',
        ]);
        // created_at is not fillable on direct create(); backdate explicitly.
        $order->forceFill(['created_at' => '2026-07-10 09:00:00'])->save();
        RestaurantOrderItem::create([
            'restaurant_order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit' => 'piece',
            'track' => 'requested',
            'line_status' => RestaurantOrderLineStatus::Confirmed->value,
            'unit_price_snapshot_eur' => 10.00,
            'vat_rate_snapshot_percent' => 7,
            'line_total_eur' => 20.00,
            'vat_amount_eur' => 1.31,
        ]);
        $mirror = ShopOrder::factory()->create([
            'restaurant_order_id' => $order->id,
            'customer_type' => CustomerType::Business,
            'status' => ShopOrderStatus::PendingPayment,
            'payment_method' => 'in_person',
            'fulfillment_mode' => 'delivery',
            'delivery_street' => 'S 1', 'delivery_postal_code' => '10115', 'delivery_city' => 'Berlin',
            'expected_delivery_date' => now()->addDay()->toDateString(),
            'items_total_eur' => 20.00,
            'delivery_fee_eur' => 0, 'discount_eur' => 0,
            'total_amount_eur' => 20.00,
            'created_at' => '2026-07-10 09:00:00',
        ]);

        $this->artisan('b2b:reprice-net --apply')->assertSuccessful();

        $order->refresh();
        // 20,00 net → 21,40 gross, VAT 1,40.
        $this->assertEqualsWithDelta(21.40, (float) $order->items_total_gross_eur, 0.001);
        $this->assertEqualsWithDelta(20.00, (float) $order->items_total_net_eur, 0.001);
        $this->assertEqualsWithDelta(1.40, (float) $order->vat_total_eur, 0.001);
        // The mirror shop order follows via syncShopOrderMirror.
        $this->assertEqualsWithDelta(21.40, (float) $mirror->refresh()->total_amount_eur, 0.001);
    }
}
