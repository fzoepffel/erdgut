<?php

namespace Tests\Feature\Restaurant;

use App\Enums\CustomerType;
use App\Enums\ProductUnit;
use App\Enums\RestaurantOrderLineStatus;
use App\Enums\RestaurantOrderStatus;
use App\Enums\ShopOrderStatus;
use App\Models\Producer;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantProfile;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Admin\AdminOperationsMetrics;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The mirror ShopOrder that firm restaurant orders (Confirmed / Fulfilled) get
 * so the admin sees them in the unified shop-orders list and inherits the
 * shop-order editing surface. Covers creation, idempotency, and the revenue
 * de-dup that keeps the source RestaurantOrder from being counted twice.
 */
class RestaurantOrderMirrorTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        Producer::factory()->create();

        return Product::factory()->create([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 10.00,
            'vat_rate_percent' => 7,
            'is_available' => true,
            'is_published' => true,
        ]);
    }

    private function verifiedRestaurant(string $businessName = 'Bistro Rot'): User
    {
        $profile = RestaurantProfile::factory()->verified()->create(['business_name' => $businessName]);
        $profile->user->forceFill(['locale' => 'de'])->save();

        return $profile->user;
    }

    private function submittedRequest(User $user, Product $product, string $date, float $qty = 3): RestaurantOrder
    {
        // Build a Submitted request order directly (bypassing catalog stock
        // constraints so the test setup stays terse).
        $order = RestaurantOrder::create([
            'user_id' => $user->id,
            'status' => RestaurantOrderStatus::Submitted->value,
            'kind' => 'request',
            'expected_delivery_date' => $date,
            'delivery_street' => 'S 1', 'delivery_postal_code' => '10115', 'delivery_city' => 'Berlin',
            'items_total_net_eur' => round($qty * 10 * 100 / 107, 2),
            'items_total_gross_eur' => $qty * 10,
            'vat_total_eur' => round($qty * 10 - ($qty * 10 * 100 / 107), 2),
            'locale' => 'de',
        ]);
        RestaurantOrderItem::create([
            'restaurant_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit' => 'piece',
            'track' => 'requested',
            'line_status' => RestaurantOrderLineStatus::Requested->value,
            'unit_price_snapshot_eur' => 10.00,
            'vat_rate_snapshot_percent' => 7,
            'line_total_eur' => $qty * 10,
            'vat_amount_eur' => round($qty * 10 * 7 / 107, 2),
        ]);

        return $order;
    }

    public function test_confirm_creates_mirror_shop_order_with_snapshotted_items(): void
    {
        $user = $this->verifiedRestaurant('Cafe Blau');
        $product = $this->product();
        $order = $this->submittedRequest($user, $product, '2026-07-20', 3);

        app(RestaurantOrderService::class)->confirm($order);

        $mirror = $order->refresh()->mirroredShopOrder;
        $this->assertNotNull($mirror);
        $this->assertSame(CustomerType::Business, $mirror->customer_type);
        $this->assertSame(ShopOrderStatus::PendingPayment, $mirror->status);
        $this->assertSame('Cafe Blau', $mirror->name);
        $this->assertSame('2026-07-20', $mirror->expected_delivery_date->toDateString());
        // Header totals mirror the gross of the source order.
        $this->assertEqualsWithDelta(30.0, (float) $mirror->total_amount_eur, 0.01);
        // One item copied over with its price snapshot.
        $this->assertCount(1, $mirror->items);
        $this->assertEqualsWithDelta(3.0, (float) $mirror->items->first()->quantity, 0.01);
        $this->assertEqualsWithDelta(30.0, (float) $mirror->items->first()->line_total_eur, 0.01);
    }

    public function test_mirror_creation_is_idempotent(): void
    {
        $user = $this->verifiedRestaurant();
        $product = $this->product();
        $order = $this->submittedRequest($user, $product, '2026-07-20');
        $service = app(RestaurantOrderService::class);
        $service->confirm($order);

        // Second call must return the same mirror, not create another.
        $second = $service->ensureShopOrderMirror($order->refresh());

        $this->assertSame(1, ShopOrder::query()->where('restaurant_order_id', $order->id)->count());
        $this->assertSame($order->mirroredShopOrder->id, $second?->id);
    }

    public function test_declined_lines_are_excluded_from_the_mirror(): void
    {
        $user = $this->verifiedRestaurant();
        $keep = $this->product();
        $drop = $this->product();
        $order = $this->submittedRequest($user, $keep, '2026-07-20', 2);
        // Add a second line to decline.
        RestaurantOrderItem::create([
            'restaurant_order_id' => $order->id,
            'product_id' => $drop->id,
            'quantity' => 5,
            'unit' => 'piece',
            'track' => 'requested',
            'line_status' => RestaurantOrderLineStatus::Requested->value,
            'unit_price_snapshot_eur' => 10.00,
            'vat_rate_snapshot_percent' => 7,
            'line_total_eur' => 50.0,
            'vat_amount_eur' => 3.27,
        ]);

        $service = app(RestaurantOrderService::class);
        // Decline the second line first, then accept the first — this
        // resolves both pending lines and settles the order to Confirmed.
        $service->declineLine($order->items()->where('product_id', $drop->id)->first());
        $service->acceptLine($order->items()->where('product_id', $keep->id)->first());

        $mirror = $order->refresh()->mirroredShopOrder;
        $this->assertNotNull($mirror);
        $this->assertCount(1, $mirror->items);
        $this->assertSame($keep->id, $mirror->items->first()->product_id);
    }

    public function test_revenue_query_does_not_double_count_a_mirrored_restaurant_order(): void
    {
        $user = $this->verifiedRestaurant();
        $product = $this->product();
        $order = $this->submittedRequest($user, $product, '2026-07-08', 3); // week of 2026-07-06
        app(RestaurantOrderService::class)->confirm($order);

        $snapshot = app(AdminOperationsMetrics::class)->build(Carbon::parse('2026-07-08 09:00:00'));
        $bucket = collect($snapshot->weeklyRevenue)->firstWhere('weekStart', '2026-07-06');

        // 3 × €10 gross = €30; net = €30 − VAT (round(30*7/107,2)=1.96) = €28.04.
        $this->assertNotNull($bucket);
        $this->assertEqualsWithDelta(28.04, $bucket->b2bNet, 0.02);
        $this->assertSame(0, $snapshot->ordersThisWeekB2c);
        // Counted once — via the mirror — not twice.
        $this->assertSame(1, $snapshot->ordersThisWeekB2b);
    }

    public function test_backfill_command_creates_mirrors_for_pre_existing_firm_orders(): void
    {
        $user = $this->verifiedRestaurant();
        $product = $this->product();
        // Simulate a legacy Confirmed order that predates the mirror rollout
        // (no mirror yet). Skip the service to skip the auto-mirror call.
        $order = $this->submittedRequest($user, $product, '2026-07-20');
        $order->forceFill(['status' => RestaurantOrderStatus::Confirmed])->save();
        $order->items()->update(['line_status' => RestaurantOrderLineStatus::Confirmed->value]);

        $this->assertNull($order->refresh()->mirroredShopOrder);

        $this->artisan('restaurant:backfill-shop-order-mirrors')->assertSuccessful();

        $this->assertNotNull($order->refresh()->mirroredShopOrder);
        // Re-run is idempotent — no additional mirrors.
        $this->artisan('restaurant:backfill-shop-order-mirrors')->assertSuccessful();
        $this->assertSame(1, ShopOrder::query()->where('restaurant_order_id', $order->id)->count());
    }
}
